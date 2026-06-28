<?php

namespace Modules\Workflows\Services\Execution\Contracts;

interface AiContentGenerator
{
    /**
     * @param  array<string, mixed>  $config  node config (content_type, tone, etc.)
     */
    public function generate(string $prompt, array $config = []): string;
}
