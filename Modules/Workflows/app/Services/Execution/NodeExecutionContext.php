<?php

namespace Modules\Workflows\Services\Execution;

use Modules\Workflows\Models\WorkflowInstance;
use Modules\Workflows\Models\WorkflowNodeExecution;
use Modules\Workflows\Services\Execution\Expression\ExpressionEvaluator;
use Modules\Workflows\Services\Execution\Expression\TemplateInterpolator;

/**
 * The read/write view a NodeExecutor is given for one execution: the node's config, the instance context
 * (variable store), and the expression/interpolation helpers — all resolved against the same scope.
 *
 * Context mutations are buffered on the in-memory instance; the runtime persists them when it commits the
 * execution's result (under the instance lock).
 */
class NodeExecutionContext
{
    public function __construct(
        protected WorkflowInstance $instance,
        protected WorkflowNodeExecution $execution,
        protected ExecutionPlan $plan,
        protected ExpressionEvaluator $evaluator,
        protected TemplateInterpolator $interpolator,
    ) {}

    public function instance(): WorkflowInstance
    {
        return $this->instance;
    }

    public function execution(): WorkflowNodeExecution
    {
        return $this->execution;
    }

    public function plan(): ExecutionPlan
    {
        return $this->plan;
    }

    public function instanceId(): int
    {
        return (int) $this->instance->id;
    }

    public function nodeKey(): string
    {
        return (string) $this->execution->node_key;
    }

    public function nodeType(): string
    {
        return (string) $this->execution->node_type;
    }

    public function idempotencyKey(): string
    {
        return (string) $this->execution->idempotency_key;
    }

    public function attempt(): int
    {
        return (int) $this->execution->attempt;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->plan->config($this->nodeKey());
    }

    /**
     * The data scope identifiers resolve against, e.g. `context.age`, `trigger.email`.
     *
     * @return array<string, mixed>
     */
    public function resolutionScope(): array
    {
        return [
            'context' => $this->instance->context ?? [],
            'trigger' => $this->instance->payload ?? [],
            'input' => $this->execution->input ?? [],
            'customer' => $this->instance->customer?->toTemplateArray() ?? [],
        ];
    }

    public function evaluate(string $expression): mixed
    {
        return $this->evaluator->evaluate($expression, $this->resolutionScope());
    }

    public function evaluateBoolean(string $expression): bool
    {
        return $this->evaluator->evaluateBoolean($expression, $this->resolutionScope());
    }

    public function render(string $template): string
    {
        return $this->interpolator->render($template, $this->resolutionScope());
    }

    /**
     * Buffer a write into the instance context (persisted by the runtime on commit).
     */
    public function setContextValue(string $key, mixed $value): void
    {
        $context = $this->instance->context ?? [];
        data_set($context, $key, $value);
        $this->instance->context = $context;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergeContext(array $values): void
    {
        $this->instance->context = array_replace_recursive($this->instance->context ?? [], $values);
    }

    /**
     * Buffer linking the instance to a resolved Customer (persisted by the runtime on commit,
     * same as context mutations — see WorkflowExecutionEngine::onSucceed()'s isDirty() check).
     */
    public function setCustomerId(int $customerId): void
    {
        $this->instance->customer_id = $customerId;
    }
}
