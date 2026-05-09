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
use Modules\Team\Models\TeamMembership;
use Modules\Workflows\Enums\WorkflowInstanceStatus;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowAccessGrant;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Models\WorkflowVersion;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowManagementService
{
    public function __construct(
        protected WorkflowDefinitionValidator $definitionValidator
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

    public function listTemplates(User $actor): Collection
    {
        return WorkflowTemplate::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($actor): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', (int) $actor->tenant_id);
            })
            ->orderBy('name')
            ->get();
    }

    public function createWorkflow(User $actor, array $data): Workflow
    {
        $this->assertManager($actor);
        $team = $this->resolveManagedTeam($actor);
        $method = $data['method'];

        return DB::transaction(function () use ($actor, $team, $method, $data): Workflow {
            $template = null;
            $definition = $this->blankDefinition();

            if ($method === 'template') {
                $template = $this->resolveTemplate($actor, (int) $data['template_id']);
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

            $this->grantTeamEmployeesViewAccess($workflow, $actor);

            return $workflow->refresh()->load(['team:id,name', 'createdBy:id,first_name,last_name,name,email']);
        });
    }

    public function generateAiProposal(User $actor, array $data): array
    {
        if (! in_array($actor->role, [Role::Manager, Role::BusinessOwner], true)) {
            throw new AuthorizationException('Only managers or business owners can generate workflow proposals.');
        }

        $goal = trim((string) $data['goal']);
        $definition = [
            'trigger' => [
                'type' => 'webhook',
                'config' => [
                    'name' => Str::slug(Str::limit($goal, 40, '')),
                ],
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'note',
                    'config' => [
                        'summary' => $goal,
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
        $this->assertCanView($actor, $workflow);

        return $workflow->load([
            'team:id,name',
            'createdBy:id,first_name,last_name,name,email',
            'currentVersion',
            'template:id,name',
        ]);
    }

    public function updateDraft(User $actor, Workflow $workflow, array $data): array
    {
        $this->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);
        $this->assertDraftRevisionMatches($workflow, (int) $data['expected_draft_revision']);

        $validation = $this->definitionValidator->validate($data['definition']);

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

    public function publish(User $actor, Workflow $workflow, array $data): WorkflowVersion
    {
        $this->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);
        $this->assertDraftRevisionMatches($workflow, (int) $data['expected_draft_revision']);

        $validation = $this->definitionValidator->validate($workflow->draft_definition);

        if (! $validation['is_publishable']) {
            throw ValidationException::withMessages([
                'definition' => $validation['errors'],
            ]);
        }

        return DB::transaction(function () use ($actor, $workflow, $data): WorkflowVersion {
            $nextVersionNumber = ((int) $workflow->versions()->max('version_number')) + 1;
            $versionLabel = $data['version_label'] ?? $this->nextVersionLabel($workflow->current_version_label);

            if ($workflow->versions()->where('version_label', $versionLabel)->exists()) {
                throw ValidationException::withMessages([
                    'version_label' => 'Version label already exists for this workflow.',
                ]);
            }

            $version = WorkflowVersion::query()->create([
                'workflow_id' => (int) $workflow->id,
                'tenant_id' => (int) $workflow->tenant_id,
                'version_number' => $nextVersionNumber,
                'version_label' => $versionLabel,
                'definition' => $workflow->draft_definition,
                'release_note' => $data['release_note'] ?? null,
                'published_by_id' => (int) $actor->id,
                'published_at' => now(),
            ]);

            $workflow->forceFill([
                'current_version_id' => (int) $version->id,
                'current_version_number' => $nextVersionNumber,
                'current_version_label' => $versionLabel,
            ])->save();

            return $version->load('publishedBy:id,first_name,last_name,name,email');
        });
    }

    public function listVersions(User $actor, Workflow $workflow): Collection
    {
        $this->assertCanView($actor, $workflow);

        return $workflow->versions()
            ->with('publishedBy:id,first_name,last_name,name,email')
            ->orderByDesc('version_number')
            ->get();
    }

    public function updateStatus(User $actor, Workflow $workflow, string $status): Workflow
    {
        $this->assertCanManage($actor, $workflow);
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
        $this->assertCanManage($actor, $workflow);

        $workflow->forceFill([
            'status' => WorkflowStatus::Deleted,
            'deleted_at' => now(),
        ])->save();

        return $workflow->refresh();
    }

    public function purge(User $actor, Workflow $workflow): void
    {
        $this->assertBusinessOwner($actor);
        $this->assertSameTenant($actor, $workflow);

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
            $workflow->accessGrants()->delete();
            $workflow->versions()->delete();
            $workflow->delete();
        });
    }

    public function triggerWebhook(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
    {
        $this->assertCanView($actor, $workflow);

        if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
            throw new HttpException(410, 'Workflow is not available for triggering.');
        }

        if ($workflow->current_version_id === null) {
            throw ValidationException::withMessages([
                'workflow' => 'Workflow must be published before it can be triggered.',
            ]);
        }

        return DB::transaction(function () use ($workflow, $payload): WorkflowInstance {
            $instance = WorkflowInstance::query()->create([
                'workflow_id' => (int) $workflow->id,
                'workflow_version_id' => (int) $workflow->current_version_id,
                'tenant_id' => (int) $workflow->tenant_id,
                'status' => WorkflowInstanceStatus::Running,
                'payload' => $payload,
                'started_at' => now(),
            ]);

            $workflow->increment('total_runs');
            $workflow->increment('active_instances');

            return $instance;
        });
    }

    public function availableActions(User $actor, Workflow $workflow): array
    {
        if (! $this->canView($actor, $workflow)) {
            return [];
        }

        if ($workflow->status === WorkflowStatus::Deleted) {
            return $actor->role === Role::BusinessOwner ? ['view', 'purge'] : ['view'];
        }

        if ($this->canManage($actor, $workflow)) {
            $statusAction = $workflow->status === WorkflowStatus::Active ? 'disable' : 'enable';

            return ['view', 'edit_draft', 'publish', $statusAction, 'delete'];
        }

        return ['view'];
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
            return $query->whereHas('accessGrants', function (Builder $query) use ($actor): void {
                $query->where('user_id', (int) $actor->id)
                    ->where('access_level', 'view');
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

    protected function resolveTemplate(User $actor, int $templateId): WorkflowTemplate
    {
        $template = WorkflowTemplate::query()
            ->where('id', $templateId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($actor): void {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', (int) $actor->tenant_id);
            })
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'template_id' => 'Selected workflow template was not found.',
            ]);
        }

        return $template;
    }

    protected function grantTeamEmployeesViewAccess(Workflow $workflow, User $actor): void
    {
        TeamMembership::query()
            ->where('tenant_id', (int) $workflow->tenant_id)
            ->where('team_id', (int) $workflow->team_id)
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('role', Role::Employee->value))
            ->pluck('user_id')
            ->each(function ($userId) use ($workflow, $actor): void {
                WorkflowAccessGrant::query()->updateOrCreate(
                    [
                        'workflow_id' => (int) $workflow->id,
                        'user_id' => (int) $userId,
                    ],
                    [
                        'access_level' => 'view',
                        'granted_by_id' => (int) $actor->id,
                        'granted_at' => now(),
                    ]
                );
            });
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

    protected function nextVersionLabel(?string $currentLabel): string
    {
        if ($currentLabel === null || ! preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $currentLabel, $matches)) {
            return 'v1.0.0';
        }

        return sprintf('v%d.%d.%d', (int) $matches[1], (int) $matches[2], ((int) $matches[3]) + 1);
    }

    protected function assertDraftRevisionMatches(Workflow $workflow, int $expectedRevision): void
    {
        if ((int) $workflow->draft_revision !== $expectedRevision) {
            throw new HttpException(409, 'Workflow draft has been modified. Refresh and retry.');
        }
    }

    protected function assertCanView(User $actor, Workflow $workflow): void
    {
        if (! $this->canView($actor, $workflow)) {
            throw new AuthorizationException('You are not allowed to view this workflow.');
        }
    }

    protected function assertCanManage(User $actor, Workflow $workflow): void
    {
        if (! $this->canManage($actor, $workflow)) {
            throw new AuthorizationException('You are not allowed to manage this workflow.');
        }
    }

    protected function canView(User $actor, Workflow $workflow): bool
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            return false;
        }

        if ($actor->role === Role::BusinessOwner) {
            return true;
        }

        if ($actor->role === Role::Manager) {
            return $this->canManage($actor, $workflow);
        }

        if ($actor->role === Role::Employee) {
            return WorkflowAccessGrant::query()
                ->where('workflow_id', (int) $workflow->id)
                ->where('user_id', (int) $actor->id)
                ->where('access_level', 'view')
                ->exists();
        }

        return false;
    }

    protected function canManage(User $actor, Workflow $workflow): bool
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            return false;
        }

        if ($actor->role === Role::BusinessOwner) {
            return true;
        }

        if ($actor->role !== Role::Manager) {
            return false;
        }

        $team = Team::query()
            ->where('tenant_id', (int) $actor->tenant_id)
            ->where('manager_id', (int) $actor->id)
            ->first();

        return $team !== null && (int) $workflow->team_id === (int) $team->id;
    }

    protected function assertManager(User $actor): void
    {
        if ($actor->role !== Role::Manager) {
            throw new AuthorizationException('Only managers can create workflows.');
        }
    }

    protected function assertBusinessOwner(User $actor): void
    {
        if ($actor->role !== Role::BusinessOwner) {
            throw new AuthorizationException('Only business owners can perform this action.');
        }
    }

    protected function assertSameTenant(User $actor, Workflow $workflow): void
    {
        if ((int) $actor->tenant_id !== (int) $workflow->tenant_id) {
            throw new AuthorizationException('You can only manage workflows within your tenant.');
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
