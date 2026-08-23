<?php

namespace Modules\Workflows\Services\Execution\Contracts;

use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;

interface AiContentGenerator
{
    /**
     * @param  array<string, mixed>  $config  node config (tone, knowledgeBaseDocuments, etc.)
     *
     * @throws AiServiceException on hard failure (non-2xx) or soft failure (success: false)
     */
    public function generate(string $prompt, string $tenantId, array $config = []): string;
}
