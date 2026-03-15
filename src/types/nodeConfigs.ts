export type ConfigFieldType =
  | 'text'
  | 'textarea'
  | 'select'
  | 'number'
  | 'toggle'
  | 'keyvalue'
  | 'fieldlist'
  | 'code'
  | 'tags'
  | 'readonly'
  | 'section';

export interface ConfigFieldOption {
  label: string;
  value: string;
}

export interface ConfigField {
  key: string;
  label: string;
  type: ConfigFieldType;
  placeholder?: string;
  description?: string;
  required?: boolean;
  defaultValue?: unknown;
  options?: ConfigFieldOption[];
  min?: number;
  max?: number;
  fields?: ConfigField[]; // for nested fieldlist items
}

export interface NodeConfigSchema {
  nodeType: string;
  sections: {
    title: string;
    fields: ConfigField[];
  }[];
}

// ─── Trigger Nodes ──────────────────────────────────────────

const manualTriggerConfig: NodeConfigSchema = {
  nodeType: 'manual-trigger',
  sections: [
    {
      title: 'Test Variables',
      fields: [
        { key: 'variables', label: 'Variables', type: 'keyvalue', description: 'Add static key-value pairs for testing', defaultValue: [] },
      ],
    },
  ],
};

const webhookTriggerConfig: NodeConfigSchema = {
  nodeType: 'webhook-trigger',
  sections: [
    {
      title: 'Endpoint',
      fields: [
        { key: 'endpointUrl', label: 'Endpoint URL', type: 'readonly', description: 'Auto-generated HTTPS URL', defaultValue: 'https://api.workflow.app/wh/abc123' },
        { key: 'httpMethod', label: 'HTTP Method', type: 'readonly', defaultValue: 'POST' },
        { key: 'enabled', label: 'Enabled', type: 'toggle', defaultValue: true, description: 'When disabled, endpoint returns 410' },
      ],
    },
    {
      title: 'Payload Configuration',
      fields: [
        { key: 'payloadFields', label: 'Required Payload Fields', type: 'keyvalue', description: 'Define expected fields and types' },
        { key: 'contentType', label: 'Content Type', type: 'readonly', defaultValue: 'application/json' },
      ],
    },
    {
      title: 'Rate Limiting',
      fields: [
        { key: 'rateLimit', label: 'Requests per minute', type: 'number', defaultValue: 60, min: 1, max: 1000 },
      ],
    },
  ],
};

const formTriggerConfig: NodeConfigSchema = {
  nodeType: 'form-trigger',
  sections: [
    {
      title: 'Form Definition',
      fields: [
        { key: 'formName', label: 'Form Name', type: 'text', required: true, placeholder: 'e.g., Contact Form' },
        { key: 'formDescription', label: 'Description', type: 'textarea', placeholder: 'Optional form description' },
        { key: 'formUrl', label: 'Public URL', type: 'readonly', defaultValue: 'https://forms.workflow.app/f/abc123' },
      ],
    },
    {
      title: 'Form Fields',
      fields: [
        { key: 'formFields', label: 'Fields', type: 'fieldlist', description: 'Define form fields', fields: [
          { key: 'fieldKey', label: 'Key', type: 'text', required: true },
          { key: 'fieldLabel', label: 'Label', type: 'text', required: true },
          { key: 'fieldType', label: 'Type', type: 'select', options: [
            { label: 'Text', value: 'text' }, { label: 'Email', value: 'email' },
            { label: 'Number', value: 'number' }, { label: 'Select', value: 'select' },
            { label: 'Textarea', value: 'textarea' }, { label: 'Date', value: 'date' },
            { label: 'File', value: 'file' }, { label: 'Checkbox', value: 'checkbox' },
          ]},
          { key: 'fieldRequired', label: 'Required', type: 'toggle', defaultValue: false },
        ]},
      ],
    },
    {
      title: 'Access & Security',
      fields: [
        { key: 'accessLevel', label: 'Access Level', type: 'select', options: [
          { label: 'Public', value: 'public' }, { label: 'Tenant Users Only', value: 'tenant' },
          { label: 'Token Protected', value: 'token' },
        ], defaultValue: 'public' },
        { key: 'captchaEnabled', label: 'CAPTCHA', type: 'toggle', defaultValue: false },
        { key: 'rateLimitPerIp', label: 'Rate Limit per IP (req/min)', type: 'number', defaultValue: 10, min: 1, max: 100 },
      ],
    },
  ],
};

const scheduleTriggerConfig: NodeConfigSchema = {
  nodeType: 'schedule-trigger',
  sections: [
    {
      title: 'Schedule',
      fields: [
        { key: 'scheduleType', label: 'Schedule Type', type: 'select', required: true, options: [
          { label: 'Simple Interval', value: 'interval' },
          { label: 'Specific Time', value: 'specific' },
          { label: 'Cron Expression', value: 'cron' },
        ], defaultValue: 'interval' },
        { key: 'intervalValue', label: 'Every', type: 'number', defaultValue: 30, min: 1 },
        { key: 'intervalUnit', label: 'Unit', type: 'select', options: [
          { label: 'Minutes', value: 'minutes' }, { label: 'Hours', value: 'hours' }, { label: 'Days', value: 'days' },
        ], defaultValue: 'minutes' },
        { key: 'specificTime', label: 'At Time (HH:MM)', type: 'text', placeholder: '09:00' },
        { key: 'timezone', label: 'Timezone', type: 'select', options: [
          { label: 'UTC', value: 'UTC' }, { label: 'US/Eastern', value: 'US/Eastern' },
          { label: 'US/Pacific', value: 'US/Pacific' }, { label: 'Europe/London', value: 'Europe/London' },
          { label: 'Europe/Berlin', value: 'Europe/Berlin' }, { label: 'Asia/Tokyo', value: 'Asia/Tokyo' },
        ], defaultValue: 'UTC' },
        { key: 'cronExpression', label: 'Cron Expression', type: 'text', placeholder: '0 9 * * 1-5' },
      ],
    },
  ],
};

const appEventTriggerConfig: NodeConfigSchema = {
  nodeType: 'app-event-trigger',
  sections: [
    {
      title: 'Application & Event',
      fields: [
        { key: 'application', label: 'Application', type: 'select', required: true, options: [
          { label: 'Email', value: 'email' }, { label: 'CRM', value: 'crm' },
          { label: 'Issue Tracker', value: 'issue-tracker' }, { label: 'Database', value: 'database' },
        ]},
        { key: 'eventType', label: 'Event Type', type: 'select', required: true, options: [
          { label: 'New Email', value: 'new-email' }, { label: 'Ticket Updated', value: 'ticket-updated' },
          { label: 'New Contact', value: 'new-contact' }, { label: 'Row Inserted', value: 'row-inserted' },
        ]},
        { key: 'connection', label: 'Connection', type: 'select', options: [
          { label: 'Select connection...', value: '' },
        ]},
      ],
    },
    {
      title: 'Event Filters',
      fields: [
        { key: 'filters', label: 'Filter Conditions', type: 'keyvalue', description: 'Key-value filter pairs (e.g., folder=INBOX)' },
      ],
    },
    {
      title: 'Delivery Mode',
      fields: [
        { key: 'deliveryMode', label: 'Mode', type: 'select', options: [
          { label: 'Instant (Webhook/Push)', value: 'instant' },
          { label: 'Polling', value: 'polling' },
        ], defaultValue: 'instant' },
        { key: 'pollingInterval', label: 'Polling Interval (minutes)', type: 'number', defaultValue: 5, min: 1, max: 60 },
      ],
    },
    {
      title: 'Idempotency',
      fields: [
        { key: 'eventIdField', label: 'Event ID Field', type: 'text', placeholder: 'e.g., messageId', description: 'Field used for deduplication' },
      ],
    },
  ],
};

// ─── Logic Nodes ──────────────────────────────────────────

const ifNodeConfig: NodeConfigSchema = {
  nodeType: 'if-node',
  sections: [
    {
      title: 'Condition',
      fields: [
        { key: 'conditionExpression', label: 'Expression', type: 'code', required: true, placeholder: '{{data.amount > 1000}}', description: 'Boolean expression using workflow variables' },
      ],
    },
    {
      title: 'Condition Builder',
      fields: [
        { key: 'leftOperand', label: 'Variable', type: 'text', placeholder: 'data.status' },
        { key: 'operator', label: 'Operator', type: 'select', options: [
          { label: 'Equals (=)', value: 'eq' }, { label: 'Not Equals (≠)', value: 'neq' },
          { label: 'Greater Than (>)', value: 'gt' }, { label: 'Less Than (<)', value: 'lt' },
          { label: 'Contains', value: 'contains' }, { label: 'Exists', value: 'exists' },
          { label: 'Starts With', value: 'startsWith' }, { label: 'Ends With', value: 'endsWith' },
        ]},
        { key: 'rightOperand', label: 'Value', type: 'text', placeholder: 'approved' },
        { key: 'conditionLogic', label: 'Multiple Conditions', type: 'select', options: [
          { label: 'AND', value: 'and' }, { label: 'OR', value: 'or' },
        ], defaultValue: 'and' },
      ],
    },
  ],
};

const ifElseChainConfig: NodeConfigSchema = {
  nodeType: 'if-else-chain',
  sections: [
    {
      title: 'Conditions (evaluated top-to-bottom)',
      fields: [
        { key: 'conditions', label: 'Condition List', type: 'fieldlist', description: 'First matching condition wins', fields: [
          { key: 'expression', label: 'Expression', type: 'code', required: true },
          { key: 'branchLabel', label: 'Branch Label', type: 'text', placeholder: 'e.g., High Priority' },
        ]},
        { key: 'hasElseBranch', label: 'Include Else Branch', type: 'toggle', defaultValue: true, description: 'Taken if no conditions match' },
      ],
    },
  ],
};

const parallelSplitConfig: NodeConfigSchema = {
  nodeType: 'parallel-split',
  sections: [
    {
      title: 'Branch Configuration',
      fields: [
        { key: 'branchCount', label: 'Number of Branches', type: 'number', required: true, defaultValue: 3, min: 2, max: 10 },
        { key: 'branchTimeout', label: 'Timeout per Branch (seconds)', type: 'number', defaultValue: 300, min: 1 },
        { key: 'continueOnFailure', label: 'Continue on Failure', type: 'toggle', defaultValue: true, description: 'Branch failures don\'t stop other branches' },
      ],
    },
  ],
};

const parallelJoinConfig: NodeConfigSchema = {
  nodeType: 'parallel-join',
  sections: [
    {
      title: 'Merge Strategy',
      fields: [
        { key: 'waitStrategy', label: 'Wait For', type: 'select', options: [
          { label: 'All Branches', value: 'all' },
          { label: 'First N Successes', value: 'first-n' },
        ], defaultValue: 'all' },
        { key: 'requiredSuccesses', label: 'Required Successes', type: 'number', min: 1, defaultValue: 1 },
        { key: 'mergeMode', label: 'Merge Mode', type: 'select', options: [
          { label: 'Deep Merge Objects', value: 'deep-merge' },
          { label: 'Override with Last', value: 'override' },
          { label: 'Concatenate Arrays', value: 'concat' },
        ], defaultValue: 'deep-merge' },
        { key: 'timeout', label: 'Timeout (seconds)', type: 'number', defaultValue: 600, min: 1 },
      ],
    },
  ],
};

const waitUntilConfig: NodeConfigSchema = {
  nodeType: 'wait-until',
  sections: [
    {
      title: 'Wait Condition',
      fields: [
        { key: 'conditionExpression', label: 'Condition', type: 'code', required: true, placeholder: '{{data.status === "approved"}}' },
        { key: 'pollInterval', label: 'Poll Interval (seconds)', type: 'number', defaultValue: 30, min: 5, max: 300 },
      ],
    },
    {
      title: 'Timeout',
      fields: [
        { key: 'timeoutDuration', label: 'Max Wait (hours)', type: 'number', defaultValue: 24, min: 1 },
        { key: 'timeoutBehavior', label: 'On Timeout', type: 'select', options: [
          { label: 'Fail', value: 'fail' }, { label: 'Proceed with Default', value: 'proceed' },
          { label: 'Take Alternate Path', value: 'alternate' },
        ], defaultValue: 'fail' },
      ],
    },
    {
      title: 'External Signal (Advanced)',
      fields: [
        { key: 'correlationId', label: 'Correlation ID', type: 'text', placeholder: 'Optional external signal ID' },
      ],
    },
  ],
};

const switchNodeConfig: NodeConfigSchema = {
  nodeType: 'switch-node',
  sections: [
    {
      title: 'Switch Expression',
      fields: [
        { key: 'switchExpression', label: 'Expression', type: 'code', required: true, placeholder: '{{data.category}}', description: 'Value to match against cases' },
        { key: 'caseSensitive', label: 'Case Sensitive', type: 'toggle', defaultValue: true },
      ],
    },
    {
      title: 'Cases',
      fields: [
        { key: 'cases', label: 'Case List', type: 'fieldlist', description: 'First match routes execution', fields: [
          { key: 'values', label: 'Match Value(s)', type: 'text', required: true, placeholder: 'e.g., pending, draft' },
          { key: 'caseLabel', label: 'Label', type: 'text', placeholder: 'e.g., Pending Items' },
        ]},
        { key: 'hasDefault', label: 'Include Default Case', type: 'toggle', defaultValue: true },
      ],
    },
  ],
};

const loopNodeConfig: NodeConfigSchema = {
  nodeType: 'loop-node',
  sections: [
    {
      title: 'Loop Type',
      fields: [
        { key: 'loopType', label: 'Type', type: 'select', required: true, options: [
          { label: 'For Each (array)', value: 'foreach' },
          { label: 'Fixed Count', value: 'count' },
          { label: 'While Condition', value: 'while' },
        ], defaultValue: 'foreach' },
        { key: 'arrayVariable', label: 'Array Variable', type: 'text', placeholder: '{{data.leads}}' },
        { key: 'fixedCount', label: 'Repeat Count', type: 'number', defaultValue: 10, min: 1, max: 1000 },
        { key: 'whileCondition', label: 'While Condition', type: 'code', placeholder: '{{data.hasMore === true}}' },
      ],
    },
    {
      title: 'Iteration Context',
      fields: [
        { key: 'itemVariable', label: 'Current Item Variable', type: 'text', defaultValue: 'currentItem', placeholder: 'e.g., currentLead' },
        { key: 'indexVariable', label: 'Index Variable', type: 'text', defaultValue: 'loopIndex' },
        { key: 'accumulatorVariable', label: 'Accumulator Variable', type: 'text', placeholder: 'Optional: collect results' },
      ],
    },
    {
      title: 'Safety',
      fields: [
        { key: 'maxIterations', label: 'Max Iterations', type: 'number', defaultValue: 1000, min: 1, max: 10000 },
        { key: 'breakCondition', label: 'Break Condition', type: 'code', placeholder: 'Optional early exit expression' },
        { key: 'continueOnError', label: 'Continue on Error', type: 'toggle', defaultValue: false },
      ],
    },
  ],
};

const foreachNodeConfig: NodeConfigSchema = {
  nodeType: 'foreach-node',
  sections: [
    {
      title: 'Input Array',
      fields: [
        { key: 'arrayVariable', label: 'Array Variable', type: 'text', required: true, placeholder: '{{data.items}}' },
        { key: 'itemVariable', label: 'Item Variable Name', type: 'text', defaultValue: 'currentItem' },
        { key: 'indexVariable', label: 'Index Variable', type: 'text', defaultValue: 'index' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'accumulatorVariable', label: 'Accumulator', type: 'text', placeholder: 'results', description: 'Collects output from each iteration' },
        { key: 'maxIterations', label: 'Max Iterations', type: 'number', defaultValue: 1000, min: 1 },
      ],
    },
  ],
};

const filterNodeConfig: NodeConfigSchema = {
  nodeType: 'filter-node',
  sections: [
    {
      title: 'Filter Configuration',
      fields: [
        { key: 'inputArray', label: 'Input Array', type: 'text', required: true, placeholder: '{{data.items}}' },
        { key: 'filterCondition', label: 'Condition', type: 'code', required: true, placeholder: '{{item.status === "active"}}', description: 'Expression evaluated per item' },
        { key: 'outputVariable', label: 'Output Variable', type: 'text', defaultValue: 'filteredItems' },
      ],
    },
  ],
};

// ─── AI Agent Nodes ──────────────────────────────────────────

const aiCommonFields: ConfigField[] = [
  { key: 'documents', label: 'Knowledge Base Documents', type: 'tags', description: 'Select documents from library' },
  { key: 'metadataFilters', label: 'Metadata Filters', type: 'keyvalue', description: 'Tags, categories, department' },
  { key: 'topK', label: 'Top-K Documents', type: 'number', defaultValue: 5, min: 1, max: 20 },
  { key: 'similarityThreshold', label: 'Similarity Threshold', type: 'number', defaultValue: 0.7, min: 0, max: 1 },
];

const aiContextFields: ConfigField[] = [
  { key: 'inputVariables', label: 'Input Variables', type: 'tags', description: 'Workflow variables to pass as context' },
  { key: 'contextWindow', label: 'Max Tokens (Context)', type: 'number', defaultValue: 8192, min: 4096, max: 32768 },
];

const aiLlmFields: ConfigField[] = [
  { key: 'model', label: 'Model', type: 'select', options: [
    { label: 'GPT-4o-mini', value: 'gpt-4o-mini' },
    { label: 'GPT-4o', value: 'gpt-4o' },
    { label: 'Claude 3.5 Sonnet', value: 'claude-3.5-sonnet' },
    { label: 'Llama 3.1', value: 'llama-3.1' },
  ], defaultValue: 'gpt-4o-mini' },
  { key: 'temperature', label: 'Temperature', type: 'number', defaultValue: 0.2, min: 0, max: 1 },
  { key: 'maxOutputTokens', label: 'Max Output Tokens', type: 'number', defaultValue: 2048, min: 100, max: 16384 },
];

const aiOutputFields: ConfigField[] = [
  { key: 'responseVariable', label: 'Response Variable', type: 'text', defaultValue: 'aiResponse' },
  { key: 'confidenceThreshold', label: 'Confidence Threshold', type: 'number', defaultValue: 0.8, min: 0, max: 1 },
];

function makeAiConfig(nodeType: string, specificSections: NodeConfigSchema['sections']): NodeConfigSchema {
  return {
    nodeType,
    sections: [
      ...specificSections,
      { title: 'Knowledge Base', fields: aiCommonFields },
      { title: 'Context Variables', fields: aiContextFields },
      { title: 'LLM Configuration', fields: aiLlmFields },
      { title: 'Output Mapping', fields: aiOutputFields },
    ],
  };
}

const aiRouterConfig = makeAiConfig('ai-router', [{
  title: 'Routing',
  fields: [
    { key: 'routingInstructions', label: 'Routing Instructions', type: 'textarea', required: true, placeholder: 'Analyze lead quality using sales policy and route appropriately' },
    { key: 'expectedRoutes', label: 'Expected Routes', type: 'tags', description: 'e.g., high-value, standard, nurture, disqualified' },
  ],
}]);

const aiClassifierConfig = makeAiConfig('ai-classifier', [{
  title: 'Classification',
  fields: [
    { key: 'classes', label: 'Classification Classes', type: 'fieldlist', description: 'Define classification dimensions', fields: [
      { key: 'name', label: 'Class Name', type: 'text', required: true, placeholder: 'e.g., priority' },
      { key: 'options', label: 'Options', type: 'text', required: true, placeholder: 'e.g., low, medium, high' },
    ]},
    { key: 'instructions', label: 'Classification Instructions', type: 'textarea', placeholder: 'Classify based on support SLA document' },
  ],
}]);

const aiSummarizerConfig = makeAiConfig('ai-summarizer', [{
  title: 'Summary Settings',
  fields: [
    { key: 'summaryType', label: 'Summary Type', type: 'select', options: [
      { label: 'Brief', value: 'brief' }, { label: 'Detailed', value: 'detailed' },
      { label: 'Bullet Points', value: 'bullets' }, { label: 'Executive', value: 'executive' },
    ], defaultValue: 'brief' },
    { key: 'focusAreas', label: 'Focus Areas', type: 'tags', description: 'e.g., financials, risks, action-items' },
    { key: 'maxLength', label: 'Max Length (words)', type: 'number', defaultValue: 200, min: 50, max: 1000 },
  ],
}]);

const aiCustomConfig = makeAiConfig('ai-custom', [{
  title: 'Custom Agent',
  fields: [
    { key: 'systemPrompt', label: 'System Prompt', type: 'textarea', required: true, placeholder: 'You are a senior sales analyst following company policy...' },
    { key: 'outputSchema', label: 'Output JSON Schema', type: 'code', placeholder: '{ "type": "object", "properties": {...} }', description: 'JSON Schema to validate output' },
    { key: 'chainOfThought', label: 'Chain of Thought', type: 'toggle', defaultValue: true, description: 'Log reasoning steps' },
  ],
}]);

const aiExtractorConfig = makeAiConfig('ai-extractor', [{
  title: 'Extraction Schema',
  fields: [
    { key: 'schema', label: 'Fields to Extract', type: 'fieldlist', required: true, fields: [
      { key: 'name', label: 'Field Name', type: 'text', required: true, placeholder: 'customerName' },
      { key: 'type', label: 'Type', type: 'select', options: [
        { label: 'String', value: 'string' }, { label: 'Number', value: 'number' },
        { label: 'Date', value: 'date' }, { label: 'Boolean', value: 'boolean' },
        { label: 'Array', value: 'array' },
      ]},
    ]},
    { key: 'instructions', label: 'Extraction Instructions', type: 'textarea', placeholder: 'Extract invoice details per finance policy' },
  ],
}]);

const aiSentimentConfig = makeAiConfig('ai-sentiment', [{
  title: 'Analysis Configuration',
  fields: [
    { key: 'analysisTypes', label: 'Analysis Types', type: 'tags', description: 'sentiment, emotion, urgency, intent' },
    { key: 'textSource', label: 'Text Source Variable', type: 'text', required: true, placeholder: '{{data.message}}' },
  ],
}]);

const aiGeneratorConfig = makeAiConfig('ai-generator', [{
  title: 'Content Generation',
  fields: [
    { key: 'contentType', label: 'Content Type', type: 'select', options: [
      { label: 'Email', value: 'email' }, { label: 'Proposal', value: 'proposal' },
      { label: 'Report', value: 'report' }, { label: 'Social Post', value: 'social' },
    ], defaultValue: 'email' },
    { key: 'tone', label: 'Tone', type: 'select', options: [
      { label: 'Professional', value: 'professional' }, { label: 'Friendly', value: 'friendly' },
      { label: 'Urgent', value: 'urgent' }, { label: 'Casual', value: 'casual' },
    ], defaultValue: 'professional' },
    { key: 'template', label: 'Template', type: 'textarea', placeholder: 'Dear {{customerName}}, based on our analysis...' },
  ],
}]);

const aiValidatorConfig = makeAiConfig('ai-validator', [{
  title: 'Validation Rules',
  fields: [
    { key: 'rules', label: 'Rule Sets', type: 'tags', description: 'e.g., contract-limits, discount-policy, customer-tier' },
    { key: 'policyDocuments', label: 'Policy Documents', type: 'tags', description: 'Select policy documents' },
    { key: 'severity', label: 'Default Severity', type: 'select', options: [
      { label: 'Critical', value: 'critical' }, { label: 'Warning', value: 'warning' }, { label: 'Info', value: 'info' },
    ], defaultValue: 'warning' },
  ],
}]);

// ─── Integration Nodes ──────────────────────────────────────────

const gmailSendConfig: NodeConfigSchema = {
  nodeType: 'gmail-send',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'Gmail Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ], description: 'OAuth2 connection (gmail.send scope)' },
      ],
    },
    {
      title: 'Recipients',
      fields: [
        { key: 'to', label: 'To', type: 'text', required: true, placeholder: '{{data.email}} or user@example.com' },
        { key: 'cc', label: 'CC', type: 'text', placeholder: 'Optional' },
        { key: 'bcc', label: 'BCC', type: 'text', placeholder: 'Optional' },
      ],
    },
    {
      title: 'Content',
      fields: [
        { key: 'subject', label: 'Subject', type: 'text', required: true, placeholder: 'Subject with {{variables}}' },
        { key: 'bodyType', label: 'Body Type', type: 'select', options: [
          { label: 'Plain Text', value: 'text' }, { label: 'HTML', value: 'html' },
        ], defaultValue: 'text' },
        { key: 'body', label: 'Body', type: 'textarea', required: true, placeholder: 'Email body with {{variables}}' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'emailResult' },
      ],
    },
  ],
};

const gmailReceiveConfig: NodeConfigSchema = {
  nodeType: 'gmail-receive',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'Gmail Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ], description: 'OAuth2 connection (gmail.readonly scope)' },
      ],
    },
    {
      title: 'Filters',
      fields: [
        { key: 'label', label: 'Label / Folder', type: 'text', defaultValue: 'INBOX' },
        { key: 'query', label: 'Search Query', type: 'text', placeholder: 'from:user@example.com subject:invoice' },
        { key: 'markAsRead', label: 'Mark as Read', type: 'toggle', defaultValue: false },
      ],
    },
    {
      title: 'Polling',
      fields: [
        { key: 'pollingInterval', label: 'Check Every (minutes)', type: 'select', options: [
          { label: '5 minutes', value: '5' }, { label: '15 minutes', value: '15' }, { label: '30 minutes', value: '30' },
        ], defaultValue: '15' },
      ],
    },
  ],
};

const hubspotContactConfig: NodeConfigSchema = {
  nodeType: 'hubspot-contact',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'HubSpot Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ]},
        { key: 'action', label: 'Action', type: 'select', options: [
          { label: 'Create New', value: 'create' }, { label: 'Search & Update', value: 'update' },
          { label: 'Create or Update', value: 'upsert' },
        ], defaultValue: 'upsert' },
      ],
    },
    {
      title: 'Contact Fields',
      fields: [
        { key: 'email', label: 'Email', type: 'text', required: true, placeholder: '{{data.email}}' },
        { key: 'firstname', label: 'First Name', type: 'text', placeholder: '{{data.firstName}}' },
        { key: 'lastname', label: 'Last Name', type: 'text', placeholder: '{{data.lastName}}' },
        { key: 'company', label: 'Company', type: 'text', placeholder: '{{data.company}}' },
        { key: 'phone', label: 'Phone', type: 'text', placeholder: '{{data.phone}}' },
        { key: 'lifecyclestage', label: 'Lifecycle Stage', type: 'select', options: [
          { label: 'Subscriber', value: 'subscriber' }, { label: 'Lead', value: 'lead' },
          { label: 'MQL', value: 'marketingqualifiedlead' }, { label: 'SQL', value: 'salesqualifiedlead' },
          { label: 'Opportunity', value: 'opportunity' }, { label: 'Customer', value: 'customer' },
        ]},
      ],
    },
    {
      title: 'Custom Properties',
      fields: [
        { key: 'customProperties', label: 'Custom Properties', type: 'keyvalue' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'hubspotContact' },
      ],
    },
  ],
};

const hubspotDealConfig: NodeConfigSchema = {
  nodeType: 'hubspot-deal',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'HubSpot Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ]},
      ],
    },
    {
      title: 'Deal Details',
      fields: [
        { key: 'dealname', label: 'Deal Name', type: 'text', required: true, placeholder: '{{data.dealName}}' },
        { key: 'dealstage', label: 'Deal Stage', type: 'text', required: true, placeholder: 'Pipeline stage ID or name' },
        { key: 'amount', label: 'Amount', type: 'text', placeholder: '{{data.amount}}' },
        { key: 'closedate', label: 'Close Date', type: 'text', placeholder: '{{data.closeDate}}' },
        { key: 'hubspot_owner_id', label: 'Owner ID', type: 'text', placeholder: 'HubSpot owner ID' },
      ],
    },
    {
      title: 'Associations',
      fields: [
        { key: 'associateContact', label: 'Associate Contact ID', type: 'text', placeholder: '{{hubspotContact.contactId}}' },
        { key: 'associateCompany', label: 'Associate Company ID', type: 'text', placeholder: 'Optional' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'hubspotDeal' },
      ],
    },
  ],
};

const clickupTaskConfig: NodeConfigSchema = {
  nodeType: 'clickup-task',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'ClickUp Workspace', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ]},
        { key: 'listId', label: 'List', type: 'text', required: true, placeholder: 'Target list ID' },
      ],
    },
    {
      title: 'Task Fields',
      fields: [
        { key: 'taskName', label: 'Task Name', type: 'text', required: true, placeholder: '{{data.taskTitle}}' },
        { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Task description with {{variables}}' },
        { key: 'priority', label: 'Priority', type: 'select', options: [
          { label: 'Urgent', value: '1' }, { label: 'High', value: '2' },
          { label: 'Normal', value: '3' }, { label: 'Low', value: '4' },
        ], defaultValue: '3' },
        { key: 'assignees', label: 'Assignees', type: 'tags', description: 'User IDs to assign' },
        { key: 'dueDate', label: 'Due Date', type: 'text', placeholder: '{{data.dueDate}}' },
        { key: 'tags', label: 'Tags', type: 'tags' },
        { key: 'parentTask', label: 'Parent Task ID', type: 'text', placeholder: 'For subtasks' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'clickupTask' },
      ],
    },
  ],
};

const gdriveFileConfig: NodeConfigSchema = {
  nodeType: 'gdrive-file',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'Google Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ], description: 'OAuth2 (Drive API scope)' },
      ],
    },
    {
      title: 'File Configuration',
      fields: [
        { key: 'fileType', label: 'File Type', type: 'select', options: [
          { label: 'Google Doc', value: 'doc' }, { label: 'Google Sheet', value: 'sheet' },
          { label: 'Upload File', value: 'upload' },
        ], defaultValue: 'doc' },
        { key: 'parentFolder', label: 'Folder ID', type: 'text', placeholder: 'Drive folder ID' },
        { key: 'fileName', label: 'File Name', type: 'text', required: true, placeholder: '{{data.reportName}}' },
        { key: 'content', label: 'Content', type: 'textarea', placeholder: 'For Docs: text content. For upload: file reference.' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'driveFile' },
      ],
    },
  ],
};

const gsheetsRowsConfig: NodeConfigSchema = {
  nodeType: 'gsheets-rows',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'Google Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ]},
      ],
    },
    {
      title: 'Spreadsheet',
      fields: [
        { key: 'spreadsheetId', label: 'Spreadsheet ID', type: 'text', required: true, placeholder: 'Spreadsheet ID or URL' },
        { key: 'sheetName', label: 'Sheet / Tab', type: 'text', defaultValue: 'Sheet1' },
        { key: 'range', label: 'Range (A1 notation)', type: 'text', placeholder: 'A:Z or A1:D10' },
      ],
    },
    {
      title: 'Operation',
      fields: [
        { key: 'operation', label: 'Operation', type: 'select', required: true, options: [
          { label: 'Read Rows', value: 'read' }, { label: 'Append Row', value: 'append' },
          { label: 'Update Range', value: 'update' }, { label: 'Clear Range', value: 'clear' },
        ], defaultValue: 'read' },
        { key: 'values', label: 'Values', type: 'code', placeholder: '[["Name", "Email"], ["John", "john@example.com"]]', description: 'For append/update: array of arrays or variable reference' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'sheetsData' },
      ],
    },
  ],
};

const gdocsEditConfig: NodeConfigSchema = {
  nodeType: 'gdocs-edit',
  sections: [
    {
      title: 'Connection',
      fields: [
        { key: 'connection', label: 'Google Account', type: 'select', required: true, options: [
          { label: 'Select connection...', value: '' },
        ]},
      ],
    },
    {
      title: 'Document',
      fields: [
        { key: 'documentId', label: 'Document ID', type: 'text', required: true, placeholder: 'From Drive node or direct ID' },
      ],
    },
    {
      title: 'Replacements',
      fields: [
        { key: 'replacements', label: 'Find & Replace', type: 'keyvalue', description: 'Find → Replace pairs with variable support' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'docResult' },
      ],
    },
  ],
};

// ─── Data Nodes ──────────────────────────────────────────

const setVariablesConfig: NodeConfigSchema = {
  nodeType: 'set-variables',
  sections: [
    {
      title: 'Variables',
      fields: [
        { key: 'variables', label: 'Variable Assignments', type: 'fieldlist', required: true, description: 'Define variables to set or update', fields: [
          { key: 'name', label: 'Name', type: 'text', required: true, placeholder: 'variableName' },
          { key: 'value', label: 'Value', type: 'text', required: true, placeholder: 'Static value or {{expression}}' },
          { key: 'typeHint', label: 'Type', type: 'select', options: [
            { label: 'String', value: 'string' }, { label: 'Number', value: 'number' },
            { label: 'Boolean', value: 'boolean' }, { label: 'Date', value: 'date' },
          ]},
        ]},
        { key: 'overwriteExisting', label: 'Overwrite Existing', type: 'toggle', defaultValue: true },
      ],
    },
  ],
};

const parseJsonConfig: NodeConfigSchema = {
  nodeType: 'parse-json',
  sections: [
    {
      title: 'Input',
      fields: [
        { key: 'inputSource', label: 'JSON String Source', type: 'text', required: true, placeholder: '{{httpResponse.body}}' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'parsedData' },
        { key: 'extractPaths', label: 'Extract Specific Paths', type: 'keyvalue', description: 'JSON path → variable name mappings' },
      ],
    },
    {
      title: 'Error Handling',
      fields: [
        { key: 'onParseFailure', label: 'On Parse Failure', type: 'select', options: [
          { label: 'Set null + error', value: 'null' }, { label: 'Fail node', value: 'fail' },
        ], defaultValue: 'null' },
        { key: 'validationSchema', label: 'Validation Schema (JSON Schema)', type: 'code', placeholder: '{"type": "object", ...}' },
      ],
    },
  ],
};

const formatDateConfig: NodeConfigSchema = {
  nodeType: 'format-date',
  sections: [
    {
      title: 'Input Date',
      fields: [
        { key: 'inputDate', label: 'Date Source', type: 'text', required: true, placeholder: '{{data.createdAt}}' },
        { key: 'inputFormat', label: 'Input Format', type: 'select', options: [
          { label: 'Auto-detect', value: 'auto' }, { label: 'ISO 8601', value: 'iso' },
          { label: 'Unix Timestamp', value: 'unix' }, { label: 'Custom', value: 'custom' },
        ], defaultValue: 'auto' },
        { key: 'customInputFormat', label: 'Custom Format', type: 'text', placeholder: 'YYYY-MM-DD HH:mm:ss' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputFormat', label: 'Output Format', type: 'select', options: [
          { label: 'ISO 8601', value: 'iso' }, { label: 'Human Readable', value: 'human' },
          { label: 'Unix Timestamp', value: 'unix' }, { label: 'Custom', value: 'custom' },
        ], defaultValue: 'iso' },
        { key: 'customOutputFormat', label: 'Custom Format', type: 'text', placeholder: 'MMM DD, YYYY' },
        { key: 'inputTimezone', label: 'Input Timezone', type: 'text', defaultValue: 'UTC' },
        { key: 'outputTimezone', label: 'Output Timezone', type: 'text', defaultValue: 'UTC' },
      ],
    },
    {
      title: 'Operations',
      fields: [
        { key: 'addDays', label: 'Add/Subtract Days', type: 'number', defaultValue: 0 },
        { key: 'addHours', label: 'Add/Subtract Hours', type: 'number', defaultValue: 0 },
        { key: 'addMinutes', label: 'Add/Subtract Minutes', type: 'number', defaultValue: 0 },
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'formattedDate' },
      ],
    },
  ],
};

// ─── Action Nodes ──────────────────────────────────────────

const httpRequestConfig: NodeConfigSchema = {
  nodeType: 'http-request',
  sections: [
    {
      title: 'Request',
      fields: [
        { key: 'method', label: 'Method', type: 'select', required: true, options: [
          { label: 'GET', value: 'GET' }, { label: 'POST', value: 'POST' },
          { label: 'PUT', value: 'PUT' }, { label: 'PATCH', value: 'PATCH' },
          { label: 'DELETE', value: 'DELETE' },
        ], defaultValue: 'GET' },
        { key: 'url', label: 'URL', type: 'text', required: true, placeholder: 'https://api.example.com/endpoint' },
        { key: 'queryParams', label: 'Query Parameters', type: 'keyvalue' },
      ],
    },
    {
      title: 'Headers & Authentication',
      fields: [
        { key: 'headers', label: 'Headers', type: 'keyvalue' },
        { key: 'authType', label: 'Authentication', type: 'select', options: [
          { label: 'None', value: 'none' }, { label: 'API Key', value: 'api-key' },
          { label: 'Basic Auth', value: 'basic' }, { label: 'Bearer Token', value: 'bearer' },
        ], defaultValue: 'none' },
        { key: 'authValue', label: 'Auth Value / Token', type: 'text', placeholder: 'From secret store' },
      ],
    },
    {
      title: 'Body',
      fields: [
        { key: 'bodyType', label: 'Body Type', type: 'select', options: [
          { label: 'None', value: 'none' }, { label: 'JSON', value: 'json' },
          { label: 'Form Data', value: 'form-data' }, { label: 'Raw Text', value: 'raw' },
        ], defaultValue: 'none' },
        { key: 'bodyContent', label: 'Body', type: 'code', placeholder: '{"key": "{{variable}}"}' },
      ],
    },
    {
      title: 'Response',
      fields: [
        { key: 'responseType', label: 'Expected Response', type: 'select', options: [
          { label: 'JSON', value: 'json' }, { label: 'Raw Text', value: 'text' },
        ], defaultValue: 'json' },
        { key: 'responseVariable', label: 'Response Variable', type: 'text', defaultValue: 'httpResponse' },
        { key: 'extractField', label: 'Extract Field', type: 'text', placeholder: 'Optional: e.g., data.result' },
      ],
    },
    {
      title: 'Timeouts & Retries',
      fields: [
        { key: 'timeout', label: 'Timeout (seconds)', type: 'number', defaultValue: 30, min: 1, max: 300 },
        { key: 'retryEnabled', label: 'Retry on Failure', type: 'toggle', defaultValue: false },
        { key: 'retryCount', label: 'Retry Count', type: 'number', defaultValue: 3, min: 1, max: 10 },
        { key: 'retryDelay', label: 'Retry Delay (seconds)', type: 'number', defaultValue: 5, min: 1 },
      ],
    },
  ],
};

const sendEmailConfig: NodeConfigSchema = {
  nodeType: 'send-email',
  sections: [
    {
      title: 'Provider',
      fields: [
        { key: 'connection', label: 'Email Provider', type: 'select', required: true, options: [
          { label: 'Select provider...', value: '' }, { label: 'SMTP', value: 'smtp' },
          { label: 'SendGrid', value: 'sendgrid' }, { label: 'Amazon SES', value: 'ses' },
        ]},
      ],
    },
    {
      title: 'Recipients',
      fields: [
        { key: 'from', label: 'From', type: 'text', required: true, placeholder: 'sender@company.com' },
        { key: 'to', label: 'To', type: 'text', required: true, placeholder: '{{data.email}}' },
        { key: 'cc', label: 'CC', type: 'text', placeholder: 'Optional' },
        { key: 'bcc', label: 'BCC', type: 'text', placeholder: 'Optional' },
      ],
    },
    {
      title: 'Content',
      fields: [
        { key: 'subject', label: 'Subject', type: 'text', required: true, placeholder: 'Subject with {{variables}}' },
        { key: 'bodyType', label: 'Body Type', type: 'select', options: [
          { label: 'Plain Text', value: 'text' }, { label: 'HTML', value: 'html' },
        ], defaultValue: 'text' },
        { key: 'body', label: 'Body', type: 'textarea', required: true, placeholder: 'Email body with {{variables}}' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'emailResult' },
      ],
    },
  ],
};

const externalServiceConfig: NodeConfigSchema = {
  nodeType: 'external-service',
  sections: [
    {
      title: 'Service & Action',
      fields: [
        { key: 'service', label: 'Service', type: 'select', required: true, options: [
          { label: 'CRM', value: 'crm' }, { label: 'Issue Tracker', value: 'issue-tracker' },
          { label: 'Payment Gateway', value: 'payment' }, { label: 'Custom', value: 'custom' },
        ]},
        { key: 'action', label: 'Action', type: 'select', required: true, options: [
          { label: 'Create Record', value: 'createRecord' }, { label: 'Update Record', value: 'updateRecord' },
          { label: 'Add Comment', value: 'addComment' }, { label: 'Create Ticket', value: 'createTicket' },
        ]},
        { key: 'connection', label: 'Connection', type: 'select', options: [
          { label: 'Select connection...', value: '' },
        ]},
      ],
    },
    {
      title: 'Action Parameters',
      fields: [
        { key: 'parameters', label: 'Parameters', type: 'keyvalue', description: 'Action-specific field values with variable support' },
      ],
    },
    {
      title: 'Error Handling',
      fields: [
        { key: 'retryEnabled', label: 'Retry on Failure', type: 'toggle', defaultValue: true },
        { key: 'retryCount', label: 'Retry Count', type: 'number', defaultValue: 3, min: 1, max: 10 },
        { key: 'useIdempotencyKey', label: 'Use Idempotency Key', type: 'toggle', defaultValue: false },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'serviceResult' },
      ],
    },
  ],
};

const taskNodeConfig: NodeConfigSchema = {
  nodeType: 'task-node',
  sections: [
    {
      title: 'Task Definition',
      fields: [
        { key: 'taskTitle', label: 'Task Title', type: 'text', required: true, placeholder: 'Review and approve request' },
        { key: 'taskDescription', label: 'Description', type: 'textarea', placeholder: 'Detailed instructions for the assignee' },
        { key: 'taskType', label: 'Task Type', type: 'select', options: [
          { label: 'Review', value: 'review' }, { label: 'Approval', value: 'approval' },
          { label: 'Data Collection', value: 'data-collection' },
        ], defaultValue: 'approval' },
      ],
    },
    {
      title: 'Assignment',
      fields: [
        { key: 'assignees', label: 'Assign To', type: 'tags', required: true, description: 'User IDs or email addresses' },
      ],
    },
    {
      title: 'Form Fields',
      fields: [
        { key: 'formFields', label: 'Input Fields', type: 'fieldlist', description: 'Fields the assignee must fill in', fields: [
          { key: 'fieldKey', label: 'Key', type: 'text', required: true },
          { key: 'fieldLabel', label: 'Label', type: 'text', required: true },
          { key: 'fieldType', label: 'Type', type: 'select', options: [
            { label: 'Text', value: 'text' }, { label: 'Number', value: 'number' },
            { label: 'Select', value: 'select' }, { label: 'Checkbox', value: 'checkbox' },
            { label: 'Date', value: 'date' }, { label: 'File', value: 'file' },
            { label: 'Textarea', value: 'textarea' },
          ]},
          { key: 'fieldRequired', label: 'Required', type: 'toggle', defaultValue: false },
        ]},
      ],
    },
    {
      title: 'Outcomes',
      fields: [
        { key: 'outcomes', label: 'Outcome Options', type: 'tags', required: true, description: 'e.g., Approved, Rejected, NeedsMoreInfo' },
      ],
    },
    {
      title: 'SLA & Notifications',
      fields: [
        { key: 'slaDuration', label: 'Due Within (hours)', type: 'number', defaultValue: 24, min: 1 },
        { key: 'reminderEnabled', label: 'Send Reminders', type: 'toggle', defaultValue: true },
        { key: 'notifyOnCreate', label: 'Notify on Assignment', type: 'toggle', defaultValue: true, description: 'Email or in-app notification' },
        { key: 'expirationPolicy', label: 'On Expiration', type: 'select', options: [
          { label: 'Escalate', value: 'escalate' }, { label: 'Auto-Reject', value: 'auto-reject' },
          { label: 'Leave Pending', value: 'pending' },
        ], defaultValue: 'escalate' },
      ],
    },
    {
      title: 'Output',
      fields: [
        { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'taskResult' },
      ],
    },
  ],
};

// ─── Registry ──────────────────────────────────────────

export const NODE_CONFIG_REGISTRY: Record<string, NodeConfigSchema> = {
  'manual-trigger': manualTriggerConfig,
  'webhook-trigger': webhookTriggerConfig,
  'form-trigger': formTriggerConfig,
  'schedule-trigger': scheduleTriggerConfig,
  'app-event-trigger': appEventTriggerConfig,
  'if-node': ifNodeConfig,
  'if-else-chain': ifElseChainConfig,
  'parallel-split': parallelSplitConfig,
  'parallel-join': parallelJoinConfig,
  'wait-until': waitUntilConfig,
  'switch-node': switchNodeConfig,
  'loop-node': loopNodeConfig,
  'foreach-node': foreachNodeConfig,
  'filter-node': filterNodeConfig,
  'ai-router': aiRouterConfig,
  'ai-classifier': aiClassifierConfig,
  'ai-summarizer': aiSummarizerConfig,
  'ai-custom': aiCustomConfig,
  'ai-extractor': aiExtractorConfig,
  'ai-sentiment': aiSentimentConfig,
  'ai-generator': aiGeneratorConfig,
  'ai-validator': aiValidatorConfig,
  'gmail-send': gmailSendConfig,
  'gmail-receive': gmailReceiveConfig,
  'hubspot-contact': hubspotContactConfig,
  'hubspot-deal': hubspotDealConfig,
  'clickup-task': clickupTaskConfig,
  'gdrive-file': gdriveFileConfig,
  'gsheets-rows': gsheetsRowsConfig,
  'gdocs-edit': gdocsEditConfig,
  'set-variables': setVariablesConfig,
  'parse-json': parseJsonConfig,
  'format-date': formatDateConfig,
  'http-request': httpRequestConfig,
  'send-email': sendEmailConfig,
  'external-service': externalServiceConfig,
  'task-node': taskNodeConfig,
};
