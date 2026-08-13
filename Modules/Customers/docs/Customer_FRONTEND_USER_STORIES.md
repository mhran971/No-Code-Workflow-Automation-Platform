# Customer Context — Frontend User Stories

Companion to [`README.md`](README.md) (this module's API/data-model reference) and [`Modules/Workflows/docs/README.md`](../../Workflows/docs/README.md) (trigger nodes, verification pipeline). Written for whoever implements the frontend for the Customer feature shipped on the backend this session — every API call below is real and already deployed; nothing here requires backend changes to implement as written, except where explicitly flagged as a **Known gap**.

**Cross-cutting**: every endpoint under `/api/v1/customers/*` requires `auth:api` + `active.user` + role `business_owner`, `manager`, or `admin`. **Employees get a 403 on all of it** — hide the nav entry/routes for Employee-role users client-side (matches the pattern already used elsewhere in the app for role-gated screens), not just relying on the API to reject.

All single-resource responses wrap in `{"data": {...}}` (Laravel's default `JsonResource` wrapping — this app does not disable it). List responses wrap in `{"data": [...], "links": {...}, "meta": {...}}` when paginated. Validation failures are `422` with `{"message": "...", "errors": {"<field>": ["..."]}}` — read `errors.<field>` for display; `message` is a generic fallback.

---

## Epic A — Trigger Customer-Context Configuration (Workflow Canvas)

Where a workflow author, while configuring a `manual-trigger` or `form-trigger` node, opts into linking instances to a Customer record.

### A1 — Enable and map customer linking on a trigger node

**As a** workflow author, **I want** to turn on "link to customer" on my trigger and pick which of my own fields supplies the value, **so that** every instance this trigger creates gets matched to a Customer automatically.

**APIs used**
- `GET /api/v1/workflows/nodes` — node catalog, drives the config-field renderer. `manual-trigger` and `form-trigger` now each carry two extra `configFields` entries at the end of their list:
  - `customerContextEnabled` — `type: "toggle"`, `defaultValue: false`, label "Link to Customer"
  - `customerContextField` — `type: "field_reference"`, label "Customer Linking Variable" (manual-trigger) / "Customer Linking Field" (form-trigger), with `options.sourceField` set to `"variables"` (manual-trigger) or `"formFields"` (form-trigger)
- `PATCH /api/v1/workflows/{workflow}/draft` — where the author's edits get saved, as before (nothing new about this call itself — the new keys just live inside `definition.trigger.config` / the matching `nodes[]` entry, same as any other config field).

**UX flow**
1. Author opens the config panel for a `manual-trigger` or `form-trigger` node (existing flow, unchanged).
2. At the bottom of the panel, below the trigger's normal fields, a new **"Link to Customer"** toggle appears, off by default.
3. Toggling it on reveals **"Customer Linking Field"** (or "...Variable" for manual-trigger) — a dropdown, initially empty/disabled if the trigger has no fields declared yet, with a hint: *"Add a form field first"* / *"Add a test variable first"*.
4. The dropdown's options come from the trigger's **own already-declared fields**, not a fresh input:
   - `form-trigger`: options are the trigger's `config.formFields[]` entries — value = `field.key`, label = `field.label` (fall back to `field.key` if blank).
   - `manual-trigger`: options are `config.variables[]` entries — value = `field.key`, label = `field.key` (these entries have no separate `label`, only `{key, value}`).
   - **This list must update live** as the author adds/removes/renames fields in the same panel — it is not a static fetch. If a previously-selected key gets removed or renamed, treat the mapping as now-undeclared (surface it the same way as validation error `customer_context.field_undeclared`, described in A2) rather than silently clearing the selection.
5. A short helper line under the dropdown: *"This will be matched against your organization's customer records using whichever field is set in [Customer Settings](#) — currently email / phone / not yet configured."* — read the tenant's current choice from `GET /api/v1/customers/settings` (Epic B) to fill this in; if unset, link to that settings screen.
6. Saving the draft (`PATCH .../draft`) works exactly as it already does — no special-casing needed, the two new keys are just more JSON in `config`.

**Acceptance criteria**
- [ ] Toggle defaults to off for new nodes (matches `defaultValue: false` from the node catalog); existing workflows without these keys behave as if off.
- [ ] Dropdown is populated from the trigger's own sibling field (`formFields` or `variables`), not hardcoded or fetched separately.
- [ ] Dropdown updates reactively as the author edits the trigger's own fields in the same session.
- [ ] Turning the toggle off clears/ignores `customerContextField` (don't submit a stale mapping for a disabled toggle — though the backend tolerates it being present and simply ignores it while `customerContextEnabled` is false, cleaner not to send it).
- [ ] Renderer has a new branch for `type === "field_reference"` — this is a genuinely new config-field type, not reusable from the existing `select` renderer (see README's note on why `SELECT` wasn't reused: `select.options` today means "static literal choices," `field_reference.options.sourceField` means "look at this other field instead").

**Known gap**: `webhook-trigger` deliberately has none of this — it isn't even a distinct canvas node type today (see `Modules/Workflows/docs/README.md`). Don't add the toggle there.

### A2 — Surface customer-context validation errors

**As a** workflow author, **I want** clear inline errors when my customer-context setup is incomplete, **so that** I fix it before trying to publish.

**APIs used**
- `POST /api/v1/workflows/validate` (dry-run) and `POST /api/v1/workflows/{workflow}/publish` (blocks on the same errors) — both already flow through the existing verification-error display (`WorkflowValidationResultResource`); this story is about handling four new `code`s that will appear in that same `issues[]` array your UI already renders for every other rule.

**New error codes to handle** (all reference `path: "trigger.config.customerContextField"` — point the inline error at the dropdown from A1):

| Code | Message | When |
|---|---|---|
| `customer_context.field_missing` | "Customer context is enabled but no linking field is selected." | Toggle on, dropdown empty |
| `customer_context.field_undeclared` | "Customer linking field '{key}' does not match any declared trigger field." | Selected key no longer exists in `formFields`/`variables` (e.g. renamed/removed after selection — see A1 step 4) |
| `customer_context.field_not_required` | "Form field '{key}' must be marked required to be used as the customer linking field." | **form-trigger only** — the mapped form field's own `required` flag is `false` |
| `customer_context.tenant_linking_field_not_configured` | "Customer context is enabled but this tenant has not chosen a customer linking field yet." | Tenant has never completed Epic B1 |

**UX flow / acceptance criteria**
- [ ] These render through whatever generic mechanism already surfaces `form_trigger.*`/`context.*` issue codes today — no new error-display component needed, just make sure the `path` mapping resolves to the right field in the trigger panel (same pattern as existing `trigger.config.accessLevel` errors).
- [ ] For `field_not_required`: since this is fully preventable client-side, consider **also** offering a one-click "Mark this field as required" fix-it action right in the error (the field lives in the same panel, `formFields[].required`) — not required by the backend, but removes a full round-trip for the single most likely mistake.
- [ ] For `tenant_linking_field_not_configured`: link directly to the Epic B settings screen from the error, since there's nothing to fix in the canvas itself.

---

## Epic B — Customer Settings Onboarding (Business Owner)

A tenant-level, one-time-ish setup: pick the linking field, define the custom-field schema. Business Owner (or Manager/Admin) scoped, not tied to any single workflow.

**Where it lives**: frame this as a step a Business Owner is prompted to complete early (e.g. surfaced the first time they try to enable customer context in Epic A1, or as part of a broader tenant-setup checklist if one exists) — but it is **not** a one-shot wizard the user can never return to. Custom fields get added over time; also expose it as a persistent Settings page.

### B1 — Choose the customer linking field

**As a** Business Owner, **I want** to declare whether my organization matches customers by email or phone, **so that** every trigger's customer-context mapping means the same thing tenant-wide.

**APIs used**
- `GET /api/v1/customers/settings` → `{"data": {"linking_field": "email" | "phone" | null}}`
- `PUT /api/v1/customers/settings` with `{"linking_field": "email" | "phone"}`

**UX flow**
1. Two-option choice (radio buttons or a segmented control), "Email" / "Phone" — no free text, this is a closed enum (`CustomerLinkingField`).
2. Explain the consequence plainly before they commit: *"Every workflow trigger that links to a customer will match by this field. You can change it later, but not while any published workflow currently uses customer linking."* (see B3 for what happens if they try anyway).
3. `null` (unset) is a valid initial state — show it as "Not yet configured" rather than defaulting the radio to one option, so the choice is deliberate.
4. On save, `PUT` — success re-fetches and reflects the new value; no separate confirmation modal needed for a *first-time* choice (there's nothing to lose yet). For a *change* after it's already set, see B3 for the confirmation/blocking flow.

**Acceptance criteria**
- [ ] Only `email` / `phone` are selectable — no custom values.
- [ ] Unset state is visually distinct from "email selected."
- [ ] This screen (or a summary of the current choice) is what Epic A1's helper text reads from.

### B2 — Define the custom fields to track per customer

**As a** Business Owner, **I want** to define what additional information we track about a customer beyond email/phone/name, **so that** my team can capture the data that matters to us and reference it in workflow templates.

**APIs used**
- `GET /api/v1/customers/fields` → `{"data": [{id, key, label, type, is_required, options, default_value, sort_order}, ...]}`, already sorted by `sort_order`.
- `POST /api/v1/customers/fields` — create. Required: `key`, `label`, `type`. Optional: `is_required`, `options`, `default_value`, `sort_order`.
- `PUT /api/v1/customers/fields/{customerField}` — partial update, same fields, all `sometimes`.
- `DELETE /api/v1/customers/fields/{customerField}`.

**UX flow** — a schema builder, structurally the same pattern the canvas already uses for a `form-trigger`'s `formFields` (reuse that component/pattern if it exists as a standalone piece):
1. List of existing fields in `sort_order`, each row: label, key (shown small/secondary), type badge, required badge, drag-to-reorder (write the new order back via repeated `PUT .../sort_order` or a batch approach — the API has no bulk-reorder endpoint today, see Known gap below).
2. "Add field" opens a form: **Label** (free text) → **Key** auto-derived from label (slugified) but editable, validated live against the reserved-key list below and checked for unique-within-tenant (the API 422s on both, but client-side pre-validation avoids a round trip). **Type** — dropdown of `CustomerFieldType`: Text, Textarea, Select, Toggle, Number, Date, Email. **Required** toggle. For `type = Select`, reveal an **Options** builder (list of `{label, value}` pairs — the API stores `options` as an unstructured JSON array/object, so this shape is a *frontend convention* to stay consistent with how `select`-type fields already work elsewhere in this app's node config, e.g. `accessLevel`; the backend doesn't enforce a shape here). **Default value** — render per selected type (text input / number input / date picker / toggle / select-from-options), stored into `default_value`.
3. **Reserved keys — block client-side before submitting**: `id, tenant_id, email, phone, first_name, last_name, custom_field_values, is_active, created_at, updated_at`. Show this as a live validation message on the Key input, not just a 422 after submit.
4. Delete requires a confirmation ("Existing customer records keep their stored value under this key even after the field definition is deleted — it just stops being editable/shown" — confirm this is actually true before writing that copy; if in doubt, phrase it more conservatively as "This cannot be undone").

**Acceptance criteria**
- [ ] All 7 `CustomerFieldType` values are supported with an appropriate input control in both the *definition* form (this screen) and — critically — the *value-entry* form in Epic C (they must share a renderer, since the same type drives both).
- [ ] Reserved-key collision is caught client-side, not just via the 422.
- [ ] Uniqueness-per-tenant on `key` is caught client-side where practical, with the 422 (`errors.key`) as the authoritative fallback.

**Known gap**: no bulk/atomic reorder endpoint — reordering N fields via drag-and-drop means N separate `PUT` calls (each just updating `sort_order`). Fine for the field counts this is realistically used at; flag to backend if that stops being true.

### B3 — Changing the linking field after workflows are already live

**As a** Business Owner, **I want** to be stopped (not silently broken) if I try to switch email↔phone while a published workflow depends on the old choice, **so that** I don't quietly desync a live trigger's mapping from what it actually matches against.

**APIs used**
- `PUT /api/v1/customers/settings` — same call as B1, but now can `422`.

**UX flow**
1. User changes the radio/segmented control and confirms (this action should have its own explicit confirm step, unlike the first-time choice in B1 — it's now a change with consequences).
2. On `422`, the response is `errors.linking_field: ["Cannot change the customer linking field while these published workflows have customer context enabled: <Workflow A>, <Workflow B>. Republish them without customer context first, or after switching."]` — **display this message directly**, it already names the blocking workflows by name. No need to parse it into a structured list; it's written to be read as-is.
3. Revert the UI control back to the current (unchanged) value on failure — don't leave the radio showing the rejected choice.
4. Optional but recommended: turn the workflow names in that message into links to the workflow editor, if you can reliably parse them back out (comma-separated after the colon) — cosmetic improvement, not required for correctness.

**Acceptance criteria**
- [ ] A rejected change never gets optimistically applied to the UI.
- [ ] The blocking-workflow message is shown verbatim (it's the only place this information exists — there's no separate "which workflows use customer context" listing endpoint).

---

## Epic C — Customer Management

Full CRUD over `Customer` records for a tenant. No dedicated "customers" nav item may exist yet in the app shell — this epic includes adding one (role-gated per the cross-cutting note above).

### C1 — Browse the customer list

**As a** Business Owner/Manager/Admin, **I want** to see all customers for my tenant, **so that** I can review who's been captured through workflows (or added manually).

**APIs used**
- `GET /api/v1/customers?per_page=N` (default `per_page=15`) → paginated `{"data": [...], "links": {...}, "meta": {...}}`, ordered newest-first.

**UX flow**
1. Table/list: email, phone, first/last name, `is_active` badge, created date. Custom fields are **not** included by default in the list payload's practical display — `custom_field_values` is present on each row but rendering all of them in a table is likely too dense; consider showing count of populated custom fields, or making columns configurable, rather than assuming which ones matter.
2. Pagination via `links`/`meta` (standard Laravel paginator shape — same as other paginated lists already in this app, e.g. documents).
3. Row click → C3 (view/edit).

**Known gap**: **no search or filter query params exist on this endpoint today** (no `?search=`, `?email=`, etc.) — it's tenant-scoped pagination only. If the UX needs "find a customer by email," that's a backend follow-up (or, for now, client-side filtering of the current page only, which won't scale past one page — call this out rather than quietly building a search box that only searches 15 rows).

### C2 — Create a customer manually

**As a** Business Owner/Manager/Admin, **I want** to add a customer record by hand, **so that** I can pre-populate data before any workflow ever touches it (e.g. importing a known contact).

**APIs used**
- `POST /api/v1/customers` — body: `email`, `phone`, `first_name`, `last_name` (all optional/nullable), `custom_field_values` (object keyed by the tenant's `CustomerField.key`s), `is_active` (defaults true server-side if omitted... actually check: `is_active` has a DB default of `true`, but confirm the create form doesn't need to send it — safe to omit).

**UX flow**
1. Form with the four core fields (all optional — there's no required-field enforcement on email/phone at creation from this module's own validation; note this is looser than the trigger-created path, which always has *some* linking value) plus a dynamically-rendered block for the tenant's custom fields (fetch `GET /api/v1/customers/fields` to build it — same renderer as B2's value-entry side).
2. On submit, unknown/mistyped custom-field keys and missing `is_required` custom fields both come back as a single `422` on `custom_field_values` (`errors.custom_field_values: ["Unknown custom field(s): ..."]` or `["Missing required custom field(s): ..."]`) — since the backend validates the whole object as one unit, surface this as one error near the custom-fields section rather than trying to attribute it to a specific input.

**Acceptance criteria**
- [ ] Dynamic custom-field form reuses the exact same per-type input renderer as B2.
- [ ] Submitting with a custom-field key that was deleted since the form loaded is handled gracefully (server 422s; don't let the app crash trying to render a value for a field definition it no longer has).

### C3 — View and edit a customer record

**As a** Business Owner/Manager/Admin, **I want** to open a customer and fill in/correct their details, **so that** records created automatically by a workflow trigger (which often only have the linking field populated) can be completed over time.

**APIs used**
- `GET /api/v1/customers/{id}` — 404 if not found *or not this tenant's* (the backend doesn't distinguish "not found" from "wrong tenant," by design — don't build UI that assumes it can tell the difference either).
- `PUT /api/v1/customers/{id}` — same body shape as C2, all fields effectively partial (`sometimes` on the backend).

**UX flow**
1. Same form as C2, pre-filled. This is the primary place to explain *why* a record might be sparse: a customer created via trigger-linking (Epic A) starts with **only** the linking field set — first/last name and custom fields get filled in here, later, by a human. Consider a subtle "auto-created" indicator if you want to distinguish these from manually-added ones — **note this isn't currently exposed by the API** (there's no `source` field on `Customer`); flag to backend if it's wanted.
2. `PUT` only what changed — omit unchanged fields rather than resubmitting the whole object (matches the `sometimes` validation and avoids accidentally clobbering `custom_field_values` with a stale merge — actually check: the service *merges* new `custom_field_values` onto the existing ones (`array_merge($customer->custom_field_values, $data['custom_field_values'])`), so partial custom-field updates are safe to send partially; but top-level fields like `email`/`first_name` are plain overwrites if included, so omit ones the user didn't touch).

**Known gap**: no way to see which `WorkflowInstance`s this customer is linked to from this screen — `WorkflowInstance.customer_id` exists and is queryable in the database, but there's no `GET /api/v1/customers/{id}/instances`-style endpoint yet. If "show this customer's activity/history" is wanted, that's a backend follow-up, not something to fake client-side.

### C4 — Delete a customer

**As a** Business Owner/Manager/Admin, **I want** to remove a customer record, **so that** I can clean up test/duplicate/incorrect entries.

**APIs used**
- `DELETE /api/v1/customers/{id}`.

**UX flow**
1. Confirmation modal — this is a hard delete (no soft-delete/`is_active`-only pattern here, unlike `Workflow`'s status field). Say so plainly in the confirmation copy.
2. **Consequence to surface before confirming**: `WorkflowInstance.customer_id` is `nullOnDelete` — any past instance linked to this customer keeps existing but its `customer_id` silently becomes `null`. If your UI ever displayed "linked customer" on an instance detail page, that link will disappear after this delete. Worth one line in the confirmation copy if instance history is something users care about (*"Past workflow runs linked to this customer will no longer show that link"*).

**Acceptance criteria**
- [ ] Confirmation is required (no single-click delete from the list).
- [ ] After delete, remove the row from C1's list without a full re-fetch if practical (standard optimistic-removal pattern).

---

## Summary of new/changed pieces for the frontend to build

1. A `field_reference` config-field renderer (Epic A1) — genuinely new, no existing type to extend.
2. Four new verification error codes to route to existing error-display UI (Epic A2) — no new component, just new `code`s.
3. A Customer Settings screen: linking-field choice (B1) + custom-field schema builder (B2), including a per-`CustomerFieldType` input renderer that gets reused in Epic C's value-entry forms.
4. A Customers section: list (C1), create (C2), view/edit (C3), delete (C4) — new nav surface, role-gated to `business_owner`/`manager`/`admin`.
