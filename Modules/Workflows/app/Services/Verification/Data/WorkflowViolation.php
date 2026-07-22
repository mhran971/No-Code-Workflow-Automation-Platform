<?php

namespace Modules\Workflows\Services\Verification\Data;

class WorkflowViolation
{
    public function __construct(
        public readonly string $severity,
        public readonly string $code,
        public readonly string $message,
        public readonly ?string $path = null,
        public readonly ?string $nodeId = null,
        public readonly ?string $edgeId = null,
    ) {}

    public static function error(
        string $code,
        string $message,
        ?string $path = null,
        ?string $nodeId = null,
        ?string $edgeId = null,
    ): self {
        return new self('error', $code, $message, $path, $nodeId, $edgeId);
    }

    public static function warning(
        string $code,
        string $message,
        ?string $path = null,
        ?string $nodeId = null,
        ?string $edgeId = null,
    ): self {
        return new self('warning', $code, $message, $path, $nodeId, $edgeId);
    }

    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'code' => $this->code,
            'message' => $this->message,
            'path' => $this->path,
            'node_id' => $this->nodeId,
            'edge_id' => $this->edgeId,
        ];
    }
}
