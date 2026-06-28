<?php

namespace Modules\Workflows\Services\Execution;

use Modules\Workflows\Services\Execution\Contracts\AiContentGenerator;

/**
 * Stand-in AI generator used when no provider is configured. Returns an empty string so the
 * workflow can proceed without failing — replace with a real provider once one is integrated.
 */
class NullAiContentGenerator implements AiContentGenerator
{
    public function generate(string $prompt, array $config = []): string
    {
        return '';
    }
}
