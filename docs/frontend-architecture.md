# Frontend Architecture (`remix-of-workflow-weaver`)

This is the React + TypeScript + Vite workflow builder. Users drag node types onto a
canvas (powered by `@xyflow/react`), configure each node in a side panel, simulate
execution, and run backend validation. This document explains how the pieces fit together
and — most importantly — **how to add a new node type or a new config field type**.

The codebase is organized so most files stay under ~200 lines. Generated shadcn UI
primitives live in `src/components/ui/` and are intentionally left untouched.

## Directory map

```
src/
├── config/                         # Node config FORM schemas (what fields a node shows)
│   ├── types.ts                    #   ConfigField / NodeConfigSchema type contracts
│   ├── registry.ts                 #   merges all category records → NODE_CONFIG_REGISTRY
│   └── schemas/                    #   one file per node CATEGORY
│       ├── triggers.ts             #     manual / webhook / form / schedule / app-event
│       ├── logic.ts                #     if / switch / loop / parallel / fork (and-node) …
│       ├── ai.ts                   #     AI agents (+ shared makeAiConfig helper)
│       ├── integrations.ts         #     Gmail / HubSpot / ClickUp
│       ├── google.ts               #     Google Workspace (Drive / Sheets / Docs)
│       ├── data.ts                 #     set-variables / parse-json / format-date
│       └── actions.ts              #     http-request / send-email / external / task
│
├── components/workflow/
│   ├── NodeConfigPanel.tsx         # orchestrates the config side panel
│   ├── config-icons.tsx            #   iconMap + colorBg lookup tables
│   ├── config-fields/              # the dynamic config FORM RENDERERS
│   │   ├── types.ts                #   shared item/prop types
│   │   ├── basic.tsx               #   text / textarea / code / number / select / toggle / readonly
│   │   ├── collections.tsx         #   keyvalue / tags / fieldlist
│   │   ├── lists.tsx               #   inputfieldlist / branchlist (complex editors)
│   │   ├── templates.tsx           #   templatetext / emailtemplate / identifier / userselect (+ validators)
│   │   ├── FieldRenderer.tsx       #   switch(field.type) → the right renderer  ← dispatch
│   │   └── ConfigSection.tsx       #   one collapsible section of fields
│   ├── ExecutionPanel.tsx          # right panel: tabs + execution timeline
│   ├── execution/                  #   VariableTree / StepRow / ExecutionControls / WorkflowSettingsTab
│   ├── WorkflowCanvas.tsx          # ReactFlow wrapper
│   ├── WorkflowNode/SwitchNode/ForkNode.tsx   # node canvas components
│   └── NodeLibrary / WorkflowHeader / *Dialog.tsx
│
├── hooks/
│   ├── useWorkflowExecution.ts     # mock execution state machine
│   ├── execution/                  #   mockData.ts / executionOrder.ts / types.ts
│   ├── useNodeConfigSchema.ts      # resolves a node's schema (registry or backend fields)
│   ├── useFieldValidationErrors.ts # splits validation issues into field/node buckets
│   ├── useCanvasNodeActions.ts     # imperative node/edge mutations for the canvas
│   ├── useCanvasDragDrop.ts        # drag-from-library → create node on canvas
│   └── useWorkflowValidation.ts    # the "Verify" flow (build definition → backend → map errors)
│
├── lib/api/                        # HTTP client, request/response types, canvas↔definition utils
├── types/
│   ├── workflow.ts                 # core domain types + base NODE_TYPES
│   └── nodeConfigs.ts              # thin barrel re-exporting config/types + registry (legacy path)
└── pages/Index.tsx                 # top-level layout: header + library + canvas + panel
```

## Data flow (drag → configure → save)

```
config/registry.ts (NODE_CONFIG_REGISTRY)        backend GET /workflows/nodes
        │  form schema per node type                     │ label, icon, config_fields
        └───────────────┬─────────────────────────────────┘
                        ▼
   NodeLibrary  ──drag──►  WorkflowCanvas (useCanvasDragDrop creates the node)
                        │
                  onNodeClick → Index.selectedNode
                        ▼
   ExecutionPanel → NodeConfigPanel
        useNodeConfigSchema(nodeType, apiConfigFields)  → schema
        schema.sections → ConfigSection → FieldRenderer(field.type) → renderer
        Save → Index.handleConfigSave → canvas updates node.data.config
```

Validation: header **Verify** → `useWorkflowValidation` → `buildDefinitionFromCanvas`
(`lib/api/utils.ts`) → `validateWorkflowDefinition` (`lib/api/client.ts`) → issues mapped
back onto nodes (red highlight) and into `NodeConfigPanel` via `useFieldValidationErrors`.

## How to add a new NODE TYPE

1. Pick the matching category file in `src/config/schemas/` (or create a new one).
2. Add an entry keyed by the node type string, e.g.:
   ```ts
   export const actionConfigs: Record<string, NodeConfigSchema> = {
     // …existing…
     'slack-post': {
       nodeType: 'slack-post',
       sections: [
         { title: 'Message', fields: [
           { key: 'channel', label: 'Channel', type: 'text', required: true },
           { key: 'text', label: 'Message', type: 'textarea', required: true },
         ]},
       ],
     },
   };
   ```
3. If you created a **new category file**, import its record in
   `src/config/registry.ts` and spread it into `NODE_CONFIG_REGISTRY`. Adding to an
   **existing** category file needs no registry change.
4. The node's label / icon / description come from the backend `/workflows/nodes`
   response. If the node needs a custom canvas component (like Switch/Fork), add a case in
   `nodeComponentType()` inside `src/hooks/useCanvasDragDrop.ts`.

AI nodes are special: use the `makeAiConfig()` helper in `schemas/ai.ts` so the shared
Knowledge Base / Context / LLM / Output sections are appended automatically — you only
declare the node-specific section(s).

## How to add a new CONFIG FIELD TYPE

1. Add the new type string to `ConfigFieldType` in `src/config/types.ts`.
2. Write a renderer component in the appropriate `config-fields/` file
   (`basic.tsx` for simple inputs, `collections.tsx`/`lists.tsx` for repeating editors,
   `templates.tsx` for validated/variable fields).
3. Add a `case` for it in `src/components/workflow/config-fields/FieldRenderer.tsx`.

That dispatch `switch` is the single place that maps a field type to its renderer.

## Execution simulation

`useWorkflowExecution` drives a mock run. It topologically orders nodes
(`hooks/execution/executionOrder.ts`), builds steps with mock output variables and log
messages (`hooks/execution/mockData.ts`), and advances them on timers (Run All / Step /
Pause / Resume / Reset). To make a new node type produce realistic mock output, add an
entry to `MOCK_VARIABLES` and `MOCK_MESSAGES` keyed by its node type.

## Conventions

- Path alias `@/*` → `src/*` (see `tsconfig.app.json`).
- Keep files under ~200 lines; split by responsibility (renderers, hooks, schemas).
- Schema files are flat data records keyed by node type — easy to diff and extend.
- Verify changes with `npm run build` (type-checks via Vite) and `npm run lint`.
