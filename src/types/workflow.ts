export type NodeCategory = 'triggers' | 'logic' | 'ai' | 'integrations' | 'data' | 'actions';

export interface NodeTypeDefinition {
  type: string;
  label: string;
  category: NodeCategory;
  icon: string;
  color: string;
  description: string;
  inputs: number;
  outputs: number;
}

export const NODE_CATEGORIES: Record<NodeCategory, { label: string; color: string; icon: string }> = {
  triggers: { label: 'Triggers', color: 'amber', icon: 'Zap' },
  logic: { label: 'Logic & Flow', color: 'indigo', icon: 'GitBranch' },
  ai: { label: 'AI Agents', color: 'violet', icon: 'Brain' },
  integrations: { label: 'Integrations', color: 'blue', icon: 'Plug' },
  data: { label: 'Data', color: 'emerald', icon: 'Database' },
  actions: { label: 'Actions', color: 'rose', icon: 'Play' },
};

export const NODE_TYPES: NodeTypeDefinition[] = [
  // Triggers
  { type: 'manual-trigger', label: 'Manual Trigger', category: 'triggers', icon: 'MousePointerClick', color: 'amber', description: 'Start workflow manually', inputs: 0, outputs: 1 },
  { type: 'webhook-trigger', label: 'Webhook', category: 'triggers', icon: 'Webhook', color: 'amber', description: 'HTTP POST endpoint', inputs: 0, outputs: 1 },
  { type: 'form-trigger', label: 'Form Trigger', category: 'triggers', icon: 'FormInput', color: 'amber', description: 'Start from form submission', inputs: 0, outputs: 1 },
  { type: 'schedule-trigger', label: 'Schedule', category: 'triggers', icon: 'Clock', color: 'amber', description: 'Run on a schedule', inputs: 0, outputs: 1 },
  { type: 'app-event-trigger', label: 'App Event', category: 'triggers', icon: 'Bell', color: 'amber', description: 'Listen for app events', inputs: 0, outputs: 1 },

  // Logic
  { type: 'if-node', label: 'If', category: 'logic', icon: 'GitBranch', color: 'indigo', description: 'Conditional branching', inputs: 1, outputs: 2 },
  { type: 'if-else-chain', label: 'If-Else Chain', category: 'logic', icon: 'GitMerge', color: 'indigo', description: 'Multi-way conditions', inputs: 1, outputs: 3 },
  { type: 'parallel-split', label: 'Parallel Split', category: 'logic', icon: 'Split', color: 'indigo', description: 'Create parallel branches', inputs: 1, outputs: 3 },
  { type: 'parallel-join', label: 'Parallel Join', category: 'logic', icon: 'Merge', color: 'indigo', description: 'Wait for all branches', inputs: 3, outputs: 1 },
  { type: 'wait-until', label: 'Wait Until', category: 'logic', icon: 'Timer', color: 'indigo', description: 'Pause until condition', inputs: 1, outputs: 1 },
  { type: 'switch-node', label: 'Switch', category: 'logic', icon: 'Route', color: 'indigo', description: 'Value-based routing', inputs: 1, outputs: 4 },
  { type: 'loop-node', label: 'Loop', category: 'logic', icon: 'Repeat', color: 'indigo', description: 'Iterate over items', inputs: 1, outputs: 1 },
  { type: 'foreach-node', label: 'For Each', category: 'logic', icon: 'ListOrdered', color: 'indigo', description: 'Process each item', inputs: 1, outputs: 1 },
  { type: 'filter-node', label: 'Filter', category: 'logic', icon: 'Filter', color: 'indigo', description: 'Filter array items', inputs: 1, outputs: 1 },

  // AI Agents
  { type: 'ai-router', label: 'Router Agent', category: 'ai', icon: 'Signpost', color: 'violet', description: 'AI-powered routing', inputs: 1, outputs: 3 },
  { type: 'ai-classifier', label: 'Classifier', category: 'ai', icon: 'Tags', color: 'violet', description: 'Categorize data', inputs: 1, outputs: 1 },
  { type: 'ai-summarizer', label: 'Summarizer', category: 'ai', icon: 'FileText', color: 'violet', description: 'Summarize content', inputs: 1, outputs: 1 },
  { type: 'ai-custom', label: 'Custom Agent', category: 'ai', icon: 'Bot', color: 'violet', description: 'Custom AI prompt', inputs: 1, outputs: 1 },
  { type: 'ai-extractor', label: 'Extractor', category: 'ai', icon: 'Search', color: 'violet', description: 'Extract structured data', inputs: 1, outputs: 1 },
  { type: 'ai-sentiment', label: 'Sentiment', category: 'ai', icon: 'Heart', color: 'violet', description: 'Analyze sentiment', inputs: 1, outputs: 1 },
  { type: 'ai-generator', label: 'Generator', category: 'ai', icon: 'Wand2', color: 'violet', description: 'Generate content', inputs: 1, outputs: 1 },
  { type: 'ai-validator', label: 'Validator', category: 'ai', icon: 'ShieldCheck', color: 'violet', description: 'Validate against rules', inputs: 1, outputs: 1 },

  // Integrations
  { type: 'gmail-send', label: 'Gmail: Send', category: 'integrations', icon: 'Mail', color: 'blue', description: 'Send email via Gmail', inputs: 1, outputs: 1 },
  { type: 'gmail-receive', label: 'Gmail: New Email', category: 'integrations', icon: 'MailOpen', color: 'blue', description: 'Trigger on new email', inputs: 0, outputs: 1 },
  { type: 'hubspot-contact', label: 'HubSpot: Contact', category: 'integrations', icon: 'UserPlus', color: 'blue', description: 'Create/update contact', inputs: 1, outputs: 1 },
  { type: 'hubspot-deal', label: 'HubSpot: Deal', category: 'integrations', icon: 'Handshake', color: 'blue', description: 'Create/update deal', inputs: 1, outputs: 1 },
  { type: 'clickup-task', label: 'ClickUp: Task', category: 'integrations', icon: 'CheckSquare', color: 'blue', description: 'Create/update task', inputs: 1, outputs: 1 },
  { type: 'gdrive-file', label: 'Google Drive', category: 'integrations', icon: 'HardDrive', color: 'blue', description: 'Create/update files', inputs: 1, outputs: 1 },
  { type: 'gsheets-rows', label: 'Google Sheets', category: 'integrations', icon: 'Sheet', color: 'blue', description: 'Read/write rows', inputs: 1, outputs: 1 },
  { type: 'gdocs-edit', label: 'Google Docs', category: 'integrations', icon: 'FileEdit', color: 'blue', description: 'Edit documents', inputs: 1, outputs: 1 },

  // Data
  { type: 'set-variables', label: 'Set Variables', category: 'data', icon: 'Variable', color: 'emerald', description: 'Set or update variables', inputs: 1, outputs: 1 },
  { type: 'parse-json', label: 'Parse JSON', category: 'data', icon: 'Braces', color: 'emerald', description: 'Parse JSON string', inputs: 1, outputs: 1 },
  { type: 'format-date', label: 'Format Date', category: 'data', icon: 'Calendar', color: 'emerald', description: 'Format and convert dates', inputs: 1, outputs: 1 },

  // Actions
  { type: 'http-request', label: 'HTTP Request', category: 'actions', icon: 'Globe', color: 'rose', description: 'Call external API', inputs: 1, outputs: 1 },
  { type: 'send-email', label: 'Send Email', category: 'actions', icon: 'Send', color: 'rose', description: 'Send email message', inputs: 1, outputs: 1 },
  { type: 'external-service', label: 'External Service', category: 'actions', icon: 'ExternalLink', color: 'rose', description: 'Call third-party service', inputs: 1, outputs: 1 },
  { type: 'task-node', label: 'Human Task', category: 'actions', icon: 'UserCheck', color: 'rose', description: 'Human review/approval', inputs: 1, outputs: 2 },
];

export type ExecutionStatus = 'idle' | 'running' | 'success' | 'failed' | 'waiting';

export interface ExecutionStep {
  id: string;
  nodeId: string;
  nodeLabel: string;
  nodeType: string;
  status: ExecutionStatus;
  timestamp: string;
  duration?: number;
  variables: Record<string, unknown>;
  message?: string;
  error?: string;
}

export interface WorkflowExecution {
  id: string;
  status: ExecutionStatus;
  startedAt: string;
  completedAt?: string;
  steps: ExecutionStep[];
}
