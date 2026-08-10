# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# No-Code Workflow Automation Platform

Laravel 12 modular monolith (PHP 8.2+). Users design automation graphs in a React canvas; the backend validates, stores, and executes workflow definitions. Primary module: `Modules/Workflows/`.

## Essential Commands

```bash
# Backend
composer run setup      # first-time: install, .env, key, migrate, npm build
composer run dev        # start everything: server + queue + logs + frontend
composer test           # run PHPUnit (all suites)
php artisan test --filter=TestName   # run a single test
composer run format     # Laravel Pint code style

# Frontend (frontend/)
cd frontend && npm run dev      # Vite dev server
cd frontend && npm run build    # TypeScript + Vite production build
cd frontend && npm run lint     # ESLint
cd frontend && npm run test     # Vitest
```

## Architecture

| Layer | Technology |
|---|---|
| Framework | Laravel 12, `nwidart/laravel-modules` |
| PHP | 8.2+ |
| Database | PostgreSQL (prod/dev), SQLite in-memory (tests) |
| Auth | JWT (`tymon/jwt-auth`) + Sanctum; middleware: `auth:api`, `active.user` |
| Queue / Cache / Session | database driver |
| Realtime | Laravel Reverb (`BROADCAST_CONNECTION=reverb`, `config/reverb.php`); private channels (`App.Models.User.{id}`, `workflow-instance.{instanceId}`) in `routes/channels.php` / `WorkflowsServiceProvider`; JWT users authenticate via `POST /api/v1/workflows/broadcasting/auth` |
| Frontend | React 19 + TypeScript + Vite (`frontend/`) |
| API docs | OpenAPI via `dedoc/scramble`, served at `/docs/api` |

**Modules:** `Auth`, `Integrations`, `KnowledgeBase`, `Notifications`, `Team`, `Workflows` (see `modules_statuses.json`)

### Module Documentation

Each module has a `docs/README.md` with its data model, services, routes, and gotchas — written so you don't have to re-read the module's source to get oriented. Read the relevant one before working in a module you haven't touched yet this session.

| Module | Docs |
|---|---|
| Auth | [`Modules/Auth/docs/README.md`](Modules/Auth/docs/README.md) |
| Integrations | [`Modules/Integrations/docs/README.md`](Modules/Integrations/docs/README.md) (see also `docs/api.md` in the same folder) |
| KnowledgeBase | [`Modules/KnowledgeBase/docs/README.md`](Modules/KnowledgeBase/docs/README.md) |
| Notifications | [`Modules/Notifications/docs/README.md`](Modules/Notifications/docs/README.md) |
| Team | [`Modules/Team/docs/README.md`](Modules/Team/docs/README.md) |
| Workflows | [`Modules/Workflows/docs/README.md`](Modules/Workflows/docs/README.md) (entry point; verification-subsystem deep dive is `README_VERIFICATION.md` in the same folder) |

**Keep these current.** When you change a module's models, services, routes, enums, or a business rule documented in its `docs/README.md`, update that file in the same change — don't leave it to drift. If you discover the doc is already wrong for something unrelated to your change, fix that too while you're there.

### Cross-module conventions

Most modules (`Auth`, `Team`, `KnowledgeBase`, `Integrations`) follow this pattern:

- **Services extend `App\Services\BaseService`**, which delegates unknown method calls to a same-named `Repository` class via `__call()` (e.g. `FooService` → `FooRepository`; override `$repositoryClass` when the convention doesn't match).
- **Controllers own the transaction**: explicit `DB::beginTransaction()` / `commit()` / `rollBack()`, catching custom business exceptions and returning `{"message": "..."}` JSON (typically 422). Services never manage transactions themselves.
- Custom exceptions live in `Modules/<Name>/app/Exceptions/` with static factory methods (e.g. `SomeException::reasonDescription()`); messages are user-facing.
- Complex validation lives in dedicated `Rule` classes under `Modules/<Name>/app/Rules/`, used from Form Requests.

**`Workflows` does not follow this pattern.** Its services are plain classes (some extend `BaseService`, most don't), controllers are thin with no manual transaction handling, and services that need atomicity use `DB::transaction()` closures internally. Don't assume the repository-delegation or controller-transaction convention applies inside `Modules/Workflows/`.

## Workflows Module

All paths relative to `Modules/Workflows/`.

### Key Services

| Service | File | Purpose |
|---|---|---|
| `WorkflowVerificationService` | `app/Services/Verification/WorkflowVerificationService.php` | Orchestrates the verification pipeline |
| `WorkflowManagementService` | `app/Services/WorkflowManagementService.php` | CRUD (list/create/update draft/status/delete) |
| `WorkflowVersioningService` | `app/Services/WorkflowVersioningService.php` | Publish + version history |
| `WorkflowTriggeringService` | `app/Services/WorkflowTriggeringService.php` | Webhook/manual trigger → creates a `WorkflowInstance` |
| `WorkflowDefinitionNormalizer` | `app/Services/Verification/WorkflowDefinitionNormalizer.php` | Normalizes raw definitions (handles legacy field aliases) |
| `ExpressionLanguageValidator` | `app/Services/Verification/ExpressionLanguageValidator.php` | Recursive-descent parser for boolean expressions |
| `ExecutionPlanCompiler` | `app/Services/Execution/ExecutionPlanCompiler.php` | Compiles an immutable `WorkflowVersion.definition` into an `ExecutionPlan` (cached per version id) |
| `WorkflowDispatcher` | `app/Services/Execution/WorkflowDispatcher.php` | Entry point for all trigger types; creates the instance + first node execution |
| `NodeRunner` | `app/Services/Execution/NodeRunner.php` | Advances a single node execution (called from `ExecuteNodeJob`) |
| `NodeExecutorRegistry` | `app/Services/Execution/NodeExecutorRegistry.php` | Maps node type → `NodeExecutor` instance |

### Verification Pipeline

Rules run sequentially in `WorkflowVerificationService::verify()` (`app/Services/Verification/WorkflowVerificationService.php`), in this order:

1. `SyntaxVerificationRule` — structure and data-type validation
2. `GraphControlFlowVerificationRule` — reachability and control-flow logic
3. `StructuredControlFlowVerificationRule` — fork/merge/if structural nesting
4. `ExpressionVerificationRule` — boolean expression parsing
5. `ContextualVerificationRule` — business rule checks
6. `NodeTypeVerificationRule` — per-node-type validation (dispatches to `Rules/NodeType/` sub-rules)
7. `DataFlowVerificationRule` — variable availability/data-flow analysis
8. `FormTriggerVerificationRule` — form-trigger-specific field checks

Each rule implements `VerificationRule` (`app/Services/Verification/Rules/VerificationRule.php`), including `skipForSegment(): bool` — rules that depend on a trigger/saved workflow should return `true` so they're excluded when verifying a `dynamic-flow` segment (`VerificationMode::Segment`, no trigger/workflow context).

**To add a new verification rule:** implement `VerificationRule`, add it to the constructor + `rules()` array in `WorkflowVerificationService`.

**To add a new node-type rule:** implement `NodeTypeRule` (`app/Services/Verification/Rules/NodeType/NodeTypeRule.php`), then add it to the `node-type-rules` tagged array in `WorkflowsServiceProvider::register()` — `NodeTypeVerificationRule` receives all tagged rules via constructor injection (`$this->app->when(...)->needs('$rules')->giveTagged('node-type-rules')`), keyed by each rule's `nodeType()`.

### Execution Engine

Lives in `app/Services/Execution/`. Token-based and queue-driven — it executes from the immutable `workflow_versions.definition`, **not** the design-time `WorkflowNode`/`WorkflowEdge` rows.

- `WorkflowDispatcher` creates a `WorkflowInstance` + the first `WorkflowNodeExecution` row (the runtime "token"), then queues `ExecuteNodeJob`.
- `ExecuteNodeJob` → `NodeRunner::advance()` claims the execution, resolves the right `NodeExecutor` via `NodeExecutorRegistry`, runs it, and hands the result to `WorkflowExecutionEngine::handleResult()`.
- `WorkflowExecutionEngine` transitions the execution (succeed/fail/wait/terminate), and — on success — advances to downstream nodes per the compiled `ExecutionPlan`, queuing further `ExecuteNodeJob`s.
- `MergeCoordinator` synchronizes incoming branches at `merge` nodes (parallel-wait or first-to-arrive, per `mergeMode`); `FailureClassifier` + `RetryPolicy` govern retry/backoff on node failure.
- `EventBroadcaster` dispatches lifecycle events (`NodeStarted`, `NodeCompleted`, `InstanceCompleted`, etc.) after DB commit, and forwards child-instance events (`sub-workflow`/`dynamic-flow`) up to the parent instance via `ChildInstanceEventForwarded`.
- Node executors live in `app/Services/Execution/Executors/` and implement `Contracts/NodeExecutor`; each is registered against a node type string in `WorkflowsServiceProvider::register()` (`NodeExecutorRegistry::register()` / `registerAs()` for aliases, e.g. the legacy `merge-or` type aliases to the same executor as `merge`).
- Scheduled commands (`app/Console/Commands/`): `workflows:scan-timers`, `workflows:admit-pending`, `workflows:expire-overdue` — registered in `WorkflowsServiceProvider::boot()`.

### Node Types (seeded by `NodeDefinitionSeeder`)

| Category | Key | Description |
|---|---|---|
| Triggers | `manual-trigger` | Manual start |
| Triggers | `form-trigger` | Form submission |
| Triggers | `dynamic-entry` | Sub-flow entry point — receives parent context (used by `dynamic-flow` segments) |
| Logic | `if-node` | Conditional branching |
| Logic | `and-node` | Fork — splits into parallel branches |
| Logic | `merge` | Synchronizes incoming branches; `mergeMode` is `parallel` (wait for all) or `conditional` (first to arrive) |
| Logic | `switch` | Multi-path routing based on a value |
| Logic | `termination-node` | Marks the end of a branch; all branches must terminate for the instance to complete |
| AI | `ai-generator` | Content generation, backed by knowledge-base documents |
| Flows | `sub-workflow` | Execute another workflow as a step |
| Flows | `dynamic-flow` | Pauses for a manager to design a runtime sub-flow (segment), then executes it |
| Actions | `send-email` | Email sending with HTML support |
| Actions | `task-node` | Task assignment with SLA (`dueWithin`) and reminders |

### API Routes

Prefix: `/api/v1/workflows` — all routes behind `auth:api`, `active.user` unless noted. See `routes/api.php`.

| Method | Path | Description |
|---|---|---|
| GET | `/nodes` | List available node types |
| POST | `/validate` | Validate a workflow definition |
| GET | `/templates` | Workflow templates |
| POST | `/broadcasting/auth` | Reverb private-channel auth for JWT users |
| GET | `/instances/{instance}` | Show workflow instance |
| GET | `/instances/{instance}/failures` | Instance failure details |
| POST | `/instances/{instance}/cancel` | Cancel a running instance |
| POST | `/instances/{instance}/retry-from-node` | Retry instance from a given node |
| GET | `/instances/{instance}/dynamic-flow` | Show pending dynamic-flow design request |
| POST | `/instances/{instance}/dynamic-flow/definition` | Submit a designed dynamic-flow segment |
| GET | `/tasks/summary` | Human-task inbox summary (counts by state) |
| GET | `/tasks` | List human tasks (role-based) |
| GET | `/tasks/{task}` | Show task detail |
| PATCH | `/tasks/{task}/draft` | Save a draft task response |
| POST | `/tasks/{task}/submit` | Submit a task response |
| GET / POST / DELETE | `/tasks/{task}/files[/{attachment}]` | Task file attachments |
| GET / POST / DELETE | `/tasks/{task}/comments[/{comment}]` | Task comments |
| GET / POST | `/` | List / create workflows |
| GET | `/{workflow}` | Show workflow |
| PATCH | `/{workflow}/draft` | Update draft |
| POST | `/{workflow}/publish` | Publish |
| GET | `/{workflow}/versions` | Version history |
| PATCH | `/{workflow}/status` | Update status |
| DELETE | `/{workflow}` | Soft delete |
| DELETE | `/{workflow}/purge` | Hard delete |
| POST | `/{workflow}/trigger/webhook` | Webhook trigger |
| POST | `/{workflow}/trigger/manual` | Manual trigger |
| GET | `/{workflow}/instances` | List instances for a workflow |

Public, unauthenticated form-trigger endpoints live outside the `/workflows` prefix at `GET/POST /api/v1/public/forms/{publicToken}[/submit]`, throttled (`30,1`) and gated in `PublicFormService` to workflows that are published, active, and marked `trigger.config.accessLevel === 'public'`.

Auth module also exposes `GET /api/v1/me` (profile, role, tenant, team) alongside `POST /register`, `POST /login`, `POST /logout` — see `Modules/Auth/routes/api.php`.

### Models

`Workflow`, `WorkflowVersion`, `WorkflowInstance`, `WorkflowNode`, `WorkflowEdge`, `WorkflowTemplate`, `WorkflowTask`, `WorkflowNodeExecution`, `WorkflowDynamicFlow`, `WorkflowEvent`, `WorkflowInstanceAttachment`, `WorkflowInstanceComment`, `Node`, `NodeConfigField`

`WorkflowNode`/`WorkflowEdge` are the design-time graph (canvas); the execution engine reads only the compiled `WorkflowVersion.definition`. `WorkflowNodeExecution` is the runtime token table. `WorkflowEvent` is an audit log, currently written only by the `dynamic-flow` executor/controller.

## Testing

- **Framework:** PHPUnit 11
- **Test DB:** SQLite in-memory (configured in `phpunit.xml`)
- **Suites:** `tests/Unit/` (includes `tests/Unit/Execution/`), `tests/Feature/` — at repo root, not per-module (unlike `Auth`/`Team`/`KnowledgeBase`/`Integrations`, which have their own `Modules/<Name>/tests/`)
- **Run:** `composer test` or `php artisan test`; single test: `php artisan test --filter=TestName`

## Code Style

- **PHP:** Laravel Pint — `composer run format`
- **TypeScript:** ESLint — `cd frontend && npm run lint`
- **IDE helpers:** `barryvdh/laravel-ide-helper` (run after model changes)

## Reference

- Per-module docs: see [Module Documentation](#module-documentation) above — that's the starting point, not this list.
- API routes: `Modules/Workflows/routes/api.php`, `Modules/Auth/routes/api.php`
- Mobile API reference: `docs/mobile-api.md` (JWT auth, `/me`, task inbox endpoints)
- Frontend: `frontend/` (separate Node.js project — React 19 + Vite + shadcn/ui — not served by Laravel; see `frontend/docs/frontend-architecture.md`)
- Mobile prototype: `mobile-prototype/index.html` (static HTML prototype, not production)
