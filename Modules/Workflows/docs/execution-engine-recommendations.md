# Workflow Execution Engine — Architecture Recommendations

> Status: **Research / proposal only.** Nothing here is implemented. This document recommends
> an architecture, execution model, schema, and rollout plan for turning published workflow
> definitions into running, observable, fault-tolerant executions.
>
> Audience: maintainers of `Modules/Workflows/`. All paths are relative to the repo root.

---

## 1. Where we are today

The platform already has a strong **authoring + verification** half. The **execution** half is a stub.

What exists:

| Concern | Status | Reference |
|---|---|---|
| Definition storage | ✅ JSON on `workflow_versions.definition` (immutable per version) + draft on `workflows.draft_definition` | `Modules/Workflows/app/Models/WorkflowVersion.php` |
| Graph model | ✅ Rich: `WorkflowDefinitionGraph` (topo sort, reachability, ancestors, cycle detection) | `app/Services/Verification/WorkflowDefinitionGraph.php` |
| Edge semantics | ✅ `branch_type` (default/conditional/parallel), `condition_expression`, `parallel_group_key`, `parallel_strategy` (fork_join/fire_and_forget), `join_node_key`, `sort_order` | `database/migrations/...create_workflow_edges_table.php` |
| Verification | ✅ 5+ stage pipeline incl. per-node-type rules | `app/Services/WorkflowVerificationService.php` |
| Expression language | ⚠️ **Validator only** — parses `context.age > 18 && ...` but does **not evaluate** | `app/Services/Verification/ExpressionLanguageValidator.php` |
| Instance model | ⚠️ Stub: `workflow_instances` with `status/payload/started_at/finished_at`; migration comment notes it will grow | `app/Models/WorkflowInstance.php` |
| Trigger | ⚠️ `triggerWebhook()` only **creates an instance row** and increments counters — no execution | `app/Services/WorkflowManagementService.php:328` |
| Connectors | ✅ `IntegrationAction::handle(payload, connection)` + `SendEmailAction` | `Modules/Integrations/app/Contracts/IntegrationAction.php` |
| Queue | ⚠️ `QUEUE_CONNECTION=database` (no Redis/Horizon yet) | `.env.example` |

Key fact the engine must respect: **a running instance is pinned to a `workflow_version`** (immutable
`definition` JSON). Editing the draft must never alter in-flight runs.

### Node types the engine must execute

From `database/seeders/NodeDefinitionSeeder.php`:

- **Triggers:** `manual-trigger`, `form-trigger` (and `webhook-trigger` referenced in code).
- **Logic:** `if-node` (condition), `and-node` (Fork → parallel branches), `merge` (parallel = wait-all / conditional = first-arrival), `switch` (multi-path), `termination-node` (branch end).
- **AI:** `ai-generator`.
- **Flows:** `sub-workflow`, `dynamic-flow`.
- **Actions:** `send-email`, `task-node` (human task with SLA `dueWithin`).

These fall into three execution archetypes that drive the whole design:

1. **Synchronous compute** — `if`, `switch`, `and`, expression evaluation. Fast, no I/O, no waiting.
2. **External I/O actions** — `send-email`, `ai-generator`, `sub-workflow`. Can fail/timeout → need retries & idempotency.
3. **Durable waits** — `task-node` (waits for a human), `merge` (waits for sibling branches, possibly with timeout), future delay/timer nodes. **The instance must survive process restarts while waiting** — this is the single biggest architectural constraint.

---

## 2. Core execution model: tokens flowing through the graph

Borrowed from BPMN/workflow engines. The unit of progress is an **execution token** (an active pointer
into the graph). This model handles linear flow, branching, parallelism, and joins uniformly.

- A trigger creates a `WorkflowInstance` and **one token** positioned at the trigger node.
- Advancing a token = execute its current node → decide which outgoing edge(s) to follow → move/spawn tokens onto the targets.
- **Branch (`if`/`switch`):** the node picks exactly one outgoing edge; the single token moves there.
- **Fork (`and-node`):** the node spawns **N child tokens**, one per outgoing edge (parallel branches).
- **Join (`merge`):** tokens *arrive* at the join node and are held; the join completes per its mode
  (parallel = all expected branches arrived; conditional = first arrival wins) then emits **one** token onward.
- **Termination:** a token reaching a `termination-node` (or a node with no successors) is consumed.
- **Instance completes** when no active/waiting tokens remain. It **fails** when a token errors past its
  retry budget with no error path. It **waits** when all remaining tokens are parked on durable waits.

Why tokens (vs. a simple program counter): parallelism and joins fall out naturally, partial failure is
localized to one branch, and "resume from here" / replay is just re-activating a token.

### Execution loop (per token)

```
dequeue ExecuteNodeJob(token)
  → lock instance row (or token) for state transition
  → load node config from the pinned version definition
  → build NodeExecutionContext (instance context + token-local data)
  → resolve NodeExecutor for node type   (registry, mirrors NodeTypeRule)
  → result = executor->execute(context)
       result ∈ { Completed(outputs, edges[]), Waiting(resumeHandle), Failed(error), Branched(edges[]) }
  → persist NodeExecution row (status, output, attempt, timing)
  → on Completed/Branched: for each selected edge → create/move child token → dispatch ExecuteNodeJob
  → on Waiting: park token (status=waiting), register timer/human-task; do NOT dispatch
  → on Failed: apply retry policy (re-dispatch w/ backoff) OR route error edge OR fail instance
  → emit lifecycle event(s)
  → release lock
```

Keep **side-effecting work outside the lock**: lock only to read/transition state, run the I/O, then
re-lock to commit the outcome. Holding a DB lock across an HTTP call to Gmail is a deadlock generator.

### Component map

```
                         ┌─────────────────────────────────────────┐
   Triggers              │            Execution Engine               │
 (manual/form/webhook/   │                                           │
  schedule/event)        │  WorkflowDispatcher  ── creates instance  │
        │                │        │              + seeds token       │
        ▼                │        ▼                                   │
  WorkflowDispatcher ───▶│   ExecuteNodeJob (queue) ◀── timers/resume │
                         │        │                                   │
                         │        ▼                                   │
                         │   NodeExecutor registry                   │
                         │   ├─ IfExecutor / SwitchExecutor          │
                         │   ├─ ForkExecutor / MergeExecutor         │
                         │   ├─ SendEmailExecutor ─▶ IntegrationAction│
                         │   ├─ AiGeneratorExecutor                  │
                         │   ├─ TaskExecutor ─▶ human_tasks (wait)   │
                         │   └─ SubWorkflowExecutor ─▶ child instance │
                         │        │                                   │
                         │        ▼                                   │
                         │   State store (instances, node_executions,│
                         │   tokens, joins, timers, context, events) │
                         └─────────────────────────────────────────┘
                                  │ lifecycle events
                                  ▼
              audit log · metrics · real-time UI (broadcast) · event-triggered workflows
```

---

## 3. The pieces, in detail

### 3.1 Compiler / execution plan

The version `definition` JSON is authoring-shaped. Compile it once into an execution-optimized,
already-validated **plan**: indexed nodes by key, adjacency lists, precomputed join nodes + their expected
in-degree per branch, ordered conditional edges, and the entry token position.

- Reuse `WorkflowDefinitionGraph` — it already does adjacency, topo order, ancestors, reachability.
- **Recommendation:** compile lazily on first execution and cache (e.g. `Cache` keyed by `version_id`,
  which is immutable), or persist a `compiled_plan` JSON column on `workflow_versions`. Compiling at publish
  time also lets you reject definitions that pass authoring validation but are unrunnable.

### 3.2 NodeExecutor registry (mirror the verification design)

The verification side already has a clean, extensible pattern: `NodeTypeRule` + `register()` in
`WorkflowVerificationService`. **Mirror it exactly** for execution so the two stay symmetric:

```php
interface NodeExecutor
{
    public function type(): string;                       // 'send-email', 'if-node', ...
    public function execute(NodeExecutionContext $ctx): NodeExecutionResult;
}
```

`NodeExecutionResult` is a small value object expressing one of: `proceed(edges, output)`,
`branch(edge)`, `wait(handle, deadline?)`, `fail(error, retryable)`, `terminate()`.

Register executors in a `NodeExecutorRegistry` resolved from the container (one per node type). This keeps
each node type's runtime logic isolated and unit-testable, exactly like the `Rules/NodeType/` directory.

### 3.3 Expression evaluator (currently missing)

`ExpressionLanguageValidator` proves the grammar but only **validates**. The engine needs to **evaluate**
the same grammar against runtime context for `if-node`/conditional edges, and a separate `{{ var }}`
**template interpolator** for action config (`send-email` subject/body, etc.).

- **Recommendation:** extract the shared grammar (tokenizer + recursive descent) so the validator and a new
  `ExpressionEvaluator` use one source of truth — guarantees "validated ⇒ evaluable". The evaluator resolves
  identifiers (`context.age`, `nodeKey.output.field`) against the instance context and returns a typed value.
- Keep it sandboxed: no function calls, no arbitrary PHP, only the operators the grammar already defines
  (`&& || == != >= <= > < !`). This is a security boundary — users author these expressions.
- For `{{ }}` interpolation in strings, reuse the same resolver. Decide HTML-escaping policy for email bodies.

### 3.4 Context / variable store

Each instance carries a **context**: trigger payload + per-node outputs + named variables
(`outputVariable`, merge `outputVariables`, AI output, etc.). Nodes read upstream values and write their own.

- **v1:** a `context` JSON column on the instance, mutated under the instance lock. Simple, transactional.
- **Scale path:** append-only `workflow_variable_writes` (node_key, key, value, token) for full data-flow
  lineage and to avoid lost updates between concurrent parallel branches. Note: concurrent fork branches
  writing the **same** variable is a real hazard — define merge semantics (last-write-wins vs. namespaced
  per-branch then reconciled at the join). The `merge` node's `outputVariables` is the intended reconciliation point.
- The existing `DataFlowAnalyzer` / `DataFlowVerificationRule` already reason about variable availability —
  reuse their notion of scope so runtime resolution matches what authoring validated.

### 3.5 Joins / synchronization (the hard part)

When `and-node` forks into parallel branches that reconverge at a `merge` node with `parallel_strategy =
fork_join` and `join_node_key`, the engine must **synchronize** concurrent branches safely.

- Maintain a **sync record** per `(instance_id, join_node_key)` with `expected_count` and `arrived_count`.
- Each arriving token does an **atomic** `arrived_count++` under a row lock (`SELECT ... FOR UPDATE`) — two
  branches finishing simultaneously must not both think they're "not last." Only the token that satisfies
  the condition emits the downstream token; the rest are consumed.
- **Parallel (wait-all):** complete when `arrived_count == expected_count`, or when the timeout timer fires
  (the `merge-and` timeout) → then either proceed with partial results or fail, per config.
- **Conditional (first-arrival):** the first token proceeds; later arrivals are discarded.
- `expected_count` comes from the compiled plan (count of branches in the `parallel_group_key`), not counted
  at runtime — runtime counting races with branches that haven't started yet.

### 3.6 Durable waits, timers, and human tasks

Anything that waits longer than a job's lifetime must be **persisted and resumed externally**, never held in
memory or a sleeping job.

- **`task-node`:** on execute, create a `workflow_human_tasks` row (assignee, due_at from `dueWithin`, input
  schema), park the token as `waiting`, and **return**. A new API endpoint lets the assignee submit a
  response; that handler writes the result into context and **re-dispatches** the parked token. SLA reminders
  and overdue escalation are timers.
- **Timers (no separate table):** a waiting `node_execution` carries `wait_until` + `wait_type`. A Laravel
  **scheduled command** (`app/Console`) scans `node_executions WHERE status = 'waiting' AND wait_until <= now()`
  every minute and resumes/expires them. Covers `merge` timeout and task SLA. Prefer this over
  `dispatch()->delay()` for long waits so waits are queryable, cancelable, and survive queue flushes. (See §5.2 —
  timers are folded into `workflow_node_executions` rather than a dedicated table.)
- **`sub-workflow`:** dispatch a child instance (`parent_instance_id`), park the parent token; on child
  completion, an event maps outputs back via `inputMapping`/`outputVariable` and resumes the parent.

### 3.7 Triggers

Funnel every trigger through one entry point: `WorkflowDispatcher::dispatch(workflow, version, payload,
TriggerContext)` → creates instance + seed token + first `ExecuteNodeJob`. This is the seam that
`triggerWebhook()` should call instead of just inserting a row.

| Trigger | Mechanism | Notes |
|---|---|---|
| **Manual** | Authenticated API endpoint | Exists structurally; wire to dispatcher. |
| **Form** | Public endpoint, validate submission against `formFields` schema, respect `accessLevel` | Rate-limit; anti-spam. |
| **Webhook** | Per-workflow URL + secret; **verify HMAC signature**; dedupe by delivery id | `triggerWebhook()` is the hook; add auth + verification + dispatch. |
| **Schedule (cron)** | A `workflow_schedules` table; a scheduled command scans due schedules and dispatches | Store next_run_at; tenant-aware. |
| **Event (internal)** | Listen to platform domain events → match `workflow_event_subscriptions` → dispatch | Enables "when X happens in module Y, run workflow Z". |

Cross-cutting: every trigger should support an **idempotency / correlation key** (webhook delivery id, form
submission id) to prevent duplicate instances from retried deliveries.

### 3.8 Events & observability

Emit lifecycle events at every transition: `InstanceStarted`, `NodeStarted`, `NodeCompleted`, `NodeFailed`,
`NodeRetrying`, `TokenWaiting`, `InstanceCompleted/Failed/Cancelled`. These drive four consumers:

1. **Audit log** — append-only `workflow_events` (who/what/when per instance) for debugging & compliance.
2. **Metrics** — per-node duration, failure rate, queue depth.
3. **Real-time UI** — broadcast over websockets so the canvas can light up nodes as they run.
4. **Event-triggered workflows** — the internal event bus that feeds the Event trigger above.

---

## 4. Failure handling & retries

Treat reliability as a first-class node concern, not an afterthought.

### Classify failures

- **Transient** (network blip, 429 rate-limit, 5xx, timeout) → **retry** with exponential backoff + jitter.
- **Permanent** (validation error, 401/403 auth, missing config, bad expression) → **fail fast**, no retry.
- Map exception types/HTTP codes to these classes in each executor or a shared classifier.

### Retry policy (per node, with category defaults)

- Config knobs: `maxAttempts`, `backoffStrategy` (fixed/exponential), `baseDelay`, `maxDelay`, `timeout`.
- Sensible defaults: **action/AI/sub-workflow nodes retry** (e.g. 3 attempts, exp backoff); **pure logic
  nodes don't** (a deterministic `if` won't succeed on retry). Lean on Laravel's `Job::backoff()`,
  `retryUntil()`, `tries`, and `failed()` rather than reinventing.

### Idempotency (critical for actions with side effects)

- Each node execution gets an **idempotency key** (`instance_id : node_key : attempt-group`). Persist a
  `NodeExecution` row *before* the side effect and guard against double-commit, so a job retried after a
  crash mid-send doesn't send `send-email` twice.
- Pass idempotency keys through to external providers where supported.

### Error routing & recovery

- **Error edges:** allow an outgoing edge with `target_handle = 'error'` (or a dedicated branch type) so a
  failed node routes to a handler branch instead of failing the whole instance — the no-code analog of try/catch.
- **Dead-letter / pause:** after exhausting retries with no error path, mark the node `failed` and the
  instance `failed` (or `paused` for manual intervention).
- **Manual replay:** "retry from node N" — re-activate a token at N reusing prior context. The token model
  makes this cheap. Also enables resuming after a deploy that fixes a bug.
- **Cancellation:** `cancel(instance)` transitions to `cancelled`, voids parked tokens, cancels timers/tasks.
- **Compensation / saga** (rollback of already-applied side effects): out of scope for v1; note it for flows
  that need all-or-nothing semantics, and design executors so a compensating action *could* be attached later.

### Concurrency correctness

- Pessimistic lock the instance (or join sync record) for **state transitions only**; do I/O outside the lock.
- Guard every state machine transition (e.g. don't complete an instance that's already cancelled).
- Ensure exactly-once token advancement: a token can be advanced once; use the `NodeExecution` unique key +
  token status as the guard against duplicate queue delivery (`database`/`redis` queues are at-least-once).

---

## 5. Schema recommendations

Honoring the migration note on `workflow_instances` ("changes will **add** columns, not remove"), this is
**additive**: extend the instance table, add new tables for execution state. Columns below are illustrative.

### 5.1 Extend `workflow_instances`

```
+ trigger_type        string   (manual|form|webhook|schedule|event|sub_workflow)
+ correlation_id      string nullable index   -- idempotency / external delivery id
+ context             json nullable           -- runtime variable store (see 3.4)
+ error               json nullable           -- failure detail when status=failed
+ parent_instance_id  fk self nullable        -- sub-workflow nesting
+ paused_reason       string nullable
```

Also extend `WorkflowInstanceStatus` enum: add `pending`, `waiting`, `paused` to existing
`running/completed/failed/cancelled`.

### 5.2 `workflow_node_executions` — unified runtime-state table

**Decision (resolves the "consider merging" notes on 5.3/5.4):** fold tokens, join sync records, and timers
into this one table. Every non-terminal row **is** an active token (its `status` says whether it's runnable
or parked); `merge` rows carry their own arrival counters; waiting rows carry their own wake-up time. One
table is the entire live state of an instance — far simpler to lock, query, and reason about than three.

```
id, instance_id (fk), tenant_id (fk), node_key, node_type,
status (pending|running|succeeded|failed|waiting|skipped|consumed),
attempt int, idempotency_key string unique,
input json, output json, error json,

-- token lineage (subsumes workflow_execution_tokens)
parent_execution_id (fk self) nullable,   -- which execution spawned this path
fork_group string nullable,               -- groups sibling branches from one fork

-- join state (subsumes workflow_joins; only set on `merge` rows)
expected_count int nullable,              -- branches this join waits for (from compiled plan)
arrived_count  int nullable default 0,    -- atomically incremented under row lock

-- durable wait / timer (subsumes workflow_timers)
wait_until timestamp nullable,            -- scanned by the timer command
wait_type  string nullable,               -- merge_timeout | task_sla

started_at, finished_at, timestamps
unique(instance_id, node_key, attempt)
index(instance_id, status)
index(status, wait_until)                 -- timer scan
```

### 5.3 ~~`workflow_execution_tokens`~~ — **folded into 5.2**

Decided: do **not** create a separate tokens table. An active path is just a `workflow_node_executions` row
in a non-terminal status (`pending`/`running`/`waiting`); lineage lives in `parent_execution_id` + `fork_group`.

### 5.4 ~~`workflow_joins`~~ — **folded into 5.2**

Decided: the `merge` node's own execution row holds `expected_count` / `arrived_count`. The first arriving
branch creates the row (`firstOrCreate` on the unique key); each subsequent branch takes a row lock and
increments `arrived_count`. The branch that satisfies the rule emits the downstream execution; the rest are
marked `consumed`. `wait_type = merge_timeout` + `wait_until` handle the wait-all timeout. Safe because loops
are out of scope (§8.3), so each join activates once per instance.

### 5.5 `workflow_tasks` — bridges `task-node` to human input

```
id, instance_id (fk), execution_id (fk workflow_node_executions), node_key, tenant_id (fk),
assignee_id (fk users), title, description, input_schema json,
status (open|completed|expired|cancelled), due_at,
response json nullable, completed_by_id nullable, completed_at nullable
index(assignee_id, status)
```

Resume/timeout is driven by the **waiting `node_execution`** (`wait_until = due_at`, `wait_type = task_sla`),
not by this table. `workflow_tasks` is the human-facing projection (inbox queries by `assignee_id`, the
response form). Completing a task writes `response`, then re-activates the parked execution.

### 5.6 `workflow_events` — append-only audit/event log

```
id, instance_id (fk), tenant_id (fk), node_key nullable,
type string, payload json, created_at
index(instance_id, created_at)
```

### 5.7 Trigger-support tables

```
workflow_schedules:           id, workflow_id, version_id, cron, timezone,
                              next_run_at index, is_active, last_run_at
workflow_event_subscriptions: id, workflow_id, version_id, event_name index,
                              filter json, is_active
```

---

## 6. Tech choices on this stack

- **Queue:** start `database` (already configured); Use **named queues** to isolate workloads:
  `workflow-control` (cheap orchestration/logic) vs. `workflow-actions` (slow external I/O) so a backed-up
  Gmail queue doesn't stall branch routing.
- **Timers / cron:** Laravel scheduler + a per-minute command scanning waiting `workflow_node_executions`
  (`status = waiting AND wait_until <= now()`) to resume/expire durable waits. (Schedule-trigger scanning of
  `workflow_schedules` is Phase 3 / out of scope.) Ensure the scheduler runs (`schedule:work` is in `composer run dev`).
- **Event bus:** Laravel events/listeners for the internal bus; broadcasting (Reverb/Pusher) for real-time UI.
- **Locking:** Postgres row locks (`lockForUpdate`) for state transitions; `Cache::lock()` only for
  coarse-grained guards. SQLite (tests) lacks real row locking — keep transition logic testable without
  relying on DB-level lock semantics.
- **Multi-tenancy:** carry `tenant_id` on every new table (consistent with existing schema) and scope all
  queries; never let one tenant's instance read another's context.
- **Reuse, don't rebuild:** `WorkflowDefinitionGraph` (traversal), the `NodeTypeRule` registry pattern
  (mirror for executors), the expression grammar (extract → evaluator), `IntegrationAction` (side effects),
  `DataFlowAnalyzer` (variable scope).

---

## 7. Suggested rollout (incremental, each phase shippable)

**Phase 0 — Foundations**
Schema migrations (§5, additive); `NodeExecutor` interface + registry; `ExpressionEvaluator` extracted from
the validator grammar; `WorkflowDispatcher` seam; compiled plan from `WorkflowDefinitionGraph`.

**Phase 1 — Linear + branching, durable**
`ExecuteNodeJob` loop; executors for `manual/form/webhook` triggers, `if-node`, `switch`, `send-email`,
`ai-generator`, `termination-node`; context store; basic retry + idempotency; instance lifecycle + audit
events. Wire `triggerWebhook()` to the dispatcher. Real-time node status (optional).

**Phase 2 — Parallelism & waits**
`and-node` fork, `merge` join (arrival counters on the merge execution row), timer-scan command over waiting
`node_executions`, `task-node` + task API + resume, merge/SLA timeouts, per-tenant admission control (§8.7).
Error edges + manual "retry from node".

**Phase 3 — Composition, scale, events**
**OUT OF SCOPE**
`sub-workflow` (+ `dynamic-flow`), schedule & event triggers, Redis/Horizon migration, named queues + rate
limiting, metrics dashboard, (optional) compensation/saga hooks.

---

## 8. Resolved decisions & recommendations

These were the open questions; each is now decided. Items marked *(recommendation)* are my call on things you
delegated or were unsure about.

### 8.1 Context concurrency — **JSON on the instance (v1)**

A `context` JSON column on `workflow_instances`, mutated under the instance lock. Append-only variable lineage
is deferred until parallel branches are observed contending on shared variables. The `merge` node's
`outputVariables` is the reconciliation point for fork branches.

### 8.2 Version pinning — **policy: instances always run their original version**

A running/waiting instance executes the `workflow_version` it was created with, even after newer publishes.
Editing a draft or publishing a new version never mutates in-flight runs. This is why we compile from the
immutable `workflow_versions.definition`, never from `workflows.draft_definition`.

### 8.3 Loops — **out of scope**

No `loop` node is seeded and iteration is not in scope. The engine treats the graph as a DAG. This guarantee
is load-bearing: each `merge`/join activates exactly once per instance (§5.4), so join arrival counters need
no per-iteration keying.

### 8.4 `dynamic-flow` — **out of scope** (with `sub-workflow`, Phase 3)

### 8.5 Per-node timeouts & global deadline — *(recommendation)*

Three distinct clocks; don't conflate them.

**(a) Per-attempt execution timeout** — wall-clock for a single attempt of one node:

| Node class | Timeout | Rationale |
|---|---|---|
| Logic/compute (`if`, `switch`, `and`, `merge`, `termination`) | **30s** | Should be ~instant; a timeout means a bug. |
| Actions (`send-email`) | **60s** | One outbound API call. |
| AI (`ai-generator`) | **120s** | LLM calls are slow. |

On breach → classified **transient** → consumes an attempt → retry policy (§4) applies → after retries, node
`failed` → error edge or instance fails. Enforced by the queue job's `timeout` plus an executor-level guard.

**(b) Durable waits are NOT execution timeouts.** A parked `task-node`/`merge` may wait days.

- **`task-node` SLA:** default from `dueWithin` (hours; seeder default **24**). On breach → mark the task
  `overdue`, emit a reminder event, **keep waiting** — never discard human work. Opt-in `expireOnOverdue`
  routes it as failed instead. *Default: keep waiting.*
- **`merge` wait-all timeout:** default **none** (wait indefinitely, backstopped by (c)). If a timeout is
  configured, on breach → **fail the merge** (→ error edge / instance fail). *Default if set: fail, not
  proceed-with-partial* — silently proceeding on partial data is surprising.

**(c) Global instance deadline (backstop against orphaned waits).** A config constant
`workflows.execution.max_instance_duration` (default **30 days**), evaluated as `started_at + duration` — **no
column** (this is why `deadline_at` was dropped from §5.1). A daily scan marks overdue instances `failed`
(`error.reason = deadline_exceeded`) and cancels parked waits/tasks. Add a per-workflow override column later
if needed.

### 8.6 Exactly-once for external actions — *(recommendation)*

**Honest framing:** true exactly-once is impossible across a network. The achievable target is
**effectively-once = at-least-once delivery + idempotent effects.** The engine guarantees at-least-once node
execution (DB/Redis queues are at-least-once); we make the *effects* safe:

1. **Persist-before-call.** Write the `node_execution` (with `idempotency_key`, `status = running`) and
   **commit** it *before* the external call. On retry, a `succeeded` row short-circuits — skip the call, reuse
   stored `output`.
2. **Provider idempotency where it exists.** Stripe-style APIs and many webhooks honor an `Idempotency-Key`
   header — send one derived from `idempotency_key`. **Gmail's `messages.send` has no native idempotency key**
   → mitigate by setting a **deterministic `Message-ID`** header derived from `idempotency_key` (Gmail respects
   a supplied `Message-Id`), so duplicate sends collapse at the mail layer; optionally pre-check Sent. Accept
   that rare duplicate emails remain possible.
3. **Classify each connector.** Add `isIdempotent(): bool` (default `false`) to `IntegrationAction`, and let
   actions accept an idempotency key.
4. **Crash-recovery policy by class.** If a node is found stuck in `running` after a crash:
   - *Idempotent* action → safe to auto-retry.
   - *Non-idempotent* action (e.g. Gmail) → **do not auto-retry**; transition the instance to `paused`
     (`paused_reason = needs_review`) for a human decision. Trades availability for safety on irreversible effects.
5. **v1 concrete (only `send-email`):** set a deterministic `Message-ID`; record `running` → `succeeded`;
   ambiguous recovery → pause for review.

### 8.7 Backpressure & fairness — *(deep dive, you asked to research)*

**Problem.** One shared `database` queue is FIFO. A single tenant's burst causes **head-of-line blocking** —
everyone else's instances stall behind it (starvation). Separately, external providers impose **rate limits**
(Gmail quota is per-connection), so even a fair queue can overrun a downstream.

Two orthogonal concerns: **(A) concurrency/fairness across tenants** and **(B) external rate limiting per
tenant/provider.** They need different tools.

**Technique survey**

1. **Segregated (named) queues** — split `workflow-control` (cheap orchestration/routing) from
   `workflow-actions` (slow I/O) so a backed-up Gmail queue never blocks branch routing. Already adopted (§6).
   Cheap, high-impact; does *not* address per-tenant fairness.
2. **Per-tenant concurrency cap (semaphore).** Limit simultaneous in-flight work per tenant.
   - *Redis:* `Redis::funnel` / `Redis::throttle`, Horizon — **out of scope** (Phase 3).
   - *DB/cache:* a `Cache::lock()` semaphore in job middleware (atomic on the database cache store). Works
     without Redis but is coarse and wastes worker cycles (a job is picked, then released/re-queued if over cap).
3. **Admission control at the instance level — *recommended for in-scope.*** Cap concurrent **running**
   instances per tenant. Excess triggers create instances as `pending`; a scheduled **admitter** promotes
   `pending → running` up to the cap as running ones finish. DB-native, no Redis, directly prevents
   starvation, smooths bursts, and shields downstream providers. Add an optional token bucket
   (`max_instances_admitted_per_minute_per_tenant`) for rate, not just concurrency.
4. **Fair-dispatch / claim (pull) model.** Instead of pushing every node job immediately, keep a runnable pool
   and have a dispatcher *claim* up to N per tenant each tick → true weighted-fair queuing. Most fair, but the
   most moving parts. **Defer** — overkill at current scale.
5. **External rate limiting per (tenant, provider).** Token bucket keyed `(tenant, provider)` enforced in the
   action executor/job middleware; on limit, reschedule with backoff. Redis `throttle` in Phase 3; a DB/cache
   token bucket suffices in-scope if Gmail volume warrants it.

**Recommendation (in-scope, database queue):** adopt **#1 (named queues) + #3 (per-tenant instance admission
control)** as the core. Config: `max_concurrent_instances_per_tenant` (default ~**20**) and an optional
per-minute admission bucket. Add **#2 (cache-lock semaphore)** only if a *single* instance can fan out enough
parallel node jobs to overwhelm a worker pool (fork-heavy graphs); otherwise the instance cap is enough. Defer
**#4** and Redis-based **#5** to Phase 3.

**Trade-off.** Push model = simple but starvation-prone; admission/claim = fair and capped but adds an
orchestration loop. Instance-level admission control is the 80/20 here. Within-tenant fairness (one huge
instance vs. many small) is acceptable as node-level FIFO for v1. Expose per-tenant `running`/`pending` counts
+ queue depth so the caps can be tuned.

### 8.8 Scope summary

**In scope (Phases 0–2):** token engine on the database queue; `manual`/`form`/`webhook` triggers; executors
for `if`/`switch`/`and`/`merge`/`termination`/`send-email`/`ai-generator`/`task`; JSON context; retries +
effectively-once idempotency; per-attempt timeouts + global deadline; durable waits via
`node_executions.wait_until`; error edges; manual retry-from-node; per-tenant admission control;
lifecycle/audit events; real-time UI (optional).

**Out of scope (Phase 3+, deferred):** `sub-workflow`, `dynamic-flow`, `schedule` & `event` triggers,
Redis/Horizon, Redis per-provider rate limiting, compensation/saga, metrics dashboard. (`parent_instance_id`
in §5.1 is reserved for the future `sub-workflow`.)
```
