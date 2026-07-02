# No-Code Workflow Automation Platform

Laravel 12 modular monolith (PHP 8.2+). Users design automation graphs in a React canvas; the backend validates, stores, and executes workflow definitions. Primary module: `Modules/Workflows/`.

## Essential Commands

```bash
# Backend
composer run setup      # first-time: install, .env, key, migrate, npm build
composer run dev        # start everything: server + queue + logs + frontend
composer test           # run PHPUnit (all suites)
composer run format     # Laravel Pint code style

# Frontend (workflow-builder/)
cd workflow-builder && npm run dev    # Vite dev server
cd workflow-builder && npm run build  # TypeScript + Vite production build
cd workflow-builder && npm run lint   # ESLint
```

## Architecture

| Layer | Technology |
|---|---|
| Framework | Laravel 12, `nwidart/laravel-modules` |
| PHP | 8.2+ |
| Database | PostgreSQL (prod/dev), SQLite in-memory (tests) |
| Auth | JWT (`tymon/jwt-auth`) + Sanctum; middleware: `auth:api`, `active.user` |
| Queue / Cache / Session | database driver |
| Realtime | Laravel Reverb (`BROADCAST_CONNECTION=reverb`, `config/reverb.php`); private channel `App.Models.User.{id}` in `routes/channels.php`; JWT users authenticate via `POST /api/v1/broadcasting/auth` |
| Frontend | React 19 + TypeScript + Vite (`workflow-builder/`) |
| API docs | OpenAPI via `dedoc/scramble` |

**Modules:** `Auth`, `Integrations`, `KnowledgeBase`, `Team`, `Users`, `Workflows`

## Workflows Module

All paths relative to `Modules/Workflows/`.

### Key Services

| Service | File | Purpose |
|---|---|---|
| `WorkflowVerificationService` | `app/Services/WorkflowVerificationService.php` | Orchestrates 5-stage verification pipeline |
| `WorkflowManagementService` | `app/Services/WorkflowManagementService.php` | CRUD + publish orchestration |
| `WorkflowDefinitionValidator` | `app/Services/WorkflowDefinitionValidator.php` | Public API entry for validation |
| `WorkflowDefinitionNormalizer` | `app/Services/Verification/WorkflowDefinitionNormalizer.php` | Normalizes raw definitions (handles legacy field aliases) |
| `ExpressionLanguageValidator` | `app/Services/Verification/ExpressionLanguageValidator.php` | Recursive-descent parser for boolean expressions |

### Verification Pipeline

5 stages run sequentially in `WorkflowVerificationService::verify()`:

1. `SyntaxVerificationRule` — structure and data-type validation
2. `GraphControlFlowVerificationRule` — reachability and control-flow logic
3. `ExpressionVerificationRule` — boolean expression parsing
4. `ContextualVerificationRule` — business rule checks
5. `NodeTypeVerificationRule` — per-node-type validation (dispatches to `Rules/NodeType/` sub-rules)

**To add a new verification rule:** implement `VerificationRule` interface (`app/Services/Verification/Rules/VerificationRule.php`), then register it in `WorkflowVerificationService::verify()`.

**To add a new node-type rule:** implement `NodeTypeRule` (`app/Services/Verification/Rules/NodeType/NodeTypeRule.php`), register via `addNodeTypeRule()` in `WorkflowVerificationService`.

### Node Types (seeded by `NodeDefinitionSeeder`)

| Category | Key | Description |
|---|---|---|
| Triggers | `manual-trigger` | Manual start |
| Triggers | `form-trigger` | Form submission |
| Logic | `if-node` | Conditional branching |
| Logic | `and-node` | All conditions must be true |
| Logic | `merge-or` | Any branch completes |
| Logic | `merge-and` | All branches complete (with timeout) |
| Logic | `switch` | Multi-path routing |
| AI | `ai-generator` | Content generation (email, proposal, report, social) |
| Flows | `sub-workflow` | Execute nested workflow |
| Flows | `dynamic-flow` | Runtime sub-flow creation |
| Actions | `send-email` | Email sending with HTML support |
| Actions | `task-node` | Task assignment with SLA and reminders |

### API Routes

Prefix: `/api/v1/workflows` — all routes behind `auth:api`, `active.user`. See `routes/api.php`.

| Method | Path | Description |
|---|---|---|
| GET | `/nodes` | List available node types |
| POST | `/validate` | Validate a workflow definition |
| GET | `/templates` | Workflow templates |
| POST | `/proposals/ai` | AI workflow proposal (Manager or BusinessOwner role required) |
| GET | `/instances/{instance}` | Show workflow instance |
| GET | `/instances/{instance}/failures` | Instance failure details |
| POST | `/instances/{instance}/cancel` | Cancel a running instance |
| POST | `/instances/{instance}/retry-from-node` | Retry instance from a given node |
| GET | `/tasks/summary` | Human-task inbox summary (counts by state) |
| GET | `/tasks` | List human tasks (role-based) |
| GET | `/tasks/{task}` | Show task detail |
| PATCH | `/tasks/{task}/draft` | Save a draft task response |
| POST | `/tasks/{task}/submit` | Submit a task response |
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
| POST | `/{workflow}/trigger/form` | Form trigger |
| GET | `/{workflow}/instances` | List instances for a workflow |

Auth module also exposes `GET /api/v1/me` (profile, role, tenant, team) alongside `POST /register`, `POST /login`, `POST /logout` — see `Modules/Auth/routes/api.php`.

### Models

`Workflow`, `WorkflowVersion`, `WorkflowInstance`, `WorkflowNode`, `WorkflowEdge`, `WorkflowTemplate`, `Node`, `NodeConfigField`, `WorkflowAccessGrant`

## Testing

- **Framework:** PHPUnit 11
- **Test DB:** SQLite in-memory (configured in `phpunit.xml`)
- **Suites:** `tests/Unit/`, `tests/Feature/`
- **Run:** `composer test` or `php artisan test`

## Code Style

- **PHP:** Laravel Pint — `composer run format`
- **TypeScript:** ESLint — `cd workflow-builder && npm run lint`
- **IDE helpers:** `barryvdh/laravel-ide-helper` (run after model changes)

## Reference

- Verification system docs: `Modules/Workflows/docs/` (6 markdown files covering overview, architecture, rules, data structures, usage guide)
- API routes: `Modules/Workflows/routes/api.php`, `Modules/Auth/routes/api.php`
- Mobile API reference: `docs/mobile-api.md` (JWT auth, `/me`, task inbox endpoints)
- Frontend: `workflow-builder/` (separate Node.js project, not served by Laravel)
- Alternate frontend experiment: `remix-of-workflow-weaver/` (Remix-based, not production)
- Mobile prototype: `mobile-prototype/index.html` (static HTML prototype, not production)
