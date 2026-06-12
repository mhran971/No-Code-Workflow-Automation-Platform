export interface ConfigField {
  id: number;
  key: string;
  label: string;
  type: string;
  placeholder: string | null;
  options: Array<{ label: string; value: string }> | null;
  required: boolean;
  default_value: unknown;
}

export interface NodeDefinition {
  id: number;
  type: string;
  label: string;
  category: string;
  icon: string | null;
  description: string | null;
  color: string | null;
  is_active: boolean;
  config_fields: ConfigField[];
}

export interface CanvasNode {
  canvasId: string;
  id: string;
  type: string;
  label: string;
  config: Record<string, unknown>;
  is_entry_point: boolean;
  is_terminal: boolean;
  x: number;
  y: number;
}

export interface WorkflowEdge {
  id: string;
  source_node_key: string;
  target_node_key: string;
  branch_type?: string;
  condition_expression?: string | null;
  is_default_branch?: boolean;
  parallel_strategy?: string | null;
  join_node_key?: string | null;
  sort_order?: number;
}

export interface WorkflowTrigger {
  type: string;
  config: Record<string, unknown>;
}

export interface WorkflowDefinition {
  trigger: WorkflowTrigger;
  nodes: Array<{
    id: string;
    type: string;
    label?: string;
    config: Record<string, unknown>;
    is_entry_point?: boolean;
    is_terminal?: boolean;
  }>;
  edges: WorkflowEdge[];
  variables: unknown[];
  settings: Record<string, unknown>;
}

export interface ValidationIssue {
  severity: string;
  code: string;
  message: string;
  location?: {
    path?: string | null;
    field?: string | null;
    scope?: string | null;
    node_id?: string | null;
    edge_id?: string | null;
  };
  path?: string | null;
  node_id?: string | null;
  edge_id?: string | null;
}

export interface ValidationResult {
  is_publishable: boolean;
  summary: {
    errors: number;
    warnings: number;
  };
  issues: ValidationIssue[];
  errors: string[];
  warnings: string[];
}
