import type { NodeConfigSchema } from '@/config/types';
import { triggerConfigs } from '@/config/schemas/triggers';
import { logicConfigs } from '@/config/schemas/logic';
import { aiConfigs } from '@/config/schemas/ai';
import { integrationConfigs } from '@/config/schemas/integrations';
import { googleConfigs } from '@/config/schemas/google';
import { dataConfigs } from '@/config/schemas/data';
import { actionConfigs } from '@/config/schemas/actions';

// Single lookup table mapping a node type to its config form schema.
// To add a whole new category of nodes: create a schemas/<category>.ts file and
// spread it here. To add a node within an existing category, edit that category file —
// no change needed here.
export const NODE_CONFIG_REGISTRY: Record<string, NodeConfigSchema> = {
  ...triggerConfigs,
  ...logicConfigs,
  ...aiConfigs,
  ...integrationConfigs,
  ...googleConfigs,
  ...dataConfigs,
  ...actionConfigs,
};
