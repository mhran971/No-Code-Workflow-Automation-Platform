# Execution Engine — Implementation Plan

> Companion to [`../execution-engine-recommendations.md`](../execution-engine-recommendations.md) (the
> architecture & decisions). This is the **build plan**: ordered milestones, a file manifest, testing
> strategy, and acceptance criteria. Detailed schema in [`SCHEMA.md`](SCHEMA.md); detailed class/interface
> design in [`COMPONENTS.md`](COMPONENTS.md).
>
> Status: **plan only — nothing here is implemented.** Paths are relative to the repo root unless noted; engine
> code lives under `Modules/Workflows/app/`.

---

## 1. Goal & non-goals

**Goal:** turn a published, immutable `workflow_versions.definition` into a running, durable, observable,
fault-tolerant execution — driven by manual / form / webhook triggers — on the existing Laravel `database`
queue.

**In scope (Phases 0–2):** token-based engine; `manual`/`form`/`webhook` triggers; executors for
`if`/`switch`/`and`(fork)/`merge`/`termination`/`send-email`/`ai-generator`/`task`; JSON context store;
retries + effectively-once idempotency; per-attempt timeouts + global instance deadline; durable waits
(human task, merge sync) via `node_executions.wait_until`; error edges; manual retry-from-node; per-tenant
admission control; lifecycle/audit events.

**Out of scope (Phase 3+):** `sub-workflow`, `dynamic-flow`, `schedule` & `event` triggers, Redis/Horizon,
Redis-based per-provider rate limiting, compensation/saga, loops/iteration, metrics dashboard. See
[recommendations §8.8](../execution-engine-recommendations.md#88-scope-summary).

---

## 2. Guiding principles

1. **Reuse the authoring half.** `WorkflowDefinitionGraph` (traversal), the `NodeTypeRule` registry pattern
   (mirror it), the expression grammar (extract → evaluator), `IntegrationAction` (side effects),
   `DataFlowAnalyzer` (variable scope). Symmetry between verification and execution is a feature.
2. **Durability over speed.** Every advance is a persisted state transition; any worker can resume any
   instance after a crash. Never hold workflow state in memory across a wait.
3. **The database is the source of truth.** One unified runtime table (`workflow_node_executions`) holds the
   entire live state of an instance (tokens, joins, timers folded in — [SCHEMA §2.2](SCHEMA.md#22-workflow_node_executions)).
4. **Additive schema only.** Extend `workflow_instances`; add new tables. Never drop/rename existing columns.
5. **Side effects outside the lock.** Lock to read/transition state; do I/O unlocked; re-lock to commit.
6. **Effectively-once, not exactly-once.** At-least-once delivery + idempotent effects
   ([recommendations §8.6](../execution-engine-recommendations.md#86-exactly-once-for-external-actions--recommendation)).

---

## 3. Milestones

Each milestone is independently shippable and testable.

| # | Milestone | Outcome | Depends on |
|---|---|---|---|
| M0 | **Foundations** | Schema, enums, models, registry, context/result value objects, expression evaluator, compiled plan — no execution yet | — |
| M1 | **Linear + branching, durable** | A webhook/manual/form trigger runs a straight-line or `if`/`switch` graph end-to-end through the queue, with retries, idempotency, audit events | M0 |
| M2 | **Parallelism & waits** | `and`(fork) + `merge`(join), durable timers, `task-node` wait+resume, error edges, manual retry-from-node, per-tenant admission control | M1 |

Phase 3 work is deliberately excluded from these milestones.

---

## 4. Work breakdown

### M0 — Foundations

Ordered; later items depend on earlier.

1. **Migrations** ([SCHEMA §2](SCHEMA.md#2-migrations)): extend `workflow_instances`; create
   `workflow_node_executions`, `workflow_tasks`, `workflow_events`. One migration per table; additive only.
2. **Enums** ([SCHEMA §3](SCHEMA.md#3-enums)): `NodeExecutionStatus`, `TriggerType`, `WaitType`; extend
   `WorkflowInstanceStatus` with `pending|waiting|paused`.
3. **Models** ([SCHEMA §4](SCHEMA.md#4-models)): `WorkflowNodeExecution`, `WorkflowTask`, `WorkflowEvent` +
   relations on `WorkflowInstance`. Restore factories (currently commented out) for tests.
4. **Execution plan** ([COMPONENTS §4](COMPONENTS.md#4-execution-plan--compiler)): `ExecutionPlan` +
   `ExecutionPlanCompiler` wrapping `WorkflowDefinitionGraph`; cache by immutable `version_id`.
5. **Expression evaluator** ([COMPONENTS §5](COMPONENTS.md#5-expression-evaluation)): extract the shared
   tokenizer/grammar out of `ExpressionLanguageValidator`; add `ExpressionEvaluator` + `TemplateInterpolator`.
   The validator must keep passing unchanged (regression guard for "validated ⇒ evaluable").
6. **Core contracts** ([COMPONENTS §3](COMPONENTS.md#3-node-executors)): `NodeExecutor` interface,
   `NodeExecutionContext`, `NodeExecutionResult`, `NodeExecutorRegistry`. No executors yet.
7. **Config** ([SCHEMA §5](SCHEMA.md#5-config)): `Modules/Workflows/config/config.php` → add an `execution`
   block (timeouts, retry defaults, deadline, admission caps, queue names).

**Acceptance:** `composer test` green; evaluator unit tests cover the full grammar against sample contexts;
compiler turns a sample definition into a plan with correct adjacency + join expected-counts. No behavior wired
to HTTP yet.

### M1 — Linear + branching, durable

1. **Runtime + job** ([COMPONENTS §6](COMPONENTS.md#6-runtime--the-advance-loop)): `WorkflowRuntime::advance()`
   (lock → resolve executor → run → persist → enqueue successors → emit events) and `ExecuteNodeJob`.
2. **Dispatcher** ([COMPONENTS §7](COMPONENTS.md#7-triggers--dispatcher)): `WorkflowDispatcher::dispatch()`
   creates the instance, seeds the first `node_execution` at the trigger node, enqueues the first job.
3. **Trigger executors:** `ManualTriggerExecutor`, `FormTriggerExecutor`, `WebhookTriggerExecutor`
   (validate/seed context → proceed to successors).
4. **Logic executors:** `IfNodeExecutor`, `SwitchNodeExecutor`, `TerminationNodeExecutor`.
5. **Action executors:** `SendEmailExecutor` (→ `IntegrationAction`, idempotent `Message-ID`),
   `AiGeneratorExecutor`.
6. **Reliability:** `FailureClassifier`, `RetryPolicy`, idempotency guard
   ([COMPONENTS §8](COMPONENTS.md#8-reliability-retries-idempotency-timeouts)).
7. **Triggers wiring:** new `WorkflowTriggerController`; **replace** the body of
   `WorkflowManagementService::triggerWebhook()` (`app/Services/WorkflowManagementService.php:328`) to call the
   dispatcher instead of only inserting a row. Add manual + form endpoints.
8. **Events + audit:** lifecycle events ([COMPONENTS §10](COMPONENTS.md#10-events--observability)) +
   `WorkflowEvent` writer. (Broadcasting optional, behind a flag.)

**Acceptance:** feature test — webhook triggers a `trigger → ai-generator → send-email → termination` graph;
instance reaches `completed`; `node_executions` rows recorded per node; an `if` graph routes to the correct
branch; a forced transient failure retries then succeeds; a duplicate job does not double-send email.

### M2 — Parallelism & waits

1. **Fork:** `ForkNodeExecutor` (`and-node`) spawns one child `node_execution` per outgoing branch.
2. **Join:** `MergeNodeExecutor` — `firstOrCreate` the merge row, lock + increment `arrived_count`, complete
   on `parallel` (all) / `conditional` (first); losers → `consumed`
   ([COMPONENTS §9.4](COMPONENTS.md#94-mergenodeexecutor-the-join)).
3. **Timers:** `ScanWorkflowTimersCommand` (per-minute) over `status = waiting AND wait_until <= now()`;
   register in the module scheduler.
4. **Human task:** `TaskNodeExecutor` (create `workflow_tasks`, park execution with `wait_type = task_sla`);
   `WorkflowTaskController` (inbox list + submit response → resume); SLA breach behavior per
   [recommendations §8.5b](../execution-engine-recommendations.md#85-per-node-timeouts--global-deadline--recommendation).
5. **Merge timeout** handling in the timer scan (`wait_type = merge_timeout`).
6. **Error routing:** support an outgoing edge with `target_handle = 'error'`; failed-past-retries routes there
   instead of failing the instance.
7. **Manual recovery:** `retryFromNode(instance, nodeKey)` re-activates an execution; `cancel(instance)`.
8. **Global deadline scan:** daily command marking overdue instances `failed` + cancelling parked waits.
9. **Backpressure:** `InstanceAdmissionService` + `AdmitPendingInstancesCommand` — per-tenant concurrent-instance
   cap; named queues `workflow-control` / `workflow-actions`
   ([COMPONENTS §11](COMPONENTS.md#11-backpressure--admission-control)).

**Acceptance:** feature tests — fork→2 branches→merge(parallel) completes once both arrive; merge(conditional)
proceeds on first; a `task-node` parks the instance as `waiting`, the task API resumes it to `completed`; a
node failing past retries with an error edge routes to the handler branch; admission control holds excess
instances `pending` and promotes them as capacity frees.

---

## 5. File / artifact manifest

New unless marked. All under `Modules/Workflows/`.

```
app/Services/Execution/
  WorkflowDispatcher.php
  WorkflowRuntime.php                      # the advance loop
  NodeExecutorRegistry.php
  ExecutionPlan.php
  ExecutionPlanCompiler.php
  Contracts/NodeExecutor.php
  NodeExecutionContext.php
  NodeExecutionResult.php
  RetryPolicy.php
  FailureClassifier.php
  Expression/
    ExpressionGrammar.php                  # extracted tokenizer/parser (shared)
    ExpressionEvaluator.php
    TemplateInterpolator.php               # {{ ... }} interpolation
  Admission/InstanceAdmissionService.php
  Executors/
    ManualTriggerExecutor.php
    FormTriggerExecutor.php
    WebhookTriggerExecutor.php
    IfNodeExecutor.php
    SwitchNodeExecutor.php
    ForkNodeExecutor.php
    MergeNodeExecutor.php
    TerminationNodeExecutor.php
    SendEmailExecutor.php
    AiGeneratorExecutor.php
    TaskNodeExecutor.php

app/Jobs/ExecuteNodeJob.php
app/Console/Commands/
  ScanWorkflowTimersCommand.php
  AdmitPendingInstancesCommand.php
  ExpireOverdueInstancesCommand.php

app/Models/
  WorkflowNodeExecution.php
  WorkflowTask.php
  WorkflowEvent.php
  WorkflowInstance.php                     # EDIT: relations + casts + new columns
  WorkflowManagementService.php            # EDIT: triggerWebhook() → dispatcher

app/Enums/
  NodeExecutionStatus.php
  TriggerType.php
  WaitType.php
  WorkflowInstanceStatus.php               # EDIT: add pending|waiting|paused

app/Events/
  InstanceStarted.php  NodeStarted.php  NodeCompleted.php  NodeFailed.php
  NodeRetrying.php  InstanceCompleted.php  InstanceFailed.php  InstancePaused.php

app/Http/Controllers/
  WorkflowTriggerController.php
  WorkflowTaskController.php
  WorkflowInstanceController.php            # show/list runs, retry, cancel

database/migrations/
  ..._extend_workflow_instances_for_execution.php
  ..._create_workflow_node_executions_table.php
  ..._create_workflow_tasks_table.php
  ..._create_workflow_events_table.php

app/Providers/WorkflowsServiceProvider.php # EDIT: register executors, scheduler entries
routes/api.php                             # EDIT: trigger/task/instance routes
config/config.php                          # EDIT: execution block
```

`tests/Unit/Execution/*` and `tests/Feature/Execution/*` accompany each milestone.

---

## 6. Testing strategy

- **Unit (per executor):** each `NodeExecutor` is pure given a fake `NodeExecutionContext` — assert the
  returned `NodeExecutionResult` (proceed/branch/wait/fail/terminate) and context writes. No DB needed.
- **Unit (evaluator):** table-driven cases over the full grammar (`&& || == != >= <= > < !`, parens, strings,
  numbers, identifiers) against sample contexts; plus a property: any expression the validator accepts, the
  evaluator runs without parse error.
- **Feature (end-to-end):** drive small real definitions through the queue with `Queue::fake()` where
  asserting dispatch, or sync queue for full runs. Scenarios: linear, if-branch, switch, fork-join (parallel &
  conditional), task wait+resume, transient-retry-then-succeed, idempotent double-delivery, error-edge routing,
  admission hold+promote, deadline expiry.
- **Concurrency caveat:** tests run on **SQLite in-memory** (`phpunit.xml`), which lacks real row-lock
  semantics. Keep transition correctness assertable at the application level (status guards, unique
  `idempotency_key`), and document that true lock-race behavior is validated only against Postgres
  (dev/staging). Consider a dedicated Postgres CI lane for the join-race test.
- **Factories:** un-comment / add factories for `WorkflowInstance`, `WorkflowNode`, `WorkflowEdge`,
  `WorkflowNodeExecution`, `WorkflowTask` to keep feature tests terse.

---

## 7. Rollout & ops

- **Workers:** run two queues — `php artisan queue:work --queue=workflow-control,workflow-actions`. Control is
  prioritized so orchestration never waits behind slow I/O. Fold into `composer run dev`.
- **Scheduler:** register `ScanWorkflowTimersCommand` (everyMinute), `AdmitPendingInstancesCommand`
  (everyMinute), `ExpireOverdueInstancesCommand` (daily) in the module provider. `schedule:work` is already in
  `composer run dev`.
- **Config/env:** caps, timeouts, retry defaults, deadline, queue names in the `execution` config block
  ([SCHEMA §5](SCHEMA.md#5-config)) so they're tunable without code change.
- **Feature flag:** gate the dispatcher behind a per-tenant or global flag so triggering can be enabled
  gradually; until then `triggerWebhook()` can keep the legacy row-only behavior.

---

## 8. Risks & mitigations

| Risk | Mitigation |
|---|---|
| **Join race** (two branches think they're last / neither does) | `firstOrCreate` + `lockForUpdate` + atomic increment on the merge row; expected-count from the compiled plan, never counted at runtime. Postgres CI lane for the race test. |
| **Double side effects** on retry/crash | Persist `node_execution` (`idempotency_key`, `running`) before the call; succeeded-row short-circuit; deterministic `Message-ID`; non-idempotent actions pause on ambiguous recovery. |
| **At-least-once queue redelivery** advancing a token twice | `unique(instance_id, node_key, attempt)` + status guards; an already-`consumed`/`succeeded` execution is a no-op. |
| **Orphaned waits** (task nobody completes) | Global instance deadline scan ([recommendations §8.5c](../execution-engine-recommendations.md#85-per-node-timeouts--global-deadline--recommendation)). |
| **Tenant starves the shared DB queue** | Named queues + per-tenant admission control ([COMPONENTS §11](COMPONENTS.md#11-backpressure--admission-control)). |
| **SQLite can't test locks** | App-level guards + Postgres lane; documented in §6. |
| **AI provider not yet wired** | `AiGeneratorExecutor` depends on the AI integration; isolate behind an interface and stub in tests so M1 isn't blocked on provider work. |
| **Draft vs. version drift** | Engine compiles only from `workflow_versions.definition`; never from `draft_definition` ([recommendations §8.2](../execution-engine-recommendations.md#82-version-pinning--policy-instances-always-run-their-original-version)). |

---

## 9. Definition of done (engine v1)

- A published workflow can be triggered (manual/form/webhook) and runs to `completed` through the queue.
- Branching, fork/join, and human-task wait/resume all work and survive a worker restart mid-run.
- Failures retry per policy, route to error edges when present, and never double-fire `send-email`.
- Operators can list runs, inspect per-node executions, retry-from-node, and cancel.
- No tenant can starve another's executions.
- `composer test` green; `composer run format` clean.
