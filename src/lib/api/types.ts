export interface ApiConfigField {
  id: number;
  key: string;
  label: string;
  type: string;
  placeholder: string | null;
  options: Array<{ label: string; value: string }> | null;
  required: boolean;
  default_value: unknown;
}

export interface ApiNodeDefinition {
  id: number;
  type: string;
  label: string;
  category: string;
  icon: string | null;
  description: string | null;
  color: string | null;
  is_active: boolean;
  config_fields: ApiConfigField[];
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

export interface WorkflowDefinition {
  trigger: {
    type: string;
    config: Record<string, unknown>;
  };
  nodes: Array<{
    id: string;
    type: string;
    label?: string;
    name?: string;
    config: Record<string, unknown>;
    position?: { x: number; y: number };
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

export interface KnowledgeBaseDocument {
  id: number;
  title: string;
}

export interface TenantUser {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  team_id: number | null;
}

export interface WorkflowSummary {
  id: string;
  public_token: string;
  name: string;
  description: string | null;
  status: string;
  version_number: number | null;
  version_label: string | null;
  updated_at: string;
}

export interface PublicFormField {
  key: string;
  label: string;
  type: string;
  required: boolean;
  options: string[];
}

export interface PublicFormSchema {
  workflow_name: string;
  form_name: string;
  form_description: string | null;
  fields: PublicFormField[];
}

export interface WorkflowTemplate {
  id: number;
  name: string;
  description: string | null;
  category: string | null;
  is_global: boolean;
  usage_count: number;
  created_at: string;
  updated_at: string;
}

export interface WorkflowDetail extends WorkflowSummary {
  draft_revision: number;
  draft_definition: WorkflowDefinition | null;
  current_version: {
    version_number: number;
    version_label: string | null;
    published_at: string;
  } | null;
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

export interface TriggerManualResponse {
  instance_id: string;
  status: string;
}

export interface InstanceSummary {
  id: string;
  status: string;
  started_at: string | null;
  finished_at: string | null;
}
