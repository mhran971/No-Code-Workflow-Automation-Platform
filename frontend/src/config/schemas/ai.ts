import type { ConfigField, NodeConfigSchema } from '@/config/types';

// AI agent node config schemas, keyed by nodeType.
// Every AI node shares the same Knowledge Base / Context / LLM / Output sections;
// makeAiConfig() appends them so each node only declares its own specific section(s).

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

export const aiConfigs: Record<string, NodeConfigSchema> = {
  'ai-router': makeAiConfig('ai-router', [{
    title: 'Routing',
    fields: [
      { key: 'routingInstructions', label: 'Routing Instructions', type: 'textarea', required: true, placeholder: 'Analyze lead quality using sales policy and route appropriately' },
      { key: 'expectedRoutes', label: 'Expected Routes', type: 'tags', description: 'e.g., high-value, standard, nurture, disqualified' },
    ],
  }]),

  'ai-classifier': makeAiConfig('ai-classifier', [{
    title: 'Classification',
    fields: [
      { key: 'classes', label: 'Classification Classes', type: 'fieldlist', description: 'Define classification dimensions', fields: [
        { key: 'name', label: 'Class Name', type: 'text', required: true, placeholder: 'e.g., priority' },
        { key: 'options', label: 'Options', type: 'text', required: true, placeholder: 'e.g., low, medium, high' },
      ]},
      { key: 'instructions', label: 'Classification Instructions', type: 'textarea', placeholder: 'Classify based on support SLA document' },
    ],
  }]),

  'ai-summarizer': makeAiConfig('ai-summarizer', [{
    title: 'Summary Settings',
    fields: [
      { key: 'summaryType', label: 'Summary Type', type: 'select', options: [
        { label: 'Brief', value: 'brief' }, { label: 'Detailed', value: 'detailed' },
        { label: 'Bullet Points', value: 'bullets' }, { label: 'Executive', value: 'executive' },
      ], defaultValue: 'brief' },
      { key: 'focusAreas', label: 'Focus Areas', type: 'tags', description: 'e.g., financials, risks, action-items' },
      { key: 'maxLength', label: 'Max Length (words)', type: 'number', defaultValue: 200, min: 50, max: 1000 },
    ],
  }]),

  'ai-custom': makeAiConfig('ai-custom', [{
    title: 'Custom Agent',
    fields: [
      { key: 'systemPrompt', label: 'System Prompt', type: 'textarea', required: true, placeholder: 'You are a senior sales analyst following company policy...' },
      { key: 'outputSchema', label: 'Output JSON Schema', type: 'code', placeholder: '{ "type": "object", "properties": {...} }', description: 'JSON Schema to validate output' },
      { key: 'chainOfThought', label: 'Chain of Thought', type: 'toggle', defaultValue: true, description: 'Log reasoning steps' },
    ],
  }]),

  'ai-extractor': makeAiConfig('ai-extractor', [{
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
  }]),

  'ai-sentiment': makeAiConfig('ai-sentiment', [{
    title: 'Analysis Configuration',
    fields: [
      { key: 'analysisTypes', label: 'Analysis Types', type: 'tags', description: 'sentiment, emotion, urgency, intent' },
      { key: 'textSource', label: 'Text Source Variable', type: 'text', required: true, placeholder: '{{data.message}}' },
    ],
  }]),

  'ai-generator': {
    nodeType: 'ai-generator',
    sections: [{
      title: 'Content Generation',
      fields: [
        { key: 'tone', label: 'Tone', type: 'text', placeholder: 'e.g. professional, friendly, urgent' },
        { key: 'prompt', label: 'Prompt', type: 'templatetextarea', required: true, placeholder: 'Write your prompt here. Use {{context.varName}} for workflow variables.' },
        { key: 'knowledgeBaseDocuments', label: 'Knowledge Base Documents', type: 'kbdocuments', description: 'Select documents to provide as context' },
        { key: 'outputVariable', label: 'Output Variable Name', type: 'text', placeholder: 'e.g. generatedEmail', description: 'Variable name to store the AI output in the workflow context' },
      ],
    }],
  },

  'ai-validator': makeAiConfig('ai-validator', [{
    title: 'Validation Rules',
    fields: [
      { key: 'rules', label: 'Rule Sets', type: 'tags', description: 'e.g., contract-limits, discount-policy, customer-tier' },
      { key: 'policyDocuments', label: 'Policy Documents', type: 'tags', description: 'Select policy documents' },
      { key: 'severity', label: 'Default Severity', type: 'select', options: [
        { label: 'Critical', value: 'critical' }, { label: 'Warning', value: 'warning' }, { label: 'Info', value: 'info' },
      ], defaultValue: 'warning' },
    ],
  }]),
};
