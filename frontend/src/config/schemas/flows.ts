import type { NodeConfigSchema } from '@/config/types';

// Flows-category config schemas (sub-workflow, dynamic-flow), keyed by nodeType.

export const flowConfigs: Record<string, NodeConfigSchema> = {
  'sub-workflow': {
    nodeType: 'sub-workflow',
    sections: [
      {
        title: 'Workflow Selection',
        fields: [
          {
            key: 'workflowId',
            label: 'Child Workflow',
            type: 'workflowselect',
            required: true,
            description: 'Select a published workflow to execute as a sub-workflow',
          },
        ],
      },
      {
        title: 'Input Mapping',
        fields: [
          {
            key: 'inputMapping',
            label: 'Input Variables',
            type: 'triggermapping',
            description: 'Map parent context values to the child workflow trigger inputs. Use {{context.variable}} for dynamic values.',
          },
        ],
      },
      {
        title: 'Output',
        fields: [
          {
            key: 'outputVariable',
            label: 'Output Variable',
            type: 'workflowoutput',
            description: 'Variable name where the child workflow context will be stored',
          },
        ],
      },
    ],
  },
  'dynamic-flow': {
    nodeType: 'dynamic-flow',
    sections: [
      {
        title: 'Settings',
        fields: [
          {
            key: 'message',
            label: 'Manager Message',
            type: 'textarea',
            placeholder: 'Describe what sub-flow is needed...',
          },
          {
            key: 'aiSuggestion',
            label: 'Enable AI Suggestion',
            type: 'toggle',
            defaultValue: true,
          },
        ],
      },
      {
        title: 'Output',
        fields: [
          {
            key: 'outputVariable',
            label: 'Output Variable',
            type: 'workflowoutput',
            description: 'Variable name where the child sub-flow context will be stored',
          },
        ],
      },
    ],
  },
};
