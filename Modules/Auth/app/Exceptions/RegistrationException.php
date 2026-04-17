<?php

namespace Modules\Auth\Exceptions;

use Exception;

class RegistrationException extends Exception
{
    public static function emailAlreadyTaken(): self
    {
        return new self('A user with this email already exists.');
    }

    public static function tenantCreationFailed(): self
    {
        return new self('Unable to create the organisation. Please try again.');
    }
}
