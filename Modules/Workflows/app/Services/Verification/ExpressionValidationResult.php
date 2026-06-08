<?php

namespace Modules\Workflows\Services\Verification;

class ExpressionValidationResult
{
    /**
     * @param list<string> $variables
     */
    public function __construct(
        public readonly bool $valid,
        public readonly bool $boolean,
        public readonly array $variables = [],
        public readonly ?string $message = null,
    ) {}
}
