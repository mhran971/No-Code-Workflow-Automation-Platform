# Execution Engine — Schema & Migrations

> Companion to [`IMPLEMENTATION-PLAN.md`](IMPLEMENTATION-PLAN.md). Concrete, additive schema for the engine.
> Decisions behind these shapes live in
> [`../execution-engine-recommendations.md` §5 & §8](../execution-engine-recommendations.md#5-schema-recommendations).
>
> Status: **plan only.** Blueprints are illustrative (final column types/lengths to be confirmed in review).
> Conventions follow the existing module migrations: `tenant_id` on every table, `foreignId()->constrained()`,
> `json` columns cast to `array`, snake_case keys, `branch_type`-style string enums.

---

## 1. Overview

The engine adds **three new tables** and **extends one**. Per the migration note on `workflow_instances`,
everything is additive — no drops/renames of existing columns.

| Table | New? | Purpose |
|---|---|---|
| `workflow_instances` | extend | Per-run header: status, trigger, context, error, nesting |
| `workflow_node_executions` | new | **Unified runtime state**: executions + tokens + joins + timers |
| `workflow_tasks` | new | Human-task projection for `task-node` (assignee inbox + response) |
| `workflow_events` | new | Append-only lifecycle/audit log |

Folded-in by decision (no separate tables): `workflow_execution_tokens`, `workflow_joins`, `workflow_timers`
→ all represented as columns on `workflow_node_executions`
([recommendations §5.2–5.4](../execution-engine-recommendations.md#52-workflow_node_executions--unified-runtime-state-table)).
Deferred to Phase 3 (out of scope): `workflow_schedules`, `workflow_event_subscriptions`.

---

## 2. Migrations

One migration per concern, in this order.

### 2.1 Extend `workflow_instances`

```php
// ..._extend_workflow_instances_for_execution.php
public function up(): void
{
    Schema::table('workflow_instances', function (Blueprint $table) {
        $table->string('trigger_type')->default('manual')->after('status');   // TriggerType
        $table->string('correlation_id')->nullable()->after('trigger_type');   // idempotency / external delivery id
        $table->json('context')->nullable()->after('payload');                 // runtime variable store (§8.1)
        $table->json('error')->nullable()->after('context');                   // failure detail when status=failed
        $table->string('paused_reason')->nullable()->after('error');           // e.g. needs_review
        $table->foreignId('parent_instance_id')->nullable()->after('workflow_version_id')
              ->constrained('workflow_instances')->nullOnDelete();             // reserved: sub-workflow (Phase 3)

        $table->index(['tenant_id', 'correlation_id']);                        // dedup lookups
    });
}
```

Notes:

- **No `deadline_at` column** — the global deadline is computed as `started_at + config(max_instance_duration)`
  ([recommendations §8.5c](../execution-engine-recommendations.md#85-per-node-timeouts--global-deadline--recommendation)).
- `status` stays a string column; the **enum gains** `pending|waiting|paused` (§3).
- `payload` (existing) keeps the raw trigger input; `context` is the evolving variable store. Keep them
  separate so the original trigger payload is always recoverable for replay.

### 2.2 `workflow_node_executions`

The heart of the engine. Every non-terminal row is an active token; `merge` rows carry join counters; waiting
rows carry their wake-up time.

```php
public function up(): void
{
    Schema::create('workflow_node_executions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

        $table->string('node_key');                 // matches definition node id
        $table->string('node_type');                // denormalized for fast filtering / replay
        $table->string('status')->default('pending'); // NodeExecutionStatus
        $table->unsignedInteger('attempt')->default(1);
        $table->string('idempotency_key')->unique(); // instance:node:attempt-group

        $table->json('input')->nullable();
        $table->json('output')->nullable();
        $table->json('error')->nullable();

        // token lineage (subsumes workflow_execution_tokens)
        $table->foreignId('parent_execution_id')->nullable()
              ->constrained('workflow_node_executions')->nullOnDelete();
        $table->string('fork_group')->nullable();   // groups sibling branches from one fork

        // join state (subsumes workflow_joins; set only on `merge` rows)
        $table->unsignedInteger('expected_count')->nullable();
        $table->unsignedInteger('arrived_count')->default(0);

        // durable wait / timer (subsumes workflow_timers)
        $table->timestamp('wait_until')->nullable();
        $table->string('wait_type')->nullable();    // WaitType

        $table->timestamp('started_at')->nullable();
        $table->timestamp('finished_at')->nullable();
        $table->timestamps();

        $table->unique(['instance_id', 'node_key', 'attempt']);
        $table->index(['instance_id', 'status']);
        $table->index(['status', 'wait_until']);     // timer scan
    });
}
```

Why these indexes:

- `unique(instance_id, node_key, attempt)` — the idempotency / exactly-once-advance guard, and lets the
  `merge` join do a safe `firstOrCreate`.
- `index(instance_id, status)` — "what is still running/waiting for this instance?" (completion check).
- `index(status, wait_until)` — the per-minute timer scan: `WHERE status='waiting' AND wait_until <= now()`.

### 2.3 `workflow_tasks`

Human-facing projection. Resume/timeout is driven by the parked `node_execution`; this table powers the
assignee inbox and stores the response.

```php
public function up(): void
{
    Schema::create('workflow_tasks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
        $table->foreignId('execution_id')->constrained('workflow_node_executions')->cascadeOnDelete();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->string('node_key');

        $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('title');
        $table->text('description')->nullable();
        $table->json('input_schema')->nullable();   // from node config `inputFields`

        $table->string('status')->default('open');   // open|completed|expired|cancelled
        $table->timestamp('due_at')->nullable();      // == execution.wait_until
        $table->json('response')->nullable();
        $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->index(['assignee_id', 'status']);     // inbox
        $table->index(['tenant_id', 'status']);
    });
}
```

### 2.4 `workflow_events`

Append-only. One row per lifecycle transition. Drives audit, debugging, and (later) real-time UI.

```php
public function up(): void
{
    Schema::create('workflow_events', function (Blueprint $table) {
        $table->id();
        $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->string('node_key')->nullable();
        $table->string('type');                       // InstanceStarted, NodeCompleted, ...
        $table->json('payload')->nullable();
        $table->timestamp('created_at')->nullable();  // append-only; no updated_at

        $table->index(['instance_id', 'created_at']);
        $table->index(['tenant_id', 'type']);
    });
}
```

> Volume note: this grows fast. Add a retention/prune command (or partition by month) before production. Not
> required for v1 correctness.

---

## 3. Enums

`app/Enums/`, matching the existing string-backed style (e.g. `WorkflowInstanceStatus`).

```php
enum NodeExecutionStatus: string {
    case Pending   = 'pending';    // created, not yet run (an active token)
    case Running   = 'running';    // executing now (or side effect in flight)
    case Succeeded = 'succeeded';
    case Failed    = 'failed';
    case Waiting   = 'waiting';    // parked on a durable wait (task / merge)
    case Skipped   = 'skipped';    // branch not taken
    case Consumed  = 'consumed';   // join loser / terminated token
}

enum TriggerType: string {
    case Manual  = 'manual';
    case Form    = 'form';
    case Webhook = 'webhook';
    // Phase 3: Schedule = 'schedule'; Event = 'event'; SubWorkflow = 'sub_workflow';
}

enum WaitType: string {
    case TaskSla      = 'task_sla';
    case MergeTimeout = 'merge_timeout';
}
```

**Extend existing** `app/Enums/WorkflowInstanceStatus.php`:

```php
case Pending = 'pending';   // admitted-but-not-started (admission control)
case Waiting = 'waiting';   // all live tokens parked on waits
case Paused  = 'paused';    // needs_review (ambiguous non-idempotent recovery) / manual hold
// existing: Running, Completed, Failed, Cancelled
```

---

## 4. Models

`app/Models/`. Follow the existing pattern (`protected $fillable`, `casts()` method, typed relations).

### 4.1 `WorkflowNodeExecution`

```php
class WorkflowNodeExecution extends Model
{
    protected $fillable = [
        'instance_id', 'tenant_id', 'node_key', 'node_type', 'status', 'attempt',
        'idempotency_key', 'input', 'output', 'error',
        'parent_execution_id', 'fork_group',
        'expected_count', 'arrived_count',
        'wait_until', 'wait_type',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status'     => NodeExecutionStatus::class,
            'wait_type'  => WaitType::class,
            'input'      => 'array', 'output' => 'array', 'error' => 'array',
            'attempt'    => 'integer', 'expected_count' => 'integer', 'arrived_count' => 'integer',
            'wait_until' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo { return $this->belongsTo(WorkflowInstance::class, 'instance_id'); }
    public function parent(): BelongsTo   { return $this->belongsTo(self::class, 'parent_execution_id'); }
    public function task(): HasOne        { return $this->hasOne(WorkflowTask::class, 'execution_id'); }
}
```

### 4.2 `WorkflowTask` & `WorkflowEvent`

Straightforward models: `WorkflowTask` casts `input_schema`/`response` to `array`, `due_at`/`completed_at` to
`datetime`, `status` to a small enum (or string for v1); relations to `instance`, `execution`, `assignee`.
`WorkflowEvent` casts `payload` to `array`, `created_at` to `datetime`, `$timestamps = false` with manual
`created_at` (append-only).

### 4.3 Extend `WorkflowInstance`

Add to `$fillable`: `trigger_type, correlation_id, context, error, paused_reason, parent_instance_id`. Add to
`casts()`: `context`/`error` → `array`. Add relations:

```php
public function nodeExecutions(): HasMany { return $this->hasMany(WorkflowNodeExecution::class, 'instance_id'); }
public function tasks(): HasMany          { return $this->hasMany(WorkflowTask::class, 'instance_id'); }
public function events(): HasMany         { return $this->hasMany(WorkflowEvent::class, 'instance_id'); }
public function parent(): BelongsTo       { return $this->belongsTo(self::class, 'parent_instance_id'); }
```

---

## 5. Config

Add an `execution` block to `Modules/Workflows/config/config.php` so caps/timeouts are tunable without code
changes (values map directly to the decisions in
[recommendations §8.5–8.7](../execution-engine-recommendations.md#85-per-node-timeouts--global-deadline--recommendation)):

```php
'execution' => [
    'queues' => [
        'control' => env('WORKFLOW_QUEUE_CONTROL', 'workflow-control'),
        'actions' => env('WORKFLOW_QUEUE_ACTIONS', 'workflow-actions'),
    ],
    'timeouts' => [           // per-attempt wall-clock seconds (§8.5a)
        'logic'  => 30,
        'action' => 60,
        'ai'     => 120,
    ],
    'retry' => [              // defaults; per-node config overrides (§4)
        'max_attempts' => 3,
        'base_delay'   => 5,   // seconds
        'max_delay'    => 300,
        'strategy'     => 'exponential',
    ],
    'max_instance_duration' => 30 * 24 * 60, // minutes → 30 days global deadline (§8.5c)
    'admission' => [          // per-tenant backpressure (§8.7)
        'max_concurrent_instances_per_tenant' => 20,
        'max_admitted_per_minute_per_tenant'  => null, // optional token bucket
    ],
    'task' => [
        'default_due_within_hours' => 24,   // seeder default for task-node
        'expire_on_overdue'        => false, // keep waiting by default (§8.5b)
    ],
],
```

---

## 6. Entity relationships

```
workflows ─1─┬─< workflow_versions ──1──< workflow_instances ─1─┬─< workflow_node_executions ─1─< workflow_tasks
             │   (immutable definition)   (one per run)         ├─< workflow_events
             │                                                  └─ (self) parent_instance_id  [Phase 3]
             └─ draft_definition (never executed)

workflow_node_executions ─ self ─ parent_execution_id   (token lineage / fork tree)
```

- A run = one `workflow_instance` bound to one immutable `workflow_version`.
- Its live state = the set of `workflow_node_executions` in non-terminal status.
- A `merge` execution row is the join sync point (`expected_count`/`arrived_count`).
- A waiting execution row is the timer (`wait_until`/`wait_type`); `workflow_tasks` mirrors the human ones.
