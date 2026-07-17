export type NodeCategory = 'triggers' | 'logic' | 'ai' | 'flows' | 'actions';

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
  logic: { label: 'Logic', color: 'indigo', icon: 'GitBranch' },
  ai: { label: 'AI', color: 'violet', icon: 'Brain' },
  flows: { label: 'Flows', color: 'teal', icon: 'Workflow' },
  actions: { label: 'Actions', color: 'rose', icon: 'Play' },
};

export const NODE_TYPES: NodeTypeDefinition[] = [
  // Triggers
  { type: 'manual-trigger', label: 'Manual', category: 'triggers', icon: 'MousePointerClick', color: 'amber', description: 'Start workflow manually', inputs: 0, outputs: 1 },
  { type: 'form-trigger', label: 'Form Trigger', category: 'triggers', icon: 'FormInput', color: 'amber', description: 'Start from form submission', inputs: 0, outputs: 1 },

  // Logic
  { type: 'if-node', label: 'If', category: 'logic', icon: 'GitBranch', color: 'indigo', description: 'Conditional branching', inputs: 1, outputs: 2 },
  { type: 'and-node', label: 'And', category: 'logic', icon: 'ListChecks', color: 'indigo', description: 'Continue only when all conditions are true', inputs: 1, outputs: 1 },
  { type: 'merge', label: 'Merge', category: 'logic', icon: 'GitMerge', color: 'indigo', description: 'Synchronize incoming branches (parallel or conditional)', inputs: 2, outputs: 1 },
  { type: 'switch', label: 'Switch', category: 'logic', icon: 'Route', color: 'indigo', description: 'Multi-path routing based on a value', inputs: 1, outputs: 4 },
  { type: 'termination-node', label: 'Terminate', category: 'logic', icon: 'OctagonX', color: 'indigo', description: 'Marks the end of a workflow branch', inputs: 1, outputs: 0 },

  // AI
  { type: 'ai-generator', label: 'AI Generator', category: 'ai', icon: 'Wand2', color: 'violet', description: 'Generate content with AI', inputs: 1, outputs: 1 },

  // Flows
  { type: 'sub-workflow', label: 'Sub Workflow', category: 'flows', icon: 'Workflow', color: 'teal', description: 'Execute another workflow as a step', inputs: 1, outputs: 1 },
  { type: 'dynamic-flow', label: 'Dynamic Flow', category: 'flows', icon: 'Shuffle', color: 'teal', description: 'Runtime sub-flow creation', inputs: 1, outputs: 1 },

  // Dynamic Entry
  { type: 'dynamic-entry', label: 'Entry Point', category: 'triggers', icon: 'LogIn', color: 'emerald', description: 'Sub-flow entry point', inputs: 0, outputs: 1 },

  // Actions
  { type: 'send-email', label: 'Send Email', category: 'actions', icon: 'Send', color: 'rose', description: 'Send email message', inputs: 1, outputs: 1 },
  { type: 'task-node', label: 'Task Node', category: 'actions', icon: 'UserCheck', color: 'rose', description: 'Assign a task for review or approval', inputs: 1, outputs: 1 },
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
