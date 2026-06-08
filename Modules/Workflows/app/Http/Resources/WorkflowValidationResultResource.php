<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Workflows\Http\Requests\ValidateWorkflowDefinitionRequest;

class WorkflowValidationResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'request_schema' => ValidateWorkflowDefinitionRequest::definitionSchema(),
            'example_definition' => ValidateWorkflowDefinitionRequest::exampleDefinition(),
            'response_schema' => self::responseSchema(),
            'is_publishable' => (bool) data_get($this->resource, 'is_publishable', false),
            'summary' => [
                'errors' => (int) data_get($this->resource, 'summary.errors', 0),
                'warnings' => (int) data_get($this->resource, 'summary.warnings', 0),
            ],
            'issues' => array_map(
                fn (array $issue): array => $this->formatIssue($issue),
                data_get($this->resource, 'issues', [])
            ),
            'errors' => data_get($this->resource, 'errors', []),
            'warnings' => data_get($this->resource, 'warnings', []),
        ];
    }

    public static function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'request_schema' => ['type' => 'object'],
                'example_definition' => ['type' => 'object'],
                'response_schema' => ['type' => 'object'],
                'is_publishable' => ['type' => 'boolean'],
                'summary' => [
                    'type' => 'object',
                    'properties' => [
                        'errors' => ['type' => 'integer'],
                        'warnings' => ['type' => 'integer'],
                    ],
                ],
                'issues' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'severity' => ['type' => 'string'],
                            'code' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'location' => [
                                'type' => 'object',
                                'properties' => [
                                    'path' => ['type' => 'string', 'nullable' => true],
                                    'field' => ['type' => 'string', 'nullable' => true],
                                    'scope' => ['type' => 'string', 'nullable' => true],
                                    'node_id' => ['type' => 'string', 'nullable' => true],
                                    'edge_id' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                'errors' => ['type' => 'array'],
                'warnings' => ['type' => 'array'],
            ],
        ];
    }

    protected function formatIssue(array $issue): array
    {
        $path = data_get($issue, 'path');

        return [
            'severity' => data_get($issue, 'severity'),
            'code' => data_get($issue, 'code'),
            'message' => data_get($issue, 'message'),
            'location' => [
                'path' => $path,
                'field' => $this->fieldFromPath($path),
                'scope' => $this->scopeFromPath($path),
                'node_id' => data_get($issue, 'node_id'),
                'edge_id' => data_get($issue, 'edge_id'),
            ],
            'path' => $path,
            'node_id' => data_get($issue, 'node_id'),
            'edge_id' => data_get($issue, 'edge_id'),
        ];
    }

    protected function fieldFromPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = preg_replace('/\[(\d+)\]/', '', $path) ?? $path;
        $segments = array_values(array_filter(explode('.', $normalized), fn (string $segment): bool => $segment !== ''));

        return $segments === [] ? null : end($segments);
    }

    protected function scopeFromPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        foreach (['trigger', 'nodes', 'edges', 'variables', 'settings'] as $scope) {
            if (str_starts_with($path, $scope)) {
                return $scope === 'nodes' ? 'node' : ($scope === 'edges' ? 'edge' : $scope);
            }
        }

        return null;
    }
}
