<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\Role;

class ValidateWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array(auth('api')->user()?->role, [Role::Manager, Role::BusinessOwner], true);
    }

    public function rules(): array
    {
        return [
            'definition' => ['required', 'array'],
            'definition.trigger' => ['present', 'array'],
            'definition.trigger.type' => ['required', 'string'],
            'definition.trigger.config' => ['present', 'array'],
            'definition.nodes' => ['present', 'array'],
            'definition.nodes.*.id' => ['required', 'string'],
            'definition.nodes.*.type' => ['required', 'string'],
            'definition.nodes.*.label' => ['sometimes', 'nullable', 'string'],
            'definition.nodes.*.config' => ['present', 'array'],
            'definition.nodes.*.is_entry_point' => ['sometimes', 'boolean'],
            'definition.nodes.*.is_terminal' => ['sometimes', 'boolean'],
            'definition.edges' => ['present', 'array'],
            'definition.edges.*.id' => ['sometimes', 'nullable', 'string'],
            'definition.edges.*.source_node_key' => ['required', 'string'],
            'definition.edges.*.target_node_key' => ['required', 'string'],
            'definition.edges.*.branch_type' => ['sometimes', 'string'],
            'definition.edges.*.condition_expression' => ['sometimes', 'nullable', 'string'],
            'definition.edges.*.is_default_branch' => ['sometimes', 'boolean'],
            'definition.edges.*.parallel_strategy' => ['sometimes', 'nullable', 'string'],
            'definition.edges.*.join_node_key' => ['sometimes', 'nullable', 'string'],
            'definition.edges.*.sort_order' => ['sometimes', 'integer'],
            'definition.variables' => ['sometimes', 'array'],
            'definition.variables.*.name' => ['sometimes', 'string'],
            'definition.variables.*.type' => ['sometimes', 'string'],
            'definition.variables.*.default' => ['sometimes'],
            'definition.variables.*.value' => ['sometimes'],
            'definition.settings' => ['sometimes', 'array'],
        ];
    }

    public static function definitionSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['trigger', 'nodes', 'edges'],
            'properties' => [
                'trigger' => [
                    'type' => 'object',
                    'required' => ['type', 'config'],
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'description' => 'Active node type key for the trigger.',
                        ],
                        'config' => [
                            'type' => 'object',
                            'description' => 'Trigger-specific configuration fields returned by the node catalog.',
                        ],
                    ],
                ],
                'nodes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'type', 'config'],
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => 'Unique node identifier used for edges and validation messages.',
                            ],
                            'type' => [
                                'type' => 'string',
                                'description' => 'Active workflow node type key.',
                            ],
                            'label' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Optional display label for the node.',
                            ],
                            'config' => [
                                'type' => 'object',
                                'description' => 'Type-specific config object defined by the node catalog.',
                            ],
                            'is_entry_point' => [
                                'type' => 'boolean',
                                'description' => 'Marks the workflow entry node.',
                            ],
                            'is_terminal' => [
                                'type' => 'boolean',
                                'description' => 'Marks the workflow terminal node.',
                            ],
                        ],
                    ],
                ],
                'edges' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['source_node_key', 'target_node_key'],
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Optional edge identifier. If omitted, the backend may generate one.',
                            ],
                            'source_node_key' => [
                                'type' => 'string',
                                'description' => 'Source node id.',
                            ],
                            'target_node_key' => [
                                'type' => 'string',
                                'description' => 'Target node id.',
                            ],
                            'branch_type' => [
                                'type' => 'string',
                                'enum' => ['default', 'conditional', 'parallel'],
                                'description' => 'Branch type for the edge.',
                            ],
                            'condition_expression' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Expression required when branch_type is conditional.',
                            ],
                            'is_default_branch' => [
                                'type' => 'boolean',
                                'description' => 'Marks the default branch for conditional routing.',
                            ],
                            'parallel_strategy' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Parallel execution strategy, if the edge is parallel.',
                            ],
                            'join_node_key' => [
                                'type' => 'string',
                                'nullable' => true,
                                'description' => 'Join node key for fork-join parallel edges.',
                            ],
                            'sort_order' => [
                                'type' => 'integer',
                                'description' => 'Optional ordering value for graph rendering or execution.',
                            ],
                        ],
                    ],
                ],
                'variables' => [
                    'type' => 'array',
                    'description' => 'Optional variable definitions used by the workflow runtime.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'default' => ['type' => 'mixed'],
                            'value' => ['type' => 'mixed'],
                        ],
                    ],
                ],
                'settings' => [
                    'type' => 'object',
                    'description' => 'Optional workflow-wide settings object.',
                ],
            ],
        ];
    }

    public static function exampleDefinition(): array
    {
        return [
            'trigger' => [
                'type' => 'webhook-trigger',
                'config' => [
                    'webhookUrl' => 'https://example.test/workflows/employee-onboarding',
                ],
            ],
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'send-email',
                    'label' => 'Send welcome email',
                    'config' => [
                        'from' => 'hr@example.test',
                        'to' => 'employee@example.test',
                        'subject' => 'Welcome aboard',
                    ],
                    'is_entry_point' => true,
                    'is_terminal' => true,
                ],
            ],
            'edges' => [],
            'variables' => [],
            'settings' => [],
        ];
    }
}
