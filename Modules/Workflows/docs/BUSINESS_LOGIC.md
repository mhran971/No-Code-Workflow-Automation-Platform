# Workflow Business Logic

This document covers the full lifecycle of a workflow — from creation through publishing — and provides a complete reference for the verification pipeline that guards every save and publish action.

---

## Table of Contents

1. [Roles and Access](#roles-and-access)
2. [User Flow](#user-flow)
   - [Creating a Workflow](#1-creating-a-workflow)
   - [Editing the Draft](#2-editing-the-draft)
   - [Validating Without Saving](#3-validating-without-saving)
   - [Saving a Draft](#4-saving-a-draft)
   - [Publishing](#5-publishing)
   - [Activating and Disabling](#6-activating-and-disabling)
   - [Versioning](#7-versioning)
   - [Deletion and Purge](#8-deletion-and-purge)
3. [Workflow Definition Structure](#workflow-definition-structure)
4. [Verification Pipeline](#verification-pipeline)
   - [Pipeline Overview](#pipeline-overview)
   - [Normalization (Pre-pass)](#normalization-pre-pass)
   - [Stage 1 — Syntax Verification](#stage-1--syntax-verification)
   - [Stage 2 — Graph Control Flow Verification](#stage-2--graph-control-flow-verification)
   - [Stage 3 — Structured Control Flow Verification](#stage-3--structured-control-flow-verification)
   - [Stage 4 — Expression Verification](#stage-4--expression-verification)
   - [Stage 5 — Contextual Verification](#stage-5--contextual-verification)
   - [Stage 6 — Node-Type Verification](#stage-6--node-type-verification)
   - [Stage 7 — Data Flow Verification](#stage-7--data-flow-verification)
   - [Stage 8 — Form Trigger Verification](#stage-8--form-trigger-verification)
5. [Publishability Rules](#publishability-rules)
6. [Node-Type Rules Reference](#node-type-rules-reference)

---

## Roles and Access

| Role | Can Create | Can Edit/Publish | Can View | Scope |
|---|---|---|---|---|
| `BusinessOwner` | Yes (must supply `team_id`) | Yes — any workflow in their tenant | Yes — all tenant workflows | Tenant-wide |
| `Manager` | Yes (team is auto-resolved from managed team) | Yes — workflows belonging to their team | Yes — their team's workflows | Team-scoped |
| `Employee` | No | No | Yes — only if a `WorkflowAccessGrant` exists for them | Per-grant |

Employees are automatically granted `view` access when a workflow is created; the system iterates over all active employees in the team and creates grants.

---

## User Flow

### 1. Creating a Workflow

**Endpoint:** `POST /api/v1/workflows`

Three creation methods are supported:

| Method | `method` value | What happens |
|---|---|---|
| Blank canvas | `blank` | A workflow with an empty definition (`{trigger: null, nodes: [], edges: [], settings: {}}`) is created in `Disabled` status. |
| From template | `template` | The template's frozen definition is copied into `draft_definition`. The template's `usage_count` is incremented. |
| AI-confirmed | `ai_confirmed` | A definition proposed by the AI endpoint (`POST /api/v1/workflows/proposals/ai`) and confirmed by the user is used directly. |

The workflow is created with:
- `status = Disabled`
- `draft_revision = 1`
- `current_version_number = 0` (no published version yet)
- `draft_definition` = the initial definition

Employee view-access grants are written in the same database transaction.

---

### 2. Editing the Draft

**Endpoint:** `PATCH /api/v1/workflows/{workflow}/draft`

The canvas sends the full updated definition together with an `expected_draft_revision` integer. The backend:

1. Confirms the actor can manage the workflow (Manager of owning team or BusinessOwner).
2. Confirms the workflow is not deleted.
3. Asserts `workflow.draft_revision === expected_draft_revision`. If they differ, a **409 Conflict** is returned — another client has saved in the meantime and the frontend must refresh before retrying.
4. Runs the full verification pipeline against the submitted definition.
5. If `validate_only: true` is in the payload, returns the validation result **without saving**.
6. Otherwise, saves `draft_definition` and increments `draft_revision`.

The response always includes the full `validation` object so the UI can display live feedback.

---

### 3. Validating Without Saving

**Endpoint:** `POST /api/v1/workflows/validate`

Runs the verification pipeline on a definition that is not yet attached to any saved workflow. Useful for pre-flight checks from the canvas. The `workflow` and `actor` context arguments are `null`, so contextual rules (assignee/KB-doc existence) are skipped.

---

### 4. Saving a Draft

Saving happens inside the `updateDraft` operation described above (step 6 when `validate_only` is false). The stored `draft_definition` is the raw definition submitted by the client, not the normalized form. Normalization is an in-memory pre-processing step only.

---

### 5. Publishing

**Endpoint:** `POST /api/v1/workflows/{workflow}/publish`

Publishing snapshots the current `draft_definition` into an immutable `WorkflowVersion` record:

1. Actor authorization and draft-revision optimistic-lock check (same as editing).
2. Full verification runs against `workflow.draft_definition`. If `is_publishable` is false, a **422 Validation Error** is thrown and publishing is blocked.
3. Inside a database transaction:
   - `version_number` is computed as `max(existing version_numbers) + 1`.
   - `version_label` defaults to semantic versioning (`v1.0.0`, then `v1.0.1`, `v1.0.2`, …) unless the caller supplies a custom label.
   - Duplicate version labels within the same workflow are rejected.
   - A `WorkflowVersion` row is created with the frozen definition, release note, publisher, and timestamp.
   - `workflow.current_version_id`, `current_version_number`, and `current_version_label` are updated.

Publishing does **not** activate the workflow. A separate status transition is required.

---

### 6. Activating and Disabling

**Endpoint:** `PATCH /api/v1/workflows/{workflow}/status`

| Transition | Precondition |
|---|---|
| `Disabled` → `Active` | `current_version_id` must not be null (workflow must have been published at least once). |
| `Active` → `Disabled` | No precondition. |

Only `Active` workflows can be triggered via webhook. A `Disabled` or `Deleted` workflow returns **410 Gone** on trigger attempts.

---

### 7. Versioning

Every publish creates a new immutable `WorkflowVersion`. The version history is available at `GET /api/v1/workflows/{workflow}/versions`, ordered newest-first.

Version labels follow semantic versioning automatically: `v1.0.0 → v1.0.1 → v1.0.2`, etc. Custom labels can be supplied at publish time but must be unique per workflow.

The `draft_definition` is always the **mutable working copy**. The latest `WorkflowVersion.definition` is the last published snapshot. These can diverge freely while the user edits.

---

### 8. Deletion and Purge

| Operation | Who | What |
|---|---|---|
| Soft delete (`DELETE /{workflow}`) | Manager or BusinessOwner | Sets `status = Deleted`, records `deleted_at`. Workflow is hidden from standard listings. |
| Purge (`DELETE /{workflow}/purge`) | BusinessOwner only | Hard-deletes the workflow and all its versions, instances, and access grants. Requires `status = Deleted` and `active_instances = 0`. |

---

## Workflow Definition Structure

The definition is a JSON object stored in `draft_definition` and frozen in `WorkflowVersion.definition`:

```json
{
  "trigger": {
    "type": "manual-trigger",
    "config": {
      "variables": [
        { "key": "customerEmail", "label": "Customer Email" }
      ]
    }
  },
  "nodes": [
    {
      "id": "node-1",
      "type": "send-email",
      "label": "Welcome Email",
      "config": {
        "to": "{{context.customerEmail}}",
        "subject": "Welcome!",
        "body": "Hello {{context.customerName}}"
      }
    },
    {
      "id": "node-end",
      "type": "termination-node",
      "config": {}
    }
  ],
  "edges": [
    {
      "id": "e1",
      "source_node_key": "node-1",
      "target_node_key": "node-end",
      "branch_type": "default"
    }
  ],
  "variables": [],
  "settings": {}
}
```

**Edge branch types:**
| `branch_type` | Meaning |
|---|---|
| `default` | Unconditional; always followed. |
| `conditional` | Followed only when `condition_expression` evaluates to true. |
| `parallel` | One of several branches that execute concurrently (from a fork). |

**Variable namespaces used in expressions and templates:**

| Prefix | Source |
|---|---|
| `context.*` | Variables produced by the trigger or intermediate nodes (`outputVariable`, `outputVariables`). |
| `customer.*` | Tenant customer record fields (read-only references). |

// TODO: display workflow nodes variables
---

## Verification Pipeline

### Pipeline Overview

`WorkflowVerificationService::verify()` runs 8 stages in sequence. Each stage receives the normalized definition, a graph object, and an accumulating result. Stages do not short-circuit on failure — all stages always run, so the UI receives the full set of errors in one pass.

```
Raw definition
      │
      ▼
 Normalization (WorkflowDefinitionNormalizer)
      │
      ▼
 WorkflowDefinitionGraph  ─────────────────────────────────────┐
      │                                                          │
      ▼                                                          │
 Stage 1: SyntaxVerificationRule                                 │
 Stage 2: GraphControlFlowVerificationRule                       │ (graph shared by all stages)
 Stage 3: StructuredControlFlowVerificationRule                  │
 Stage 4: ExpressionVerificationRule                             │
 Stage 5: ContextualVerificationRule                             │
 Stage 6: NodeTypeVerificationRule (dispatches to sub-rules)     │
 Stage 7: DataFlowVerificationRule                               │
 Stage 8: FormTriggerVerificationRule                            │
      │                                                          │
      ▼                                                       ───┘
 WorkflowVerificationResult
   .errors[]   → prevents publishing
   .warnings[] → informational only
   .is_publishable
```

`is_publishable` is `true` only when `errors` is empty.

---

### Normalization (Pre-pass)

**Class:** `WorkflowDefinitionNormalizer`

Before any rule runs, the raw definition is normalized into a canonical form. This insulates rules from field aliases and loose input shapes.

Key normalizations:

- **Nodes:** `id` falls back to `key` (legacy alias). Non-array node entries are marked `_invalid: true` and flagged by Syntax, not silently dropped.
- **Edges:** `source_node_key` falls back to `source` / `from`; `target_node_key` falls back to `target` / `to`. `condition_expression` falls back to `condition`. A synthetic `edge-N` id is generated when `id` is absent.
- **Trigger:** `config` defaults to `[]` if absent or non-array.
- The original input is preserved under `_raw` at the top level and on each node/edge, so Syntax rules can still report against the user's submitted field names.

---

### Stage 1 — Syntax Verification

**Class:** `SyntaxVerificationRule`  
**Responsibility:** Shape and type correctness of the raw definition.

**Checks performed:**

| Check | Error code | Triggered when |
|---|---|---|
| Top-level `trigger`, `nodes`, `edges` keys exist | `definition.*_missing` | Key is absent from the raw input |
| `trigger`, `nodes`, `edges`, `variables`, `settings` are correct JSON types | `definition.*_invalid` | Value is present but wrong type |
| Trigger has a non-empty `type` | `trigger.type_missing` | `type` is blank |
| Trigger type matches a known active node definition | `trigger.type_unknown` | No active `Node` record with that type |
| Trigger `config` fields match their declared types | `config.type_invalid` / `config.required_missing` | Per `NodeConfigField.is_required` and `NodeConfigField.type` |
| Each node has a non-empty `id` | `node.id_missing` | `id` and `key` are both blank |
| Node ids are unique | `node.id_duplicate` | Same id appears twice |
| Each node has a non-empty `type` | `node.type_missing` | `type` is blank |
| Node type matches an active definition | `node.type_unknown` | No active `Node` record with that type |
| Node `config` shape and required fields | `config.*` | Per `NodeConfigField` records |
| Each edge has a `source_node_key` that exists | `edge.source_missing` / `edge.source_missing_reference` | Source is blank or node not found |
| Each edge has a `target_node_key` that exists | `edge.target_missing` / `edge.target_missing_reference` | Target is blank or node not found |
| No duplicate edges (same source → target pair) | `edge.duplicate` | Pair already seen |
| `branch_type` is `default`, `conditional`, or `parallel` | `edge.branch_type_invalid` | Any other value |

**Config field type validation** (`configValueMatchesFieldType`) maps `NodeConfigFieldType` enum values to PHP type checks:

| Field type | Valid values |
|---|---|
| `text`, `textarea`, `select`, `readonly` | string or numeric |
| `email` | string passing `FILTER_VALIDATE_EMAIL` |
| `number` | numeric |
| `toggle` | boolean |
| `tags`, `json`, `branches` | array |

---

### Stage 2 — Graph Control Flow Verification

**Class:** `GraphControlFlowVerificationRule`  
**Responsibility:** Ensures the graph is connected and well-formed as a directed graph, independent of node semantics.

**Algorithms used:**

**Trigger connectivity:** Direct adjacency check. The trigger node's outgoing edge list is checked; an empty list means the trigger is disconnected.

**Degree checking:** Every non-trigger node must have at least one incoming edge. Every non-terminal node must have at least one outgoing edge. Checks run in O(N) by iterating the pre-built adjacency maps.

**Forward reachability (BFS/DFS from trigger):** `WorkflowDefinitionGraph::reachableFrom()` uses iterative DFS with a visited set. Every node not in the reachable set is flagged as `graph.unreachable`.

**Backward reachability (reverse BFS to terminals):** `WorkflowDefinitionGraph::nodesThatCanReachAny()` traverses the graph along **incoming** edges from all terminal nodes. Any node not in this set cannot reach a terminal and is flagged as `graph.dead_end`.

**Cycle detection:** `WorkflowDefinitionGraph::invalidCycleEdges()` runs an iterative DFS with a "currently visiting" set (`visiting[]`). Back edges (edges into `visiting`) are cycle edges. An edge is considered valid if either endpoint is a `loop` node (explicit loops are permitted). All other back edges produce `graph.unstructured_cycle` errors.

| Check | Error code | Algorithm |
|---|---|---|
| Trigger node exists | `graph.trigger_missing` | Presence check |
| Trigger has outgoing edges | `graph.trigger_disconnected` | Adjacency lookup |
| At least one terminal node exists | `graph.terminal_missing` | `terminalNodeIds()` |
| Every non-trigger node has incoming edges | `graph.incoming_missing` | Degree check |
| Every non-terminal node has outgoing edges | `graph.outgoing_missing` | Degree check |
| Every node reachable from trigger | `graph.unreachable` | Forward DFS |
| Every node on a path to a terminal | `graph.dead_end` | Reverse DFS |
| No unstructured cycles | `graph.unstructured_cycle` | DFS with visiting set |

---

### Stage 3 — Structured Control Flow Verification

**Class:** `StructuredControlFlowVerificationRule`  
**Algorithm:** `ControlFlowReducer` — graph reduction to fixpoint

**Purpose:** Ensures every split node (fork/if/switch) is correctly paired with a matching merge node of the appropriate type, preventing runtime deadlocks and lack-of-synchronization.

**Node classification:**

| Node type | Reducer kind |
|---|---|
| `and-node` | `p-split` (parallel split) |
| `if-node`, `switch` | `c-split` (conditional split) |
| `merge` with `mergeMode=parallel` | `p-merge` (parallel merge) |
| `merge` with `mergeMode=conditional` | `c-merge` (conditional merge) |
| Everything else | `plain` |

**Reduction rules (applied to fixpoint):**

1. **Sequence rule:** A `plain` node with exactly one predecessor and one successor is removed, and a direct edge is added from predecessor to successor. Collapses linear chains until only structural nodes remain.

2. **Block rule (SESE — Single Entry, Single Exit):** A split node whose all outgoing edges lead to the same merge node, and whose merge node's predecessors are exclusively those branches, is a well-formed block. The pair is collapsed into a single `plain` node and the merge is removed.

   While collapsing, the type pairing is validated:
   - `p-split` + `p-merge` → compatible (parallel fork joins parallel merge)
   - `c-split` + `c-merge` → compatible (conditional split joins conditional merge)
   - `c-split` + `p-merge` → **deadlock**: only one branch executes, but the parallel merge waits for all. The AND-join never fires.
   - `p-split` + `c-merge` → **lack of synchronization**: all branches execute, but the conditional merge fires once per branch arriving, not once after all complete.

**After reduction to fixpoint:**

- Surviving split nodes with ≥ 2 outgoing edges → `merge.fork_unjoined` or `merge.split_unsynchronized` (split has no matching merge).
- Surviving merge nodes with ≥ 2 incoming edges → `merge.unmatched` (merge has no matching upstream split).
- Detected mismatches during reduction → `merge.deadlock` or `merge.lack_of_synchronization`.

| Check | Error code |
|---|---|
| Conditional split paired with parallel merge | `merge.deadlock` |
| Parallel split paired with conditional merge | `merge.lack_of_synchronization` |
| Split with no matching merge | `merge.fork_unjoined` / `merge.split_unsynchronized` |
| Merge with no matching upstream split | `merge.unmatched` |

---

### Stage 4 — Expression Verification

**Class:** `ExpressionVerificationRule`  
**Algorithm:** Recursive-descent parser (`ExpressionLanguageValidator`)

**What is parsed:** Boolean expressions used in:
- Conditional edge `condition_expression` fields.
- Node config fields named `expression` or `condition`.

**Grammar (simplified):**

```
expr     → or
or       → and ( "||" and )*
and      → comparison ( "&&" comparison )*
comparison → unary ( ( "==" | "!=" | ">=" | "<=" | ">" | "<" ) unary )?
unary    → "!" unary | primary
primary  → "(" expr ")" | identifier | number | string | literal
```

**Token types:** `operator` (`&&`, `||`, `==`, `!=`, `>=`, `<=`, `>`, `<`, `!`, `(`, `)`), `string` (quoted), `number`, `identifier` (dotted paths like `context.age`), `literal` (`true`, `false`, `null`).

**Type system:** The parser tracks expression types (`boolean`, `variable`, `number`, `string`, `null`) and enforces that logical operators (`&&`, `||`, `!`) only receive boolean-like operands. A bare variable reference is not a valid condition — a comparison operator must be used.

**Side effects:** All `identifier` tokens encountered during parsing are collected as the expression's variable references. These are returned in `ExpressionValidationResult.variables` and used by node-type rules (e.g., `IfNodeTypeRule`) to verify variable namespace and availability.

| Check | Error code |
|---|---|
| Non-default conditional edge missing `condition_expression` | `expression.condition_missing` |
| Conditional edge expression fails to parse | `expression.condition_invalid` |
| Node `expression` or `condition` config field fails to parse | `expression.node_invalid` |

---

### Stage 5 — Contextual Verification

**Class:** `ContextualVerificationRule`  
**Responsibility:** Cross-entity consistency checks that require database lookups.

This stage **only runs when a `Workflow` model is provided** (skipped on standalone `POST /validate` calls).

**Human task assignees:**  
For every `human-task` node with a numeric `assignee` config value:
1. Checks `users` table: user must exist in the same tenant and be active.
2. Checks `team_memberships` table: user must be an active member of the workflow's team.

**Knowledge base documents (AI nodes):**  
For every node whose type starts with `ai-`, if `kbDocs` is a non-empty array:
1. Each entry must be numeric.
2. Each referenced document must exist in the `documents` table under the same tenant.

| Check | Error code |
|---|---|
| Assignee not found in tenant | `context.assignee_unknown` |
| Assignee not a team member | `context.assignee_not_team_member` |
| `kbDocs` is not an array | `context.kb_docs_invalid` |
| A KB document id is non-numeric | `context.kb_doc_id_invalid` |
| KB document not found for tenant | `context.kb_doc_unknown` |

---

### Stage 6 — Node-Type Verification

**Class:** `NodeTypeVerificationRule` (dispatcher) + per-type sub-rules  
**Responsibility:** Per-node-type semantic rules beyond generic config field validation.

The dispatcher iterates every node and calls the registered `NodeTypeRule` for its type. Sub-rules are registered at service construction time in `WorkflowVerificationService`.

#### if-node (`IfNodeTypeRule`)

1. `conditionExpression` must be present and pass the expression parser.
2. Variables extracted by the parser must use `context.*` or `customer.*` namespaces.
3. `context.*` variables must exist among the trigger's declared variables (for `manual-trigger` only).
4. If the node is reachable via parallel paths (an ancestor has multiple outgoing edges leading to this node), a **warning** is emitted (variable availability is not guaranteed on all paths).
5. Node must have exactly 2 outgoing branches.

#### and-node / fork (`ForkNodeTypeRule`)

1. `branches` config must be a non-empty array; each entry must have a non-empty `name` and `key`.
2. Branch `key` values must be unique within the node.
3. The number of outgoing edges must equal the number of declared branches.

#### switch (`SwitchNodeTypeRule`)

1. `variable` must be present, use `context.*` or `customer.*` namespace.
2. For `context.*` variables with a `manual-trigger`, the variable must be declared in the trigger.
3. Parallel-path warning if applicable.
4. `options` must be a non-empty array of non-empty strings.
5. Outgoing branch count must equal `count(options) + 1` (options plus one default branch).

#### merge (`MergeNodeTypeRule`)

1. `mergeMode` must be `parallel` or `conditional`.
2. `branchCount` must be a whole number ≥ 2.
3. Actual incoming edge count must equal the declared `branchCount`.

#### task-node (`TaskNodeTypeRule`)

1. `title` must be present.
2. `inputFields` must be a non-empty array; each field must have a `key`, `label`, and valid `type` (`text`, `textarea`, `number`, `date`, `file`, `checkbox`, `select`). `select` and `checkbox` fields must declare at least one option.
3. Node must have exactly 1 outgoing branch.

#### send-email (`SendEmailNodeTypeRule`)

1. `to` is required. Value must be either a valid email address or exactly `{{context.<key>}}` / `{{customer.<key>}}`. No mixed or complex templates.
2. `cc` and `bcc` are optional but subject to the same format rule when present.
3. `subject` and `body` are required and may contain `{{context.<key>}}` / `{{customer.<key>}}` template variables. Invalid variable references are flagged.
4. `context.*` template variables are checked against trigger-declared variables (manual-trigger only).

#### ai-generator (`AiGeneratorNodeTypeRule`)

1. `prompt` must be present and may contain template variables (same rules as `send-email`).
2. `outputVariable`, if present, must match `[a-zA-Z_][a-zA-Z0-9_]*` (valid identifier).

#### termination-node (`TerminationNodeTypeRule`)

1. Node must have zero outgoing edges (terminal by definition).

---

### Stage 7 — Data Flow Verification

**Class:** `DataFlowVerificationRule`  
**Algorithm:** `DataFlowAnalyzer` — forward data-flow analysis over a topological ordering

**Purpose:** Detects data hazards at validation time without running the workflow:

- **Parallel write conflicts:** Two concurrent branches (from a fork) both write the same `context.*` variable. At the merge point, one value silently overwrites the other.
- **Missing-data hazards:** A node reads a `context.*` variable that is produced somewhere in the workflow but is not guaranteed on every incoming path (only on some branches).
- **Merge output availability:** A merge node declares output variables that are not produced by the branches feeding into it.

**Analysis algorithm:**

1. **Classify outputs:** For every node, compute the set of `context.*` variables it produces (`outputVariables` array or singular `outputVariable`). The trigger is a special producer: `manual-trigger` produces its declared `variables`; `form-trigger` produces its `formFields` keys.

2. **Topological traversal:** Nodes are visited in topological order (reverse DFS post-order from the trigger). For each node, the variables **guaranteed** and **possible** on arrival are computed from predecessors:
   - Each predecessor's **leaving set** = `guaranteed[pred] ∪ outputs[pred]`.
   - **Parallel merge node:** All branches run concurrently. The guaranteed set is the **union** of all leaving sets. Variables written by ≥ 2 branches are flagged as conflicts.
   - **All other nodes (conditional path):** Only one incoming path executes. The guaranteed set is the **intersection** of all leaving sets. The possible set is the union.

3. **Missing-data check:** For each non-trigger node, every `context.*` reference extracted from its config strings (excluding `outputVariables` / `outputVariable` fields) is compared against `guaranteed[nodeId]`. If the variable is produced somewhere in the workflow but not in the guaranteed set, it is flagged as potentially unset.

4. **Merge output availability:** A merge node's declared output variables must appear in the guaranteed set at the merge:
   - `conditional` merge: only one branch runs, so every output must be guaranteed (intersection).
   - `parallel` merge: all branches run, so at least one must produce it (union, already guaranteed).

| Check | Error code |
|---|---|
| Variable written by ≥ 2 parallel branches at a merge | `dataflow.parallel_conflict` |
| `context.*` variable read but not guaranteed on all paths | `dataflow.variable_missing` |
| Conditional merge output not on every incoming branch | `merge.output_not_on_all_branches` |
| Parallel merge output not on any incoming branch | `merge.output_not_on_any_branch` |

---

### Stage 8 — Form Trigger Verification

**Class:** `FormTriggerVerificationRule`  
**Responsibility:** Validates the structure of a `form-trigger` configuration.

Runs only when `trigger.type === 'form-trigger'`.

**Form fields:**

Each entry in `config.formFields` must have:
- `key` — non-empty string.
- `label` — non-empty string.
- `type` — one of: `text`, `textarea`, `number`, `date`, `file`, `checkbox`, `select`.
- `options` — required (non-empty array of non-empty strings) when type is `select` or `checkbox`.

**Access level:**

`config.accessLevel` must be `public` or `tenant`.

| Check | Error code |
|---|---|
| `formFields` is empty or missing | `form_trigger.form_fields_missing` |
| A field is not an object | `form_trigger.form_field_invalid` |
| Field missing `key` | `form_trigger.form_field_key_missing` |
| Field missing `label` | `form_trigger.form_field_label_missing` |
| Field `type` is invalid | `form_trigger.form_field_type_invalid` |
| `select`/`checkbox` field has no options | `form_trigger.form_field_options_missing` |
| `accessLevel` is missing | `form_trigger.access_level_missing` |
| `accessLevel` is not `public` or `tenant` | `form_trigger.access_level_invalid` |

---

## Publishability Rules

A workflow is publishable (`is_publishable = true`) when the verification result contains **zero errors**. Warnings do not block publishing.

The `publish` endpoint re-runs verification internally and throws a 422 if `is_publishable` is false, even if the client already validated — this guards against race conditions where the definition changes between the last canvas validation and the publish request.

---

## Node-Type Rules Reference

| Node type | Rule class | Key constraints |
|---|---|---|
| `if-node` | `IfNodeTypeRule` | Boolean expression required; exactly 2 outgoing branches |
| `and-node` | `ForkNodeTypeRule` | ≥ 1 branch with unique keys; outgoing count = branch count |
| `switch` | `SwitchNodeTypeRule` | Variable required; outgoing = options + 1 default |
| `merge` | `MergeNodeTypeRule` | `mergeMode` required; `branchCount` = actual incoming |
| `task-node` | `TaskNodeTypeRule` | Title + ≥ 1 input field; exactly 1 outgoing branch |
| `send-email` | `SendEmailNodeTypeRule` | `to`, `subject`, `body` required; `to` is email or single variable |
| `ai-generator` | `AiGeneratorNodeTypeRule` | `prompt` required; `outputVariable` must be valid identifier |
| `termination-node` | `TerminationNodeTypeRule` | Zero outgoing edges |
