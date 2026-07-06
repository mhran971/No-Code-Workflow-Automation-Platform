<?php

namespace Modules\Workflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Enums\NodeExecutionStatus;
use Modules\Workflows\Http\Requests\ListTasksRequest;
use Modules\Workflows\Http\Requests\SaveTaskDraftRequest;
use Modules\Workflows\Http\Requests\SubmitTaskRequest;
use Modules\Workflows\Http\Resources\WorkflowTaskDetailResource;
use Modules\Workflows\Http\Resources\WorkflowTaskResource;
use Modules\Workflows\Jobs\ExecuteNodeJob;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Models\WorkflowTask;

/**
 * Assignee inbox and task submission for `task-node` executions.
 */
class WorkflowTaskController extends Controller
{
    /**
     * List tasks scoped to the caller's role.
     *
     * BusinessOwner — all tasks in the tenant.
     * Manager       — all tasks assigned to members of their team.
     * Employee      — only tasks assigned to themselves.
     *
     * Query params:
     *   sort        — due_asc | due_desc | created_asc  (default: due_asc)
     *   search      — searches title and description
     *   status      — open | completed | expired | cancelled (default: open)
     *   assignee_id — filter by a specific assignee (BusinessOwner and Manager only)
     */
    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $user = $this->actor();
        $query = WorkflowTask::query()->where('tenant_id', $user->tenant_id);

        $this->scopeToRole($query, $user);

        if ($request->filled('assignee_id') && $user->role !== Role::Employee) {
            $query->where('assignee_id', $request->integer('assignee_id'));
        }

        $status = $request->filled('status') ? $request->string('status')->toString() : 'open';

        if ($status === 'expired') {
            $query->where('status', 'open')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now());
        } elseif ($status === 'open') {
            $query->where('status', 'open')
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()));
        } else {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        match ($request->string('sort', 'due_asc')->toString()) {
            'due_desc'    => $query->orderBy('due_at', 'desc'),
            'created_asc' => $query->orderBy('created_at', 'asc'),
            default       => $query->orderBy('due_at', 'asc'),
        };

        return WorkflowTaskResource::collection($query->with('assignee')->paginate(20));
    }

    /**
     * Return pending and overdue task counts for the home screen.
     *
     * pending_count — all open tasks in scope.
     * overdue_count — open tasks whose due_at has already passed.
     */
    public function summary(): JsonResponse
    {
        $user = $this->actor();

        $base = WorkflowTask::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'open');

        $this->scopeToRole($base, $user);

        $pendingCount = (clone $base)->count();
        $overdueCount = (clone $base)->whereNotNull('due_at')->where('due_at', '<', now())->count();

        return response()->json([
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
        ]);
    }

    /**
     * Return full detail for a single task.
     *
     * BusinessOwner — any task in the tenant.
     * Manager       — any task assigned to a member of their team.
     * Employee      — only tasks assigned to themselves.
     */
    public function show(WorkflowTask $task): WorkflowTaskDetailResource
    {
        $user = $this->actor();

        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }

        if ($user->role === Role::Employee && (int) $task->assignee_id !== (int) $user->id) {
            abort(403);
        }

        if ($user->role === Role::Manager && ! in_array($task->assignee_id, $this->teamMemberIds($user), true)) {
            abort(403);
        }

        $task->load(['assignee', 'completedBy']);

        return new WorkflowTaskDetailResource($task);
    }

    /**
     * Apply role-based assignee scoping to the given query.
     * BusinessOwner: no additional constraint (sees all tenant tasks).
     * Manager: constrained to their team members.
     * Employee: constrained to themselves.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<WorkflowTask>  $query
     */
    private function scopeToRole(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        match ($user->role) {
            Role::BusinessOwner => null,
            Role::Manager       => $query->whereIn('assignee_id', $this->teamMemberIds($user)),
            default             => $query->where('assignee_id', $user->id),
        };
    }

    private function teamMemberIds(User $manager): array
    {
        return Team::query()
            ->where('manager_id', $manager->id)
            ->with('memberships:id,team_id,user_id')
            ->get()
            ->flatMap(fn (Team $team) => $team->memberships->pluck('user_id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Persist a partial response without closing the task.
     * Only allowed while the task is still open.
     */
    public function saveDraft(SaveTaskDraftRequest $request, WorkflowTask $task): JsonResponse
    {
        $user = $this->actor();

        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }

        if ($user->role === Role::Employee && (int) $task->assignee_id !== (int) $user->id) {
            abort(403);
        }

        if ($user->role === Role::Manager && ! in_array($task->assignee_id, $this->teamMemberIds($user), true)) {
            abort(403);
        }

        if ($task->status !== 'open') {
            return response()->json(['error' => 'Task is already closed.'], 422);
        }

        $task->update(['draft_response' => $request->input('response', [])]);

        return response()->json(['message' => 'Draft saved.'], 200);
    }

    /**
     * Submit a response for a task, resuming the parked execution.
     *
     * Validates the response payload against the task's input_schema before
     * transitioning status to completed.
     */
    public function submit(SubmitTaskRequest $request, WorkflowTask $task): JsonResponse
    {
        $user = $this->actor();

        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }

        if ($user->role === Role::Employee && (int) $task->assignee_id !== (int) $user->id) {
            abort(403);
        }

        if ($user->role === Role::Manager && ! in_array($task->assignee_id, $this->teamMemberIds($user), true)) {
            abort(403);
        }

        if ($task->status !== 'open') {
            return response()->json(['error' => 'Task is already closed.'], 422);
        }

        if (! empty($task->input_schema)) {
            $this->validateAgainstSchema($request->input('response', []), $task->input_schema);
        }

        $task->update([
            'status' => 'completed',
            'response' => $request->input('response', []),
            'completed_by_id' => $user->id,
            'completed_at' => now(),
        ]);

        $this->resumeExecution((int) $task->execution_id);

        return response()->json(['message' => 'Task submitted.'], 200);
    }

    /**
     * Build and run Laravel validation rules derived from a task's input_schema.
     * Throws ValidationException (→ 422) on failure.
     *
     * @param  array<string, mixed>  $response
     * @param  array<int, array<string, mixed>>  $schema
     */
    private function validateAgainstSchema(array $response, array $schema): void
    {
        $rules = [];
        $labels = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }

            $required = ($field['required'] ?? false) ? 'required' : 'nullable';
            $type = (string) ($field['type'] ?? 'text');
            $options = array_values(array_filter(
                (array) ($field['options'] ?? []),
                fn ($o) => is_string($o) && $o !== '',
            ));

            $fieldRules = [$required];

            if ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'date') {
                $fieldRules[] = 'date';
            } elseif ($type === 'checkbox') {
                $fieldRules[] = 'array';
                if (! empty($options)) {
                    $rules["response.{$key}.*"] = ['string', Rule::in($options)];
                }
            } elseif ($type === 'select' && ! empty($options)) {
                $fieldRules[] = Rule::in($options);
            } else {
                $fieldRules[] = 'string';
            }

            $rules["response.{$key}"] = $fieldRules;
            $labels["response.{$key}"] = (string) ($field['label'] ?? $key);
        }

        validator(['response' => $response], $rules, [], $labels)->validate();
    }

    protected function resumeExecution(int $executionId): void
    {
        $execution = WorkflowNodeExecution::query()->find($executionId);
        if ($execution === null || $execution->status !== NodeExecutionStatus::Waiting) {
            return;
        }

        $execution->update(['status' => NodeExecutionStatus::Pending, 'wait_until' => null]);

        ExecuteNodeJob::dispatch($execution->id, NodeCategory::Action->value)
            ->onQueue((string) config('workflows.execution.queues.actions', 'workflow-actions'));
    }

    protected function actor(): User
    {
        return auth('api')->user();
    }
}
