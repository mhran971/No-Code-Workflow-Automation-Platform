// Backwards-compatible barrel. The node config system now lives in src/config/:
//   - config/types.ts             — field & schema type contracts
//   - config/schemas/*.ts         — per-category node config schemas
//   - config/registry.ts          — assembles NODE_CONFIG_REGISTRY
// See docs/frontend-architecture.md for how to add node types / field types.

export type { ConfigFieldType, ConfigFieldOption, ConfigField, NodeConfigSchema } from '@/config/types';
export { NODE_CONFIG_REGISTRY } from '@/config/registry';
