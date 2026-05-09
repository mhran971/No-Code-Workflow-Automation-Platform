<?php

namespace Modules\Integrations\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Integrations\Models\IntegrationProvider;

class SchemaValidator
{
    /**
     * Validate a payload against the stored provider schema.
     *
     * @throws ValidationException
     */
    public function validateForProvider(IntegrationProvider $provider, array $authConfig, array $config): void
    {
        $this->validateSchema($this->normalizeSchema($provider->auth_schema), $authConfig, 'auth_config');
        $this->validateSchema($this->normalizeSchema($provider->config_schema), $config, 'config');
    }

    /**
     * Convert the stored schema metadata into Laravel validation rules.
     *
     * @throws ValidationException
     */
    protected function validateSchema(array $schema, array $payload, string $bag): void
    {
        $rules = [];

        foreach ($schema as $field => $definition) {
            $definition = is_array($definition) ? $definition : [];
            $required = (bool) Arr::get($definition, 'required', false);
            $type = (string) Arr::get($definition, 'type', 'string');

            $fieldRules = [$required ? 'required' : 'nullable'];

            $fieldRules[] = match ($type) {
                'array' => 'array',
                'boolean' => 'boolean',
                'integer' => 'integer',
                'numeric' => 'numeric',
                'email' => 'email',
                default => 'string',
            };

            $rules[$field] = $fieldRules;
        }

        Validator::make($payload, $rules)->validate();
    }

    /**
     * Normalize a schema to an array, handling JSON-encoded strings or native arrays.
     */
    protected function normalizeSchema(mixed $schema): array
    {
        if (is_array($schema)) {
            return $schema;
        }

        if (is_string($schema) && $schema !== '') {
            try {
                $decoded = json_decode($schema, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }
}
