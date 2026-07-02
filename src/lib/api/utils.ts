import type { Edge, Node } from '@xyflow/react';
import type { NodeCategory, NodeTypeDefinition } from '@/types/workflow';
import { NODE_TYPES } from '@/types/workflow';
import type { ApiNodeDefinition, WorkflowDefinition } from './types';

export function normalizeToken(token: string): string {
  let value = token.trim();

  if (value.toLowerCase().startsWith('bearer ')) {
    value = value.slice(7).trim();
  }

  if (
    (value.startsWith('"') && value.endsWith('"')) ||
    (value.startsWith("'") && value.endsWith("'"))
  ) {
    value = value.slice(1, -1).trim();
  }

  return value;
}

export function readStoredValue(key: string, fallback: string): string {
  if (typeof window === 'undefined') {
    return fallback;
  }

  const stored = window.localStorage.getItem(key);
  if (stored === null || stored.trim() === '') {
    return fallback;
  }

  return stored;
}

export function defaultConfigForNode(definition: ApiNodeDefinition): Record<string, unknown> {
  const config: Record<string, unknown> = {};

  for (const field of definition.config_fields) {
    if (field.default_value !== null && field.default_value !== undefined) {
      config[field.key] = field.default_value;
    }
  }

  return config;
}

const API_CATEGORY_MAP: Record<string, NodeCategory> = {
  trigger: 'triggers',
  logic: 'logic',
  ai: 'ai',
  flows: 'flows',
  action: 'actions',
};

const CATEGORY_COLOR_MAP: Record<string, string> = {
  trigger: 'amber',
  logic: 'indigo',
  ai: 'violet',
  flows: 'teal',
  action: 'rose',
};

export function mapApiNodeToUiNode(apiNode: ApiNodeDefinition): NodeTypeDefinition {
  const fallback = NODE_TYPES.find((node) => node.type === apiNode.type);
  const category = API_CATEGORY_MAP[apiNode.category] ?? 'actions';

  return {
    type: apiNode.type,
    label: apiNode.label,
    category,
    icon: fallback?.icon ?? 'Zap',
    color: fallback?.color ?? CATEGORY_COLOR_MAP[apiNode.category] ?? 'rose',
    description: apiNode.description ?? fallback?.description ?? '',
    inputs: fallback?.inputs ?? (apiNode.category === 'trigger' ? 0 : 1),
    outputs: fallback?.outputs ?? 1,
  };
}

function branchTypeFromHandle(handle: string | null | undefined, nodeType?: string): string {
  if (!handle) return 'default';

  // If-node explicit handles: 'yes' → true branch, 'no' → false/else branch
  if (handle === 'yes') return 'true';
  if (handle === 'no') return 'else';

  // Switch node named handles: "default" or "option-{value}"
  if (handle === 'default') return 'default';
  if (handle.startsWith('option-')) return handle.slice(7);

  // Fork (and-node) named branch handles: "branch-{key}"
  if (handle.startsWith('branch-')) return handle.slice(7);

  // Generic output handles from WorkflowNode: "output-N"
  const match = handle.match(/^output-(\d+)$/);
  if (match) {
    // All single-output nodes use output-0 as the default/only path
    return 'default';
  }

  return 'default';
}

export interface CanvasNodeData {
  label: string;
  nodeType: string;
  name?: string;
  config?: Record<string, unknown>;
  hasErrors?: boolean;
  [key: string]: unknown;
}

export function isTriggerNode(node: Node, definitions: ApiNodeDefinition[]): boolean {
  const nodeType = (node.data as CanvasNodeData).nodeType;
  if (!nodeType) {
    return false;
  }

  const definition = definitions.find((item) => item.type === nodeType);
  if (definition) {
    return definition.category === 'trigger';
  }

  return nodeType.endsWith('-trigger');
}

export function buildDefinitionFromCanvas(
  nodes: Node[],
  edges: Edge[],
  definitions: ApiNodeDefinition[],
): WorkflowDefinition {
  const triggerNode = nodes.find((node) => isTriggerNode(node, definitions));
  const triggerData = triggerNode?.data as CanvasNodeData | undefined;

  return {
    trigger: {
      type: triggerData?.nodeType ?? 'manual-trigger',
      config: triggerData?.config ?? {},
    },
    nodes: nodes.map((node) => {
      const data = node.data as CanvasNodeData;

      return {
        id: node.id,
        type: data.nodeType,
        label: data.label,
        name: data.name,
        config: data.config ?? {},
        position: node.position,
      };
    }),
    edges: edges.map((edge, index) => {
      const sourceNode = nodes.find(n => n.id === edge.source);
      const sourceNodeType = (sourceNode?.data as CanvasNodeData | undefined)?.nodeType as string | undefined;
      return {
        id: edge.id || `edge-${index + 1}`,
        source_node_key: edge.source,
        target_node_key: edge.target,
        branch_type: branchTypeFromHandle(edge.sourceHandle, sourceNodeType),
      };
    }),
    variables: [],
    settings: {},
  };
}

export function formatApiError(error: unknown): string {
  if (error instanceof Error && error.name === 'ApiError') {
    const apiError = error as { status: number; message: string; url: string };
    return apiError.status > 0
      ? `${apiError.message} (${apiError.status}) — ${apiError.url}`
      : `${apiError.message} — ${apiError.url}`;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return 'Request failed';
}
