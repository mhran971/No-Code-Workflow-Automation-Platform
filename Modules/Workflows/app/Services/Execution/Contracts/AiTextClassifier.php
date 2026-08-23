<?php

namespace Modules\Workflows\Services\Execution\Contracts;

use Modules\Workflows\Services\Execution\Exceptions\AiServiceException;

interface AiTextClassifier
{
    /**
     * Classify $text into exactly one of $categories.
     *
     * @param  list<string>  $categories  allowed category labels (at least 2)
     * @return array{classification: string, confidence: float}
     *
     * @throws AiServiceException on hard failure (non-2xx) or soft failure (success: false)
     */
    public function classify(string $text, array $categories, string $tenantId): array;
}
