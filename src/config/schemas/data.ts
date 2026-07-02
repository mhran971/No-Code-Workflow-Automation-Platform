import type { NodeConfigSchema } from '@/config/types';

// Data-transformation node config schemas, keyed by nodeType.
// To add a data node: add an entry to this record — registry.ts picks it up automatically.

export const dataConfigs: Record<string, NodeConfigSchema> = {
  'set-variables': {
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
  },

  'parse-json': {
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
  },

  'format-date': {
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
  },
};
