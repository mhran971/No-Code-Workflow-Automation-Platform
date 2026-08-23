# KnowledgeBase Module

Tenant-scoped PDF document library (metadata + file storage, tagging, and a fixed document-type taxonomy). Laravel remains the source of truth for the file itself; text extraction, chunking, and embedding are delegated to an external Python RAG microservice (`RagService`, see [RAG Ingestion Pipeline](#rag-ingestion-pipeline)) — there is still no in-module content pipeline, and AI-feature "knowledge retrieval" at the `Workflows` layer is a separate, still-unwired concern (see Cross-Module Dependencies).

## Data Model

| Model | Key fields/relationships | Migration file |
| --- | --- | --- |
| `Document` (`app/Models/Document.php`) | `tenant_id`, `title`, `document_type_id`, `file_path`, `is_active` (bool, default `true`), `index_status` (string, default `pending`; `pending`\|`indexed`\|`failed`), `index_error` (nullable text — last RAG error message), `chunks_count` (nullable int, set on successful indexing); `belongsTo` `Tenant` (`Modules\Auth\Models\Tenant`), `belongsTo` `DocumentType`, `belongsToMany` `Tag` via `document_tag` | `2026_02_13_120000_create_documents_table.php`; `is_active` added later in `2026_05_15_092841_add_is_active_to_documents_table.php`; `index_status`/`index_error`/`chunks_count` added in `2026_08_15_090000_add_index_status_to_documents_table.php`; a prior `uploaded_at` column was dropped in `2026_02_13_130000_remove_uploaded_at_from_documents_table.php` (API now reports `created_at` as `uploaded_at`, see `DocumentResource`) |
| `DocumentType` (`app/Models/DocumentType.php`) | `name`, `description`; **not** tenant-scoped (global taxonomy); no inverse relation to `Document` defined on this model | `2026_02_13_000000_create_document_types_table.php` — no unique constraint on `name` at the DB level |
| `Tag` (`app/Models/Tag.php`) | `name`, `tenant_id`; `belongsTo` `Tenant`, `belongsToMany` `Document` via `document_tag`; DB-level `unique(tenant_id, name)` | `2026_02_13_110001_create_tags_table.php` |
| `document_tag` (pivot, no Eloquent model) | `document_id`, `tag_id`, timestamps; `unique(document_id, tag_id)`, both FKs cascade-delete | `2026_02_13_120001_create_document_tag_table.php` |

All FKs (`tenant_id`, `document_type_id`, `document_id`, `tag_id`) cascade on delete.

## Services & Repositories

| Class | File | Purpose |
| --- | --- | --- |
| `DocumentService` | `app/Services/DocumentService.php` | Upload (stores file + creates row + syncs tags + dispatches `IndexDocumentJob`, `upload()`), metadata update (`updateMetadata()`, file is never replaced), tenant-scoped list/get, streamed download (`getFileResponse()`), active-flag toggle (`setActiveStatus()`, also calls `RagService::updateDocumentStatus()`), delete (`delete()`, also calls `RagService::deleteDocument()`), private `storeFile()` |
| `RagService` | `app/Services/RagService.php` | Thin HTTP client for the external RAG microservice (`config('services.rag.*')`, `X-API-Key` auth): `indexDocument()`, `updateDocumentStatus()`, `deleteDocument()`. See [RAG Ingestion Pipeline](#rag-ingestion-pipeline) |
| `TagService` | `app/Services/TagService.php` | Tenant/Admin-scoped tag listing (`getTagsForCurrentUser()` L22-41), create-or-reuse (`createTagForCurrentUser()`), mixed name/ID resolution with in-memory case-insensitive de-dup (`resolveTagIdsForTenant()` L60-94) |
| `DocumentRepository` | `app/Repositories/DocumentRepository.php` | Plain Eloquent query class (no interface): `create`, `update` (defined but **unused** — `DocumentService::updateMetadata()` calls `$document->update()` directly instead), `paginateForTenant` (eager-loads `documentType`,`tags`), `findForTenant` |
| `TagRepository` | `app/Repositories/TagRepository.php` | `all()` (admin, eager-loads `tenant`), `allForTenant()`, `create()`, `findByName()` (case-insensitive via `whereRaw('LOWER(name) = ?')`), `firstOrCreateByName()` |

`DocumentService` extends `App\Services\BaseService` and sets `protected ?string $repositoryClass` explicitly (redundant — `BaseService`'s naming-convention guesser would resolve the same class); `RagService` does not extend it (plain HTTP client, no repository). They follow the module convention structurally, but **neither service currently relies on `__call()` delegation in practice**: every method the controllers invoke is explicitly defined on the service class itself, not magically forwarded to the repository. No controller or service in this module uses `DB::transaction()`/`beginTransaction()` — multi-step mutations (e.g. `upload()` creating a `Document` row then syncing tags) run as plain sequential Eloquent calls with no explicit transaction wrapper.

## API Routes

All routes are defined in `routes/api.php`, mounted with prefix `v1` inside a group requiring `auth:api` + `active.user` (module's `RouteServiceProvider::mapApiRoutes()` additionally wraps everything in `api` prefix — final paths are `/api/v1/...`, consistent with the rest of the app).

| Method | Path | Middleware | Description |
| --- | --- | --- | --- |
| GET | `/mobile/documents` | `auth:api`, `active.user` | List documents for current tenant, mobile-optimized alias — `MobileDocumentController@index` |
| GET | `/document-types` | `auth:api`, `active.user` | List all document types (global, unfiltered) — `DocumentTypeController@index` |
| GET | `/tags` | `auth:api`, `active.user` | List tags — all tags if `Role::Admin`, else own tenant's — `TagController@index` |
| POST | `/tags` | `auth:api`, `active.user`, `role:business_owner, manager` † | Create or reuse (case-insensitive) a tag for current tenant — `TagController@store` |
| GET | `/documents` | `auth:api`, `active.user` | Paginated document list for current tenant (`per_page`, default 15, **no upper bound**) — `DocumentController@index` |
| POST | `/documents` | `auth:api`, `active.user`, `role:business_owner, manager` † | Upload a new PDF (multipart; comma-separated `tags` string) — `DocumentController@store` |
| GET | `/documents/{id}` | `auth:api`, `active.user` | Show one document (404 if not in caller's tenant) — `DocumentController@show` |
| PUT | `/documents/{id}` | `auth:api`, `active.user`, `role:business_owner, manager` † | Update `title`/`document_type_id`/`tags` — file itself is immutable — `DocumentController@update` |
| GET | `/documents/{id}/download` | `auth:api`, `active.user` | Stream PDF as an attachment — `DocumentController@download` |
| PATCH | `/documents/{id}/activity/{is_active}` | `auth:api`, `active.user`, `role:business_owner, manager` † | Toggle `is_active` — `DocumentController@setActive` |
| DELETE | `/documents/{id}` | `auth:api`, `active.user`, `role:business_owner, manager` † | Hard-delete the row (see gotchas — file is **not** removed from disk) — `DocumentController@destroy` |

† See "role middleware has a leading-space bug" below — Manager-role access to these five routes is likely broken today.

`MobileDocumentController::show()` (`app/Http/Controllers/MobileDocumentController.php` L38-47) is fully implemented but **has no route registered** — currently unreachable dead code, and not mentioned in `docs/mobile-api.md`.

## Enums

This module defines **no Enums** (no `app/Enums/` directory) — `index_status` is a plain string column (`pending`\|`indexed`\|`failed`), not a backed enum. It consumes `Modules\Auth\Enums\Role` (`TagService::getTagsForCurrentUser()` checks `$user->role === Role::Admin`) and raw role-name strings (`business_owner`, `manager`) in route middleware. It also has **no `app/Exceptions/`** directory — all errors are inline `response()->json([...], 4xx)` calls or standard Laravel `FormRequest` validation. `app/Jobs/` contains one job, `IndexDocumentJob` (see below); document upload itself (the DB row + file write) is still fully synchronous — only the RAG-indexing call is queued.

## RAG Ingestion Pipeline

Text extraction, chunking, and embedding are delegated to an external Python RAG microservice (not part of this repo) — Laravel keeps owning the file and metadata; the RAG service only owns the vector index built from a copy of it.

- **`RagService`** (`app/Services/RagService.php`) is a thin HTTP client: `indexDocument()`, `updateDocumentStatus()`, `deleteDocument()`. Base URL/timeout/key come from `config('services.rag.*')` (`RAG_SERVICE_URL`, `RAG_SERVICE_TIMEOUT`, `RAG_SERVICE_API_KEY` in `.env`); every call sends the shared-secret `X-API-Key` header. A failed/non-2xx response throws `RuntimeException`.
- **`IndexDocumentJob`** (`app/Jobs/IndexDocumentJob.php`, `ShouldQueue`, `$tries = 3`, backoff `[10, 30, 60]`) is dispatched from `DocumentService::upload()` right after the `Document` row is created. It re-reads the file from the configured disk (not the request's temp upload path, which no longer exists once queued), calls `RagService::indexDocument()` with `article_id = (string) $document->id`, `category = $document->documentType?->name ?? 'general'`, `tenant_id = (string) $document->tenant_id`, and on success updates `index_status = 'indexed'` + `chunks_count`. On final failure (all retries exhausted) its `failed()` hook sets `index_status = 'failed'` + `index_error`. It's queued because RAG-side indexing is fully synchronous (OCR fallback, embedding) and can take several seconds for a large/scanned PDF.
- **Lifecycle sync stays best-effort.** `DocumentService::setActiveStatus()` and `delete()` call `RagService::updateDocumentStatus()` / `deleteDocument()` respectively, but wrap the call in try/catch + `Log::warning()` — a RAG-side failure never blocks or rolls back the primary Laravel mutation (matches the microservice's own documented best practice). There is currently no retry mechanism for a failed status-sync or delete call, unlike indexing which retries via the queue.
- **No reindex-on-edit.** The RAG service exposes `/reindex-document` for when a file is replaced, but this module has no file-replace flow (`updateMetadata()` only ever touches `title`/`document_type_id`/tags — see gotcha below) and `RagService` has no `reindexDocument()` method as a result. If a future change adds file replacement, add it then.
- **Document type → RAG `category` is a loose mapping.** The RAG service's chunking strategy recognizes `Policy`/`general`/`records`/`contract`; this module's seeded `DocumentType` names are `Policy`, `Procedure`, `FAQ`, `Troubleshooting guide`. Only `"Policy"` lines up exactly — the other three are passed through as-is and presumably fall back to the RAG service's default chunking behavior there.

## Key Business Rules & Gotchas

- **Storage layout.** Files go to the app's default filesystem disk (`config('filesystems.default')`, `local` per `.env.example`) at `documents/{tenant_id}/{uuid}.pdf` (`DocumentService::storeFile()` L130-139). The original uploaded filename is discarded; download regenerates one via `Str::slug($title).'.pdf'`.
- **PDF-only, double-checked.** Enforced both by the `FormRequest` rule `mimes:pdf` and again by an explicit MIME check against `config('knowledgebase.documents.allowed_mimes')` in `StoreDocumentRequest::withValidator()` (L52-73). Max size defaults to 10 MB (`KNOWLEDGEBASE_MAX_FILE_SIZE_KB`, in **KB**, default `10240` — `config/config.php`).
- **Manual tenant scoping, no global scope.** Neither `Document` nor `Tag` has an Eloquent global scope for `tenant_id`; every repository method filters explicitly (`DocumentRepository::paginateForTenant/findForTenant`, `TagRepository::allForTenant`). Any new direct `Document::query()` elsewhere in the codebase must remember to scope by tenant itself.
- **`DocumentType` is global**, not tenant-scoped, and is read-only via the API (only `GET /document-types`; managed solely by `DocumentTypeSeeder`, seeding "Policy", "Procedure", "FAQ", "Troubleshooting guide").
- **Tag case-insensitive de-dup is app-layer only.** `TagRepository::findByName()` matches via `LOWER(name)`, but the DB unique constraint on `(tenant_id, name)` is case-sensitive — "CV" and "cv" could coexist if inserted outside `firstOrCreateByName()`, and there's a narrow TOCTOU race between the lookup and the create under concurrent requests.
- **Delete leaves an orphaned local file (but not a remote one).** `DocumentService::delete()` calls `$document->delete()` and, best-effort, `RagService::deleteDocument()` — the latter does remove the RAG service's own on-disk copy and vector entries, but `Storage::delete()` on Laravel's own file is never called, so the physical PDF under this app's disk is still never removed. There's a literal `// TODO` there acknowledging deletion should probably be a soft (`is_active=false`) operation for audit purposes instead, and a matching `// TODO` on `setActiveStatus()` noting there's currently **no check that a live workflow still references the document** before it's deactivated.
- **`PATCH .../activity/{is_active}` has a PHP truthiness trap.** The route captures `{is_active}` as a raw string with no `->where()` constraint, and the controller (`DocumentController::setActive`, L104) passes it straight through to `DocumentService::setActiveStatus()`, which casts with `(bool) $isActive`. In PHP, `(bool) "false"` is `true` (any non-empty string is truthy) — so `PATCH .../activity/false` actually **activates** the document. Only `0` or an empty value reliably deactivates.
- **Role middleware likely has a leading-space bug.** The five gated routes use `->middleware('role:business_owner, manager')` (note the space after the comma). Laravel's pipeline splits middleware parameters via plain `explode(',', $parameters)` with no trimming, so the second parameter is literally `" manager"`. `EnsureUserHasRole` (`app/Http/Middleware/EnsureUserHasRole.php` L26-29) maps each through `Role::tryFrom($role)?->value ?? $role`; `Role::tryFrom(' manager')` fails (backed-enum matching is exact-string), so the raw `" manager"` string is kept and then never strictly equals the user's actual role value `"manager"` (`Modules\Auth\Enums\Role::Manager->value === 'manager'`, `EnsureUserHasRole.php` L31 uses strict `in_array(..., true)`). Net effect: users with the Manager role are likely rejected (403) from document/tag mutation endpoints even though both roles were clearly intended to have access — only Business Owner reliably works.
- **`MobileDocumentController::show()`'s "404" test passes for the wrong reason.** Root-level `tests/Feature/NotificationApiTest.php::test_mobile_document_show_returns_404_for_nonexistent()` (L467-475) hits `GET /api/v1/mobile/documents/99999` and asserts `assertNotFound()`. Since no route is actually registered for that path, this 404 comes from Laravel's router failing to match a route, not from the controller's own "Document not found or access denied." JSON response — the test provides false confidence that `show()`'s tenant-scoped lookup works; it never actually executes.
- **No factories yet.** `database/factories/` contains only a `.gitkeep` — no `DocumentFactory`/`TagFactory`/`DocumentTypeFactory` exists.

## Cross-Module Dependencies

**KnowledgeBase's own dependencies:** `Modules\Auth\Models\Tenant` (owning relation on `Document`/`Tag`) and `Modules\Auth\Enums\Role` (admin check in `TagService`), plus app-level `auth:api`/`active.user`/`role:` middleware. KnowledgeBase has **zero imports of `Modules\Workflows\*`** (verified repo-wide) — the dependency runs one-way, from Workflows into KnowledgeBase.

**How Workflows references KnowledgeBase — verification and execution, now wired:**

- `Modules/Workflows/app/Services/Verification/Rules/ContextualVerificationRule.php` is the **only file outside this module** that touches `Modules\KnowledgeBase` (imports `Document`). Its `verifyKnowledgeBaseDocuments()` scans every node whose `type` starts with `ai-`, reads `node['config']['knowledgeBaseDocuments']` (falling back to the legacy `kbDocs` key for older definitions), and validates each entry is numeric and exists as a `Document` row owned by the workflow's tenant.
- **Historical key mismatch, now fixed:** the `ai-generator` node's config schema, seeded by `Modules/Workflows/database/seeders/NodeDefinitionSeeder.php`, names this field `knowledgeBaseDocuments` (type `"tags"`) — that's what the builder UI writes into `node.config`. The verification rule used to read only `config['kbDocs']`, a different key, so the existence/tenant-ownership check silently no-opped for real UI-built definitions. It now reads `knowledgeBaseDocuments` first.
- `AiGeneratorNodeTypeRule` (the per-node-type structural rule) still validates only `prompt` and `outputVariable` — the KB-document existence check lives solely in `ContextualVerificationRule`.

**How the `ai-generator` executor consumes documents:**

- `AiGeneratorExecutor::execute()` (`Modules/Workflows/app/Services/Execution/Executors/AiGeneratorExecutor.php`) renders the `prompt` template and calls `$this->ai->generate($prompt, $tenantId, $config)` against the `Contracts/AiContentGenerator` interface, bound to `Modules\Workflows\Services\Ai\RagAiClient` in `WorkflowsServiceProvider`.
- `RagAiClient::generate()` forwards the node's `knowledgeBaseDocuments` config field as RAG's `article_ids` in the `POST /api/v1/ai/generate` request body (see `Modules/Workflows/docs/RAG_AI_NODES_API.md`) — Workflows itself never queries `Modules\KnowledgeBase` or fetches document content directly; RAG does the retrieval against its own vector index (populated by this module's [RAG Ingestion Pipeline](#rag-ingestion-pipeline)) using the forwarded document IDs plus the server-set `tenant_id`.
- `AiGeneratorExecutor` is registered in `NodeExecutorRegistry` (`Modules/Workflows/app/Providers/WorkflowsServiceProvider.php`) alongside the sibling `ai-classifier` node's `AiClassifierExecutor` (which does not consume KB documents — it classifies arbitrary `text` into one of a fixed set of `categories` via RAG's `POST /api/v1/ai/classify`).

## Testing

- The module's **own** test directories are empty: `Modules/KnowledgeBase/tests/Feature/` and `tests/Unit/` each contain only `.gitkeep`. `composer.json` maps `Modules\KnowledgeBase\Tests\` → `tests/` for PSR-4 autoloading, but nothing has been placed there yet.
- `database/factories/` is likewise empty (only `.gitkeep`) — no `DocumentFactory`/`TagFactory`/`DocumentTypeFactory`; any test needs to build rows manually.
- Actual coverage of this module's behavior lives in the **root** `tests/Feature/` instead:
  - `tests/Feature/NotificationApiTest.php` (search `B-28 & B-29: Documents Mobile API`, L447-475) exercises the mobile documents endpoints — list (empty-tenant shape check only, no real documents created), unauthenticated rejection, and the (misleading, see gotchas) 404-for-missing-route test.
  - `tests/Feature/WorkflowVerificationTest.php` imports `Modules\KnowledgeBase\Models\Document` directly to build fixture rows and exercises `ContextualVerificationRule::verifyKnowledgeBaseDocuments()` end-to-end (search `kbDocs`, around L1383-1543) — this is the only place the `kbDocs` key is exercised by tests; there is no test anywhere using the seeded `knowledgeBaseDocuments` key.
- Both live under the app's standard PHPUnit 11 / SQLite in-memory setup (`composer test` / `php artisan test` from repo root, per root `CLAUDE.md`).
- **No test coverage yet for the RAG pipeline.** `RagService` and `IndexDocumentJob` (see [RAG Ingestion Pipeline](#rag-ingestion-pipeline)) have no tests anywhere in the repo as of their introduction — any test exercising `DocumentService::upload()` should `Bus::fake()`/`Queue::fake()` or `Http::fake()` the RAG calls rather than hitting a real service.
