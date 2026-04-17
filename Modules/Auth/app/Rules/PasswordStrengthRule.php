<?php

namespace Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordStrengthRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if (! preg_match('/\d/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        if (! preg_match('/[@$!%*?&]/', $value)) {
            $fail('The :attribute must contain at least one special character (@$!%*?&).');
        }
    }
}
