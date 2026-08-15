# Workflows Module

Design-time workflow authoring (canvas graph → versioned definition) plus a token-based, queue-driven runtime that executes published versions. This is the platform's core module — see the [repo-level CLAUDE.md](../../../CLAUDE.md) for how it fits into the wider app.

This file is the module-level entry point. For the verification subsystem specifically, start at [`README_VERIFICATION.md`](README_VERIFICATION.md) instead — it has its own navigation, glossary, and learning paths and is more detailed than the summary below.

## Data Model

All models in `app/Models/`. Migrations in `database/migrations/` (23 files, one per schema change — check there for exact column history rather than assuming from model `$fillable`).

| Model | Key relationships | Notes |
|---|---|---|
| `Workflow` | `tenant`, `team`, `template`, `createdBy`, `currentVersion` (belongsTo `WorkflowVersion`), `versions` (hasMany), `instances` (hasMany) | The design-time record; `status` is `WorkflowStatus` (active/disabled/deleted, soft-delete-like). |
| `WorkflowVersion` | `workflow`, `tenant`, `publishedBy` | **Immutable** once published — holds the `definition` JSON (nodes+edges) that `ExecutionPlanCompiler` compiles from. This is the only thing the runtime reads; never the live `WorkflowNode`/`WorkflowEdge` rows. |
| `WorkflowNode` / `WorkflowEdge` | belong to `workflow` + `version` | The **canvas** graph — what the frontend edits and what verification runs against pre-publish. Not read by the execution engine. |
| `WorkflowTemplate` | `tenant`, `createdBy` | Prebuilt definitions surfaced via `GET /templates` (seeded by `WorkflowTemplateSeeder`). |
| `WorkflowInstance` | `workflow`, `workflowVersion`, `tenant`, `parent`/`parentExecution` (self-referential for sub-workflow/dynamic-flow children), `nodeExecutions` (hasMany), `tasks` (hasMany), `events` (hasMany), `dynamicFlow`/`dynamicFlows`, `childInstances`, `customer` (belongsTo `Modules\Customers\Models\Customer`, nullable) | One per trigger firing. `status` is `WorkflowInstanceStatus` (pending/running/waiting/paused/completed/failed/cancelled). `customer_id` is set by the opt-in "customer context" trigger feature — see [`Modules/Customers/docs/README.md`](../../Customers/docs/README.md). |
| `WorkflowNodeExecution` | `instance`, `tenant`, `parent`/`children` (self-referential join tracking), `task` (hasOne) | The **runtime token** — one row per node visit. `status` is `NodeExecutionStatus` (pending/running/succeeded/failed/waiting/skipped/consumed). This table, not `WorkflowNode`, is what the engine advances. |
| `WorkflowTask` | `instance`, `execution`, `tenant`, `assignee`, `completedBy` | Human-in-the-loop step created by `task-node`; has an SLA (`dueWithin`) enforced by `ExpireOverdueInstancesCommand`. |
| `WorkflowDynamicFlow` | `tenant`, `instance`, `execution`, `createdBy`, `childInstance` | Backs the `dynamic-flow` pause/design/resume cycle. `status` is `DynamicFlowStatus` (awaiting_design/executing/completed/cancelled). |
| `WorkflowEvent` | `instance`, `tenant` | Audit log. Currently written only by the dynamic-flow executor/controller (`dynamic_flow.requested`, `dynamic_flow.completed`) — not a general-purpose event log yet. |
| `WorkflowInstanceAttachment` / `WorkflowInstanceComment` | `workflowInstance`, `uploadedBy`/`author` | Task file uploads and comments; gated by `WorkflowInstanceAttachmentPolicy` / `WorkflowInstanceCommentPolicy`. |
| `Node` / `NodeConfigField` | `Node hasMany NodeConfigField` | The **node type catalog** (not a workflow's nodes) — seeded by `NodeDefinitionSeeder`, surfaced via `GET /nodes`. Drives the frontend's node palette + config forms. |

## Services

### Design-time (`app/Services/`)

| Service | File | Purpose |
|---|---|---|
| `WorkflowManagementService` | `WorkflowManagementService.php` | List/create/update-draft/update-status/soft-delete/purge. Plain class, no `BaseService`. |
| `WorkflowVersioningService` | `WorkflowVersioningService.php` | Publish (runs verification first) + version history. Extends `BaseService`; uses `DB::transaction()` closures internally (deviates from the controller-owns-transactions convention other modules follow — see repo-level CLAUDE.md). `assertNotDeleted()` is defined on this class as well as `WorkflowManagementService` (identical duplicated body, not inherited/shared) — previously it was only on `WorkflowManagementService`, so every `publish()` call threw `BadMethodCallException` via `BaseService::__call()`; fixed. |
| `WorkflowTriggeringService` | `WorkflowTriggeringService.php` | `triggerWebhook` / `triggerManual` → delegates to `WorkflowDispatcher` to create an instance. |
| `WorkflowAuthorizationService` | `WorkflowAuthorizationService.php` | Extends `BaseService`. `canView`/`canManage`/`assertBusinessOwner`/`assertSameTenant` — tenant + role gating for workflow access. |
| `WorkflowTemplateService` | `WorkflowTemplateService.php` | Extends `BaseService`. Lists/resolves `WorkflowTemplate`s. |
| `WorkflowCommentsService` / `WorkflowAttachmentService` | same names | List/create/delete comments and file attachments on a task, authorization via the Policies above. |
| `WorkflowInstanceContextService` | `WorkflowInstanceContextService.php` | Resolves the owning `WorkflowInstance` from a `WorkflowTask` (used by mobile task endpoints). |
| `PublicFormService` | `PublicFormService.php` | Backs the unauthenticated public form endpoints: resolves workflow by public token, builds form schema, validates + submits (via `WorkflowDispatcher`). |

### Verification (`app/Services/Verification/`) — see [README_VERIFICATION.md](README_VERIFICATION.md)

`WorkflowVerificationService` orchestrates 8 `Rules/*` (below) over a `WorkflowDefinitionGraph`, after `WorkflowDefinitionNormalizer` normalizes legacy field aliases. `ExpressionLanguageValidator` (+ `ControlFlowReducer`, `DataFlowAnalyzer`) support the expression- and data-flow-related rules.

### Execution (`app/Services/Execution/`)

| Service | File | Purpose |
|---|---|---|
| `WorkflowDispatcher` | `WorkflowDispatcher.php` | Single entry point for all trigger types. Creates the `WorkflowInstance` + first `WorkflowNodeExecution`, queues `ExecuteNodeJob`. |
| `ExecutionPlanCompiler` | `ExecutionPlanCompiler.php` | Compiles a `WorkflowVersion.definition` (or raw array) into an `ExecutionPlan` (nodes indexed, edges resolved). Cached forever per version id. |
| `NodeRunner` | `NodeRunner.php` | `advance(executionId)` — claims the execution row, resolves the executor via `NodeExecutorRegistry`, runs it, hands the `NodeExecutionResult` to `WorkflowExecutionEngine`. Also `cancel()` and `retryFromNode()`. |
| `WorkflowExecutionEngine` | `WorkflowExecutionEngine.php` | State machine: `onSucceed`/`onFail`/`onWait`/`onTerminate`/`onConsume`. On success, advances to downstream nodes per the compiled plan and queues further `ExecuteNodeJob`s. |
| `NodeExecutorRegistry` | `NodeExecutorRegistry.php` | Node type string → `NodeExecutor` instance. Populated in `WorkflowsServiceProvider::register()`; supports aliasing (`registerAs()`) — e.g. legacy `merge-or` aliases to the same executor as `merge`. |
| `MergeCoordinator` | `MergeCoordinator.php` | Synchronizes branches arriving at a `merge` node — parallel (wait for all via `JoinSpec`) or conditional (first-to-arrive, rest become `Consumed`). |
| `FailureClassifier` / `RetryPolicy` | same names | Classify a node failure as retryable, compute backoff. |
| `InstanceLifecycleManager` | `InstanceLifecycleManager.php` | Instance-level status transitions (running/waiting/completed/failed/cancelled), fired as instance-level events wrap up. |
| `InstanceAdmissionService` (`Admission/`) | `InstanceAdmissionService.php` | `canAdmit(tenantId)` — tenant-level concurrency/admission gate; instances that can't be admitted stay `Pending` until `AdmitPendingInstancesCommand` promotes them. |
| `EventBroadcaster` | `EventBroadcaster.php` | Dispatches lifecycle events after `DB::afterCommit()`; forwards child-instance (sub-workflow/dynamic-flow) events up to the parent via `ChildInstanceEventForwarded`. |
| `Expression/ExpressionEvaluator` + `TemplateInterpolator` | `Expression/` | Runtime evaluation of `if-node`/`switch` conditions and `{{context.x}}` template interpolation in node configs (e.g. email subject/body). Distinct from the design-time `ExpressionLanguageValidator` parser used by verification. |
| Executors (`Executors/`, implement `Contracts/NodeExecutor`) | one file per node type | `ManualTriggerExecutor`, `FormTriggerExecutor`, `WebhookTriggerExecutor`, `IfNodeExecutor`, `SwitchNodeExecutor`, `ForkNodeExecutor`, `MergeNodeExecutor`, `TerminationNodeExecutor`, `SendEmailExecutor`, `ParseJsonExecutor`, `ClickUpCreateTaskExecutor`, `TaskNodeExecutor`, `SubWorkflowExecutor`, `DynamicFlowExecutor`, `DynamicEntryExecutor` — all registered in `WorkflowsServiceProvider`. `AiGeneratorExecutor` also exists in this folder but is **not** registered — see Gotchas. Each returns a `NodeExecutionResult` tagged with a `ResultKind` (Proceed/Branch/Wait/Fail/Terminate/Noop). |

Scheduled console commands (`app/Console/Commands/`, registered in `WorkflowsServiceProvider::boot()`): `workflows:scan-timers` (every minute — resolves durable waits like task SLA/merge timeout), `workflows:admit-pending` (every minute — promotes admitted `Pending` instances), `workflows:expire-overdue` (daily — expires overdue tasks/instances).

## Verification Pipeline

9 rules run in order inside `WorkflowVerificationService::verify()` (`app/Services/Verification/WorkflowVerificationService.php`): `SyntaxVerificationRule` → `GraphControlFlowVerificationRule` → `StructuredControlFlowVerificationRule` → `ExpressionVerificationRule` → `ContextualVerificationRule` → `NodeTypeVerificationRule` → `DataFlowVerificationRule` → `FormTriggerVerificationRule` → `CustomerContextVerificationRule`.

`CustomerContextVerificationRule` validates the opt-in "customer context" mapping on `manual-trigger`/`form-trigger` nodes (mapped field selected, matches a declared trigger field, is `required: true` for form-trigger, and the tenant has a linking field configured) — see [`Modules/Customers/docs/README.md`](../../Customers/docs/README.md).

`NodeTypeVerificationRule` fans out to per-node-type `Rules/NodeType/*` (registered via DI tagging in `WorkflowsServiceProvider`, keyed by each rule's `nodeType()` — there's no `addNodeTypeRule()` method). Each top-level rule implements `skipForSegment(): bool`, honored when verifying a `dynamic-flow` segment (`VerificationMode::Segment` — a mid-execution sub-flow with no trigger/saved workflow context).

Full detail, glossary, and "how to add a rule" walkthroughs: [README_VERIFICATION.md](README_VERIFICATION.md), [VERIFICATION_ARCHITECTURE.md](VERIFICATION_ARCHITECTURE.md), [VERIFICATION_RULES.md](VERIFICATION_RULES.md), [VERIFICATION_DATA_STRUCTURES.md](VERIFICATION_DATA_STRUCTURES.md), [VERIFICATION_USAGE_GUIDE.md](VERIFICATION_USAGE_GUIDE.md).

## Node Types

Seeded by `NodeDefinitionSeeder` (`database/seeders/NodeDefinitionSeeder.php`) into `Node`/`NodeConfigField`:

| Category | Key | Label | Description |
|---|---|---|---|
| Trigger | `manual-trigger` | Manual | Manual start with test variables. Optional `customerContextEnabled`/`customerContextField` — see [`Modules/Customers/docs/README.md`](../../Customers/docs/README.md). |
| Trigger | `form-trigger` | Form Trigger | Public or tenant-gated form submission. Same optional customer-context fields as `manual-trigger`. |
| Trigger | `dynamic-entry` | Entry Point | Sub-flow entry point, receives parent context |
| Logic | `if-node` | If | Conditional branching |
| Logic | `and-node` | Fork | Splits into parallel branches |
| Logic | `merge` | Merge | Synchronizes branches — `mergeMode`: parallel (wait for all) or conditional (first arrives) |
| Logic | `switch` | Switch | Multi-path routing on a value |
| Logic | `parse-json` | Parse JSON | Parses a stringified JSON `inputVariable` into an object, written to `outputVariable` |
| Logic | `termination-node` | Terminate | Marks end of a branch; all branches must terminate for the instance to complete |
| AI | `ai-generator` | AI Generator | Content generation using knowledge-base documents — see [`Modules/KnowledgeBase/docs/README.md`](../../KnowledgeBase/docs/README.md) |
| Flows | `sub-workflow` | Sub Workflow | Executes another workflow as a child instance |
| Flows | `dynamic-flow` | Dynamic Flow | Pauses for a manager to design a runtime sub-flow, then executes it |
| Action | `send-email` | Send Email | Via connected Gmail workspace — see [`Modules/Integrations/docs/README.md`](../../Integrations/docs/README.md) |
| Action | `clickup-create-task` | ClickUp: Create Task | Creates a task in a ClickUp list via the connected ClickUp workspace — see [`Modules/Integrations/docs/README.md`](../../Integrations/docs/README.md). Note: the frontend has an unrelated, unwired mock schema for a different node type string `clickup-task` — don't confuse the two. |
| Action | `task-node` | Task Node | Human task assignment with SLA (`dueWithin`) and reminders |

## API Routes

Prefix `/api/v1/workflows`, behind `auth:api` + `active.user`, defined in `routes/api.php`. Full list kept in the [repo-level CLAUDE.md](../../../CLAUDE.md#api-routes) to avoid duplication — noteworthy points not obvious from the route list alone:

- `POST /broadcasting/auth` exists because Laravel's default `Broadcast::routes()` only supports the `web` guard; this project's users are JWT (`api` guard), so this module defines its own auth endpoint for Reverb private channels.
- Public form endpoints (`GET/POST /api/v1/public/forms/{publicToken}[/submit]`) live **outside** the `/workflows` prefix and outside `auth:api` entirely — gated instead at the `PublicFormService` layer to workflows that are published + active + `trigger.config.accessLevel === 'public'`, and throttled (`30,1`) since they're open to the internet.
- Mobile-specific task file/comment endpoints live under `/tasks/{task}/files` and `/tasks/{task}/comments` — see `docs/mobile-api.md` at the repo root for the mobile client's full contract.

## Cross-Module Dependencies

- **Auth**: every controller resolves the acting `Modules\Auth\Models\User` for tenant scoping and role checks (`WorkflowAuthorizationService::assertBusinessOwner`, etc).
- **Team**: task `assignTo` and role-gated actions reference Team's role/membership model — see `Modules/Team/docs/README.md`.
- **KnowledgeBase**: `ai-generator` node's `knowledgeBaseDocuments` field is consumed by `AiGeneratorExecutor` — see `Modules/KnowledgeBase/docs/README.md`.
- **Integrations**: `send-email` node sends through Integrations' connected Gmail workspace; `clickup-create-task` node creates a task through Integrations' connected ClickUp workspace (`ClickUpClient`) — see `Modules/Integrations/docs/README.md`.
- **Notifications**: `DynamicFlowDesignRequested` (`app/Notifications/`) and task-assignment notifications route through the Notifications module — see `Modules/Notifications/docs/README.md`.
- **Customers**: `manual-trigger`/`form-trigger` executors resolve/create a `Customer` via `Modules\Customers\Services\CustomerResolutionService` when the trigger's optional customer-context mapping is enabled; linked customers become readable as `{{customer.x}}` in downstream templates. `CustomerContextVerificationRule` queries `Modules\Customers\Models\CustomerSettings` at verification time. See `Modules/Customers/docs/README.md` — that module also reaches back into `Workflow`/`WorkflowVersion` in one place (blocking a tenant's linking-field change while a published workflow depends on it).

## Key Business Rules & Gotchas

- The execution engine **never reads `WorkflowNode`/`WorkflowEdge`** — only the compiled, immutable `WorkflowVersion.definition`. Editing a published version's nodes/edges directly will not affect running or future instances of that version; publish a new version instead.
- `ExecutionPlanCompiler::compileVersion()` caches forever per version id — this is safe only because versions are immutable. Never mutate a published `WorkflowVersion.definition` in place.
- `merge-or` is a legacy node-type alias still registered in `NodeExecutorRegistry` (points at the same executor as `merge`), even though `NodeDefinitionSeeder` no longer seeds it as a selectable node type. `merge-and` has no equivalent alias.
- `WorkflowEvent` is not (yet) a general audit log — only the dynamic-flow executor/controller write to it. Don't assume every instance/node event is queryable there; use the broadcast events (`app/Events/`) for that instead.
- Segment verification (`VerificationMode::Segment`, used for dynamic-flow submissions) skips any rule whose `skipForSegment()` returns `true`. When adding a new top-level verification rule, decide this deliberately — the default in a naive implementation would be to run it, which will spuriously fail segments that have no trigger/workflow context.
- **Live bug**: `Workflow::scopeVisibleTo()` (`app/Models/Workflow.php:106`), reached from `WorkflowManagementService::listVisibleWorkflows()` → `GET /api/v1/workflows`, does `$actor->managedTeam->id` for `Role::Manager`. `Modules\Auth\Models\User` has no `managedTeam` relation/property — only a `getManagedTeam()` method (see `Modules/Auth/docs/README.md`). `$actor->managedTeam` resolves to `null` via Eloquent's magic accessor, so `->id` throws `Attempt to read property "id" on null`. **Any Manager-role user listing workflows hits a fatal error today.** Fix by calling `$actor->getManagedTeam()?->id` (and handle the null case — see `Modules/Team/docs/README.md` for what `getManagedTeam()` returns when a Manager has no team).
- **Live bug**: the `ai-generator` node type is fully seeded (`NodeDefinitionSeeder`) and has both a verification rule (`AiGeneratorNodeTypeRule`) and an executor class (`Executors/AiGeneratorExecutor.php`) — but `AiGeneratorExecutor` is never passed to `NodeExecutorRegistry::register()` in `WorkflowsServiceProvider` (compare against the registration list above). A workflow that verifies and publishes fine will throw `UnsupportedNodeTypeException` the moment execution actually reaches an `ai-generator` node. Separately, `AiGeneratorExecutor`'s target interface `Contracts/AiContentGenerator` has no concrete implementation anywhere in the codebase yet, so registering the executor alone wouldn't be enough to make this node type work end-to-end. See `Modules/KnowledgeBase/docs/README.md` for the related finding that the verification-side KB-document check also silently no-ops (field name mismatch: `kbDocs` vs. the seeded `knowledgeBaseDocuments`).

## Testing

- Tests live at the **repo root** `tests/Unit/` (including `tests/Unit/Execution/`) and `tests/Feature/`, not in a `Modules/Workflows/tests/` folder (unlike `Auth`/`Team`/`KnowledgeBase`/`Integrations`, which each have their own).
- `composer test` / `php artisan test`; single test: `php artisan test --filter=TestName`.

## Further Reading

Everything else in this `docs/` folder: [README_VERIFICATION.md](README_VERIFICATION.md) (index), [VERIFICATION_ARCHITECTURE.md](VERIFICATION_ARCHITECTURE.md), [VERIFICATION_RULES.md](VERIFICATION_RULES.md), [VERIFICATION_DATA_STRUCTURES.md](VERIFICATION_DATA_STRUCTURES.md), [VERIFICATION_USAGE_GUIDE.md](VERIFICATION_USAGE_GUIDE.md), [VERIFICATION_OVERVIEW.md](VERIFICATION_OVERVIEW.md), [VERIFICATION_REVIEW.md](VERIFICATION_REVIEW.md), [BUSINESS_LOGIC.md](BUSINESS_LOGIC.md), [DYNAMIC_FLOW_FRONTEND_GUIDE.md](DYNAMIC_FLOW_FRONTEND_GUIDE.md), [class-diagram.md](class-diagram.md).
