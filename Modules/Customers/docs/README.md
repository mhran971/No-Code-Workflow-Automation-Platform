# Customers Module

Tenant-scoped customer records, linked to `Workflows` `WorkflowInstance`s via an opt-in "customer context" mapping on `manual-trigger`/`form-trigger` nodes: when enabled, the trigger's declared field value is looked up (or a new `Customer` created) against the tenant's configured linking field (`email` or `phone`), and the resolved customer becomes readable as `{{customer.*}}` in downstream node templates alongside `{{context.*}}`.

## Data Model

| Model | Key fields/relationships | Migration |
|---|---|---|
| `Customer` (`app/Models/Customer.php`) | `tenant_id`, `email`, `phone` (both nullable, `unique(tenant_id, email)` / `unique(tenant_id, phone)`), `first_name`, `last_name`, `custom_field_values` (JSON), `is_active`. `belongsTo(Tenant)`. `toTemplateArray()` flattens core + custom fields for the `customer.*` template namespace (fixed columns win on key collision). | `2026_08_11_090100_create_customers_table.php` |
| `CustomerField` (`app/Models/CustomerField.php`) | Tenant-defined custom-field **schema** — mirrors `Modules\Workflows\Models\NodeConfigField`'s shape (`key`, `label`, `type`, `is_required`, `options`, `default_value`, `sort_order`) but scoped by `tenant_id` instead of `node_id`. `unique(tenant_id, key)`. Values live in `Customer.custom_field_values` (JSON), not a parallel value table — same pattern as `NodeConfigField` (schema-only) vs. `WorkflowNode.config` (inline JSON values). | `2026_08_11_090200_create_customer_fields_table.php` |
| `CustomerSettings` (`app/Models/CustomerSettings.php`) | One row per tenant (`unique(tenant_id)`, lazily `firstOrCreate`d). `linking_field` (nullable, cast to `CustomerLinkingField`) — the tenant-wide choice of `email` or `phone` used for lookup-or-create. | `2026_08_11_090000_create_customer_settings_table.php` |

`Modules\Workflows\Models\WorkflowInstance` gets a nullable `customer_id` FK (`nullOnDelete`, added by `Modules/Workflows/database/migrations/2026_08_11_090300_add_customer_id_to_workflow_instances_table.php` — must sort after this module's `create_customers_table` migration since Laravel merges all modules' migrations by filename).

## Enums

| Enum | Values | Notes |
|---|---|---|
| `CustomerLinkingField` | `Email = 'email'`, `Phone = 'phone'` | Tenant-wide choice, stored on `CustomerSettings`. |
| `CustomerFieldType` | `Text, Textarea, Select, Toggle, Number, Date, Email` | Deliberately **not** the same class as `Modules\Workflows\Enums\NodeConfigFieldType`, even though `CustomerField` mirrors `NodeConfigField`'s shape — importing across modules would invert this module's dependency direction (see Cross-Module Dependencies). |

## Services

| Service | Purpose |
|---|---|
| `CustomerService` (extends `BaseService`, `CustomerRepository`) | CRUD for `Customer`. Validates `custom_field_values` against the tenant's `CustomerField` schema on create/update (unknown keys rejected, required fields enforced) — this validation does **not** run on the runtime lookup-or-create path (`CustomerResolutionService`), which only ever writes the single linking-field value. Owns `RESERVED_KEYS` (fixed column names a `CustomerField.key` must never collide with). |
| `CustomerFieldService` (extends `BaseService`, `CustomerFieldRepository`) | CRUD for the tenant's custom-field schema. Rejects reserved keys via `CustomerService::RESERVED_KEYS`. |
| `CustomerSettingsService` | `getLinkingField(tenantId)`, `getForTenant(tenantId)` (lazy `firstOrCreate`), `updateForTenant(tenantId, field)`. The update path **blocks** the change if any of the tenant's *published* workflows currently have `customerContextEnabled` on their live trigger (published `workflow_versions.definition` is immutable, so a silent switch would leave an already-published mapping semantically wrong — e.g. an "email" field now feeding a phone lookup). This is the one place this module reaches into `Modules\Workflows\Models\Workflow` — see Cross-Module Dependencies. |
| `CustomerResolutionService` | Runtime-only, no HTTP surface. `resolve(tenantId, rawValue): ?Customer` — normalizes (email: lowercase+trim; phone: strips non-digits, preserves a leading `+`; **not** full E.164 — no phone-parsing library exists in this codebase), looks up an existing `Customer` by the tenant's linking field, creates one if not found, and retries the lookup on a unique-constraint race (SQLSTATE `23000`) rather than failing. Returns `null` if the tenant has no `linking_field` configured or the normalized value is empty. Called from `Modules\Workflows\Services\Execution\Concerns\ResolvesCustomerContext` (used by `ManualTriggerExecutor`/`FormTriggerExecutor`). |

## API Routes

Prefix `/api/v1/customers`, all routes `auth:api` + `active.user` + `role:business_owner,manager,admin` (**no space** after commas — `Modules/KnowledgeBase` has a documented bug where a leading space in this exact middleware string silently locks out Managers; not repeated here). Unlike `KnowledgeBase`'s pattern, **reads are restricted too**, not just writes — Employees have no access to Customer records at all, since they carry more PII than a document.

| Method | Path | Description |
|---|---|---|
| GET / POST | `/` | List (paginated, tenant-scoped) / create a customer |
| GET / PUT / DELETE | `/{id}` | Show / update / delete a customer |
| GET / POST | `/fields` | List / create the tenant's custom-field schema |
| PUT / DELETE | `/fields/{customerField}` | Update / delete a custom-field definition |
| GET / PUT | `/settings` | Show / update the tenant's linking-field choice |

## Key Business Rules & Gotchas

- **Customer context is opt-in per trigger, but the linking field is tenant-wide.** A `manual-trigger`/`form-trigger` node's `customerContextEnabled`/`customerContextField` config only says *which of that trigger's own declared fields* supplies the value — it does not itself choose `email` vs. `phone`; that's `CustomerSettings.linking_field`, chosen once per tenant. See `Modules/Workflows/docs/README.md` for the trigger-side config fields and verification rule.
- **`first_name`/`last_name` are fixed columns, not custom fields**, even though they're often "just more data to track" — they're near-universal identity fields (not tenant-specific like a real custom field), and a customer list needs to sort/search them without JSON-path lookups.
- **Reserved keys are enforced at write time, not just documentation.** `CustomerService::RESERVED_KEYS` (id, tenant_id, email, phone, first_name, last_name, custom_field_values, is_active, created_at, updated_at) blocks creating a `CustomerField` with a colliding key — `Customer::toTemplateArray()`'s "fixed columns win on collision" comment describes the theoretical fallback, not something that should ever actually trigger.
- **No cross-tenant customer merging or global search.** Every lookup is `tenant_id`-scoped by hand (no global Eloquent scope, consistent with the rest of this codebase) — the same real person submitting forms to two different tenants gets two unrelated `Customer` rows, by design.
- **`{{customer.*}}` template variables are not existence-checked at verification time.** Unlike `{{context.x}}` (checked against `DataFlowAnalyzer`'s guaranteed-keys set), `Modules\Workflows\...\Concerns\VariableAvailability` accepts any `{{customer.foo}}` syntactically without checking `foo` is a real fixed column or `CustomerField.key` for that tenant. This is pre-existing behavior this module didn't change — a natural follow-up now that `CustomerField` schema exists per tenant.
- **Phone normalization is intentionally minimal.** No E.164 handling — see `CustomerResolutionService::normalize()`. A phone number entered differently across two form submissions (e.g. with vs. without a country code) will not match and creates a second `Customer` row.

## Cross-Module Dependencies

- **`Workflows -> Customers` (primary direction)**: `ManualTriggerExecutor`/`FormTriggerExecutor` inject `CustomerResolutionService`; `NodeExecutionContext::resolutionScope()` reads `WorkflowInstance.customer` for the `customer.*` template scope; `Modules\Workflows\...\CustomerContextVerificationRule` queries `CustomerSettings` directly to check a tenant has a linking field configured before allowing publish.
- **`Customers -> Workflows` (one deliberate exception)**: `CustomerSettingsService::updateForTenant()` queries `Modules\Workflows\Models\Workflow`/`WorkflowVersion` to block a linking-field change while a published workflow depends on it (see Services above). This is the only place the dependency runs in this direction — precedent for this kind of one-off, undeclared cross-module coupling already exists elsewhere in this codebase (e.g. `Auth` <-> `Team`, per `Modules/Auth/docs/README.md`).
- **`Auth`**: `Customer`/`CustomerField`/`CustomerSettings` all `belongsTo` `Modules\Auth\Models\Tenant`.
- **Deliberately no dependency on `Modules\Workflows\Enums\NodeConfigFieldType`** despite `CustomerFieldType` mirroring its shape — see Enums above.

## Testing

No `Modules/Customers/tests/` directory — this module follows the repo-wide convention (`Auth`/`Team`/`KnowledgeBase`/`Integrations` all keep their own `tests/` empty too) of placing real coverage at the **repo root**:

- `tests/Feature/CustomerContextVerificationTest.php` — `CustomerContextVerificationRule`'s branches (missing/undeclared/not-required mapping, tenant linking-field not configured, valid cases for both trigger types).
- `tests/Feature/CustomerLinkingExecutionTest.php` — end-to-end lookup-or-create and reuse via a real publish + manual-trigger flow (email and phone linking fields), and a regression test proving `customerContextEnabled=false` changes nothing.
- `tests/Feature/CustomerManagementApiTest.php` — CRUD, role gating (Employee denied), reserved-key/unknown-custom-field-key validation, and the linking-field-change-blocked-while-published-workflow-depends-on-it rule.
- `tests/Unit/CustomerResolutionServiceTest.php` — lookup vs. create, normalization, tenant scoping.
- `tests/Unit/Execution/NodeExecutionContextTest.php` — `resolutionScope()`'s `customer` key and `{{customer.x}}` template rendering (DB-backed despite living under `Unit/`, since it exercises `WorkflowInstance.customer`; consistent with other DB-touching tests in that directory).
