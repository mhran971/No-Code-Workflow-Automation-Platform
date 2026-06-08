<?php

namespace Modules\Workflows\Services\Verification;

class WorkflowDefinitionNormalizer
{
    public function normalize(array $definition): array
    {
        return [
            'trigger' => $this->normalizeTrigger($definition['trigger'] ?? null),
            'variables' => is_array($definition['variables'] ?? null) ? array_values($definition['variables']) : [],
            'nodes' => $this->normalizeNodes($definition['nodes'] ?? null),
            'edges' => $this->normalizeEdges($definition['edges'] ?? null),
            'settings' => is_array($definition['settings'] ?? null) ? $definition['settings'] : [],
            '_raw' => $definition,
        ];
    }

    protected function normalizeTrigger(mixed $trigger): ?array
    {
        if (! is_array($trigger)) {
            return null;
        }

        return [
            'type' => isset($trigger['type']) ? (string) $trigger['type'] : null,
            'config' => is_array($trigger['config'] ?? null) ? $trigger['config'] : [],
            '_raw' => $trigger,
        ];
    }

    protected function normalizeNodes(mixed $nodes): array
    {
        if (! is_array($nodes)) {
            return [];
        }

        $nodes = array_values($nodes);

        return array_map(function (mixed $node, int $index): array {
            if (! is_array($node)) {
                return [
                    'id' => null,
                    'type' => null,
                    'label' => null,
                    'config' => [],
                    'is_entry_point' => false,
                    'is_terminal' => false,
                    '_index' => $index,
                    '_invalid' => true,
                    '_raw' => $node,
                ];
            }

            return [
                'id' => isset($node['id']) ? (string) $node['id'] : (isset($node['key']) ? (string) $node['key'] : null),
                'type' => isset($node['type']) ? (string) $node['type'] : null,
                'label' => isset($node['label']) ? (string) $node['label'] : null,
                'config' => is_array($node['config'] ?? null) ? $node['config'] : [],
                'is_entry_point' => (bool) ($node['is_entry_point'] ?? false),
                'is_terminal' => (bool) ($node['is_terminal'] ?? false),
                '_index' => $index,
                '_invalid' => false,
                '_raw' => $node,
            ];
        }, $nodes, array_keys($nodes));
    }

    protected function normalizeEdges(mixed $edges): array
    {
        if (! is_array($edges)) {
            return [];
        }

        $edges = array_values($edges);

        return array_map(function (mixed $edge, int $index): array {
            if (! is_array($edge)) {
                return [
                    'id' => null,
                    'source_node_key' => null,
                    'target_node_key' => null,
                    'branch_type' => 'default',
                    'condition_expression' => null,
                    'is_default_branch' => false,
                    'parallel_strategy' => null,
                    'join_node_key' => null,
                    'sort_order' => 0,
                    '_index' => $index,
                    '_invalid' => true,
                    '_raw' => $edge,
                ];
            }

            $source = $edge['source_node_key'] ?? $edge['source'] ?? $edge['from'] ?? null;
            $target = $edge['target_node_key'] ?? $edge['target'] ?? $edge['to'] ?? null;

            return [
                'id' => isset($edge['id']) ? (string) $edge['id'] : sprintf('edge-%d', $index + 1),
                'source_node_key' => $source !== null ? (string) $source : null,
                'target_node_key' => $target !== null ? (string) $target : null,
                'branch_type' => isset($edge['branch_type']) ? (string) $edge['branch_type'] : 'default',
                'condition_expression' => isset($edge['condition_expression'])
                    ? (string) $edge['condition_expression']
                    : (isset($edge['condition']) ? (string) $edge['condition'] : null),
                'is_default_branch' => (bool) ($edge['is_default_branch'] ?? false),
                'parallel_strategy' => isset($edge['parallel_strategy']) ? (string) $edge['parallel_strategy'] : null,
                'join_node_key' => isset($edge['join_node_key']) ? (string) $edge['join_node_key'] : null,
                'sort_order' => isset($edge['sort_order']) ? (int) $edge['sort_order'] : 0,
                '_index' => $index,
                '_invalid' => false,
                '_raw' => $edge,
            ];
        }, $edges, array_keys($edges));
    }
}
