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
use Modules\Workflows\Enums\TriggerType;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Execution\WorkflowDispatcher;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkflowManagementService
{
    public function __construct(
        protected WorkflowDefinitionValidator $definitionValidator,
        protected WorkflowDispatcher $dispatcher,
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

    public function validateDefinition(array $definition, ?Workflow $workflow = null, ?User $actor = null): array
    {
        return $this->definitionValidator->validate($definition, $workflow, $actor);
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

        $validation = $this->definitionValidator->validate($data['definition'], $workflow, $actor);

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

        $validation = $this->definitionValidator->validate($workflow->draft_definition, $workflow, $actor);

        if (! $validation['is_publishable']) {
            throw ValidationException::withMessages([
                'definition' => $validation['errors'],
            ]);
        }

        $workflow->loadMissing('currentVersion');
        $changeType = $this->detectPublishChangeType($workflow);

        if ($changeType === 'none') {
            $workflow->update(['status' => WorkflowStatus::Active]);

            return $workflow->currentVersion->load('publishedBy:id,first_name,last_name,name,email');
        }

        return DB::transaction(function () use ($actor, $workflow, $changeType): WorkflowVersion {
            $nextVersionNumber = ((int) $workflow->versions()->max('version_number')) + 1;
            $versionLabel = $this->nextVersionLabel($workflow->current_version_label, $changeType);

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
                'published_by_id' => (int) $actor->id,
                'published_at' => now(),
            ]);

            $workflow->forceFill([
                'current_version_id' => (int) $version->id,
                'current_version_number' => $nextVersionNumber,
                'current_version_label' => $versionLabel,
                'status' => WorkflowStatus::Active,
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

        return $this->dispatcher->dispatch($workflow, TriggerType::Webhook, $payload);
    }

    public function triggerManual(User $actor, Workflow $workflow, array $payload = []): WorkflowInstance
    {
        $this->assertCanView($actor, $workflow);

        if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
            throw new HttpException(410, 'Workflow is not available for triggering.');
        }

        return $this->dispatcher->dispatch($workflow, TriggerType::Manual, $payload);
    }

    public function triggerForm(User $actor, Workflow $workflow, array $formData = []): WorkflowInstance
    {
        $this->assertCanView($actor, $workflow);

        if (in_array($workflow->status, [WorkflowStatus::Disabled, WorkflowStatus::Deleted], true)) {
            throw new HttpException(410, 'Workflow is not available for triggering.');
        }

        return $this->dispatcher->dispatch($workflow, TriggerType::Form, $formData);
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

    public function serializeWorkflow(Workflow $workflow, User $actor, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $workflow->id,
            'public_token' => $workflow->public_token,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'status' => $workflow->status?->value,
            'team' => $workflow->team ? [
                'id' => $workflow->team->id,
                'name' => $workflow->team->name,
            ] : null,
            'version_number' => $workflow->current_version_number,
            'version_label' => $workflow->current_version_label,
            'created_by' => $workflow->createdBy ? [
                'id' => $workflow->createdBy->id,
                'name' => $workflow->createdBy->name,
                'email' => $workflow->createdBy->email,
            ] : null,
            'created_at' => $workflow->created_at,
            'updated_at' => $workflow->updated_at,
            'total_runs' => $workflow->total_runs,
            'active_instances' => $workflow->active_instances,
            'actions' => $this->availableActions($actor, $workflow),
        ];

        if ($includeDetails) {
            $payload['draft_revision'] = $workflow->draft_revision;
            $payload['draft_definition'] = $workflow->draft_definition;
            $payload['template'] = $workflow->template ? [
                'id' => $workflow->template->id,
                'name' => $workflow->template->name,
            ] : null;
            $payload['current_version'] = $workflow->currentVersion
                ? $this->serializeVersion($workflow->currentVersion)
                : null;
        }

        return $payload;
    }

    public function serializeVersion(WorkflowVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'version_label' => $version->version_label,
            'release_note' => $version->release_note,
            'published_at' => $version->published_at,
            'published_by' => $version->publishedBy ? [
                'id' => $version->publishedBy->id,
                'name' => $version->publishedBy->name,
                'email' => $version->publishedBy->email,
            ] : null,
        ];
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

    protected function blankDefinition(): array
    {
        return [
            'trigger' => null,
            'nodes' => [],
            'edges' => [],
            'settings' => [],
        ];
    }

    protected function nextVersionLabel(?string $currentLabel, string $changeType): string
    {
        if ($currentLabel === null || ! preg_match('/^v(\d+)\.(\d+)\.(\d+)$/', $currentLabel, $matches)) {
            return 'v1.0.0';
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];

        return match ($changeType) {
            'structural' => sprintf('v%d.%d.%d', $major + 1, $minor + 1, $patch),
            'config' => sprintf('v%d.%d.%d', $major + 1, $minor, $patch + 1),
            default => sprintf('v%d.%d.%d', $major + 1, $minor, $patch),
        };
    }

    protected function detectPublishChangeType(Workflow $workflow): string
    {
        $currentVersion = $workflow->currentVersion;

        if ($currentVersion === null) {
            return 'structural';
        }

        $currentDefinition = is_array($currentVersion->definition ?? null) ? $currentVersion->definition : [];
        $draftDefinition = is_array($workflow->draft_definition ?? null) ? $workflow->draft_definition : [];

        if (
            $this->fingerprint($this->structuralDefinition($currentDefinition))
            !== $this->fingerprint($this->structuralDefinition($draftDefinition))
        ) {
            return 'structural';
        }

        if (
            $this->fingerprint($this->configDefinition($currentDefinition))
            !== $this->fingerprint($this->configDefinition($draftDefinition))
        ) {
            return 'config';
        }

        return 'none';
    }

    protected function structuralDefinition(array $definition): array
    {
        return [
            'trigger' => $this->structuralTrigger($definition['trigger'] ?? null),
            'nodes' => array_values(array_map(
                fn (mixed $node): array => $this->structuralNode($node),
                is_array($definition['nodes'] ?? null) ? $definition['nodes'] : []
            )),
            'edges' => array_values(array_map(
                fn (mixed $edge): array => $this->structuralEdge($edge),
                is_array($definition['edges'] ?? null) ? $definition['edges'] : []
            )),
        ];
    }

    protected function configDefinition(array $definition): array
    {
        return [
            'trigger_config' => $this->configTrigger($definition['trigger'] ?? null),
            'variables' => is_array($definition['variables'] ?? null) ? array_values($definition['variables']) : [],
            'nodes' => array_values(array_map(
                fn (mixed $node): array => $this->configNode($node),
                is_array($definition['nodes'] ?? null) ? $definition['nodes'] : []
            )),
            'settings' => is_array($definition['settings'] ?? null) ? $definition['settings'] : [],
        ];
    }

    protected function structuralTrigger(mixed $trigger): array
    {
        if (! is_array($trigger)) {
            return ['type' => null];
        }

        return ['type' => isset($trigger['type']) ? (string) $trigger['type'] : null];
    }

    protected function configTrigger(mixed $trigger): array
    {
        if (! is_array($trigger)) {
            return [];
        }

        return is_array($trigger['config'] ?? null) ? $trigger['config'] : [];
    }

    protected function structuralNode(mixed $node): array
    {
        if (! is_array($node)) {
            return [
                'id' => null,
                'type' => null,
                'label' => null,
                'name' => null,
                'is_entry_point' => false,
                'is_terminal' => false,
            ];
        }

        return [
            'id' => isset($node['id']) ? (string) $node['id'] : null,
            'type' => isset($node['type']) ? (string) $node['type'] : null,
            'label' => isset($node['label']) ? (string) $node['label'] : null,
            'name' => isset($node['name']) ? (string) $node['name'] : null,
            'is_entry_point' => (bool) ($node['is_entry_point'] ?? false),
            'is_terminal' => (bool) ($node['is_terminal'] ?? false),
        ];
    }

    protected function configNode(mixed $node): array
    {
        if (! is_array($node)) {
            return ['config' => []];
        }

        return [
            'id' => isset($node['id']) ? (string) $node['id'] : null,
            'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
        ];
    }

    protected function structuralEdge(mixed $edge): array
    {
        if (! is_array($edge)) {
            return [
                'id' => null,
                'source_node_key' => null,
                'target_node_key' => null,
                'condition_expression' => null,
                'is_default_branch' => false,
                'parallel_strategy' => null,
                'join_node_key' => null,
                'sort_order' => 0,
            ];
        }

        return [
            'id' => isset($edge['id']) ? (string) $edge['id'] : null,
            'source_node_key' => isset($edge['source_node_key']) ? (string) $edge['source_node_key'] : null,
            'target_node_key' => isset($edge['target_node_key']) ? (string) $edge['target_node_key'] : null,
            'condition_expression' => isset($edge['condition_expression']) ? (string) $edge['condition_expression'] : null,
            'is_default_branch' => (bool) ($edge['is_default_branch'] ?? false),
            'parallel_strategy' => isset($edge['parallel_strategy']) ? (string) $edge['parallel_strategy'] : null,
            'join_node_key' => isset($edge['join_node_key']) ? (string) $edge['join_node_key'] : null,
            'sort_order' => isset($edge['sort_order']) ? (int) $edge['sort_order'] : 0,
        ];
    }

    protected function fingerprint(mixed $value): string
    {
        return md5(json_encode($this->sortRecursive($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
            return TeamMembership::query()
                ->where('tenant_id', (int) $actor->tenant_id)
                ->where('team_id', (int) $workflow->team_id)
                ->where('user_id', (int) $actor->id)
                ->where('status', 'active')
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
