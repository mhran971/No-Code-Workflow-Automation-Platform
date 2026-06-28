# Execution Engine — Component & Interface Design

> Companion to [`IMPLEMENTATION-PLAN.md`](IMPLEMENTATION-PLAN.md) and [`SCHEMA.md`](SCHEMA.md). The concrete
> classes, contracts, and per-node execution semantics. Architecture rationale:
> [`../execution-engine-recommendations.md`](../execution-engine-recommendations.md).
>
> Status: **plan only.** Code is illustrative (signatures and control flow, not final implementations).
> Namespace root: `Modules\Workflows\Services\Execution`.

---

## 1. Layout

```
Services/Execution/
  Contracts/NodeExecutor.php          interface every node-type executor implements
  NodeExecutionContext.php            read/write view of one execution (immutable inputs, context accessor)
  NodeExecutionResult.php             value object: proceed | branch | wait | fail | terminate
  NodeExecutorRegistry.php            type → executor lookup (mirrors NodeTypeRule registry)
  ExecutionPlan.php                   compiled, indexed view of a version definition
  ExecutionPlanCompiler.php           definition → ExecutionPlan (wraps WorkflowDefinitionGraph)
  WorkflowDispatcher.php              trigger → instance + first execution + first job
  WorkflowRuntime.php                 the advance loop (lock, run, persist, fan out, emit)
  RetryPolicy.php / FailureClassifier.php
  Expression/{ExpressionGrammar,ExpressionEvaluator,TemplateInterpolator}.php
  Admission/InstanceAdmissionService.php
  Executors/*Executor.php             one per node type
Jobs/ExecuteNodeJob.php               queue wrapper around WorkflowRuntime::advance()
```

Design symmetry: this mirrors `Services/Verification/` (rules + registry + graph). Executors are to runtime
what `Rules/NodeType/*` are to validation.

---

## 2. The runtime data flow, in one paragraph

A trigger calls `WorkflowDispatcher::dispatch()`, which creates a `WorkflowInstance`, compiles (or loads) the
`ExecutionPlan` for its pinned version, writes the first `WorkflowNodeExecution` (status `pending`) at the
trigger node, and queues an `ExecuteNodeJob`. The job calls `WorkflowRuntime::advance(execution)`, which locks
the instance, builds a `NodeExecutionContext`, resolves the `NodeExecutor` for the node type, runs it, persists
a `NodeExecutionResult`, and — depending on the result — creates successor executions + queues their jobs,
parks the token, retries, or finishes the instance. Lifecycle events are emitted throughout. The loop repeats,
one job per node, until no non-terminal executions remain.

---

## 3. Node executors

### 3.1 Contract

```php
namespace Modules\Workflows\Services\Execution\Contracts;

interface NodeExecutor
{
    /** Node type key this executor handles, e.g. 'send-email'. */
    public function type(): string;

    /** Execution category — drives default timeout & retry policy. */
    public function category(): NodeCategoryClass; // logic | action | ai | trigger

    public function execute(NodeExecutionContext $ctx): NodeExecutionResult;
}
```

`category()` lets the runtime apply the right per-attempt timeout and retry default
([recommendations §8.5a](../execution-engine-recommendations.md#85-per-node-timeouts--global-deadline--recommendation))
without each executor repeating itself. Reuse the existing `Modules\Workflows\Enums\NodeCategory` if it fits;
otherwise a small execution-specific enum.

### 3.2 Registry

```php
class NodeExecutorRegistry
{
    /** @var array<string, NodeExecutor> */
    private array $executors = [];

    public function register(NodeExecutor $e): void { $this->executors[$e->type()] = $e; }

    public function for(string $type): NodeExecutor
    {
        return $this->executors[$type]
            ?? throw new UnsupportedNodeTypeException($type);
    }
}
```

Register all executors in `WorkflowsServiceProvider` exactly like `WorkflowVerificationService` wires
`NodeTypeRule`s. An unknown type fails fast (mirrors verification rejecting unknown node types).

---

## 4. Execution plan & compiler

`ExecutionPlan` is an immutable, execution-shaped view compiled once per `version_id` (immutable ⇒ cacheable).

```php
final class ExecutionPlan
{
    public function triggerNodeKey(): string;
    public function node(string $key): PlanNode;            // type + config
    public function outgoing(string $key): array;           // ordered PlanEdge[] (sort_order honored)
    public function selectableEdges(string $key): array;    // for if/switch: conditional + default
    public function forkBranches(string $key): array;       // for and-node: parallel_group edges
    public function joinFor(string $key): ?JoinSpec;        // merge node → {mode, expected_count}
    public function isTerminal(string $key): bool;
}
```

`ExecutionPlanCompiler` builds it from the normalized definition, **reusing**
`Services/Verification/WorkflowDefinitionGraph` for adjacency/topology and the normalizer for legacy aliases.

- `expected_count` for each `merge` join is computed **here, from the plan** (count of branches in the
  `parallel_group_key` / incoming edges), never at runtime — runtime counting races with not-yet-started
  branches ([recommendations §3.5](../execution-engine-recommendations.md#35-joins--synchronization-the-hard-part)).
- Compile lazily: `Cache::rememberForever("wf_plan:{$versionId}", …)`. Optionally also validate-on-publish and
  persist to `workflow_versions.compiled_plan` later (Phase 3 nicety).

---

## 5. Expression evaluation

The validator (`Services/Verification/ExpressionLanguageValidator`) proves the grammar but only *validates*.
Extract the tokenizer + recursive-descent parser into a shared `ExpressionGrammar`, then build two consumers so
**"validated ⇒ evaluable"** is guaranteed by construction.

```php
// Conditions: if-node, conditional edges. Returns a typed scalar/bool.
class ExpressionEvaluator
{
    public function evaluate(string $expression, ExecutionContextData $ctx): mixed;
    public function evaluateBoolean(string $expression, ExecutionContextData $ctx): bool;
}

// Strings: send-email subject/body, etc. Resolves {{ context.firstName }} against context.
class TemplateInterpolator
{
    public function render(string $template, ExecutionContextData $ctx): string;
}
```

Rules:

- Identifiers resolve dotted paths against context: `context.age`, `<nodeKey>.output.<field>`, trigger payload.
- **Sandboxed:** only the grammar's operators (`&& || == != >= <= > < !`, parens, string/number/`true/false/null`
  literals). No function calls, no PHP eval. This is a user-authored-input security boundary.
- Missing identifier → defined policy: `null` (and `null`-comparison semantics) rather than throw, so a sparse
  context doesn't crash a run. Decide & document; keep it consistent between evaluator and `DataFlowAnalyzer`.
- Reuse `DataFlowAnalyzer`'s notion of variable scope so runtime resolution matches what authoring validated.

---

## 6. Runtime — the advance loop

`WorkflowRuntime::advance()` is the engine core. `ExecuteNodeJob` is a thin durable wrapper.

```php
class WorkflowRuntime
{
    public function advance(WorkflowNodeExecution $execution): void
    {
        // 1. Transition guard (short lock): claim the execution
        $claimed = DB::transaction(function () use ($execution) {
            $instance = WorkflowInstance::whereKey($execution->instance_id)->lockForUpdate()->first();
            if ($instance->isTerminal()) return null;                 // cancelled/failed/completed → no-op
            $fresh = $execution->fresh();
            if (! $fresh->isRunnable()) return null;                  // already running/succeeded/consumed → dedup guard
            $fresh->update(['status' => Running, 'started_at' => now()]);
            return [$instance, $fresh];
        });
        if ($claimed === null) return;                                // at-least-once redelivery: someone else has it
        [$instance, $execution] = $claimed;

        $plan = $this->planFor($instance);                            // cached by version_id
        $ctx  = new NodeExecutionContext($instance, $execution, $plan, $this->expression, $this->interpolator);

        // 2. Run the node OUTSIDE the lock (I/O can be slow)
        try {
            $result = $this->registry->for($execution->node_type)->execute($ctx);
        } catch (Throwable $e) {
            $result = NodeExecutionResult::fail($e, $this->classifier->isRetryable($e));
        }

        // 3. Commit the outcome (short lock again)
        DB::transaction(fn () => $this->applyResult($instance, $execution, $plan, $ctx, $result));
    }
}
```

`applyResult()` handles each `NodeExecutionResult` variant:

| Result | Effect |
|---|---|
| `proceed(edges, output)` | persist output + `succeeded`; for each edge create a successor execution (`pending`) and queue `ExecuteNodeJob`; merge context writes |
| `branch(edge, output)` | like proceed but exactly one edge; siblings on untaken edges may be marked `skipped` |
| `wait(until, type)` | `waiting` + set `wait_until`/`wait_type`; do **not** queue; if all live executions now waiting → instance `waiting` |
| `fail(error, retryable)` | if retryable & attempts remain → schedule retry (new attempt row or re-queue with backoff); else error-edge route, else instance `failed` |
| `terminate(output)` | mark execution `consumed`; run completion check |

After any terminal transition, a **completion check**: `instance has no executions in {pending,running,waiting}`
→ instance `completed`. This check runs inside the same lock to avoid a race with a sibling branch finishing
concurrently.

### 6.1 `ExecuteNodeJob`

```php
class ExecuteNodeJob implements ShouldQueue
{
    public int $tries = 1;        // engine owns retries (RetryPolicy), not the queue's blind retry
    public function __construct(public int $executionId) {}

    public function handle(WorkflowRuntime $runtime): void
    {
        $execution = WorkflowNodeExecution::find($this->executionId);
        if ($execution !== null) $runtime->advance($execution);
    }

    public function backoff(): array { /* from RetryPolicy when re-queued for retry */ }
    public function retryUntil(): \DateTimeInterface { /* node/global deadline */ }
}
```

The job carries only the `executionId` — never the workflow state — so state always comes from the DB (source
of truth) and survives redeploys. Queue chosen per category: `workflow-control` for logic, `workflow-actions`
for I/O (action/ai). `tries = 1` because the engine schedules its own retries as explicit attempts (so each
attempt is an auditable `node_execution` row); the queue's automatic retry is reserved for true infra faults.

### 6.2 Locking & concurrency

- **Pessimistic, narrow:** `lockForUpdate()` on the instance row for the *claim* and the *commit*; the
  executor's I/O runs unlocked in between.
- **At-least-once safety:** the claim step's `isRunnable()` check + `unique(instance_id, node_key, attempt)`
  make a redelivered job a no-op.
- **Joins** take a row lock on the *merge execution row* specifically (§9.4), not the whole instance, to keep
  parallel branches moving.
- **SQLite caveat:** tests can't exercise real `FOR UPDATE`; assert correctness via status guards + unique keys,
  and validate true races on Postgres (see [plan §6](IMPLEMENTATION-PLAN.md#6-testing-strategy)).

### 6.3 State machines

```
instance:  pending → running → (waiting ⇄ running) → completed
                         └→ failed | cancelled | paused
execution: pending → running → succeeded
                         ├→ waiting → running (resumed)
                         ├→ failed (→ retry: new attempt = pending)
                         ├→ skipped   (branch not taken)
                         └→ consumed  (terminated / join loser)
```

Every transition is guarded (e.g. never complete a `cancelled` instance; never resume a `consumed` execution).

---

## 7. Triggers & dispatcher

Single entry point for all triggers:

```php
class WorkflowDispatcher
{
    public function dispatch(
        Workflow $workflow,
        WorkflowVersion $version,
        array $payload,
        TriggerType $trigger,
        ?string $correlationId = null,
    ): WorkflowInstance;
}
```

Steps: dedup on `correlation_id` (return existing instance if seen) → **admission check** (§11; create
`pending` if tenant at cap) → create instance (`running`, `trigger_type`, `payload`) → compile/load plan →
create the trigger node's `WorkflowNodeExecution` → queue `ExecuteNodeJob` → emit `InstanceStarted`.

| Trigger | Entry | Notes |
|---|---|---|
| **Manual** | `POST /{workflow}/trigger/manual` (`auth:api`) | actor recorded; payload = `manual-trigger` test variables |
| **Form** | `POST /{workflow}/trigger/form` (public, respects `accessLevel`) | validate submission against `form-trigger.formFields`; rate-limit; `correlation_id` = submission id |
| **Webhook** | `POST /{workflow}/trigger/webhook` (existing route) | **verify HMAC**; `correlation_id` = delivery id; **rewrite** `WorkflowManagementService::triggerWebhook()` to call the dispatcher instead of only inserting a row |

Trigger nodes have trivial executors: validate/normalize payload into context, then `proceed` to successors.

---

## 8. Reliability: retries, idempotency, timeouts

### 8.1 Failure classification

```php
class FailureClassifier
{
    public function isRetryable(Throwable $e): bool;  // transient (network/429/5xx/timeout) vs permanent (4xx/validation/config)
}
```

Map provider exceptions + HTTP status to transient/permanent
([recommendations §4](../execution-engine-recommendations.md#4-failure-handling--retries)). A per-attempt
**timeout breach is transient** → consumes an attempt.

### 8.2 Retry policy

```php
class RetryPolicy
{
    public function shouldRetry(NodeExecutor $e, int $attempt, Throwable $err): bool;
    public function delayFor(int $attempt): int;   // exp backoff + jitter, capped at max_delay
}
```

Defaults from config; per-node config overrides. **Logic nodes don't retry** (deterministic); action/AI nodes
do. A retry creates the next `attempt` as a fresh `pending` execution row (auditable) and re-queues with
`delayFor()`.

### 8.3 Idempotency / effectively-once

The crux for `send-email` and any side effect
([recommendations §8.6](../execution-engine-recommendations.md#86-exactly-once-for-external-actions--recommendation)):

1. The `node_execution` row (with `idempotency_key`, `running`) is committed **before** the external call. A
   redelivery/crash that finds a `succeeded` row reuses its `output` and skips the call.
2. Actions pass a provider idempotency key where supported; `send-email` sets a **deterministic `Message-ID`**
   derived from `idempotency_key` so duplicate sends collapse at the mail layer.
3. Extend the connector contract: `IntegrationAction::isIdempotent(): bool` (default `false`).
4. **Crash recovery:** an action found `running` after a crash → if idempotent, retry; if non-idempotent
   (Gmail), **pause** the instance (`paused_reason = needs_review`) rather than risk a double effect.

### 8.4 Timeouts & deadlines

- Per-attempt wall-clock from `category()` + config (`30/60/120s`); enforced by the job `timeout` and an
  executor guard.
- Global instance deadline = `started_at + config(max_instance_duration)`; `ExpireOverdueInstancesCommand`
  (daily) fails overdue instances and cancels parked waits/tasks.

---

## 9. Per-node executor specifications

Each executor is pure given its `NodeExecutionContext` and returns a `NodeExecutionResult`.

### 9.1 Trigger executors (`manual-trigger`, `form-trigger`, `webhook-trigger`)
- **Do:** normalize/validate the trigger payload into `context` (form: validate against `formFields`).
- **Return:** `proceed(outgoing edges)`.
- **Fail:** invalid payload → permanent fail (no retry).

### 9.2 `IfNodeExecutor` (`if-node`)
- **Do:** `evaluateBoolean(config.conditionExpression, ctx)`.
- **Return:** `branch(true-edge)` or `branch(false-edge)` by `source_handle` / `is_default_branch`; mark the
  untaken edge's path `skipped`.
- **Fail:** unresolvable expression → permanent fail (authoring should have caught it; defensive).

### 9.3 `SwitchNodeExecutor` (`switch`)
- **Do:** resolve `config.variable`; match against `config.options`.
- **Return:** `branch(matching-edge)` or the default edge; no match & no default → permanent fail.

### 9.4 `ForkNodeExecutor` (`and-node`) and `MergeNodeExecutor` (`merge`) — fork/join

**Fork:** `proceed(all fork branches)` — `applyResult` creates one child execution per branch sharing a
`fork_group`, each with `parent_execution_id = fork execution`.

**Merge (the hard part):**

```php
public function execute(NodeExecutionContext $ctx): NodeExecutionResult
{
    $join = $ctx->plan()->joinFor($ctx->nodeKey());          // {mode, expected_count} from compiled plan

    return DB::transaction(function () use ($ctx, $join) {
        // one merge row per (instance, node) — first arriving branch creates it
        $merge = WorkflowNodeExecution::lockForUpdate()->firstOrCreate(
            ['instance_id' => $ctx->instanceId(), 'node_key' => $ctx->nodeKey(), 'attempt' => 1],
            ['node_type' => 'merge', 'expected_count' => $join->expectedCount,
             'idempotency_key' => $ctx->idempotencyKey(), 'status' => Waiting,
             'wait_type' => $join->mode === 'parallel' ? WaitType::MergeTimeout : null,
             'wait_until' => $join->timeout ? now()->addSeconds($join->timeout) : null],
        );
        $merge->increment('arrived_count');                  // atomic under the lock

        $satisfied = $join->mode === 'conditional'           // first arrival wins
            ? $merge->arrived_count === 1
            : $merge->arrived_count >= $merge->expected_count; // wait-all

        // the arriving branch token is consumed either way
        $ctx->execution()->update(['status' => Consumed]);

        return $satisfied
            ? NodeExecutionResult::proceed($ctx->plan()->outgoing($ctx->nodeKey()))
            : NodeExecutionResult::noop();                   // not last: nothing downstream yet
    });
}
```

- **Parallel:** completes when `arrived_count >= expected_count`, or the `merge_timeout` timer fires → fail (or
  proceed-with-partial if configured).
- **Conditional:** first arrival proceeds; later arrivals find `satisfied=false`-after-first and are
  `consumed` (discarded).
- Loops are out of scope, so `attempt = 1` is always correct (each join activates once).

### 9.5 `TerminationNodeExecutor` (`termination-node`)
- **Return:** `terminate()` → execution `consumed`; runtime runs the completion check.

### 9.6 `SendEmailExecutor` (`send-email`)
- **Do:** `interpolator.render()` subject/body/to/cc/bcc; resolve the tenant's Gmail `IntegrationConnection`;
  call `SendEmailAction::handle($payload, $connection)` with a deterministic `Message-ID`.
- **Return:** `proceed(outgoing, output: {message_id, ...})`.
- **Fail:** transient (network/429/5xx) → retry; permanent (no connection / bad recipient) → fail. Idempotency
  per §8.3.

### 9.7 `AiGeneratorExecutor` (`ai-generator`)
- **Do:** render `prompt`/`tone`, attach `knowledgeBaseDocuments`, call the AI provider (use the latest Claude
  model per platform guidance), write `config.outputVariable` into context.
- **Return:** `proceed(outgoing, output)`. Retryable on transient provider errors.
- **Note:** depends on the AI integration; keep behind an interface and stub in tests so M1 isn't blocked.

### 9.8 `TaskNodeExecutor` (`task-node`) — durable human wait
- **Do:** create a `workflow_tasks` row (assignee from `config.assignTo`, `due_at = now + dueWithin`,
  `input_schema = config.inputFields`).
- **Return:** `wait(until: due_at, type: task_sla)` — parks the execution; instance goes `waiting` when no
  runnable executions remain. **No job queued.**
- **Resume:** `WorkflowTaskController@submit` writes `response` into context, marks task `completed`, flips the
  parked execution to `pending`, queues `ExecuteNodeJob` → `proceed` to successors.
- **SLA breach:** timer scan fires; default = mark task `overdue` + reminder event, keep waiting; if
  `expire_on_overdue` → route as failed.

---

## 10. Events & observability

Emit at every transition; a listener appends a `WorkflowEvent` row and (optionally) broadcasts.

```
InstanceStarted · NodeStarted · NodeCompleted · NodeFailed · NodeRetrying
· TokenWaiting · InstanceCompleted · InstanceFailed · InstancePaused · InstanceCancelled
```

Consumers: audit log (`workflow_events`), metrics (durations/failure rates), real-time UI (broadcast — behind
a flag), and — in Phase 3 — event-triggered workflows. `WorkflowInstanceController` exposes run list + per-node
execution timeline for the UI.

---

## 11. Backpressure & admission control

In-scope, database-queue-friendly design
([recommendations §8.7](../execution-engine-recommendations.md#87-backpressure--fairness--deep-dive-you-asked-to-research)):

```php
class InstanceAdmissionService
{
    public function admit(Workflow $w): bool;   // false → caller creates instance as `pending`
    // running count per tenant vs config(max_concurrent_instances_per_tenant)
}
```

- `WorkflowDispatcher` calls `admit()`. Under cap → `running` + queue. At cap → instance saved `pending`, no job.
- `AdmitPendingInstancesCommand` (per-minute) promotes `pending → running` (oldest-first, fair across tenants)
  as capacity frees, queuing their first job. Optional per-minute token bucket caps admission *rate*.
- **Named queues** (`workflow-control` vs `workflow-actions`) keep slow Gmail I/O from blocking orchestration —
  the cheapest, highest-impact isolation.
- Secondary `Cache::lock()` semaphore on action jobs only if single-instance fan-out can still overwhelm
  workers. Redis throttling + per-provider rate limits are **Phase 3**.

---

## 12. Sequence sketches

**Linear run**
```
Trigger → Dispatcher.dispatch → instance(running) + exec#1(pending) → ExecuteNodeJob#1
  advance(#1): trigger proceed → exec#2(pending) + Job#2
  advance(#2): send-email succeeded → exec#3(pending) + Job#3
  advance(#3): termination consumed → completion check → instance(completed)
```

**Fork / join (parallel)**
```
advance(fork): proceed[A,B] → exec(A),exec(B) share fork_group → Job(A),Job(B)
  advance(A): succeeded → exec(merge) Job; advance(merge): firstOrCreate, arrived=1 < 2 → noop
  advance(B): succeeded → exec(merge) Job; advance(merge): lock, arrived=2 == 2 → proceed downstream
```

**Human task wait → resume**
```
advance(task): create workflow_tasks, wait(task_sla, due_at) → exec(waiting); instance(waiting); no job
... hours later ...
POST /tasks/{id}/submit → write context, task(completed), exec(pending) → ExecuteNodeJob → proceed
(or) timer scan at due_at → reminder + keep waiting  (default)
```

**Retry + idempotency**
```
advance(send-email) attempt#1: commit exec(running, key) → API throws 503 (transient)
  → RetryPolicy.shouldRetry → exec attempt#2 (pending), re-queue with backoff
advance(send-email) attempt#2: API ok → succeeded(output)   # Message-ID dedupes any duplicate from #1
```

**Admission control**
```
dispatch (tenant at cap) → instance(pending), no job
AdmitPendingInstancesCommand → capacity free → instance(running) + first Job
```
