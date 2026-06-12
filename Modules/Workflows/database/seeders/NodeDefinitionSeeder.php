<?php

namespace Modules\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflows\Enums\NodeConfigFieldType;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Models\NodeConfigField;

class NodeDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            // ── Triggers ────────────────────────────────────────────────────
            [
                'type' => 'manual-trigger',
                'label' => 'Manual',
                'category' => 'trigger',
                'description' => 'Start workflow manually',
                'color' => 'node-trigger',
                'icon' => 'MousePointerClick',
                'configFields' => [
                    ['key' => 'variables', 'label' => 'Test Variables', 'type' => 'json', 'placeholder' => '{"key": "value"}'],
                ],
            ],
            [
                'type' => 'form-trigger',
                'label' => 'Form Trigger',
                'category' => 'trigger',
                'description' => 'Start from form submission',
                'color' => 'node-trigger',
                'icon' => 'FormInput',
                'configFields' => [
                    ['key' => 'formName', 'label' => 'Form Name', 'type' => 'text', 'required' => true],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    [
                        'key' => 'accessLevel',
                        'label' => 'Access Level',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Public', 'value' => 'public'],
                            ['label' => 'Tenant Users Only', 'value' => 'tenant'],
                            ['label' => 'Token Protected', 'value' => 'token'],
                        ],
                    ],
                    ['key' => 'captcha', 'label' => 'Enable CAPTCHA', 'type' => 'toggle', 'defaultValue' => false],
                ],
            ],

            // ── Logic ────────────────────────────────────────────────────────
            [
                'type' => 'if-node',
                'label' => 'If',
                'category' => 'logic',
                'description' => 'Conditional branching',
                'color' => 'node-logic',
                'icon' => 'GitBranch',
                'configFields' => [
                    ['key' => 'conditions', 'label' => 'Conditions (JSON array)', 'type' => 'json', 'placeholder' => '[{"field":"status","op":"eq","value":"active"}]'],
                ],
            ],
            [
                'type' => 'and-node',
                'label' => 'And',
                'category' => 'logic',
                'description' => 'Continue only when all conditions are true',
                'color' => 'node-logic',
                'icon' => 'ListChecks',
                'configFields' => [
                    ['key' => 'conditions', 'label' => 'Conditions (JSON array)', 'type' => 'json', 'placeholder' => '[{"field":"status","op":"eq","value":"active"}]'],
                ],
            ],
            [
                'type' => 'merge-or',
                'label' => 'Merge Or',
                'category' => 'logic',
                'description' => 'Continue when any incoming branch completes',
                'color' => 'node-logic',
                'icon' => 'GitMerge',
                'configFields' => [],
            ],
            [
                'type' => 'merge-and',
                'label' => 'Merge And',
                'category' => 'logic',
                'description' => 'Continue when all incoming branches complete',
                'color' => 'node-logic',
                'icon' => 'Merge',
                'configFields' => [
                    ['key' => 'timeout', 'label' => 'Timeout (seconds)', 'type' => 'number', 'defaultValue' => 300],
                ],
            ],
            [
                'type' => 'switch',
                'label' => 'Switch',
                'category' => 'logic',
                'description' => 'Multi-path routing based on a value',
                'color' => 'node-logic',
                'icon' => 'Route',
                'configFields' => [
                    ['key' => 'expression', 'label' => 'Expression', 'type' => 'text', 'required' => true],
                    ['key' => 'cases', 'label' => 'Cases (JSON)', 'type' => 'json', 'placeholder' => '[{"label":"Case 1","value":"value1"}]'],
                    ['key' => 'hasDefault', 'label' => 'Has Default Case', 'type' => 'toggle', 'defaultValue' => true],
                ],
            ],

            // ── AI ───────────────────────────────────────────────────────────
            [
                'type' => 'ai-generator',
                'label' => 'AI Generator',
                'category' => 'ai',
                'description' => 'Generate content with AI',
                'color' => 'node-ai',
                'icon' => 'Wand2',
                'configFields' => [
                    [
                        'key' => 'contentType',
                        'label' => 'Content Type',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Email', 'value' => 'email'],
                            ['label' => 'Proposal', 'value' => 'proposal'],
                            ['label' => 'Report', 'value' => 'report'],
                            ['label' => 'Social Post', 'value' => 'social'],
                        ],
                    ],
                    ['key' => 'tone', 'label' => 'Tone', 'type' => 'text'],
                    ['key' => 'template', 'label' => 'Template / Prompt', 'type' => 'textarea', 'required' => true],
                ],
            ],

            // ── Flows ────────────────────────────────────────────────────────
            [
                'type' => 'sub-workflow',
                'label' => 'Sub Workflow',
                'category' => 'flows',
                'description' => 'Execute another workflow as a step',
                'color' => 'node-flows',
                'icon' => 'Workflow',
                'configFields' => [
                    ['key' => 'workflowId', 'label' => 'Workflow ID', 'type' => 'text', 'required' => true],
                    ['key' => 'inputMapping', 'label' => 'Input Mapping (JSON)', 'type' => 'json', 'placeholder' => '{"param":"{{variable}}"}'],
                    ['key' => 'outputVariable', 'label' => 'Output Variable', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'dynamic-flow',
                'label' => 'Dynamic Flow',
                'category' => 'flows',
                'description' => 'Runtime sub-flow creation',
                'color' => 'node-flows',
                'icon' => 'Shuffle',
                'configFields' => [
                    ['key' => 'message', 'label' => 'Manager Message', 'type' => 'textarea', 'defaultValue' => 'Manager must design a custom sub-flow'],
                    ['key' => 'aiSuggestion', 'label' => 'Enable AI Suggestion', 'type' => 'toggle', 'defaultValue' => true],
                ],
            ],

            // ── Actions ──────────────────────────────────────────────────────
            [
                'type' => 'send-email',
                'label' => 'Send Email',
                'category' => 'action',
                'description' => 'Send an email message',
                'color' => 'node-action',
                'icon' => 'Send',
                'configFields' => [
                    ['key' => 'from', 'label' => 'From', 'type' => 'email', 'required' => true],
                    ['key' => 'to', 'label' => 'To', 'type' => 'email', 'required' => true],
                    ['key' => 'cc', 'label' => 'CC', 'type' => 'email'],
                    ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'textarea'],
                    ['key' => 'isHtml', 'label' => 'HTML Body', 'type' => 'toggle', 'defaultValue' => false],
                ],
            ],
            [
                'type' => 'task-node',
                'label' => 'Task Node',
                'category' => 'action',
                'description' => 'Assign a task to a team member for review or approval',
                'color' => 'node-action',
                'icon' => 'UserCheck',
                'configFields' => [
                    ['key' => 'taskName', 'label' => 'Task Name', 'type' => 'text', 'required' => true],
                    [
                        'key' => 'taskType',
                        'label' => 'Task Type',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Review', 'value' => 'review'],
                            ['label' => 'Approval', 'value' => 'approval'],
                            ['label' => 'Data Collection', 'value' => 'data'],
                        ],
                    ],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['key' => 'outcomes', 'label' => 'Outcomes', 'type' => 'tags', 'required' => true],
                    ['key' => 'assignee', 'label' => 'Assignee', 'type' => 'text'],
                    ['key' => 'slaDuration', 'label' => 'SLA Duration (hours)', 'type' => 'number'],
                    ['key' => 'enableReminders', 'label' => 'Enable Reminders', 'type' => 'toggle', 'defaultValue' => true],
                ],
            ],
        ];

        $allowedTypes = array_column($definitions, 'type');

        // Deactivate any nodes not in the allowed set
        Node::query()->whereNotIn('type', $allowedTypes)->update(['is_active' => false]);

        foreach ($definitions as $definition) {
            $node = Node::query()->updateOrCreate(
                ['type' => $definition['type']],
                [
                    'label' => $definition['label'],
                    'category' => $definition['category'],
                    'icon' => $definition['icon'],
                    'description' => $definition['description'],
                    'color' => $definition['color'],
                    'is_active' => true,
                ]
            );

            $keys = [];

            foreach ($definition['configFields'] as $fieldIndex => $field) {
                $keys[] = $field['key'];

                NodeConfigField::query()->updateOrCreate(
                    [
                        'node_id' => $node->id,
                        'key' => $field['key'],
                    ],
                    [
                        'label' => $field['label'],
                        'type' => NodeConfigFieldType::from($field['type'])->value,
                        'placeholder' => $field['placeholder'] ?? null,
                        'options' => $field['options'] ?? null,
                        'is_required' => $field['required'] ?? false,
                        'default_value' => array_key_exists('defaultValue', $field) ? $field['defaultValue'] : null,
                        'sort_order' => $fieldIndex + 1,
                    ]
                );
            }

            NodeConfigField::query()
                ->where('node_id', $node->id)
                ->whereNotIn('key', $keys)
                ->delete();
        }
    }
}
