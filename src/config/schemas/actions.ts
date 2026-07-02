import type { NodeConfigSchema } from '@/config/types';

// Action node config schemas (HTTP, email, external services, human tasks), keyed by nodeType.
// To add an action node: add an entry to this record — registry.ts picks it up automatically.

export const actionConfigs: Record<string, NodeConfigSchema> = {
  'http-request': {
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
  },

  'send-email': {
    nodeType: 'send-email',
    sections: [
      {
        title: 'Recipients',
        fields: [
          { key: 'to', label: 'To', type: 'text', required: true, placeholder: 'user@example.com or {{context.email}}' },
          { key: 'cc', label: 'CC', type: 'text', placeholder: 'user@example.com or {{context.email}}' },
          { key: 'bcc', label: 'BCC', type: 'text', placeholder: 'user@example.com or {{context.email}}' },
        ],
      },
      {
        title: 'Content',
        fields: [
          {
            key: 'subject',
            label: 'Subject',
            type: 'templatetext',
            required: true,
            placeholder: 'e.g. Hello {{context.firstName}}',
            description: 'Use {{context.<key>}} or {{customer.<key>}} to insert variables. One variable per {{ }} block.',
          },
          { key: 'bodyType', label: 'Body Type', type: 'select', options: [
            { label: 'Plain Text', value: 'text' }, { label: 'HTML', value: 'html' },
          ], defaultValue: 'text' },
          {
            key: 'body',
            label: 'Body',
            type: 'templatetextarea',
            required: true,
            placeholder: 'Dear {{context.firstName}},\n\nYour request has been processed.',
            description: 'Use {{context.<key>}} or {{customer.<key>}} to insert variables. One variable per {{ }} block.',
          },
        ],
      },
    ],
  },

  'external-service': {
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
  },

  'task-node': {
    nodeType: 'task-node',
    sections: [
      {
        title: 'Task',
        fields: [
          { key: 'title', label: 'Title', type: 'text', required: true, placeholder: 'e.g., Review and Approve Request' },
          { key: 'description', label: 'Description', type: 'textarea', placeholder: 'Detailed instructions for the assignee' },
          { key: 'assignTo', label: 'Assign To', type: 'userselect', required: true },
        ],
      },
      {
        title: 'Input Fields',
        fields: [
          {
            key: 'inputFields',
            label: 'Fields',
            type: 'inputfieldlist',
            required: true,
            description: 'Fields the assignee must fill in. At least one field is required.',
          },
        ],
      },
      {
        title: 'Settings',
        fields: [
          { key: 'dueWithin', label: 'Due Within (hours)', type: 'number', defaultValue: 24, min: 1 },
        ],
      },
    ],
  },
};
