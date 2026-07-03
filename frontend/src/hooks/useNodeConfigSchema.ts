import { useMemo } from 'react';
import { NODE_CONFIG_REGISTRY, type ConfigField, type NodeConfigSchema } from '@/types/nodeConfigs';
import type { ApiConfigField } from '@/lib/api/types';

// Convert a backend-supplied config field into the local ConfigField shape.
function mapApiConfigField(field: ApiConfigField): ConfigField {
  const type =
    field.type === 'json'
      ? 'code'
      : field.type === 'email'
        ? 'text'
        : field.type;

  return {
    key: field.key,
    label: field.label,
    type: type as ConfigField['type'],
    placeholder: field.placeholder ?? undefined,
    required: field.required,
    defaultValue: field.default_value,
    options: field.options ?? undefined,
  };
}

// Seed config values from the saved node config, filling in schema defaults.
export function buildInitialValues(
  schema: NodeConfigSchema | undefined,
  nodeConfig: Record<string, unknown> | undefined,
): Record<string, unknown> {
  const values: Record<string, unknown> = { ...nodeConfig };

  schema?.sections.forEach((section) => {
    section.fields.forEach((field) => {
      if (values[field.key] === undefined && field.defaultValue !== undefined) {
        values[field.key] = field.defaultValue;
      }
    });
  });

  return values;
}

// Resolve a node's config form schema: prefer the local registry, otherwise
// synthesize a single "Configuration" section from backend config fields.
export function useNodeConfigSchema(
  nodeType: string,
  apiConfigFields?: ApiConfigField[],
): NodeConfigSchema | undefined {
  return useMemo<NodeConfigSchema | undefined>(() => {
    const localSchema = NODE_CONFIG_REGISTRY[nodeType];
    if (localSchema) {
      return localSchema;
    }

    if (!apiConfigFields?.length) {
      return undefined;
    }

    return {
      nodeType,
      sections: [
        {
          title: 'Configuration',
          fields: apiConfigFields.map(mapApiConfigField),
        },
      ],
    };
  }, [apiConfigFields, nodeType]);
}
