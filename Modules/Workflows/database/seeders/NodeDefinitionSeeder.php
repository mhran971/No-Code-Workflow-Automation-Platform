<?php

namespace Modules\Workflows\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflows\Enums\NodeConfigFieldType;
use Modules\Workflows\Models\Node;
use Modules\Workflows\Models\NodeConfigField;

class NodeDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = [
            [
                'type' => 'manual-trigger',
                'label' => 'Manual Trigger',
                'category' => 'trigger',
                'description' => 'Start workflow manually',
                'color' => 'node-trigger',
                'configFields' => [
                    ['key' => 'variables', 'label' => 'Test Variables', 'type' => 'json', 'placeholder' => '{"key": "value"}'],
                ],
            ],
            [
                'type' => 'webhook-trigger',
                'label' => 'Webhook Trigger',
                'category' => 'trigger',
                'description' => 'Receive HTTP POST requests',
                'color' => 'node-trigger',
                'configFields' => [
                    ['key' => 'webhookUrl', 'label' => 'Webhook URL', 'type' => 'text', 'placeholder' => 'Auto-generated'],
                    ['key' => 'payloadFields', 'label' => 'Required Payload Fields', 'type' => 'tags'],
                    ['key' => 'rateLimit', 'label' => 'Rate Limit (req/min)', 'type' => 'number', 'defaultValue' => 60],
                ],
            ],
            [
                'type' => 'form-trigger',
                'label' => 'Form Trigger',
                'category' => 'trigger',
                'description' => 'Start from form submission',
                'color' => 'node-trigger',
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
            [
                'type' => 'schedule-trigger',
                'label' => 'Schedule Trigger',
                'category' => 'trigger',
                'description' => 'Run on a schedule',
                'color' => 'node-trigger',
                'configFields' => [
                    [
                        'key' => 'scheduleType',
                        'label' => 'Schedule Type',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Interval', 'value' => 'interval'],
                            ['label' => 'Cron Expression', 'value' => 'cron'],
                        ],
                    ],
                    ['key' => 'interval', 'label' => 'Interval / Cron', 'type' => 'text', 'placeholder' => '*/5 * * * *'],
                    ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'defaultValue' => 'UTC'],
                ],
            ],
            [
                'type' => 'app-event-trigger',
                'label' => 'App Event Trigger',
                'category' => 'trigger',
                'description' => 'Trigger from external app event',
                'color' => 'node-trigger',
                'configFields' => [
                    [
                        'key' => 'application',
                        'label' => 'Application',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Gmail', 'value' => 'gmail'],
                            ['label' => 'HubSpot', 'value' => 'hubspot'],
                            ['label' => 'ClickUp', 'value' => 'clickup'],
                        ],
                    ],
                    ['key' => 'eventType', 'label' => 'Event Type', 'type' => 'text', 'placeholder' => 'e.g. New Email'],
                    ['key' => 'filters', 'label' => 'Event Filters', 'type' => 'json'],
                ],
            ],

            [
                'type' => 'http-request',
                'label' => 'HTTP Request',
                'category' => 'action',
                'description' => 'Make an HTTP request',
                'color' => 'node-action',
                'configFields' => [
                    [
                        'key' => 'method',
                        'label' => 'Method',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            ['label' => 'GET', 'value' => 'GET'],
                            ['label' => 'POST', 'value' => 'POST'],
                            ['label' => 'PUT', 'value' => 'PUT'],
                            ['label' => 'PATCH', 'value' => 'PATCH'],
                            ['label' => 'DELETE', 'value' => 'DELETE'],
                        ],
                    ],
                    ['key' => 'url', 'label' => 'URL', 'type' => 'text', 'required' => true, 'placeholder' => 'https://api.example.com'],
                    ['key' => 'headers', 'label' => 'Headers (JSON)', 'type' => 'json'],
                    ['key' => 'body', 'label' => 'Body (JSON)', 'type' => 'json'],
                    [
                        'key' => 'auth',
                        'label' => 'Authentication',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'None', 'value' => 'none'],
                            ['label' => 'API Key', 'value' => 'apikey'],
                            ['label' => 'Basic Auth', 'value' => 'basic'],
                            ['label' => 'Bearer Token', 'value' => 'bearer'],
                        ],
                    ],
                    ['key' => 'timeout', 'label' => 'Timeout (ms)', 'type' => 'number', 'defaultValue' => 30000],
                ],
            ],
            [
                'type' => 'send-email',
                'label' => 'Send Email',
                'category' => 'action',
                'description' => 'Send an email',
                'color' => 'node-action',
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
                'type' => 'external-service',
                'label' => 'External Service',
                'category' => 'action',
                'description' => 'Call an external service',
                'color' => 'node-action',
                'configFields' => [
                    [
                        'key' => 'service',
                        'label' => 'Service',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'HubSpot', 'value' => 'hubspot'],
                            ['label' => 'ClickUp', 'value' => 'clickup'],
                            ['label' => 'Custom', 'value' => 'custom'],
                        ],
                    ],
                    ['key' => 'action', 'label' => 'Action', 'type' => 'text', 'placeholder' => 'e.g. Create Contact'],
                    ['key' => 'config', 'label' => 'Configuration', 'type' => 'json'],
                ],
            ],

            [
                'type' => 'if-else',
                'label' => 'If/Else',
                'category' => 'control',
                'description' => 'Conditional branching',
                'color' => 'node-control',
                'configFields' => [
                    ['key' => 'conditions', 'label' => 'Conditions (JSON array)', 'type' => 'json', 'placeholder' => '[{"field":"status","op":"eq","value":"active"}]'],
                ],
            ],
            [
                'type' => 'switch',
                'label' => 'Switch / Router',
                'category' => 'control',
                'description' => 'Multi-path routing',
                'color' => 'node-control',
                'configFields' => [
                    ['key' => 'expression', 'label' => 'Expression', 'type' => 'text', 'required' => true],
                    ['key' => 'cases', 'label' => 'Cases (JSON)', 'type' => 'json', 'placeholder' => '{"case1":"value1"}'],
                    ['key' => 'hasDefault', 'label' => 'Has Default Case', 'type' => 'toggle', 'defaultValue' => true],
                ],
            ],
            [
                'type' => 'parallel-split',
                'label' => 'Parallel Split',
                'category' => 'control',
                'description' => 'Execute branches in parallel',
                'color' => 'node-control',
                'configFields' => [
                    ['key' => 'branches', 'label' => 'Number of Branches', 'type' => 'number', 'defaultValue' => 2],
                    ['key' => 'timeout', 'label' => 'Timeout (seconds)', 'type' => 'number', 'defaultValue' => 300],
                ],
            ],
            [
                'type' => 'parallel-join',
                'label' => 'Parallel Join',
                'category' => 'control',
                'description' => 'Wait for all branches',
                'color' => 'node-control',
                'configFields' => [
                    ['key' => 'timeout', 'label' => 'Timeout (seconds)', 'type' => 'number', 'defaultValue' => 300],
                    ['key' => 'allMustSucceed', 'label' => 'All Must Succeed', 'type' => 'toggle', 'defaultValue' => false],
                ],
            ],
            [
                'type' => 'wait',
                'label' => 'Wait',
                'category' => 'control',
                'description' => 'Pause execution',
                'color' => 'node-control',
                'configFields' => [
                    [
                        'key' => 'waitType',
                        'label' => 'Wait Type',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Duration', 'value' => 'duration'],
                            ['label' => 'Date/Time', 'value' => 'datetime'],
                            ['label' => 'Cron', 'value' => 'cron'],
                        ],
                    ],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text', 'placeholder' => 'e.g. 30s, 2025-07-01T00:00:00Z'],
                ],
            ],
            [
                'type' => 'loop',
                'label' => 'Loop',
                'category' => 'control',
                'description' => 'Iterate over data',
                'color' => 'node-control',
                'configFields' => [
                    [
                        'key' => 'loopType',
                        'label' => 'Loop Type',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'For-each', 'value' => 'foreach'],
                            ['label' => 'Fixed Count', 'value' => 'count'],
                            ['label' => 'While', 'value' => 'while'],
                        ],
                    ],
                    ['key' => 'source', 'label' => 'Source / Count / Condition', 'type' => 'text'],
                    ['key' => 'accumulator', 'label' => 'Accumulator Variable', 'type' => 'text'],
                ],
            ],

            [
                'type' => 'set-variables',
                'label' => 'Set Variables',
                'category' => 'data',
                'description' => 'Set workflow variables',
                'color' => 'node-data',
                'configFields' => [
                    ['key' => 'assignments', 'label' => 'Variable Assignments (JSON)', 'type' => 'json', 'placeholder' => '{"varName": "value"}'],
                ],
            ],
            [
                'type' => 'parse-json',
                'label' => 'Parse JSON',
                'category' => 'data',
                'description' => 'Parse JSON string',
                'color' => 'node-data',
                'configFields' => [
                    ['key' => 'source', 'label' => 'Source Variable', 'type' => 'text', 'required' => true],
                    ['key' => 'output', 'label' => 'Output Variable', 'type' => 'text', 'required' => true],
                    ['key' => 'paths', 'label' => 'JSON Paths to Extract', 'type' => 'json'],
                ],
            ],
            [
                'type' => 'format-date',
                'label' => 'Format Date',
                'category' => 'data',
                'description' => 'Format and convert dates',
                'color' => 'node-data',
                'configFields' => [
                    ['key' => 'source', 'label' => 'Source Variable', 'type' => 'text', 'required' => true],
                    [
                        'key' => 'format',
                        'label' => 'Output Format',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'ISO', 'value' => 'iso'],
                            ['label' => 'Human Readable', 'value' => 'human'],
                            ['label' => 'Unix Timestamp', 'value' => 'unix'],
                            ['label' => 'Custom', 'value' => 'custom'],
                        ],
                    ],
                    ['key' => 'timezone', 'label' => 'Timezone', 'type' => 'text'],
                    ['key' => 'output', 'label' => 'Output Variable', 'type' => 'text', 'required' => true],
                ],
            ],
            [
                'type' => 'filter',
                'label' => 'Filter',
                'category' => 'data',
                'description' => 'Filter array data',
                'color' => 'node-data',
                'configFields' => [
                    ['key' => 'inputArray', 'label' => 'Input Array Variable', 'type' => 'text', 'required' => true],
                    ['key' => 'condition', 'label' => 'Filter Condition', 'type' => 'text', 'required' => true],
                    ['key' => 'output', 'label' => 'Output Variable', 'type' => 'text', 'required' => true],
                ],
            ],

            [
                'type' => 'gmail-send',
                'label' => 'Gmail Send',
                'category' => 'integration',
                'description' => 'Send email via Gmail',
                'color' => 'node-integration',
                'configFields' => [
                    ['key' => 'to', 'label' => 'To', 'type' => 'email', 'required' => true],
                    ['key' => 'cc', 'label' => 'CC', 'type' => 'email'],
                    ['key' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'textarea'],
                ],
            ],
            [
                'type' => 'hubspot-contact',
                'label' => 'HubSpot Contact',
                'category' => 'integration',
                'description' => 'Create/Update HubSpot contact',
                'color' => 'node-integration',
                'configFields' => [
                    ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['key' => 'firstname', 'label' => 'First Name', 'type' => 'text'],
                    ['key' => 'lastname', 'label' => 'Last Name', 'type' => 'text'],
                    ['key' => 'company', 'label' => 'Company', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'hubspot-deal',
                'label' => 'HubSpot Deal',
                'category' => 'integration',
                'description' => 'Create/Update HubSpot deal',
                'color' => 'node-integration',
                'configFields' => [
                    ['key' => 'dealName', 'label' => 'Deal Name', 'type' => 'text', 'required' => true],
                    ['key' => 'dealStage', 'label' => 'Deal Stage', 'type' => 'text'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'number'],
                    ['key' => 'contactId', 'label' => 'Associated Contact ID', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'clickup-task',
                'label' => 'ClickUp Task',
                'category' => 'integration',
                'description' => 'Create ClickUp task',
                'color' => 'node-integration',
                'configFields' => [
                    ['key' => 'name', 'label' => 'Task Name', 'type' => 'text', 'required' => true],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    [
                        'key' => 'priority',
                        'label' => 'Priority',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Low', 'value' => 'low'],
                            ['label' => 'Normal', 'value' => 'normal'],
                            ['label' => 'High', 'value' => 'high'],
                            ['label' => 'Urgent', 'value' => 'urgent'],
                        ],
                    ],
                    ['key' => 'assignees', 'label' => 'Assignees', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'google-drive',
                'label' => 'Google Drive',
                'category' => 'integration',
                'description' => 'Create/update Drive files',
                'color' => 'node-integration',
                'configFields' => [
                    [
                        'key' => 'operation',
                        'label' => 'Operation',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Create Doc', 'value' => 'doc'],
                            ['label' => 'Create Sheet', 'value' => 'sheet'],
                            ['label' => 'Upload File', 'value' => 'upload'],
                        ],
                    ],
                    ['key' => 'folderId', 'label' => 'Parent Folder ID', 'type' => 'text'],
                    ['key' => 'fileName', 'label' => 'File Name', 'type' => 'text'],
                ],
            ],
            [
                'type' => 'google-sheets',
                'label' => 'Google Sheets',
                'category' => 'integration',
                'description' => 'Read/Write Google Sheets',
                'color' => 'node-integration',
                'configFields' => [
                    ['key' => 'spreadsheetId', 'label' => 'Spreadsheet ID', 'type' => 'text', 'required' => true],
                    ['key' => 'range', 'label' => 'Cell Range (A1)', 'type' => 'text', 'required' => true],
                    [
                        'key' => 'operation',
                        'label' => 'Operation',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Read', 'value' => 'read'],
                            ['label' => 'Append', 'value' => 'append'],
                            ['label' => 'Update', 'value' => 'update'],
                            ['label' => 'Clear', 'value' => 'clear'],
                        ],
                    ],
                ],
            ],

            [
                'type' => 'ai-router',
                'label' => 'AI Router',
                'category' => 'ai',
                'description' => 'AI-powered routing',
                'color' => 'node-ai',
                'configFields' => [
                    ['key' => 'routes', 'label' => 'Routes (JSON array)', 'type' => 'json', 'required' => true, 'placeholder' => '["route1","route2"]'],
                    ['key' => 'kbDocs', 'label' => 'Knowledge Base Documents', 'type' => 'tags'],
                    ['key' => 'contextVars', 'label' => 'Context Variables', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'ai-classifier',
                'label' => 'AI Classifier',
                'category' => 'ai',
                'description' => 'Classify data with AI',
                'color' => 'node-ai',
                'configFields' => [
                    ['key' => 'dimensions', 'label' => 'Classification Dimensions (JSON)', 'type' => 'json', 'required' => true],
                    ['key' => 'kbDocs', 'label' => 'Knowledge Base Documents', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'ai-summarizer',
                'label' => 'AI Summarizer',
                'category' => 'ai',
                'description' => 'Summarize content with AI',
                'color' => 'node-ai',
                'configFields' => [
                    [
                        'key' => 'format',
                        'label' => 'Output Format',
                        'type' => 'select',
                        'options' => [
                            ['label' => 'Brief', 'value' => 'brief'],
                            ['label' => 'Detailed', 'value' => 'detailed'],
                            ['label' => 'Bullets', 'value' => 'bullets'],
                            ['label' => 'Executive', 'value' => 'executive'],
                        ],
                    ],
                    ['key' => 'focusArea', 'label' => 'Focus Area', 'type' => 'text'],
                    ['key' => 'wordLimit', 'label' => 'Word Limit', 'type' => 'number'],
                ],
            ],
            [
                'type' => 'ai-custom',
                'label' => 'Custom AI Agent',
                'category' => 'ai',
                'description' => 'Custom AI with prompt',
                'color' => 'node-ai',
                'configFields' => [
                    ['key' => 'systemPrompt', 'label' => 'System Prompt', 'type' => 'textarea', 'required' => true],
                    ['key' => 'outputSchema', 'label' => 'Expected Output Schema (JSON)', 'type' => 'json'],
                    ['key' => 'kbDocs', 'label' => 'Knowledge Base Documents', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'ai-extractor',
                'label' => 'AI Extractor',
                'category' => 'ai',
                'description' => 'Extract structured data',
                'color' => 'node-ai',
                'configFields' => [
                    ['key' => 'extractionSchema', 'label' => 'Extraction Schema (JSON)', 'type' => 'json', 'required' => true],
                    ['key' => 'kbDocs', 'label' => 'Knowledge Base Documents', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'ai-sentiment',
                'label' => 'Sentiment Analyzer',
                'category' => 'ai',
                'description' => 'Analyze sentiment',
                'color' => 'node-ai',
                'configFields' => [
                    ['key' => 'sourceText', 'label' => 'Source Text Variable', 'type' => 'text', 'required' => true],
                    ['key' => 'kbDocs', 'label' => 'Knowledge Base Documents', 'type' => 'tags'],
                ],
            ],
            [
                'type' => 'ai-generator',
                'label' => 'Content Generator',
                'category' => 'ai',
                'description' => 'Generate content with AI',
                'color' => 'node-ai',
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
                    ['key' => 'template', 'label' => 'Template', 'type' => 'textarea'],
                ],
            ],

            [
                'type' => 'human-task',
                'label' => 'Human Task',
                'category' => 'human',
                'description' => 'Assign task to human',
                'color' => 'node-human',
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

            [
                'type' => 'dynamic-flow',
                'label' => 'Dynamic Flow',
                'category' => 'dynamic',
                'description' => 'Runtime sub-flow creation',
                'color' => 'node-dynamic',
                'configFields' => [
                    ['key' => 'message', 'label' => 'Manager Message', 'type' => 'textarea', 'defaultValue' => 'Manager must design a custom sub-flow'],
                    ['key' => 'aiSuggestion', 'label' => 'Enable AI Suggestion', 'type' => 'toggle', 'defaultValue' => true],
                ],
            ],
        ];

        $iconsByType = [
            'manual-trigger' => 'Play',
            'webhook-trigger' => 'Webhook',
            'form-trigger' => 'FileText',
            'schedule-trigger' => 'Clock',
            'app-event-trigger' => 'AppWindow',
            'http-request' => 'Globe',
            'send-email' => 'Mail',
            'external-service' => 'Link',
            'if-else' => 'GitBranch',
            'switch' => 'ArrowLeftRight',
            'parallel-split' => 'Layers',
            'parallel-join' => 'Layers',
            'wait' => 'Timer',
            'loop' => 'Repeat',
            'set-variables' => 'Variable',
            'parse-json' => 'FileJson',
            'format-date' => 'Calendar',
            'filter' => 'Filter',
            'gmail-send' => 'Send',
            'hubspot-contact' => 'Database',
            'hubspot-deal' => 'Database',
            'clickup-task' => 'CheckSquare',
            'google-drive' => 'FileText',
            'google-sheets' => 'FileText',
            'ai-router' => 'Route',
            'ai-classifier' => 'Tags',
            'ai-summarizer' => 'FileSearch',
            'ai-custom' => 'Brain',
            'ai-extractor' => 'FileSearch',
            'ai-sentiment' => 'Smile',
            'ai-generator' => 'Pen',
            'human-task' => 'UserCheck',
            'dynamic-flow' => 'AlertTriangle',
        ];

        foreach ($definitions as $definition) {
            $node = Node::query()->updateOrCreate(
                ['type' => $definition['type']],
                [
                    'label' => $definition['label'],
                    'category' => $definition['category'],
                    'icon' => $iconsByType[$definition['type']] ?? null,
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
