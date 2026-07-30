# Epic 6: Dynamic Flow — Research & Implementation Plan

Runtime creation and execution of custom sub-flows within a running instance when an
"Other / Dynamic" routing branch is reached. Covers Features 6.1 (Detect & Enter Dynamic
Flow Mode), 6.2 (Design & Save Dynamic Flow), and 6.3 (Execute Dynamic Flow Nodes),
plus the `sub-workflow` node it builds on.

---

## Part 1 — Current state of the codebase

### 1.1 Execution engine

A full, production-shaped execution engine exists under
`Modules/Workflows/app/Services/Execution/`. It is a **token-based, queue-driven,
crash-safe advance loop**, not a synchronous walker.

| Component | File | Role |
|---|---|---|
| `WorkflowRuntime` | `Execution/WorkflowRuntime.php` | Heart of the engine. `advance(int $executionId)` claims the execution row (`lockForUpdate`), runs the executor outside the lock, commits via `applyResult()` matching on `ResultKind`: `Proceed`/`Branch` → `onSucceed`, `Terminate` → `onTerminate`, `Wait` → `onWait`, `Fail` → `onFail`, `Noop` → `onConsume`. Also `cancel()`, `retryFromNode()`, `scheduleRetry()` (with `RetryPolicy`), `completeInstance()`, `failInstance()`. Broadcasts via `DB::afterCommit`. |
| `ExecuteNodeJob` | `Jobs/ExecuteNodeJob.php` | `tries = 1` (retries live inside the runtime); per-category timeouts from `Modules/Workflows/config/config.php`; two named queues: `workflow-control`, `workflow-actions`. |
| `WorkflowDispatcher` | `Execution/WorkflowDispatcher.php` | Single entry point for all triggers: creates the `WorkflowInstance`, the trigger-node `WorkflowNodeExecution`, and the first job in one transaction. Called from `WorkflowManagementService::triggerManual/triggerForm/triggerWebhook`. |
| `ExecutionPlanCompiler` / `ExecutionPlan` | `Execution/ExecutionPlanCompiler.php`, `Execution/ExecutionPlan.php` | Compiles the normalized definition per immutable `WorkflowVersion` and caches it **forever** by version id (`Cache::rememberForever("workflows:plan:{$version->id}")`). Execution always reads the immutable published `workflow_versions.definition` — there is no mechanism for per-instance graph extension. |
| `NodeExecutorRegistry` | `Execution/NodeExecutorRegistry.php` | Type → `NodeExecutor` lookup; unknown type throws `UnsupportedNodeTypeException`. Executors registered in `WorkflowsServiceProvider::register()` (lines ~108–129). |
| Timer scan | `Console/Commands/ScanWorkflowTimersCommand.php` | Runs every minute; promotes `waiting` executions whose `wait_until` passed back to `pending` and re-dispatches. Also `AdmitPendingInstancesCommand`, `ExpireOverdueInstancesCommand`. |

Executors present in `Execution/Executors/`: ManualTrigger, FormTrigger, WebhookTrigger,
IfNode, SwitchNode, ForkNode, MergeNode, TaskNode, SendEmail, AiGenerator,
TerminationNode. **There is no executor for `sub-workflow` or `dynamic-flow`** — a token
reaching either throws `UnsupportedNodeTypeException` and fails the instance.

**Routing nodes and the "Other/Dynamic" branch:**

- `IfNodeExecutor`: true branch identified by `branch_type` in `['true', 'branch_1']`;
  everything else is the else branch. Two-way only.
- `SwitchNodeExecutor`: evaluates `config.variable`, matches edges by
  `branch_type === value` (per-edge `condition_expression` override), falls back to the
  edge with `branch_type === 'default'` or `is_default_branch`.
- **No "Other/Dynamic" branch concept exists anywhere** — not in `NodeDefinitionSeeder`,
  not in `EdgeBranchType` (`app/Enums/EdgeBranchType.php`: only `Default`, `Conditional`,
  `Parallel`), not in executors, verification rules, or the frontend. The switch `default`
  branch is the closest existing hook.
- `SwitchNodeTypeRule` (`Verification/Rules/NodeType/SwitchNodeTypeRule.php`) **enforces**
  outgoing branches = options + exactly one default — new branch semantics must update it.

### 1.2 `sub-workflow` and `dynamic-flow` node types: seeder entries only

`Modules/Workflows/database/seeders/NodeDefinitionSeeder.php`:

- `sub-workflow` (line 139): category `flows`; config fields `workflowId` (required),
  `inputMapping` (JSON), `outputVariable`.
- `dynamic-flow` (line 152): category `flows`; config fields `message`
  ("Manager Message", default "Manager must design a custom sub-flow") and
  `aiSuggestion` (toggle, default true).

What does **not** exist:

- No `SubWorkflowNodeTypeRule` / `DynamicFlowNodeTypeRule` under
  `Verification/Rules/NodeType/`. `NodeTypeVerificationRule` silently skips unregistered
  types, so definitions containing these nodes pass verification with zero type-specific
  validation today.
- No executors (see 1.1) — publishing and triggering a workflow containing these nodes
  hard-fails at runtime.
- Docs explicitly defer both: `Modules/Workflows/docs/execution-engine-recommendations.md`
  §8.4 ("`dynamic-flow` — out of scope, Phase 3 with `sub-workflow`") and §3.6, which
  sketches the intended sub-workflow design: *"dispatch a child instance
  (`parent_instance_id`), park the parent token; on child completion map outputs back via
  `inputMapping`/`outputVariable` and resume the parent."*

### 1.3 Models & migrations

All under `Modules/Workflows/app/Models/` and `Modules/Workflows/database/migrations/`.

- **`WorkflowInstance`** — fillable already includes **`parent_instance_id`**
  (self-FK, nullOnDelete; migration `2026_06_21_120000_extend_workflow_instances_for_execution.php`)
  and **`paused_reason`**, plus `trigger_type`, `correlation_id`, `payload`,
  `context` (runtime variable store), `error`. Has a `parent()` relation.
  **`WorkflowInstanceStatus::Paused = 'paused'` exists** (non-terminal) and an
  `InstancePaused` broadcast event exists (`app/Events/InstancePaused.php`) — **but
  nothing ever sets Paused or fires that event today.** No `triggering_node_id`, no
  `is_dynamic` anywhere.
- **`WorkflowNodeExecution`** — the runtime token table: `instance_id`, `node_key`,
  `node_type`, `status`, `attempt`, unique `idempotency_key`, `input`/`output`/`error`,
  `parent_execution_id` (lineage), `fork_group`, `expected_count`/`arrived_count` (merge),
  `wait_until`/`wait_type`. `WaitType` enum has only `TaskSla` and `MergeTimeout`.
- **`WorkflowNode` / `WorkflowEdge`** — design-time artifacts. **Not read by the
  execution engine** (the engine executes `workflow_versions.definition` JSON).
- **`Workflow`** — `draft_definition`, `current_version_id`, `team_id`, `created_by_id`,
  `status`, `active_instances`, `public_token`, soft deletes.
- **`WorkflowVersion`** — immutable `definition` JSON, `published_by_id`, `published_at`.

Nothing sub-flow-specific exists beyond `parent_instance_id`.

### 1.4 Task node — the pause/notify/resume template

The exact pattern Dynamic Flow should mirror:

1. **Park:** `TaskNodeExecutor::execute()` on first entry creates a `WorkflowTask` row
   (`instance_id`, `execution_id`, `node_key`, `assignee_id`, rendered title/description,
   `input_schema`, `status='open'`, `due_at`) and returns
   `NodeExecutionResult::wait($dueAt, WaitType::TaskSla)`. `WorkflowRuntime::onWait()`
   marks the execution `Waiting` and flips the **instance** to `Waiting` when no other
   live tokens exist, broadcasting `NodeWaiting`.
2. **Notify:** pull-only — the task appears in the assignee's inbox
   (`GET /tasks`, `GET /tasks/summary`) plus the `NodeWaiting` broadcast on
   `workflow-instance.{id}`. No mail, no Laravel Notification.
3. **Human input:** `WorkflowTaskController::saveDraft()` (PATCH `/tasks/{task}/draft`)
   and `submit()` (POST `/tasks/{task}/submit`): inline tenant/role authorization,
   schema validation, marks the task completed, then **`resumeExecution()`** flips the
   parked `WorkflowNodeExecution` from `Waiting` → `Pending`, clears `wait_until`, and
   re-dispatches `ExecuteNodeJob`.
4. **Resume:** the executor runs **again** for the same execution row, detects the
   non-open task, merges `task_response` into instance context, and `proceed()`s.
   SLA-breach path: timer scan fires, task marked `expired`, flow proceeds anyway.

### 1.5 Audit trail

Two mechanisms:

1. **`workflow_events` + `WorkflowEvent` model** — append-only per-instance log
   (`instance_id`, `tenant_id`, `node_key`, `type`, `payload`, `created_at`).
   **Nothing writes to it yet** — a ready-made, unused audit sink.
2. **`audit_trails` (Team module)** — actively used, actor-centric:
   `Modules/Team/app/Models/AuditTrail.php` + `AuditTrailRepository`
   (`actor_user_id/name/email`, `action`, `subject_type/id`, `metadata`). Written from
   `TeamMemberManagementService` and `TenantUserManagementService`.

No activity-log package. Workflow publish/status changes currently have no audit trail
beyond the `workflow_versions` row itself.

### 1.6 AI proposal generation

`POST /proposals/ai` → `WorkflowController::proposal()` →
`WorkflowManagementService::generateAiProposal()` — an **explicit TODO placeholder**:
inline Manager/BusinessOwner gate, returns a hardcoded definition with a
`proposal_checksum` and fake `confidence: 0.82`. **Ephemeral — never persisted** (which
already matches 6.2's "AI suggestions are not stored unless the user confirms").
The only other AI seam is the `AiContentGenerator` contract
(`Execution/Contracts/AiContentGenerator.php`), bound to `NullAiContentGenerator`.
**No real AI provider is integrated anywhere in the repo.**

### 1.7 Notifications

**No Laravel Notification class exists anywhere.** What exists:

- **Reverb broadcasts:** 10 `ShouldBroadcastNow` events in
  `Modules/Workflows/app/Events/` (Instance Started/Paused/Completed/Failed/Cancelled,
  Node Started/Completed/Failed/Retrying/Waiting) on private channel
  `workflow-instance.{instanceId}`, authorized by tenant match in
  `WorkflowsServiceProvider::bootBroadcasting()`. The per-user channel
  `App.Models.User.{id}` is authorized in `routes/channels.php` but **nothing broadcasts
  to it**. Frontend subscribes in `frontend/src/hooks/useBackendExecution.ts`.
- **Mail:** direct `Mail::to(...)->send(...)` with Mailables (e.g.
  `TenantUserManagementService` → `NewUserCredentialsMail`).

Task assignment notifies no one (pull-based inbox only). Epic 6's "notify an authorized
user" needs new machinery.

### 1.8 Frontend

The actual React frontend is **`frontend/`** (React 19 + Vite + @xyflow/react) —
CLAUDE.md's `workflow-builder/` does not exist.

- **No dynamic-flow builder scaffolding.** Sole references: static palette defs in
  `frontend/src/types/workflow.ts` (lines 38–39) and icons in `NodeLibrary.tsx`. Both
  seeded types already appear draggable in the palette via `GET /nodes`.
- No `is_dynamic`/`isDynamic` anywhere in `frontend/src`.
- Instance/execution UI: `ExecutionPanel.tsx` (has `isBackendMode` — mock simulation vs
  real run), `execution/StepRow.tsx` (timeline row — where a "dynamic" visual distinction
  goes), `execution/VariableTree.tsx`, `useBackendExecution.ts` (WebSocket node status).
- Frontend "test mode" is client-side mock execution only; **there is no backend
  test-mode concept** (no `test_mode` anywhere in `Modules/Workflows/app`).

### 1.9 Roles / permissions

No policies, no gates, no role middleware — all inline checks:

- `Modules/Auth/app/Enums/Role.php`: `BusinessOwner`, `Manager`, `Admin`, `Employee`.
- Routes use `auth:api` (JWT) + `active.user`; neither checks roles.
- Canonical workflow authorization: `WorkflowManagementService::canView()` /
  `canManage()` (BusinessOwner always; Manager if their team owns the workflow), with
  `assertCanView`/`assertCanManage`/`assertBusinessOwner` wrappers.
- **`workflows.created_by_id` is stored but never used in authorization** — Epic 6's
  "workflow creator or responsible Manager" check is new logic.
- Instance endpoints are tenant-match only (`WorkflowInstanceController::authorizeInstance()`).

### 1.10 Verification pipeline extension points

`Modules/Workflows/app/Services/WorkflowVerificationService.php`:

- `verify()` actually runs **8 stages** (not the 5 in CLAUDE.md): Syntax,
  GraphControlFlow, StructuredControlFlow, Expression, Contextual, NodeType, DataFlow,
  FormTrigger.
- NodeType sub-rules are registered in the constructor via
  **`NodeTypeVerificationRule::register()`** (CLAUDE.md's `addNodeTypeRule()` does not
  exist). Implement `NodeTypeRule` (`nodeType(): string` + `verify(...)`) and register.
- Runtime mirror: new executors register in `NodeExecutorRegistry` in
  `WorkflowsServiceProvider::register()`.

### 1.11 Key design tensions

1. Execution plans are compiled from the **immutable published version and cached
   forever by version id** — dynamic nodes appended at runtime cannot flow through that
   path unchanged.
2. `WorkflowNode`/`WorkflowEdge` rows are design-time artifacts not read by the engine —
   "save dynamic nodes flagged `is_dynamic`" needs a decision on where the executable
   definition lives.
3. The pause/notify/resume skeleton (`Waiting` status + `wait_type` + re-entrant
   executor + controller-driven re-dispatch + timer scan) is complete and directly
   reusable.
4. `parent_instance_id`, `paused_reason`, `Paused` status, and `InstancePaused` are
   pre-provisioned but unused — purpose-built hooks for this epic.

---

## Part 2 — Implementation plan

### Core architectural decision: dynamic segment = child instance

Execute the dynamic segment as a **child `WorkflowInstance`** rather than mutating the
running plan. Rationale:

- The plan cache is immutable per version id (tension #1); injecting nodes into a live
  plan would require invalidating/forking cached plans per instance.
- A child instance reuses the entire runtime for free: context propagation, retry
  policies, error handling, per-node broadcasting, timers — exactly what Feature 6.3
  requires ("same execution model … as standard workflow nodes").
- `parent_instance_id` already exists for precisely this.
- It makes `sub-workflow` the natural foundation: **dynamic-flow becomes "a sub-workflow
  whose definition is designed at runtime."**

### Phase 1 — `sub-workflow` node (foundation)

1. **Migration:** add `parent_execution_id` (nullable FK → `workflow_node_executions`)
   to `workflow_instances`, pointing at the parked parent token.
2. **`SubWorkflowExecutor`** (`Execution/Executors/`), re-entrant:
   - First entry: resolve `config.workflowId` → published workflow (same tenant);
     evaluate `inputMapping` against parent context; spawn a child instance via
     `WorkflowDispatcher::dispatch()` with `parent_instance_id` + `parent_execution_id`
     set; return `NodeExecutionResult::wait(null, WaitType::SubWorkflow)`.
   - Re-entry: read the completed child's final `context`, map into
     `config.outputVariable`, `proceed()`.
3. **Parent-resume plumbing** in `WorkflowRuntime::completeInstance()` /
   `failInstance()`: if the finishing instance has a `parent_execution_id`, flip that
   token `Waiting` → `Pending` and re-dispatch `ExecuteNodeJob` (mirror of
   `WorkflowTaskController::resumeExecution()`). Child **failure** flows through the
   parent's standard `onFail` path — satisfying 6.3's error rule automatically.
4. **`WaitType::SubWorkflow`** enum case.
5. **`SubWorkflowNodeTypeRule`** (register in `WorkflowVerificationService`
   constructor): `workflowId` exists, is published, same tenant; recursion/depth guard
   (a workflow may not reach itself through sub-workflow references; cap nesting depth).
6. Register the executor in `WorkflowsServiceProvider::register()`.
7. Tests: unit (executor park/resume, output mapping) + feature (parent completes after
   child; child failure fails parent; cancel parent cancels child).

### Phase 2 — Dynamic Flow backend (Features 6.1 + 6.3)

1. **Migration — `workflow_dynamic_flows` table:**
   `id`, `tenant_id`, `instance_id` (parent), `execution_id` (parked token),
   `node_key` (triggering node), `status` (`awaiting_design` / `executing` /
   `completed` / `cancelled`), `definition` JSON nullable, `created_by_id` nullable
   (who designed it), `child_instance_id` nullable, timestamps. Add nullable
   `dynamic_flow_id` FK + boolean `is_dynamic` to `workflow_instances` so the child
   instance is queryable/flaggable per the spec.
2. **`DynamicFlowExecutor`**, re-entrant (task-node pattern):
   - First entry: create the `workflow_dynamic_flows` row; set instance status
     `Paused` + `paused_reason` (e.g. `dynamic_flow_design`); fire `InstancePaused`;
     write a `workflow_events` row (`dynamic_flow.requested`); return
     `wait(WaitType::DynamicFlowDesign)` (nullable `wait_until`, or an SLA if desired).
   - Re-entry (after child completion): merge child output into parent context, write
     `dynamic_flow.completed` event, `proceed()` — the main workflow resumes at the node
     after the routing point (6.3).
3. **"Other/Dynamic" branch modeling:** keep routing semantics unchanged — the
   switch/if routes its default/"other" edge into a `dynamic-flow` node placed on the
   canvas. Optionally add an `other` case to `EdgeBranchType` + update
   `SwitchNodeTypeRule` for explicit labeling, but node-as-target avoids touching
   executor branch logic.
4. **Plan compilation for the dynamic definition:** the saved definition is immutable
   after save, so `ExecutionPlanCompiler` gets a second path — compile/cache keyed by
   `workflow_dynamic_flows.id` — and `WorkflowDispatcher` accepts a dynamic-flow source
   in place of a `WorkflowVersion` when spawning the child instance.
5. **Verification "segment mode":** the pipeline currently requires a trigger node.
   Add a mode (flag on `WorkflowDefinitionValidator`) that tolerates a trigger-less
   segment (or synthesize an implicit start node) while still running all structural,
   expression, and node-type rules. Task nodes inside the segment may be assigned to
   any tenant user (6.2) — reuse existing task-node validation.
6. **Endpoints** (new `DynamicFlowController`, routes under
   `/instances/{instance}/dynamic-flow`):
   - `GET /instances/{instance}/dynamic-flow` — pending design request + read-only
     parent definition (for rendering locked outer nodes).
   - `POST /instances/{instance}/dynamic-flow/definition` — validate (segment mode),
     save, spawn the child instance through Phase 1 machinery, flip parent instance
     `Paused` → `Running`, audit.
   - Authorization: `workflow.created_by_id === user->id` **or** Manager of the owning
     team (reuse `canManage()` shape). New logic — creator is not used in auth today.
7. **Notify (6.1):** first Laravel Notification class in the codebase
   (`DynamicFlowDesignRequested`, `database` + `broadcast` channels on
   `App.Models.User.{id}` — the channel is already authorized in `routes/channels.php`),
   sent to the workflow creator and the owning team's Manager(s). Optionally a pull
   endpoint alongside the task inbox.
8. **Audit trail:** `workflow_events` rows for instance-scoped events
   (`dynamic_flow.requested/designed/completed/failed`) + `AuditTrailRepository` entry
   (`action: dynamic_flow_created`, actor = designer) for the actor-centric trail (6.2).
9. **DynamicFlowNodeTypeRule:** validate `dynamic-flow` node placement (exactly one
   incoming routing edge, at least one outgoing edge, config shape).

### Phase 3 — AI-drafted sub-flow (Feature 6.2, optional draft)

- `POST /instances/{instance}/dynamic-flow/proposal` reusing the
  `generateAiProposal()` shape: ephemeral response + `proposal_checksum`; the draft is
  only persisted when the user saves it via the definition endpoint (satisfies "AI
  suggestions are not stored or executed unless the user confirms").
- Include runtime context in the prompt seam (triggering node, instance context,
  `dynamic-flow` node's `message` config).
- Real AI provider integration is a separate decision — nothing exists today
  (`NullAiContentGenerator` and a hardcoded proposal stub are the only seams).

### Phase 4 — Frontend (`frontend/`)

1. **Dynamic Flow designer mode** of the existing canvas: parent workflow nodes rendered
   locked/read-only (not selectable, not editable), editable region seeded empty or from
   the AI draft; same node palette (6.2). Entry from a notification/inbox item or from
   the paused instance view.
2. **Timeline distinction (6.3):** nest the child instance's steps under the
   dynamic-flow node in `ExecutionPanel` / `StepRow.tsx` with a colored band or
   "Dynamic" label; subscribe to the child's `workflow-instance.{id}` channel in
   `useBackendExecution.ts`.
3. **Notification surfacing:** listen on the user channel for
   `DynamicFlowDesignRequested`; show a banner/inbox entry linking to the designer.
4. API client additions in `frontend/src/lib/api/client.ts` for the three new endpoints.

### Spec deviations / open questions

- **`is_dynamic` on nodes:** the epic says "Dynamic Flow nodes are stored with an
  `is_dynamic` flag", but design-time `WorkflowNode` rows are not read by the engine.
  The flag belongs on the child instance / `workflow_dynamic_flows` record; echo it into
  stored node rows only if literal compliance is needed for reporting.
- **Test mode:** there is no backend test-mode concept; the frontend "test mode" is a
  client-side mock. Treat backend execution as the single mode (available in both
  contexts per 6.2) and optionally teach the frontend mock engine to simulate the pause.
- **Design SLA:** should a dynamic-flow design request expire (timer scan +
  `wait_until`) or wait indefinitely? Task-node precedent: SLA breach proceeds anyway —
  probably wrong for dynamic flow; suggest indefinite wait + cancel endpoint.
- **Notification recipients:** "workflow creator or responsible Manager" — notify both,
  first save wins; concurrent saves guarded by `status` transition on the
  `workflow_dynamic_flows` row.
