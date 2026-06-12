import type { CanvasNode, ConfigField, NodeDefinition, WorkflowDefinition, WorkflowEdge } from './types';

/** Internal canvas-only edge source; not sent to the API. */
export const TRIGGER_EDGE_SOURCE = '__trigger__';

export const TRIGGER_CANVAS = {
  x: 40,
  y: 40,
  width: 220,
  height: 72,
};

export function createId(prefix: string): string {
  return `${prefix}-${Math.random().toString(36).slice(2, 9)}`;
}

/** Trim pasted tokens and strip accidental "Bearer " prefix or quotes. */
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

export function defaultConfigForNode(definition: NodeDefinition): Record<string, unknown> {
  const config: Record<string, unknown> = {};

  for (const field of definition.config_fields) {
    if (field.default_value !== null && field.default_value !== undefined) {
      config[field.key] = field.default_value;
    }
  }

  return config;
}

export function isTriggerEdge(edge: WorkflowEdge): boolean {
  return edge.source_node_key === TRIGGER_EDGE_SOURCE;
}

export function buildDefinitionPayload(
  triggerType: string,
  triggerConfig: Record<string, unknown>,
  nodes: CanvasNode[],
  edges: WorkflowEdge[],
): WorkflowDefinition {
  const triggerTargetIds = new Set(
    edges.filter(isTriggerEdge).map((edge) => edge.target_node_key),
  );

  return {
    trigger: {
      type: triggerType,
      config: triggerConfig,
    },
    nodes: nodes.map((node) => ({
      id: node.id,
      type: node.type,
      label: node.label,
      config: node.config,
      is_entry_point: triggerTargetIds.has(node.id) || node.is_entry_point,
      is_terminal: node.is_terminal,
    })),
    edges: edges.filter((edge) => !isTriggerEdge(edge)),
    variables: [],
    settings: {},
  };
}

export function nodeAnchorPoints(node: CanvasNode) {
  return {
    in: { x: node.x, y: node.y + 40 },
    out: { x: node.x + 220, y: node.y + 40 },
  };
}

export function triggerAnchorPoints() {
  return {
    out: {
      x: TRIGGER_CANVAS.x + TRIGGER_CANVAS.width,
      y: TRIGGER_CANVAS.y + TRIGGER_CANVAS.height / 2,
    },
  };
}

export function parseFieldValue(raw: string, fieldType: string): unknown {
  if (fieldType === 'number') {
    if (raw.trim() === '') {
      return '';
    }

    const parsed = Number(raw);
    return Number.isNaN(parsed) ? raw : parsed;
  }

  if (fieldType === 'toggle') {
    return raw === 'true';
  }

  if (fieldType === 'json' || fieldType === 'tags') {
    if (raw.trim() === '') {
      return fieldType === 'tags' ? [] : {};
    }

    try {
      return JSON.parse(raw);
    } catch {
      return raw;
    }
  }

  return raw;
}

export function serializeFieldValue(value: unknown, fieldType: string): string {
  if (value === null || value === undefined) {
    return '';
  }

  if (fieldType === 'toggle') {
    return value ? 'true' : 'false';
  }

  if (fieldType === 'json' || fieldType === 'tags') {
    return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
  }

  return String(value);
}

export function isTriggerDefinition(node: NodeDefinition): boolean {
  return node.category === 'trigger';
}

export function groupNodesByCategory(nodes: NodeDefinition[]): Record<string, NodeDefinition[]> {
  return nodes.reduce<Record<string, NodeDefinition[]>>((groups, node) => {
    const category = node.category || 'other';
    groups[category] = groups[category] ?? [];
    groups[category].push(node);
    return groups;
  }, {});
}

export function getNodeDefinition(
  definitions: NodeDefinition[],
  type: string,
): NodeDefinition | undefined {
  return definitions.find((node) => node.type === type);
}

export function fieldInputType(field: ConfigField): string {
  switch (field.type) {
    case 'email':
      return 'email';
    case 'number':
      return 'number';
    default:
      return 'text';
  }
}
