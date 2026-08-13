<?php

namespace Modules\Workflows\Services;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\User;
use Modules\Workflows\Enums\WorkflowStatus;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowVersion;
use Modules\Workflows\Services\Verification\WorkflowVerificationService;

class WorkflowVersioningService extends BaseService
{
    public function __construct(
        protected WorkflowVerificationService $verificationService,
        protected WorkflowAuthorizationService $authorizationService,
    ) {}

    public function publish(User $actor, Workflow $workflow, array $data): WorkflowVersion
    {
        $this->authorizationService->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);

        $validation = $this->verificationService->verify($workflow->draft_definition, $workflow, $actor)->toArray();

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

    public function listVersions(User $actor, Workflow $workflow)
    {
        $this->authorizationService->assertCanView($actor, $workflow);

        return $workflow->versions()
            ->with('publishedBy:id,first_name,last_name,name,email')
            ->orderByDesc('version_number')
            ->get();
    }

    public function showVersion(User $actor, Workflow $workflow, WorkflowVersion $version): WorkflowVersion
    {
        $this->authorizationService->assertCanView($actor, $workflow);
        $this->assertVersionBelongsToWorkflow($version, $workflow);

        return $version->load(['publishedBy:id,first_name,last_name,name,email', 'workflow:id,current_version_id']);
    }

    public function compareVersions(
        User $actor,
        Workflow $workflow,
        WorkflowVersion $from,
        WorkflowVersion $to
    ): array {
        $this->authorizationService->assertCanView($actor, $workflow);
        $this->assertVersionBelongsToWorkflow($from, $workflow);
        $this->assertVersionBelongsToWorkflow($to, $workflow);

        $fromDefinition = is_array($from->definition) ? $from->definition : [];
        $toDefinition = is_array($to->definition) ? $to->definition : [];

        $fromNodes = collect(is_array($fromDefinition['nodes'] ?? null) ? $fromDefinition['nodes'] : [])->keyBy('id');
        $toNodes = collect(is_array($toDefinition['nodes'] ?? null) ? $toDefinition['nodes'] : [])->keyBy('id');

        $fromEdges = collect(is_array($fromDefinition['edges'] ?? null) ? $fromDefinition['edges'] : [])->keyBy('id');
        $toEdges = collect(is_array($toDefinition['edges'] ?? null) ? $toDefinition['edges'] : [])->keyBy('id');

        return [
            'from_version' => [
                'id' => $from->id,
                'version_number' => $from->version_number,
                'version_label' => $from->version_label,
            ],
            'to_version' => [
                'id' => $to->id,
                'version_number' => $to->version_number,
                'version_label' => $to->version_label,
            ],
            'nodes_added' => $toNodes->diffKeys($fromNodes)->keys()->values()->all(),
            'nodes_removed' => $fromNodes->diffKeys($toNodes)->keys()->values()->all(),
            'nodes_modified' => $toNodes->intersectByKeys($fromNodes)
                ->filter(fn (mixed $node, string $id) => $this->fingerprint($node) !== $this->fingerprint($fromNodes[$id]))
                ->keys()
                ->values()
                ->all(),
            'edges_added' => $toEdges->diffKeys($fromEdges)->keys()->values()->all(),
            'edges_removed' => $fromEdges->diffKeys($toEdges)->keys()->values()->all(),
            'edges_modified' => $toEdges->intersectByKeys($fromEdges)
                ->filter(fn (mixed $edge, string $id) => $this->fingerprint($edge) !== $this->fingerprint($fromEdges[$id]))
                ->keys()
                ->values()
                ->all(),
            'trigger_changed' => $this->fingerprint($fromDefinition['trigger'] ?? null)
                !== $this->fingerprint($toDefinition['trigger'] ?? null),
            'settings_changed' => $this->fingerprint($fromDefinition['settings'] ?? null)
                !== $this->fingerprint($toDefinition['settings'] ?? null),
        ];
    }

    public function rollback(User $actor, Workflow $workflow, WorkflowVersion $version, array $data = []): WorkflowVersion
    {
        $this->authorizationService->assertCanManage($actor, $workflow);
        $this->assertNotDeleted($workflow);
        $this->assertVersionBelongsToWorkflow($version, $workflow);

        if ((int) $workflow->current_version_id === (int) $version->id) {
            throw ValidationException::withMessages([
                'version' => 'This version is already the current active version.',
            ]);
        }

        $definition = is_array($version->definition) ? $version->definition : [];

        $validation = $this->verificationService->verify($definition, $workflow, $actor)->toArray();

        if (! $validation['is_publishable']) {
            throw ValidationException::withMessages([
                'definition' => $validation['errors'],
            ]);
        }

        return DB::transaction(function () use ($actor, $workflow, $version, $definition, $data): WorkflowVersion {
            $nextVersionNumber = ((int) $workflow->versions()->max('version_number')) + 1;
            $versionLabel = $this->nextVersionLabel($workflow->current_version_label, 'structural');

            if ($workflow->versions()->where('version_label', $versionLabel)->exists()) {
                throw ValidationException::withMessages([
                    'version_label' => 'Version label already exists for this workflow.',
                ]);
            }

            $releaseNote = $data['release_note']
                ?? sprintf('Rollback to %s', $version->version_label ?? "v#{$version->version_number}");

            $newVersion = WorkflowVersion::query()->create([
                'workflow_id' => (int) $workflow->id,
                'tenant_id' => (int) $workflow->tenant_id,
                'version_number' => $nextVersionNumber,
                'version_label' => $versionLabel,
                'definition' => $definition,
                'release_note' => $releaseNote,
                'published_by_id' => (int) $actor->id,
                'published_at' => now(),
                'rollback_source_version_id' => (int) $version->id,
            ]);

            $workflow->forceFill([
                'current_version_id' => (int) $newVersion->id,
                'current_version_number' => $nextVersionNumber,
                'current_version_label' => $versionLabel,
                'status' => WorkflowStatus::Active,
            ])->save();

            return $newVersion->load('publishedBy:id,first_name,last_name,name,email');
        });
    }

    protected function assertVersionBelongsToWorkflow(WorkflowVersion $version, Workflow $workflow): void
    {
        if ((int) $version->workflow_id !== (int) $workflow->id) {
            throw ValidationException::withMessages([
                'version' => 'The specified version does not belong to this workflow.',
            ]);
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
}
