import type { NodeConfigSchema } from '@/config/types';

// Third-party integration node config schemas (Gmail, HubSpot, ClickUp), keyed by nodeType.
// Google Workspace nodes live in google.ts. To add an integration: add an entry to this
// record — registry.ts picks it up automatically.

export const integrationConfigs: Record<string, NodeConfigSchema> = {
  'gmail-send': {
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
  },

  'gmail-receive': {
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
  },

  'hubspot-contact': {
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
  },

  'hubspot-deal': {
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
  },

  'clickup-task': {
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
  },
};
