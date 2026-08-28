# Dynamic Flow & Sub-Workflow — Frontend Implementation Guide

## Table of Contents

1. [Concepts Overview](#1-concepts-overview)
2. [Node Types](#2-node-types)
3. [Execution Lifecycle](#3-execution-lifecycle)
4. [Context Propagation](#4-context-propagation)
5. [API Endpoints](#5-api-endpoints)
6. [WebSocket / Real-Time Events](#6-websocket--real-time-events)
7. [Frontend Implementation Requirements](#7-frontend-implementation-requirements)
8. [Data Structures](#8-data-structures)
9. [Verification & Validation](#9-verification--validation)
10. [Edge Cases & Error Handling](#10-edge-cases--error-handling)
11. [Full Implementation Checklist](#11-full-implementation-checklist)

---

## 1. Concepts Overview

This system has **two** flow-nesting mechanisms. They share the same parent-child instance model but differ in when and how the child graph is determined.

### Sub-Workflow (Static)

A **sub-workflow** node references a **published workflow by ID**. The child graph is fixed at design time — the parent workflow author selects an existing workflow and optionally maps inputs/outputs. At runtime the system spawns a child instance from that workflow's published version, parks the parent, and resumes when the child completes.

**Key characteristics:**
- Child graph is known at parent design time
- Configured via `workflowId` on the node
- Child runs from a published version
- Input mapping resolves templates against parent context before dispatch
- Output variable receives the child's full context object on completion

### Dynamic Flow (Runtime)

A **dynamic-flow** node pauses the parent and asks a user (the workflow creator or a team manager) to **design a sub-graph at runtime**. The child graph does not exist when the parent workflow is published. It is authored in a separate modal, validated, and then dispatched as a child instance.

**Key characteristics:**
- Child graph is unknown until execution time
- Parent instance is **paused** until the sub-flow is designed and submitted
- The child instance's trigger is always `dynamic-entry`, which merges parent context into child context
- The child runs from a user-defined definition stored as JSON (no published version)
- Output variable receives the child's full context object on completion

### Comparison

| Aspect | Sub-Workflow | Dynamic Flow |
|--------|-------------|--------------|
| Child graph known at design time | Yes | No |
| Requires user intervention at runtime | No | Yes |
| Parent pauses until child ready | N/A | Yes |
| Use case | Reusable, pre-built logic | Custom steps for a specific case |

---

## 2. Node Types

### 2.1 `sub-workflow`

**Category:** `flows` | **Icon:** `Workflow` | **Color:** `teal`

| Config Key | Type | Required | Description |
|-----------|------|----------|-------------|
| `workflowId` | `string` | **Yes** | ID of the published workflow to execute as a child |
| `inputMapping` | `json` | No | Key-value map of values to pass into child. Values support `{{context.key}}` / `{{trigger.key}}` template syntax. Nested objects allowed. |
| `outputVariable` | `text` | No | Context key under which the child's full context will be stored after completion. E.g. `subResult` makes child output available as `{{context.subResult}}` downstream. |

### 2.2 `dynamic-flow`

**Category:** `flows` | **Icon:** `Shuffle` | **Color:** `teal`

| Config Key | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `message` | `textarea` | No | `"Manager must design a custom sub-flow"` | Instructional text shown to the user who will design the sub-flow |
| `aiSuggestion` | `toggle` | No | `true` | Whether the AI proposal feature should be offered for this node |
| `outputVariable` | `text` | No | — | Context key under which the child's full context will be stored after completion |

### 2.3 `dynamic-entry`

**Category:** `trigger` | **Icon:** `LogIn` | **Color:** `emerald`

| Config Key | Type | Required | Description |
|-----------|------|----------|-------------|
| — | — | — | No configurable fields |

This is the **trigger node** for dynamically-designed sub-flows. It is always the first node in a dynamic sub-graph. It receives the parent's context as its initial payload and makes it available as `context.*` variables for all downstream nodes.

**Canvas behavior:** When the dynamic flow design modal opens, the canvas should be pre-populated with a single `dynamic-entry` node. The user connects their first action node to this entry point.

---

## 3. Execution Lifecycle

### 3.1 Parent Reaches a `dynamic-flow` Node

1. Parent instance is `running`
2. The `dynamic-flow` node executes
3. Parent instance transitions to `paused` with `paused_reason = "dynamic_flow:awaiting_design"`
4. An `instance.paused` WebSocket event fires on the parent channel with `reason: "dynamic_flow"`
5. The system waits for a user to design and submit the sub-flow

### 3.2 User Designs the Sub-Flow

1. Frontend detects the paused state via WebSocket or by loading the instance
2. `GET /instances/{id}/dynamic-flow` fetches parent definition, parent context, and dynamic flow metadata
3. A full-screen modal opens with a canvas pre-populated with a `dynamic-entry` node
4. User builds the sub-graph using nodes from the library
5. User can click **Verify** to validate the definition
6. User clicks **Submit & Resume** which calls `POST /instances/{id}/dynamic-flow/definition`

### 3.3 After Submission

1. The definition is validated (Segment mode — no trigger required)
2. The backend sets `definition.trigger` to `{ type: "dynamic-entry" }` (matching the segment's always-present entry node) and injects the parent context keys as `trigger.config.variables` for verification. This trigger is also what lets the dispatcher find the entry node when it spawns the child instance.
3. If valid: the dynamic flow row moves to `executing`, the parent instance resumes to `running`, and a child instance is dispatched. WebSocket events flow automatically.
4. If invalid: 422 with error messages, dynamic flow stays in `awaiting_design`, user can fix and resubmit

### 3.4 Child Executes

1. The `dynamic-entry` trigger merges parent context into child context
2. Child nodes execute sequentially/branching as designed
3. Child reaches termination or all branches complete
4. Child instance status becomes `completed`

### 3.5 Parent Resumes

1. Parent execution re-enters the `dynamic-flow` node
2. The child instance is terminal — output variable is set on parent context (if configured)
3. Parent continues to the next node(s)

### 3.6 Child Failure

If the child instance fails:
1. The `dynamic-flow` status becomes `cancelled`
2. Parent instance also fails with the child's error message
3. Frontend receives `child.instance.failed` and `instance.failed` events

---

## 4. Context Propagation

### 4.1 The Resolution Scope

All template interpolation (`{{context.key}}`) and expression evaluation (`context.key == "value"`) resolves against three namespaces:

| Namespace | Source | Description |
|-----------|--------|-------------|
| `context` | Instance context | Mutable variable store. Nodes write outputs here. |
| `trigger` | Instance payload | Original trigger payload (form fields, webhook body, parent context for children). |
| `input` | Execution input | The previous node's output passed as input to this execution. |

### 4.2 How Parent Context Reaches the Child

1. When a `dynamic-flow` node is submitted, the parent's full context is passed as the child instance's payload
2. The `dynamic-entry` trigger merges this payload into the child's context
3. Subsequent child nodes can reference parent variables via `{{context.key}}`

**Example:** If parent context is `{ hrEmail: "hr@company.com", amount: 500 }`, the child context will contain the same keys after the `dynamic-entry` trigger runs.

### 4.3 Output Variable

Both `sub-workflow` and `dynamic-flow` support an `outputVariable` config key. When set, the child's full context object is stored under that key in the parent's context after the child completes.

**Example:** If `outputVariable = "subResult"` and the child's context is `{ approvalStatus: "approved", reviewer: "Bob" }`, the parent's context will contain:
```
context.subResult.approvalStatus  =>  "approved"
context.subResult.reviewer        =>  "Bob"
```

### 4.4 Verification of Context Variables

When a user designs a dynamic sub-flow and uses `{{context.hrEmail}}` in a node config (e.g., send-email body), the validator needs to know that `hrEmail` will exist at runtime.

**On Submit:** The backend automatically injects parent context keys into the definition before validation.

**On Verify (frontend):** The `useWorkflowValidation` hook accepts an `extraTriggerVariables` parameter. Pass parent context keys:
```
extraTriggerVariables: Object.keys(designData?.parent_context ?? {})
```
The hook merges these into `trigger.config.variables` before sending to `POST /workflows/validate`.

---

## 5. API Endpoints

All endpoints require `Authorization: Bearer <JWT>` header and `active.user` middleware.

### 5.1 Fetch Dynamic Flow Design Data

```
GET /api/v1/workflows/instances/{instanceId}/dynamic-flow
```

**Authorization:** Only the workflow creator or a team manager can design a dynamic flow.

**Response 200:**
```json
{
  "dynamic_flow": {
    "id": 42,
    "status": "awaiting_design",
    "node_key": "dynamic-flow-1",
    "instance_id": 100
  },
  "parent_definition": {
    "trigger": { "type": "manual-trigger", "config": {} },
    "nodes": [ "..." ],
    "edges": [ "..." ],
    "variables": [],
    "settings": {}
  },
  "parent_context": {
    "hrEmail": "hr@company.com",
    "amount": 500,
    "employeeName": "Alice"
  }
}
```

**Use this to:**
- Know which `instanceId` the dynamic flow belongs to
- Display parent context variables to the designer
- Show the parent workflow graph as reference (optional)
- Check that `status` is still `awaiting_design`

### 5.2 Submit Dynamic Flow Definition

```
POST /api/v1/workflows/instances/{instanceId}/dynamic-flow/definition
```

**Request body:**
```json
{
  "definition": {
    "nodes": [
      { "id": "dynamic-entry-1", "type": "dynamic-entry", "label": "Entry Point", "config": {}, "position": { "x": 200, "y": 100 } },
      { "id": "send-email-1", "type": "send-email", "label": "Notify HR", "config": { "to": "{{context.hrEmail}}", "subject": "Approval needed" }, "position": { "x": 200, "y": 250 } }
    ],
    "edges": [
      { "id": "edge-1", "source_node_key": "dynamic-entry-1", "target_node_key": "send-email-1" }
    ],
    "variables": [],
    "settings": {}
  }
}
```

**Response 201:**
```json
{
  "dynamic_flow": {
    "id": 42,
    "status": "executing",
    "child_instance_id": 101
  }
}
```

**Response 422 (validation errors):**
```json
{
  "errors": [
    "Variable 'context.hrEmail' is not defined in the workflow context."
  ]
}
```

### 5.3 Fetch Instance Detail (with Dynamic Flow data)

```
GET /api/v1/workflows/instances/{instanceId}
```

**Response (relevant fields):**
```json
{
  "id": "100",
  "status": "running",
  "paused_reason": null,
  "context": { "hrEmail": "...", "subResult": { "approvalStatus": "approved" } },
  "node_executions": [ "..." ],
  "dynamic_flows": [
    {
      "id": 42,
      "node_key": "dynamic-flow-1",
      "status": "completed",
      "child_instance_id": 101,
      "child_instance": {
        "id": 101,
        "status": "completed",
        "error": null,
        "node_executions": [
          { "id": 500, "node_key": "dynamic-entry-1", "node_type": "dynamic-entry", "status": "succeeded", "attempt": 1, "input": { "hrEmail": "..." }, "output": { "triggered_by": "dynamic_entry" }, "error": null, "started_at": "...", "finished_at": "..." },
          { "id": 501, "node_key": "send-email-1", "node_type": "send-email", "status": "succeeded", "attempt": 1, "input": { "..." }, "output": { "..." }, "error": null, "started_at": "...", "finished_at": "..." }
        ]
      }
    }
  ]
}
```

### 5.4 Validate a Workflow Definition

```
POST /api/v1/workflows/validate
```

Used by the Verify button inside the dynamic flow design modal. Send the same definition structure. The response includes `is_publishable`, `issues[]`, `errors[]`, `warnings[]`.

**Important:** For dynamic flow sub-graphs, the definition must include `trigger.config.variables` listing parent context keys, otherwise `{{context.*}}` references will fail validation.

### 5.5 Cancel Instance

```
POST /api/v1/workflows/instances/{instanceId}/cancel
```

Cancels a running or paused instance. If the instance is paused waiting for a dynamic flow design, this cancels it.

---

## 6. WebSocket / Real-Time Events

### 6.1 Connection

The backend uses **Laravel Reverb** (Pusher-compatible WebSocket server). The frontend connects via `laravel-echo` with the Pusher connector.

- **Auth endpoint:** `POST /api/broadcasting/auth` with `Authorization: Bearer <JWT>` header
- **Auth endpoint URL derivation:** Strip `/api/v1` from the API base URL, append `/api/broadcasting/auth`
- **Reverb config:** `key`, `cluster`, `forceTLS: true` (values from env/reverb config)

### 6.2 Channel Structure

| Channel Pattern | Auth Check | Purpose |
|----------------|------------|---------|
| `private-App.Models.User.{userId}` | User ID matches JWT | Per-user notifications |
| `private-workflow-instance.{instanceId}` | Instance exists AND user's tenant matches instance's tenant | Per-instance execution events |

**Subscribe to the parent instance channel** to receive both parent and child events.

### 6.3 All Events

#### Instance-Level Events

| Event Name | Payload | When |
|-----------|---------|------|
| `instance.started` | `{ instance_id, status, started_at }` | Instance begins execution |
| `instance.completed` | `{ instance_id, status, context, finished_at }` | Instance finishes successfully |
| `instance.failed` | `{ instance_id, status, error, context, finished_at }` | Instance fails |
| `instance.paused` | `{ instance_id, status, reason }` | Instance pauses (dynamic flow awaiting design) |
| `instance.cancelled` | `{ instance_id, status, finished_at }` | Instance is cancelled |

#### Node-Level Events

| Event Name | Payload | When |
|-----------|---------|------|
| `node.started` | `{ instance_id, node_key, node_type, status, attempt, input, started_at }` | Node begins executing |
| `node.completed` | `{ instance_id, node_key, node_type, status, attempt, input, output, started_at, finished_at }` | Node succeeds |
| `node.failed` | `{ instance_id, node_key, node_type, status, attempt, input, error, will_retry, started_at, finished_at }` | Node fails |
| `node.waiting` | `{ instance_id, node_key, node_type, status, attempt, input, wait_type, wait_until, started_at }` | Node enters waiting state |
| `node.retrying` | `{ instance_id, node_key, node_type, status, attempt, failed_attempt }` | Node is retried after failure |

#### Child Instance Events (Forwarded to Parent Channel)

Every event from a child instance is re-broadcast on the **parent instance's channel** with the `child.` prefix and an additional `child_instance_id` field:

| Event Name | Payload | When |
|-----------|---------|------|
| `child.instance.started` | `{ ..., child_instance_id }` | Child instance begins |
| `child.instance.completed` | `{ ..., child_instance_id }` | Child instance finishes |
| `child.instance.failed` | `{ ..., child_instance_id }` | Child instance fails |
| `child.node.started` | `{ ..., child_instance_id }` | Child node begins |
| `child.node.completed` | `{ ..., child_instance_id }` | Child node finishes |
| `child.node.failed` | `{ ..., child_instance_id }` | Child node fails |
| `child.node.waiting` | `{ ..., child_instance_id }` | Child node waits |
| `child.node.retrying` | `{ ..., child_instance_id }` | Child node retries |

**The `child_instance_id` is the key discriminator.** It tells the frontend which child instance this event belongs to, enabling nested timeline rendering.

### 6.4 Key Behavior

- The frontend subscribes only to the **parent instance channel**
- Both `node.*` events (parent) and `child.node.*` events (child) arrive on the same channel
- The `child.node.*` events carry the same payload shape as `node.*` events, plus `child_instance_id`
- `child.instance.completed` and `child.instance.failed` carry the instance-level payload plus `child_instance_id`

---

## 7. Frontend Implementation Requirements

### 7.1 Detecting "Needs Design" State

When viewing a workflow instance execution, detect whether a dynamic flow needs designing:

- `instance.status === 'paused'` AND `instance.paused_reason === 'dynamic_flow:awaiting_design'`

Alternatively, listen for the WebSocket `instance.paused` event and check `payload.reason === 'dynamic_flow'`.

### 7.2 "Design Sub-Flow" Button

When the instance is paused for design and the current user is authorized (workflow creator or team manager), display a prominent button in the execution panel that opens the dynamic flow design modal.

### 7.3 Dynamic Flow Design Modal

A full-screen modal containing:

1. **Header** — shows node key, Verify button, Submit & Resume button
2. **Node Library** (left panel) — available node types from `GET /workflows/nodes`
3. **Canvas** (center) — React Flow canvas pre-populated with a `dynamic-entry` node
4. **Config Panel** (right) — when no node selected, show:
   - "Available Context Variables" listing all `parent_context` keys with `{{context.key}}` syntax and copy-to-clipboard
   - When a node is selected, show that node's config fields
5. **Validation Dialog** — shows verification results

#### Modal Data Loading

When the modal opens, call `GET /instances/{id}/dynamic-flow` to get `parent_context`, `parent_definition`, and `dynamic_flow` metadata. Verify `dynamic_flow.status` is `awaiting_design`.

#### Canvas Pre-Population

Always start with a single `dynamic-entry` node (`id: 'dynamic-entry-1'`, position `{ x: 200, y: 100 }`, inputs: 0, outputs: 1). The user connects their first action node(s) to this entry point.

#### Verification with Parent Context

Pass parent context keys as `extraTriggerVariables` to the `useWorkflowValidation` hook:
```
extraTriggerVariables: Object.keys(designData?.parent_context ?? {})
```
The hook injects these into the trigger config before sending to `POST /workflows/validate`.

#### Submitting the Definition

Build the definition from the canvas snapshot using `buildDefinitionFromCanvas()`, then call `POST /instances/{id}/dynamic-flow/definition` with the definition. On success, close the modal and refresh the parent execution. WebSocket events will flow automatically.

### 7.4 Rendering Child Steps in the Execution Timeline

Child execution steps must be rendered **nested under** the parent's `dynamic-flow` node step. The `childInstanceId` field on `ExecutionStep` is the link.

#### Grouping Steps

1. Separate parent steps from child steps: any step with `childInstanceId` and `nodeType !== 'dynamic-flow'` is a child step
2. Group child steps by their `childInstanceId`
3. Filter parent steps to those without `childInstanceId` OR with `nodeType === 'dynamic-flow'`
4. Render parent steps; for each parent step with a `childInstanceId`, render the grouped child steps underneath using a `ChildInstanceTimeline` component

#### Building Steps from API Response

When loading an instance via `GET /instances/{id}`:

1. Build parent steps from `node_executions` (skip `consumed` status rows)
2. For each entry in `dynamic_flows`:
   - Find the parent step matching `df.node_key` with `nodeType === 'dynamic-flow'` and set `childInstanceId = df.child_instance_id`
   - Add child node executions from `df.child_instance.node_executions` as steps with `childInstanceId` set
3. Sort all steps by timestamp

### 7.5 Handling WebSocket Events for Child Instances

When a `child.node.*` event arrives:

1. Build a step from the payload
2. Set `step.childInstanceId = payload.child_instance_id`
3. Set `step.id = 'child-${child_instance_id}-${node_key}-${attempt}'` to avoid collisions with parent steps
4. If the parent `dynamic-flow` step doesn't yet have `childInstanceId`, attach it
5. Insert/replace the step in the execution state, sorted by timestamp

When a `child.instance.completed` or `child.instance.failed` event arrives:

1. Find the parent `dynamic-flow` step matching `childInstanceId`
2. Update its status to `'success'` or `'failed'`

### 7.6 Preserving `childInstanceId` Across Node Events

When a parent `node.*` event arrives for the `dynamic-flow` node, the step is replaced. **Always preserve the `childInstanceId`** from the existing step before replacing. This prevents the nested execution from disappearing when the parent node's step is updated.

---

## 8. Data Structures

### 8.1 TypeScript Types

```ts
interface DynamicFlowSummary {
  id: number;
  status: string;             // "awaiting_design" | "executing" | "completed" | "cancelled"
  node_key: string;
  instance_id: number;
  child_instance_id: number | null;
  definition: WorkflowDefinition | null;
  created_at: string;
}

interface DynamicFlowDesignResponse {
  dynamic_flow: DynamicFlowSummary;
  parent_definition: WorkflowDefinition | null;
  parent_context: Record<string, unknown>;
}

interface DynamicFlowDetail {
  id: number;
  node_key: string;
  status: string;
  child_instance_id: number | null;
  child_instance: {
    id: number;
    status: string;
    error: { message?: string } | null;
    node_executions: NodeExecutionSummary[];
  } | null;
}

interface NodeExecutionSummary {
  id: number;
  node_key: string;
  node_type: string;
  status: string;             // "pending" | "running" | "succeeded" | "failed" | "waiting" | "consumed"
  attempt: number;
  input: Record<string, unknown> | null;
  output: Record<string, unknown> | null;
  error: { message?: string; exception?: string; file?: string } | null;
  started_at: string | null;
  finished_at: string | null;
}

interface InstanceDetail {
  id: string;
  status: string;
  paused_reason: string | null;
  error: { message?: string } | null;
  context: Record<string, unknown> | null;
  started_at: string | null;
  finished_at: string | null;
  node_executions: NodeExecutionSummary[];
  dynamic_flows: DynamicFlowDetail[];
}
```

### 8.2 ExecutionStep

```ts
interface ExecutionStep {
  id: string;                    // "nodeKey-attempt" or "child-{childId}-nodeKey-attempt"
  nodeId: string;
  nodeLabel: string;
  nodeType: string;
  status: ExecutionStatus;       // "idle" | "running" | "success" | "failed" | "waiting"
  timestamp: string;
  duration?: number;
  variables: Record<string, unknown>;
  message?: string;
  error?: string;
  childInstanceId?: number;      // Links dynamic-flow step to its child instance
}
```

### 8.3 WorkflowDefinition

```ts
interface WorkflowDefinition {
  trigger: {
    type: string;                 // "dynamic-entry" for sub-flows
    config: Record<string, unknown>;
  };
  nodes: Array<{
    id: string;                   // e.g. "dynamic-entry-1", "send-email-1"
    type: string;                 // e.g. "dynamic-entry", "send-email"
    label?: string;
    name?: string;
    config: Record<string, unknown>;
    position?: { x: number; y: number };
  }>;
  edges: WorkflowEdge[];
  variables: unknown[];
  settings: Record<string, unknown>;
}
```

---

## 9. Verification & Validation

### 9.1 Verification Mode: Segment vs Full

| Mode | When Used | Trigger Required | Graph Completeness |
|------|-----------|-----------------|-------------------|
| `Full` | `POST /workflows/validate` (Verify button) | Yes | Full graph validation |
| `Segment` | `POST /instances/{id}/dynamic-flow/definition` (Submit) | No | Partial graph (sub-graph only) |

Dynamic flow sub-graphs use `Segment` mode because they have no trigger node of their own — the `dynamic-entry` serves as the entry point.

### 9.2 What Gets Validated

For both modes, the verification pipeline runs these checks in order:

1. **Syntax** — structure and data-type validation (trigger check skipped in Segment mode)
2. **Graph control flow** — reachability, dead ends, cycles
3. **Expressions** — boolean expression parsing in conditions
4. **Contextual** — business rule checks
5. **Node-type rules** — per-node-type validation (send-email template variables, if-node conditions, etc.)
6. **Data flow** — variable read/write analysis

### 9.3 Context Variable Verification

The verifier checks `trigger.config.variables` to know which `context.*` keys are available. For dynamic flows:

- The `dynamic-entry` node has `category: 'trigger'`, so it is recognized as a trigger
- **On Submit:** the backend sets `trigger.type` to `dynamic-entry` and injects the parent context keys as `trigger.config.variables`
- Without that injection, `trigger.config.variables` is empty, causing `{{context.*}}` references to fail
- **On Verify (frontend):** Pass `extraTriggerVariables` to the validation hook so it injects parent context keys before sending

---

## 10. Edge Cases & Error Handling

### 10.1 Child Instance Fails

- Parent's `dynamic-flow` step status becomes `failed`
- Parent instance status becomes `failed`
- Frontend receives `child.instance.failed` event, updates the parent step
- Frontend also receives `instance.failed` event for the parent

### 10.2 User Submits Invalid Definition

- Backend returns 422 with validation errors
- Frontend should display errors in the validation dialog
- The dynamic flow remains in `awaiting_design` state
- User can fix and resubmit

### 10.3 User Closes Modal Without Submitting

- Dynamic flow stays in `awaiting_design` state
- Parent instance remains paused
- User can reopen the modal later via the "Design Sub-Flow" button

### 10.4 Multiple Dynamic-Flow Nodes in One Workflow

Each `dynamic-flow` node creates its own record. Each has its own child instance. The `node_key` field distinguishes them. Multiple dynamic flows can be active on one parent instance simultaneously.

### 10.5 Page Refresh / Reconnection

When the user refreshes the page or reconnects:
1. Load the instance via `GET /instances/{id}`
2. Build steps from `node_executions` and `dynamic_flows` (see 7.4)
3. If instance is still running, connect to WebSocket for live updates
4. If instance is paused for design, show the "Design Sub-Flow" button

### 10.6 Authorization

Only the workflow creator or a team manager can design a dynamic flow. The frontend should check the user's role before showing the design button, or handle the 403 gracefully.

### 10.7 Concurrent Access

If two users try to design the same dynamic flow simultaneously, the first to submit wins. The second will get a 422 because the record is already in `executing` or `completed` status.

---

## 11. Full Implementation Checklist

### API Client Layer

- [ ] `fetchDynamicFlow(baseUrl, token, instanceId)` — `GET /instances/{id}/dynamic-flow`
- [ ] `submitDynamicFlowDefinition(baseUrl, token, instanceId, definition)` — `POST /instances/{id}/dynamic-flow/definition`
- [ ] `fetchInstance(baseUrl, token, instanceId)` — `GET /instances/{id}` (returns `dynamic_flows[]` with nested child data)

### Types

- [ ] `DynamicFlowSummary`
- [ ] `DynamicFlowDesignResponse`
- [ ] `DynamicFlowDetail`
- [ ] `NodeExecutionSummary`
- [ ] `InstanceDetail` (with `dynamic_flows` field)
- [ ] `ExecutionStep.childInstanceId`

### Dynamic Flow Design Modal

- [ ] Full-screen dialog with canvas, node library, config panel
- [ ] Pre-populates canvas with `dynamic-entry` node
- [ ] Shows "Available Context Variables" panel with copy-to-clipboard
- [ ] Verify button sends definition with `extraTriggerVariables`
- [ ] Submit button calls `submitDynamicFlowDefinition`
- [ ] Handles validation errors from backend
- [ ] Closes on success and triggers parent refresh

### Execution Panel

- [ ] Detects `paused_reason === 'dynamic_flow:awaiting_design'`
- [ ] Shows "Design Sub-Flow" button when applicable
- [ ] Groups child steps under parent `dynamic-flow` step
- [ ] Renders `ChildInstanceTimeline` for nested executions

### WebSocket Handler

- [ ] Subscribes to parent instance channel
- [ ] Handles `instance.paused` with `reason: "dynamic_flow"` to show design button
- [ ] Handles `child.node.*` events with `child_instance_id` discriminator
- [ ] Handles `child.instance.completed` / `child.instance.failed` to update parent step status
- [ ] Preserves `childInstanceId` when replacing parent `dynamic-flow` step on `node.*` events

### State Management

- [ ] `loadInstance()` builds steps from both `node_executions` and `dynamic_flows`
- [ ] `runtimeContext` updated from `instance.completed` / `instance.failed` events
- [ ] `nodeStatuses` map includes child node statuses under `child:{childId}:{nodeKey}` keys
