<?php

namespace Modules\Workflows\Services\Execution;

/**
 * An execution-shaped view of a single workflow edge, derived from the normalized definition.
 */
final class PlanEdge
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $source,
        public readonly string $target,
        public readonly ?string $branchType,
        public readonly ?string $conditionExpression,
        public readonly bool $isDefaultBranch,
        public readonly ?string $joinNodeKey,
        public readonly int $sortOrder,
    ) {}

    /**
     * @param  array<string, mixed>  $edge  a normalized edge array
     */
    public static function fromNormalized(array $edge): self
    {
        return new self(
            id: isset($edge['id']) ? (string) $edge['id'] : null,
            source: (string) ($edge['source_node_key'] ?? ''),
            target: (string) ($edge['target_node_key'] ?? ''),
            branchType: isset($edge['branch_type']) ? (string) $edge['branch_type'] : null,
            conditionExpression: isset($edge['condition_expression']) ? (string) $edge['condition_expression'] : null,
            isDefaultBranch: (bool) ($edge['is_default_branch'] ?? false),
            joinNodeKey: isset($edge['join_node_key']) ? (string) $edge['join_node_key'] : null,
            sortOrder: (int) ($edge['sort_order'] ?? 0),
        );
    }

    public function isConditional(): bool
    {
        return $this->conditionExpression !== null && $this->conditionExpression !== '';
    }

    public function isParallel(): bool
    {
        return $this->joinNodeKey !== null && $this->joinNodeKey !== '';
    }

    public function isErrorEdge(): bool
    {
        return false;
    }
}
