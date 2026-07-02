import type { NodeConfigSchema } from '@/config/types';

// Google Workspace integration node config schemas, keyed by nodeType.
// To add a Google node: add an entry to this record — registry.ts picks it up automatically.

export const googleConfigs: Record<string, NodeConfigSchema> = {
  'gdrive-file': {
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
  },

  'gsheets-rows': {
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
  },

  'gdocs-edit': {
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
  },
};
