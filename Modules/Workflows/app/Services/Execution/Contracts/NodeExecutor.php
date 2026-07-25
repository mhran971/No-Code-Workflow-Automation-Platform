<?php

namespace Modules\Workflows\Services\Execution\Contracts;

use Modules\Workflows\Enums\NodeCategory;
use Modules\Workflows\Services\Execution\NodeExecutionContext;
use Modules\Workflows\Services\Execution\Data\NodeExecutionResult;

/**
 * One implementation per node type. Mirrors the verification side's NodeTypeRule registry so authoring and
 * runtime stay symmetric.
 */
interface NodeExecutor
{
    /** The node type key this executor handles, e.g. 'send-email'. */
    public function type(): string;

    /** Execution category — drives default per-attempt timeout and retry policy. */
    public function category(): NodeCategory;

    public function execute(NodeExecutionContext $context): NodeExecutionResult;
}
