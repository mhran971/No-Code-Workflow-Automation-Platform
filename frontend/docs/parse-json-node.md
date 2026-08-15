# Frontend integration: `parse-json` node

Companion to [`frontend-architecture.md`](frontend-architecture.md) — read that first for how
node schemas/rendering fit together. This doc covers the frontend side of the backend's new
`parse-json` node type (`Modules/Workflows/database/seeders/NodeDefinitionSeeder.php`), and flags
one blocking mismatch found while wiring it up.

## What the backend now provides

`GET /api/v1/workflows/nodes` returns this definition (category `logic`, icon `Braces`):

| key | label | type | required |
|---|---|---|---|
| `inputVariable` | Input Variable | `text` | yes |
| `outputVariable` | Output Variable Name | `text` | yes |

Runtime behavior (`ParseJsonExecutor`): reads `inputVariable` as a bare context reference (e.g.
`context.rawJson`, **not** `{{context.rawJson}}`), `json_decode`s its resolved value, and writes
the decoded object to `outputVariable`. Malformed JSON or a non-string input fails the node
non-retryably with a descriptive error message.

## ⚠️ Blocking mismatch: existing local schema overrides it

`src/config/schemas/data.ts` already contains a `parse-json` entry — leftover prototype scaffolding
from before this node existed on the backend, alongside two other node types (`set-variables`,
`format-date`) that **the backend does not implement at all**:

```ts
'parse-json': {
  nodeType: 'parse-json',
  sections: [
    { title: 'Input', fields: [
      { key: 'inputSource', label: 'JSON String Source', type: 'text', required: true, placeholder: '{{httpResponse.body}}' },
    ]},
    { title: 'Output', fields: [
      { key: 'outputVariable', label: 'Result Variable', type: 'text', defaultValue: 'parsedData' },
      { key: 'extractPaths', label: 'Extract Specific Paths', type: 'keyvalue', description: '...' },
    ]},
    { title: 'Error Handling', fields: [
      { key: 'onParseFailure', label: 'On Parse Failure', type: 'select', options: [...] },
      { key: 'validationSchema', label: 'Validation Schema (JSON Schema)', type: 'code' },
    ]},
  ],
},
```

`useNodeConfigSchema` (`src/hooks/useNodeConfigSchema.ts:50-53`) **prefers the local registry over
the backend's `configFields`** whenever both exist for the same node type key:

```ts
const localSchema = NODE_CONFIG_REGISTRY[nodeType];
if (localSchema) {
  return localSchema; // backend's apiConfigFields are never consulted
}
```

Net effect if this ships as-is: the config panel shows `inputSource` (not `inputVariable`),
plus `extractPaths`/`onParseFailure`/`validationSchema` fields the executor never reads and
silently ignores. The one field that looks right — `outputVariable` — happens to share a key
with the backend, but everything else is disconnected from what `ParseJsonNodeTypeRule` validates
and `ParseJsonExecutor` executes.

**Fix**: replace the `parse-json` entry in `data.ts` with the two fields the backend actually
defines:

```ts
'parse-json': {
  nodeType: 'parse-json',
  sections: [
    { title: 'Input', fields: [
      { key: 'inputVariable', label: 'Input Variable', type: 'text', required: true, placeholder: 'context.rawJson' },
    ]},
    { title: 'Output', fields: [
      { key: 'outputVariable', label: 'Output Variable Name', type: 'text', required: true, placeholder: 'parsedData' },
    ]},
  ],
},
```

Or, if you'd rather not maintain a parallel local schema at all, delete the `parse-json` entry
from `data.ts` entirely — `useNodeConfigSchema` will then fall back to synthesizing a single
"Configuration" section straight from the backend's `configFields`, which is already correct and
requires no frontend maintenance as the backend definition evolves.

While in `data.ts`, note `set-variables` and `format-date` have the same problem in the other
direction: they render a full config UI for node types that don't exist in
`NodeDefinitionSeeder` at all, so saving/publishing a workflow using either would fail backend
verification (`NodeTypeVerificationRule` has no rule for either key, but `SyntaxVerificationRule`
would reject an unknown node type). Out of scope for this change, but worth a follow-up.

## What already works, no action needed

- **Icon**: `Braces` is already in `iconMap` (`src/components/workflow/WorkflowNode.tsx`) — the
  node renders correctly on the canvas the moment it's seeded.
- **Canvas component**: `nodeComponentType()` (`src/hooks/useCanvasDragDrop.ts:11-17`) has no
  special case for `parse-json`, so it uses the generic `workflowNode` component — correct, since
  this node has a single input/output like `send-email` or `ai-generator`, not branching output
  like `if-node`/`switch`.
- **Execution simulation mock data**: `src/hooks/execution/mockData.ts` already has `parse-json`
  entries in both `MOCK_VARIABLES` (line ~189) and `MOCK_MESSAGES` (line ~236) for the client-side
  "Run" simulation feature. These are cosmetic/demo-only (unrelated to the real backend executor)
  and need no change.

## Verification checklist

1. Apply the `data.ts` fix above (or delete the entry).
2. `npm run build` — type-checks the schema change via Vite.
3. `npm run lint`.
4. Manually: drag a Parse JSON node onto the canvas, confirm the config panel shows exactly
   **Input Variable** and **Output Variable Name** (no `extractPaths`/`onParseFailure`/
   `validationSchema`), save, and confirm `node.data.config` round-trips `{ inputVariable, outputVariable }`
   matching what `POST /workflows/{id}/draft` and `POST /workflows/validate` expect.
