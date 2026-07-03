import type { NodeConfigSchema } from '@/config/types';

// Logic / control-flow node config schemas, keyed by nodeType.
// To add a logic node: add an entry to this record — registry.ts picks it up automatically.

export const logicConfigs: Record<string, NodeConfigSchema> = {
  'if-node': {
    nodeType: 'if-node',
    sections: [
      {
        title: 'Condition',
        fields: [
          { key: 'conditionExpression', label: 'Condition Expression', type: 'code', required: true, placeholder: 'context.age > 18', description: 'Boolean expression using context.* or customer.* variables' },
        ],
      },
    ],
  },

  'if-else-chain': {
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
  },

  'parallel-split': {
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
  },

  'parallel-join': {
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
  },

  'wait-until': {
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
  },

  'switch': {
    nodeType: 'switch',
    sections: [
      {
        title: 'Switch Variable',
        fields: [
          {
            key: 'variable',
            label: 'Variable',
            type: 'text',
            required: true,
            placeholder: 'context.status',
            description: 'Context variable to match against options. Must use context.* or customer.* namespace.',
          },
        ],
      },
      {
        title: 'Options',
        fields: [
          {
            key: 'options',
            label: 'Case Options',
            type: 'tags',
            required: true,
            description: 'Each option creates an outgoing branch. A default branch is always included for unmatched values.',
          },
        ],
      },
    ],
  },

  'loop-node': {
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
  },

  'foreach-node': {
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
  },

  'filter-node': {
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
  },

  'and-node': {
    nodeType: 'and-node',
    sections: [
      {
        title: 'Branches',
        fields: [
          {
            key: 'branches',
            label: 'Outgoing Branches',
            type: 'branchlist',
            required: true,
            description: 'Each branch creates one outgoing path. The key is auto-generated from the name.',
          },
        ],
      },
    ],
  },

  merge: {
    nodeType: 'merge',
    sections: [
      {
        title: 'Merge Mode',
        fields: [
          {
            key: 'mergeMode',
            label: 'Mode',
            type: 'select',
            required: true,
            options: [
              { label: 'Parallel (wait for all)', value: 'parallel' },
              { label: 'Conditional (first to arrive)', value: 'conditional' },
            ],
            defaultValue: 'parallel',
            description: 'Parallel joins a fork; conditional joins an if/switch. Mismatches cause deadlock or lost synchronization.',
          },
        ],
      },
      {
        title: 'Incoming Branches',
        fields: [
          {
            key: 'branchCount',
            label: 'Number of Incoming Branches',
            type: 'number',
            required: true,
            defaultValue: 2,
            min: 2,
            description: 'How many branches feed into this merge. Each adds one incoming connection point. Branch names come from the upstream fork/switch.',
          },
        ],
      },
      {
        title: 'Output Variables',
        fields: [
          {
            key: 'outputVariables',
            label: 'Output Variables',
            type: 'tags',
            description: 'Context variables this merge forwards downstream. Conditional merges require each on every branch; parallel merges require each on at least one.',
          },
        ],
      },
    ],
  },
};
