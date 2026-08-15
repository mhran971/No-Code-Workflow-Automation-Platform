# Frontend implementation guide: ClickUp "Create Task" node

Companion to [`frontend-architecture.md`](frontend-architecture.md) — read that first for how node
schemas, config field rendering, and the drag/configure/save data flow fit together. This doc is a
self-contained handoff for implementing the config UI for the new backend node type
`clickup-create-task`: what it does, the exact API contracts it depends on, and a recommended UX.

## 1. What this node does

Placed on a workflow canvas (Action category), it creates a task in ClickUp when the workflow
reaches it — e.g. "file a ClickUp task in the Support list when a customer submits this form."
The user configures:

1. Which **ClickUp workspace** (ClickUp calls these "Teams") to use.
2. Which **List** within that workspace the task goes into.
3. The **task name**.
4. Optional **markdown content** for the task body.

The node only works for a tenant that has already connected ClickUp via the existing Integrations
page (`GET /api/v1/integrations` → connect flow, documented in
[`Modules/Integrations/docs/api.md`](../../Modules/Integrations/docs/api.md)). This node's own
config panel is not where that connection happens — it only consumes an existing connection.

## 2. Backend contract — do not deviate from these field keys

`GET /api/v1/workflows/nodes` returns this definition (already seeded, live today):

| key | backend type | required | notes |
|---|---|---|---|
| `workspaceId` | `text` | yes | ClickUp workspace (Team) ID, as a string |
| `listId` | `text` | yes | ClickUp List ID, as a string |
| `name` | `text` | yes | Task title. Supports `{{context.x}}` / `{{customer.x}}` template variables, rendered at execution time. |
| `markdownContent` | `textarea` | no | Maps 1:1 to ClickUp's `markdown_content` field. Also supports template variables. |

**These four keys — `workspaceId`, `listId`, `name`, `markdownContent` — are exactly what
`ClickUpCreateTaskExecutor` reads and `ClickUpCreateTaskNodeTypeRule` validates.** Whatever UI
widgets you build, the saved `node.data.config` object must end up as plain strings under these
exact keys (e.g. `{"workspaceId": "900", "listId": "901", "name": "Follow up with {{context.customerName}}", "markdownContent": "## Details\n..."}`). The backend's declared field `type`
(`text`/`textarea`) only matters as a fallback for the auto-generated form — see §4, you're free to
render richer local widgets as long as the output shape matches this table.

### ⚠️ Node type key — do not reuse `clickup-task`

Use the node type string **`clickup-create-task`**, not `clickup-task`. `clickup-task` already
exists as an unrelated, unwired mock schema in `src/config/schemas/integrations.ts` — a v0 UI
prototype from before this feature existed on the backend, with entirely different field names
(`listId` as free text, `taskName`, `description`, a `connection` select with only a placeholder
option). There is no backend `Node` row, rule, or executor for `clickup-task` — it's dead
scaffolding. Reusing that key would collide with it (see §4's note on how the local schema
registry overrides backend data) and produce a form that doesn't match what the backend expects.

## 3. New API endpoints (Integrations module)

Two endpoints exist specifically to back this node's config panel. Full reference:
[`Modules/Integrations/docs/api.md`](../../Modules/Integrations/docs/api.md). Both require the
same JWT bearer auth as every other authenticated call in this app, and are restricted to
`business_owner`/`manager` roles (a plain `employee` gets a 403 — same shape as other role-gated
endpoints in this app).

### List workspaces

```
GET /api/v1/integrations/clickup/workspaces
```

```json
{ "data": [{ "id": "900", "name": "Acme Workspace" }] }
```

- `401` if unauthenticated.
- `403` if the user's role isn't `business_owner`/`manager`.
- `404` `{"message": "No clickup connection found for this tenant. Connect clickup via Integrations first."}` if the tenant hasn't connected ClickUp yet. **Design the empty/error state around this** — see §5.

### List lists in a workspace

```
GET /api/v1/integrations/clickup/workspaces/{workspaceId}/lists
```

```json
{
  "data": [
    { "id": "901", "name": "Backlog", "space": "Engineering", "folder": null },
    { "id": "902", "name": "Sprint 14", "space": "Engineering", "folder": "Sprints" }
  ]
}
```

- `space`/`folder` are provided so you can group/label the dropdown (e.g. "Engineering / Sprints /
  Sprint 14" vs. "Engineering / Backlog"); `folder` is `null` for lists that sit directly in a
  Space with no Folder.
- Same `401`/`403`/`404` shape as above.
- **This call can be slow.** The backend fans out across every Space and Folder in the workspace
  (ClickUp has no single "all lists" endpoint) — for a workspace with many spaces/folders this is
  several sequential HTTP calls server-side. Show a loading state; don't assume it resolves
  instantly.
- A ClickUp-side failure (e.g. an invalid/stale `workspaceId`) surfaces as `{"message": "The clickup API returned an error: ..."}` with ClickUp's real HTTP status.

### Suggested fetch helpers

Model these after the existing `loadWorkflow()` in `src/lib/api/client.ts` (same `request(baseUrl, token, path)` internal helper used by every other authenticated GET in this file, e.g. `fetchTeamUsers`, `fetchDocuments`):

```ts
export function fetchClickUpWorkspaces(
  baseUrl: string,
  token: string,
): Promise<{ data: Array<{ id: string; name: string }> }> {
  return request(baseUrl, token, '/integrations/clickup/workspaces');
}

export function fetchClickUpLists(
  baseUrl: string,
  token: string,
  workspaceId: string,
): Promise<{ data: Array<{ id: string; name: string; space: string; folder: string | null }> }> {
  return request(baseUrl, token, `/integrations/clickup/workspaces/${workspaceId}/lists`);
}
```

## 4. Where this plugs into the existing schema system

Per `frontend-architecture.md`'s "How to add a new NODE TYPE": add an entry to
`src/config/schemas/integrations.ts` keyed `'clickup-create-task'` (a **new** entry — do not touch
or reuse the existing `'clickup-task'` entry, see §2). Reminder of the override rule from
`useNodeConfigSchema.ts`: **the local `NODE_CONFIG_REGISTRY` entry, if present for a node type,
completely replaces whatever `configFields` the backend's `GET /nodes` response has for that type**
— the backend fields are only used as a fallback when no local entry exists. So once you add a
`clickup-create-task` entry, it's the only thing that renders; make sure its field `key`s match §2
exactly.

Two of the four fields can reuse existing field types as-is — no new renderer needed:

| config key | recommended local field `type` | why |
|---|---|---|
| `name` | `templatetext` | Existing renderer (`src/components/workflow/config-fields/templates.tsx` → `TemplateTextField`) already validates `{{context.x}}`/`{{customer.x}}` inline as the user types — exactly what a task-name field needs. |
| `markdownContent` | `templatetextarea` | Same as above, multi-line (`TemplateTextareaField`). |
| `workspaceId` | **new type**, e.g. `clickupworkspaceselect` | No existing type fetches options from a live external API — see below. |
| `listId` | **new type**, e.g. `clickuplistselect` | Same, plus depends on the sibling `workspaceId` field's value. |

### Building the two new field types

1. Add `'clickupworkspaceselect'` and `'clickuplistselect'` to the `ConfigFieldType` union in
   `src/config/types.ts`.
2. Add renderer components to `templates.tsx` (or a new file if you'd rather keep ClickUp-specific
   code together — either is fine, `frontend-architecture.md` just asks that files stay under
   ~200 lines). **`TriggerMappingField`/`WorkflowOutputField`** in `templates.tsx` are the closest
   existing precedent to copy: both are "options depend on a sibling field's value, fetched
   on-demand via a `useEffect` keyed off that value, using `apiBaseUrl`/`accessToken` threaded down
   through `NodeConfigPanel` → `FieldRenderer`." Your `ClickUpWorkspaceSelectField` has no
   dependency (fetch once when the field mounts, given `apiBaseUrl`/`accessToken`); your
   `ClickUpListSelectField` mirrors `TriggerMappingField` exactly — read `allValues?.workspaceId`,
   re-fetch via `fetchClickUpLists` whenever it changes (use the same `fetchedRef` dedup pattern so
   you don't refetch on every keystroke of unrelated fields), and render nothing/a prompt until a
   workspace is chosen.
3. Add a `case` for both in `FieldRenderer.tsx`'s dispatch `switch`, following the existing
   `case 'triggermapping': return <TriggerMappingField ... apiBaseUrl={apiBaseUrl} accessToken={accessToken} allValues={allValues} />` line as the template — both new fields need `apiBaseUrl`/`accessToken`, and the list field additionally needs `allValues`.

No changes are needed to `useNodeConfigSchema.ts`, `NodeConfigPanel.tsx`'s prop signature, or
`config/registry.ts` — `apiBaseUrl`/`accessToken`/`allValues` are already threaded all the way down
for exactly this "dependent live-fetched field" case.

### Icon and canvas node — already work, no changes required

- **Icon**: seeded as `CheckSquare`, already present in `iconMap`
  (`src/components/workflow/WorkflowNode.tsx`) — the node renders correctly the moment it's on the
  canvas. A dedicated ClickUp brand icon (matching the custom inline-SVG `Gmail` icon already used
  for `send-email`) is a nice-to-have polish item, not required for function.
- **Canvas component**: `nodeComponentType()` (`src/hooks/useCanvasDragDrop.ts`) has no entry for
  `clickup-create-task`, so it falls through to the generic `workflowNode` component — correct,
  since this node has one input and one output, no branching (unlike `if-node`/`switch`, which
  *do* need special-cased components).

## 5. Recommended UX flow

1. **User drags "ClickUp: Create Task" onto the canvas** and opens its config panel.
2. **Workspace field loads immediately** (no dependency) — show a loading spinner/skeleton while
   `fetchClickUpWorkspaces` resolves.
   - **Empty/error state — no ClickUp connection**: if the call 404s with the "no connection"
     message, don't show a bare error. Show something like *"ClickUp isn't connected for your
     workspace yet — [connect it in Integrations]"* linking to wherever the existing
     `GET /api/v1/integrations` connect flow lives in the app (the Business Owner-driven Connect
     button described in `Modules/Integrations/docs/api.md`'s "Frontend flow" section). A Manager
     configuring this node may not be able to connect it themselves (connect/disconnect is
     `business_owner`-only) — the message should make that ownership clear rather than just
     erroring.
   - If the list of workspaces is empty but the tenant *is* connected (unusual, but possible if
     they have no ClickUp Teams), show "No ClickUp workspaces found."
3. **User picks a workspace.** The List field:
   - Is disabled/shows a placeholder ("Select a workspace first") until `workspaceId` has a value —
     mirror `TriggerMappingField`'s "Select a workflow first to configure inputs" placeholder
     pattern exactly.
   - **Resets `listId` to empty whenever `workspaceId` changes** — a list ID from a previously
     selected workspace is meaningless once the workspace changes, and silently keeping a stale
     `listId` would let the user publish a broken config (the backend doesn't cross-check that
     `listId` actually belongs to `workspaceId` — see §6).
   - Fetches via `fetchClickUpLists` on `workspaceId` change; show a loading state (per §3, this can
     be slow for large workspaces).
   - Renders grouped/labeled by `space`/`folder` so the user isn't picking from a flat list of
     ambiguous list names, e.g. **Engineering / Sprints / Sprint 14** vs. **Engineering / Backlog**
     — an actual `<optgroup>` per space (and per folder within it, or a "— Sprints —" separator
     option) reads far better than a flat list once a workspace has more than a handful of lists.
4. **Task Name** (`templatetext`) and **Content** (`templatetextarea`) — standard template-variable
   fields, consistent with every other text/textarea field elsewhere in the app that accepts
   `{{context.x}}` (e.g. `send-email`'s subject/body). No new interaction pattern needed here.
5. **Save** — standard `NodeConfigPanel` save flow; no special handling needed beyond making sure
   the four keys in §2 are what land in `node.data.config`.

### Validation error mapping

If the backend's `POST /workflows/validate` (via `useWorkflowValidation`) returns issues for this
node, the codes are:

| code | meaning | which field to highlight |
|---|---|---|
| `clickup_create_task.workspace_missing` | `workspaceId` blank | Workspace select |
| `clickup_create_task.list_missing` | `listId` blank | List select |
| `clickup_create_task.name_missing` | `name` blank | Task Name |
| `clickup_create_task.invalid_template_variable` | malformed `{{...}}` in `name`/`markdownContent` | whichever field the issue's `path` points at |
| `clickup_create_task.connection_missing` | tenant has no ClickUp `IntegrationConnection` at publish time | show at the node level (not a specific field) — same "connect ClickUp" messaging as the workspace-load empty state in step 2 |

These flow through `useFieldValidationErrors` the same way every other node type's errors do — no
new plumbing needed, just make sure your field components accept and display an error prop like
the other `config-fields/*` renderers do (see `TemplateTextField`'s inline error rendering for the
pattern).

## 6. Known limitation — not this task's scope, worth knowing

The backend does **not** verify that a given `listId` actually belongs to the given `workspaceId`
— it only checks both are present and that *some* ClickUp connection exists for the tenant (see
`ClickUpCreateTaskNodeTypeRule`). If your list-fetch UX (§5) is implemented as described — list
options only ever populated from the currently-selected workspace, reset on workspace change — a
mismatched pair should never actually occur in the saved config. This is called out so you don't
go looking for a `workspace_list_mismatch`-style backend error code that doesn't exist; getting the
picker's dependent-reset behavior right is what actually prevents this class of bug.

## 7. Verification checklist

1. `npm run build` (type-checks the new `ConfigFieldType` variants and schema entry) and
   `npm run lint`.
2. Manually, against a tenant with ClickUp connected: drag the node, confirm workspaces load,
   selecting one loads its lists grouped by space/folder, selecting a list clears correctly if you
   go back and change the workspace, fill in name/content with a `{{context.x}}` reference, save,
   and confirm `node.data.config` has exactly `{workspaceId, listId, name, markdownContent}`.
3. Against a tenant **without** ClickUp connected: confirm the workspace field shows the
   "connect ClickUp" prompt rather than a raw 404/error.
4. Trigger a real (or sandbox) workflow run through this node and confirm the task appears in
   ClickUp with the rendered name/content — the backend side of this is already covered by
   `tests/Unit/Execution/ClickUpCreateTaskExecutorTest.php` and
   `tests/Feature/ClickUpApiTest.php`, but an end-to-end run is the only way to catch a frontend/
   backend field-key mismatch (the exact bug this doc's §2 warns about).
