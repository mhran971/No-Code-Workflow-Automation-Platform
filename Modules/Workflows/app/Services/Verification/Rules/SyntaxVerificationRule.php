<?php

namespace Modules\Workflows\Services\Verification\Rules;

use Modules\Auth\Models\User;
use Modules\Workflows\Enums\NodeConfigFieldType;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Models\NodeConfigField;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Services\Verification\WorkflowDefinitionGraph;
use Modules\Workflows\Services\Verification\WorkflowVerificationResult;

class SyntaxVerificationRule implements VerificationRule
{
    public function verify(
        array $definition,
        WorkflowDefinitionGraph $graph,
        WorkflowVerificationResult $result,
        ?Workflow $workflow = null,
        ?User $actor = null,
    ): void {
        $raw = $definition['_raw'] ?? [];
        $nodeDefinitions = Node::query()
            ->where('is_active', true)
            ->with('configFields')
            ->get()
            ->keyBy('type');

        $this->verifyTopLevelShape($raw, $result);
        $this->verifyTrigger($definition['trigger'] ?? null, $nodeDefinitions, $result);
        $this->verifyNodes($definition['nodes'] ?? [], $nodeDefinitions, $result);
        $this->verifyEdges($definition['edges'] ?? [], $graph, $result);
    }

    protected function verifyTopLevelShape(array $raw, WorkflowVerificationResult $result): void
    {
        foreach (['trigger', 'nodes', 'edges'] as $key) {
            if (! array_key_exists($key, $raw)) {
                $result->addError("definition.{$key}_missing", "Workflow {$key} must be configured.", $key);
            }
        }

        if (array_key_exists('trigger', $raw) && ! is_array($raw['trigger'])) {
            $result->addError('definition.trigger_invalid', 'Workflow trigger must be an object.', 'trigger');
        }

        if (array_key_exists('nodes', $raw) && ! is_array($raw['nodes'])) {
            $result->addError('definition.nodes_invalid', 'Workflow nodes must be an array.', 'nodes');
        }

        if (array_key_exists('edges', $raw) && ! is_array($raw['edges'])) {
            $result->addError('definition.edges_invalid', 'Workflow edges must be an array.', 'edges');
        }

        if (array_key_exists('variables', $raw) && ! is_array($raw['variables'])) {
            $result->addError('definition.variables_invalid', 'Workflow variables must be an array.', 'variables');
        }

        if (array_key_exists('settings', $raw) && ! is_array($raw['settings'])) {
            $result->addError('definition.settings_invalid', 'Workflow settings must be an object.', 'settings');
        }
    }

    protected function verifyTrigger(?array $trigger, $nodeDefinitions, WorkflowVerificationResult $result): void
    {
        if ($trigger === null) {
            $result->addError('trigger.missing', 'Workflow trigger must be configured.', 'trigger');

            return;
        }

        $type = trim((string) ($trigger['type'] ?? ''));

        if ($type === '') {
            $result->addError('trigger.type_missing', 'Workflow trigger type is required.', 'trigger.type');

            return;
        }

        $nodeDefinition = $nodeDefinitions->get($type);

        if ($nodeDefinition === null) {
            $result->addError('trigger.type_unknown', "Workflow trigger type '{$type}' is not active or does not exist.", 'trigger.type');

            return;
        }

        $config = $trigger['config'] ?? [];

        if (! is_array($config)) {
            $result->addError('trigger.config_invalid', 'Workflow trigger config must be an object.', 'trigger.config');

            return;
        }

        $this->verifyConfigFields($nodeDefinition->configFields, $config, 'trigger.config', null, $result);
    }

    protected function verifyNodes(array $nodes, $nodeDefinitions, WorkflowVerificationResult $result): void
    {
        if ($nodes === []) {
            $result->addError('nodes.empty', 'Workflow must contain at least one node.', 'nodes');

            return;
        }

        $seenIds = [];

        foreach ($nodes as $index => $node) {
            $path = "nodes[{$index}]";

            if (($node['_invalid'] ?? false) === true) {
                $result->addError('node.invalid', 'Workflow node must be an object.', $path);

                continue;
            }

            $nodeId = trim((string) ($node['id'] ?? ''));
            $type = trim((string) ($node['type'] ?? ''));

            if ($nodeId === '') {
                $result->addError('node.id_missing', 'Workflow node id must be a non-empty string.', "{$path}.id");
            } elseif (isset($seenIds[$nodeId])) {
                $result->addError('node.id_duplicate', "Workflow node id '{$nodeId}' is duplicated.", "{$path}.id", $nodeId);
            } else {
                $seenIds[$nodeId] = true;
            }

            if ($type === '') {
                $result->addError('node.type_missing', 'Workflow node type is required.', "{$path}.type", $nodeId !== '' ? $nodeId : null);
                continue;
            }

            $nodeDefinition = $nodeDefinitions->get($type);

            if ($nodeDefinition === null) {
                $result->addError('node.type_unknown', "Workflow node type '{$type}' is not active or does not exist.", "{$path}.type", $nodeId !== '' ? $nodeId : null);
                continue;
            }

            if (! is_array($node['_raw']['config'] ?? [])) {
                $result->addError('node.config_invalid', 'Workflow node config must be an object.', "{$path}.config", $nodeId !== '' ? $nodeId : null);
                continue;
            }

            $this->verifyConfigFields(
                $nodeDefinition->configFields,
                $node['config'] ?? [],
                "{$path}.config",
                $nodeId !== '' ? $nodeId : null,
                $result
            );
        }
    }

    protected function verifyEdges(array $edges, WorkflowDefinitionGraph $graph, WorkflowVerificationResult $result): void
    {
        $seenPairs = [];

        foreach ($edges as $index => $edge) {
            $path = "edges[{$index}]";
            $edgeId = $edge['id'] ?? null;

            if (($edge['_invalid'] ?? false) === true) {
                $result->addError('edge.invalid', 'Workflow edge must be an object.', $path, null, $edgeId);

                continue;
            }

            $source = trim((string) ($edge['source_node_key'] ?? ''));
            $target = trim((string) ($edge['target_node_key'] ?? ''));

            if ($source === '') {
                $result->addError('edge.source_missing', 'Edge source node is required.', "{$path}.source_node_key", null, $edgeId);
            } elseif (! $graph->hasNode($source)) {
                $result->addError('edge.source_missing_reference', 'Edge source node does not exist.', "{$path}.source_node_key", $source, $edgeId);
            }

            if ($target === '') {
                $result->addError('edge.target_missing', 'Edge target node is required.', "{$path}.target_node_key", null, $edgeId);
            } elseif (! $graph->hasNode($target)) {
                $result->addError('edge.target_missing_reference', 'Edge target node does not exist.', "{$path}.target_node_key", $target, $edgeId);
            }

            if ($source !== '' && $target !== '') {
                $pair = "{$source}->{$target}";

                if (isset($seenPairs[$pair])) {
                    $result->addError('edge.duplicate', "Duplicate edge from '{$source}' to '{$target}' is not allowed.", $path, null, $edgeId);
                }

                $seenPairs[$pair] = true;
            }

            if (! in_array($edge['branch_type'] ?? 'default', ['default', 'conditional', 'parallel'], true)) {
                $result->addError('edge.branch_type_invalid', 'Edge branch type must be default, conditional, or parallel.', "{$path}.branch_type", null, $edgeId);
            }
        }
    }

    protected function verifyConfigFields(
        iterable $fields,
        array $config,
        string $path,
        ?string $nodeId,
        WorkflowVerificationResult $result,
    ): void {
        foreach ($fields as $field) {
            if ($field->is_required && blank($config[$field->key] ?? null)) {
                $result->addError(
                    'config.required_missing',
                    "Required config field '{$field->key}' is missing.",
                    "{$path}.{$field->key}",
                    $nodeId
                );

                continue;
            }

            if (! array_key_exists($field->key, $config) || blank($config[$field->key])) {
                continue;
            }

            if (! $this->configValueMatchesFieldType($config[$field->key], $field)) {
                $result->addError(
                    'config.type_invalid',
                    "Config field '{$field->key}' has an invalid value for type '{$this->fieldTypeValue($field)}'.",
                    "{$path}.{$field->key}",
                    $nodeId
                );
            }
        }
    }

    protected function configValueMatchesFieldType(mixed $value, NodeConfigField $field): bool
    {
        return match ($this->fieldType($field)) {
            NodeConfigFieldType::TEXT,
            NodeConfigFieldType::TEXTAREA,
            NodeConfigFieldType::SELECT => is_string($value) || is_numeric($value),
            NodeConfigFieldType::EMAIL => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            NodeConfigFieldType::NUMBER => is_numeric($value),
            NodeConfigFieldType::TOGGLE => is_bool($value),
            NodeConfigFieldType::TAGS,
            NodeConfigFieldType::JSON => is_array($value),
        };
    }

    protected function fieldType(NodeConfigField $field): NodeConfigFieldType
    {
        return $field->type instanceof NodeConfigFieldType
            ? $field->type
            : NodeConfigFieldType::from((string) $field->type);
    }

    protected function fieldTypeValue(NodeConfigField $field): string
    {
        return $this->fieldType($field)->value;
    }
}
