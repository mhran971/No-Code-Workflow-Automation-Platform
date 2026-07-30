<?php

namespace Modules\Workflows\Services\Verification\Data;

class WorkflowVerificationResult
{
    /**
     * @var list<WorkflowViolation>
     */
    protected array $issues = [];

    public function add(WorkflowViolation $violation): void
    {
        $this->issues[] = $violation;
    }

    public function addError(
        string $code,
        string $message,
        ?string $path = null,
        ?string $nodeId = null,
        ?string $edgeId = null,
    ): void {
        $this->add(WorkflowViolation::error($code, $message, $path, $nodeId, $edgeId));
    }

    public function addWarning(
        string $code,
        string $message,
        ?string $path = null,
        ?string $nodeId = null,
        ?string $edgeId = null,
    ): void {
        $this->add(WorkflowViolation::warning($code, $message, $path, $nodeId, $edgeId));
    }

    /**
     * @return list<WorkflowViolation>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function errorCount(): int
    {
        return count(array_filter(
            $this->issues,
            fn (WorkflowViolation $issue): bool => $issue->severity === 'error'
        ));
    }

    public function warningCount(): int
    {
        return count(array_filter(
            $this->issues,
            fn (WorkflowViolation $issue): bool => $issue->severity === 'warning'
        ));
    }

    public function isPublishable(): bool
    {
        return $this->errorCount() === 0;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return array_values(array_map(
            fn (WorkflowViolation $issue): string => $issue->message,
            array_filter($this->issues, fn (WorkflowViolation $issue): bool => $issue->severity === 'error')
        ));
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_values(array_map(
            fn (WorkflowViolation $issue): string => $issue->message,
            array_filter($this->issues, fn (WorkflowViolation $issue): bool => $issue->severity === 'warning')
        ));
    }

    public function toArray(): array
    {
        return [
            'is_publishable' => $this->isPublishable(),
            'summary' => [
                'errors' => $this->errorCount(),
                'warnings' => $this->warningCount(),
            ],
            'issues' => array_map(
                fn (WorkflowViolation $issue): array => $issue->toArray(),
                $this->issues
            ),
            'errors' => $this->errors(),
            'warnings' => $this->warnings(),
        ];
    }
}
