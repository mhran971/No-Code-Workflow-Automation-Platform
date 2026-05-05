<?php

namespace Modules\Integrations\Exceptions;

use Exception;

class IntegrationException extends Exception
{
    public function __construct(string $message, protected int $status = 400)
    {
        parent::__construct($message);
    }

    public static function inactiveProvider(string $provider): self
    {
        return new self("The {$provider} integration is not active.", 422);
    }

    public static function unknownProvider(string $provider): self
    {
        return new self("The {$provider} integration is not supported.", 404);
    }

    public static function invalidState(): self
    {
        return new self('The integration state is invalid or expired.', 422);
    }

    public static function tokenExchangeFailed(string $provider): self
    {
        return new self("Unable to connect to {$provider}. Please try again.", 502);
    }

    public static function actionNotConfigured(string $action): self
    {
        return new self("The {$action} action is not configured.", 404);
    }

    public function status(): int
    {
        return $this->status;
    }
}
