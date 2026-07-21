<?php

namespace Modules\Workflows\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowManagementService
{
    public function __construct(
        protected WorkflowVerificationService $verificationService,
        protected WorkflowAuthorizationService $authorizationService,
        protected WorkflowTemplateService $templateService,
    ) {}

    public function listVisibleWorkflows(User $actor, array $filters = []): Collection
    {
        return $this->visibleWorkflowsQuery($actor)
            ->when(empty($filters['include_deleted']), function (Builder $query): void {
                $query->where('status', '!=', WorkflowStatus::Deleted->value);
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['name'] ?? null, fn (Builder $query, string $name) => $query->where('name', 'like', "%{$name}%"))
            ->when($filters['description'] ?? null, fn (Builder $query, string $description) => $query->where('description', 'like', "%{$description}%"))
            ->when($filters['team_id'] ?? null, fn (Builder $query, $teamId) => $query->where('team_id', (int) $teamId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->with([
                'team:id,name',
                'createdBy:id,first_name,last_name,name,email',
            ])
            ->latest('updated_at')
            ->get();
    }

    public function createWorkflow(User $actor, array $data): Workflow
    {
        if (! in_array($actor->role, [Role::Manager, Role::BusinessOwner], true)) {
            throw new AuthorizationException('Only managers and business owners can create workflows.');
        }

        if ($actor->role === Role::Manager) {
            $team = $this->resolveManagedTeam($actor);
        } else {
            if (empty($data['team_id'])) {
                throw ValidationException::withMessages([
                    'team_id' => 'The team id is required to assign workflow to cause you are a Business Owners.',
                ]);
            }

            $team = Team::query()
                ->where('tenant_id', (int) $actor->tenant_id)
                ->where('id', (int) $data['team_id'])
                ->first();

            if ($team === null) {
                throw ValidationException::withMessages([
                    'team_id' => 'The selected team is invalid or does not belong to your tenant.',
                ]);
            }
        }

        $method = $data['method'];

        return DB::transaction(function () use ($actor, $team, $method, $data): Workflow {
            $template = null;
            $definition = $this->blankDefinition();

            if ($method === 'template') {
                $template = $this->templateService->resolveTemplate($actor, (int) $data['template_id']);
                $definition = $template->definition;
                $template->increment('usage_count');
            }

            if ($method === 'ai_confirmed') {
                $definition = $data['definition'];
            }

            $workflow = Workflow::query()->create([
                'tenant_id' => (int) $actor->tenant_id,
                'team_id' => (int) $team->id,
                'template_id' => $template?->id,
                'created_by_id' => (int) $actor->id,
                'name' => trim((string) $data['name']),
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'status' => WorkflowStatus::Disabled,
                'draft_definition' => $definition,
                'draft_revision' => 1,
                'current_version_number' => 0,
                'total_runs' => 0,
                'active_instances' => 0,
            ]);

            return $workflow->refresh()->load(['team:id,name', 'createdBy:id,first_name,last_name,name,email']);
        });
    }

    public function generateAiProposal(User $actor, array $data): array
    {
        // TODO: This is a placeholder implementation, the actual implementation will depend on the AI service
        // that we will use, and the format of the definition that we will adopt.
        if (! in_array($actor->role, [Role::Manager, Role::BusinessOwner], true)) {
            throw new AuthorizationException('Only managers or business owners can generate workflow proposals.');
        }

        $goal = trim((string) $data['goal']);
        $definition = [
            'trigger' => [
                'type' => 'webhook-trigger',
                'config' => [
                    'webhookUrl' => Str::slug(Str::limit($goal, 40, '')),
                ],
            ],
            'nodes' => [
                [
                    'id' => 'draft-human-task',
                    'type' => 'human-task',
                    'config' => [
                        'taskName' => 'Review proposed workflow',
                        'outcomes' => ['approved', 'needs_changes'],
                        'description' => $goal,
                    ],
                ],
            ],
            'edges' => [],
            'settings' => [
                'source' => 'ai_proposal',
            ],
        ];

        return [
            'proposal_definition' => $definition,
            'proposal_checksum' => 'sha256:'.hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR)),
            'warnings' => [],
            'confidence' => 0.82,
            'generated_at' => now(),
        ];
    }

    public function getVisibleWorkflow(User $actor, Workflow $workflow): Workflow
    {
        $this->authorizationService->assertCanView($actor, $workflow);

        return $workflow->load([
            'team:id,name',
            'createdBy:id,first_name,last_name,name,email',
            'currentVersion',
            'template:id,name',
        ]);
    }

    public function updateDraft(User $actor, Workflow $workflow, array $data): array
    {
        $this->authorizationService->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);
        $this->assertDraftRevisionMatches($workflow, (int) $data['expected_draft_revision']);

        $validation = $this->verificationService->verify($data['definition'], $workflow, $actor)->toArray();

        if ((bool) ($data['validate_only'] ?? false)) {
            return [
                'workflow' => $workflow,
                'saved' => false,
                'validation' => $validation,
            ];
        }

        $workflow->forceFill([
            'draft_definition' => $data['definition'],
            'draft_revision' => (int) $workflow->draft_revision + 1,
        ])->save();

        return [
            'workflow' => $workflow->refresh(),
            'saved' => true,
            'validation' => $validation,
        ];
    }

    public function updateStatus(User $actor, Workflow $workflow, string $status): Workflow
    {
        $this->authorizationService->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);

        if ($status === WorkflowStatus::Active->value && $workflow->current_version_id === null) {
            throw ValidationException::withMessages([
                'status' => 'Workflow must be published before it can be activated.',
            ]);
        }

        $workflow->forceFill(['status' => $status])->save();

        return $workflow->refresh();
    }

    public function softDelete(User $actor, Workflow $workflow): Workflow
    {
        $this->authorizationService->assertCanManage($actor, $workflow);

        $workflow->forceFill([
            'status' => WorkflowStatus::Deleted,
            'deleted_at' => now(),
        ])->save();

        return $workflow->refresh();
    }

    public function purge(User $actor, Workflow $workflow): void
    {
        $this->authorizationService->assertBusinessOwner($actor);
        $this->authorizationService->assertSameTenant($actor, $workflow);

        if ($workflow->status !== WorkflowStatus::Deleted) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow must be deleted before it can be purged.',
            ]);
        }

        if ((int) $workflow->active_instances > 0) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow cannot be purged while active instances exist.',
            ]);
        }

        DB::transaction(function () use ($workflow): void {
            $workflow->instances()->delete();
            $workflow->versions()->delete();
            $workflow->delete();
        });
    }

    protected function visibleWorkflowsQuery(User $actor): Builder
    {
        $query = Workflow::query()->where('tenant_id', (int) $actor->tenant_id);

        if ($actor->role === Role::BusinessOwner) {
            return $query;
        }

        if ($actor->role === Role::Manager) {
            $team = $this->resolveManagedTeam($actor);

            return $query->where('team_id', (int) $team->id);
        }

        if ($actor->role === Role::Employee) {
            return $query->whereHas('team.memberships', function (Builder $query) use ($actor): void {
                $query->where('tenant_id', (int) $actor->tenant_id)
                    ->where('user_id', (int) $actor->id)
                    ->where('status', 'active');
            });
        }

        throw new AuthorizationException('You are not allowed to view workflows.');
    }

    protected function resolveManagedTeam(User $actor): Team
    {
        $team = Team::query()
            ->where('tenant_id', (int) $actor->tenant_id)
            ->where('manager_id', (int) $actor->id)
            ->first();

        if ($team === null) {
            throw new AuthorizationException('Manager must belong to a team before managing workflows.');
        }

        return $team;
    }

    protected function blankDefinition(): array
    {
        return [
            'trigger' => null,
            'nodes' => [],
            'edges' => [],
            'settings' => [],
        ];
    }

    protected function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursive($item);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    protected function assertDraftRevisionMatches(Workflow $workflow, int $expectedRevision): void
    {
        if ((int) $workflow->draft_revision !== $expectedRevision) {
            throw new HttpException(409, 'Workflow draft has been modified. Refresh and retry.');
        }
    }

    protected function assertNotDeleted(Workflow $workflow): void
    {
        if ($workflow->status === WorkflowStatus::Deleted) {
            throw ValidationException::withMessages([
                'workflow' => 'Deleted workflows cannot be modified.',
            ]);
        }
    }
}
