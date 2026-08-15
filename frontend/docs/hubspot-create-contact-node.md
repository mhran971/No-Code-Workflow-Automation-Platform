# Frontend implementation guide: HubSpot "Create Contact" node

Companion to [`frontend-architecture.md`](frontend-architecture.md) and
[`clickup-create-task-node.md`](clickup-create-task-node.md) (same handoff format, read that one
first if you haven't — this doc assumes the same background on schema registration and the
local-registry-overrides-backend rule). Good news up front: **this node needs no new field types,
renderers, or API calls** — it's a plain config schema entry using components that already exist.

## 1. What this node does

Placed on a workflow canvas (Action category), it creates a contact in the connected HubSpot
account when the workflow reaches it. Deliberately minimal scope — just four fields:

| key | HubSpot property | individually required? |
|---|---|---|
| `firstName` | `firstname` | no |
| `lastName` | `lastname` | no |
| `email` | `email` | no |
| `phone` | `phone` | no |

No field is individually required, but **the backend requires at least one of `firstName`,
`lastName`, or `email`** (a blank contact with only a phone number is rejected — this mirrors
HubSpot's own API constraint). All four support `{{context.x}}` / `{{customer.x}}` template
variables, rendered at execution time — same convention as every other text field elsewhere in
this app (e.g. `send-email`'s `subject`).

Like `clickup-create-task`, this node only works for a tenant that has already connected HubSpot
via the Integrations page — it doesn't do the connecting itself.

## 2. Backend contract — do not deviate from these field keys

`GET /api/v1/workflows/nodes` returns this definition (already seeded):

```json
{
  "type": "hubspot-create-contact",
  "label": "HubSpot: Create Contact",
  "category": "action",
  "configFields": [
    { "key": "firstName", "label": "First Name", "type": "text", "placeholder": "{{context.firstName}}" },
    { "key": "lastName", "label": "Last Name", "type": "text", "placeholder": "{{context.lastName}}" },
    { "key": "email", "label": "Email", "type": "text", "placeholder": "{{context.email}}" },
    { "key": "phone", "label": "Phone", "type": "text", "placeholder": "{{context.phone}}" }
  ]
}
```

`HubSpotCreateContactExecutor` reads exactly these four keys and skips whichever are blank when
building the HubSpot `properties` payload. The saved `node.data.config` must use these exact keys.

### ⚠️ Node type key — do not reuse `hubspot-contact` or `hubspot-deal`

Use **`hubspot-create-contact`**, not `hubspot-contact`. Same situation as `clickup-task` from the
ClickUp node: `src/config/schemas/integrations.ts` already has unwired mock schemas for both
`hubspot-contact` and `hubspot-deal` — v0 prototypes with different field names (`outputVariable`,
a `connection` select, deal-specific fields) and no backend `Node` row, rule, or executor behind
either. Don't touch those entries; add a new one keyed `hubspot-create-contact`.

## 3. No new API endpoints — unlike ClickUp

The ClickUp node needed two new endpoints because the user had to pick a workspace and then a list
from live ClickUp data. HubSpot contact creation has no equivalent "pick a target" step — there's
nothing to browse. So there is nothing to fetch for this config panel beyond what
`GET /api/v1/workflows/nodes` already returns. If a future iteration wants an "associate with an
existing company" picker or similar, that would need new Integrations endpoints the same way
ClickUp's did — but that's out of scope for this minimal version.

## 4. Schema entry — reuses existing field types, nothing new to build

Add to `src/config/schemas/integrations.ts` (a **new** entry keyed `'hubspot-create-contact'` —
leave the existing `'hubspot-contact'`/`'hubspot-deal'` entries alone):

```ts
'hubspot-create-contact': {
  nodeType: 'hubspot-create-contact',
  sections: [
    {
      title: 'Contact',
      fields: [
        { key: 'firstName', label: 'First Name', type: 'templatetext', placeholder: '{{context.firstName}}' },
        { key: 'lastName', label: 'Last Name', type: 'templatetext', placeholder: '{{context.lastName}}' },
        { key: 'email', label: 'Email', type: 'emailtemplate', placeholder: '{{context.email}} or jane@example.com' },
        { key: 'phone', label: 'Phone', type: 'templatetext', placeholder: '{{context.phone}}' },
      ],
    },
  ],
},
```

Field type choices, both already implemented in `src/components/workflow/config-fields/templates.tsx`
and dispatched in `FieldRenderer.tsx` — no new `ConfigFieldType`, no new renderer, no new `case`:

- **`templatetext`** for `firstName`/`lastName`/`phone` — validates any `{{...}}` in the value is a
  well-formed `context.x`/`customer.x` reference as the user types. Same type `send-email`'s
  `subject` field already uses.
- **`emailtemplate`** for `email` — validates the value is *either* one complete
  `{{context.x}}`/`{{customer.x}}` variable *or* a literal, valid-looking email address (via
  `EmailTemplateField`'s `validateEmailOrVar`). This is an exact frontend mirror of what
  `HubSpotCreateContactNodeTypeRule::validateEmailOrVar()` enforces server-side. Worth noting:
  this field type exists in the codebase but isn't actually wired into any schema yet (`send-email`
  itself uses plain `text` for `to`/`cc`/`bcc`, relying on backend-only validation) — this would be
  its first real use. It's the more precise choice for `email` here since the backend specifically
  validates email *format*, not just template-variable syntax.

That's the entire frontend change. No `ConfigFieldType` additions, no new components, no
`apiBaseUrl`/`accessToken` threading — every piece this node needs already exists.

### Icon and canvas node — already work, no changes required

- **Icon**: seeded as `UserPlus`, already in `iconMap` (`src/components/workflow/WorkflowNode.tsx`).
- **Canvas component**: no entry needed in `nodeComponentType()` — falls through to the generic
  `workflowNode` component (single input/output, no branching).

## 5. Recommended UX

Much simpler than ClickUp's — no loading states, no dependent fields, no "connect first" empty
state to build into the panel itself (the connection-missing case only surfaces as a publish-time
validation error, not something the config panel needs to pre-check).

1. User drags "HubSpot: Create Contact" onto the canvas and opens the config panel — four fields
   render immediately, no fetches.
2. User fills in whichever fields are relevant. Since none are individually marked `required` (the
   constraint is "at least one of three"), don't red-border a single field by default — instead,
   surface the identity-requirement as node-level guidance, e.g. a small helper line under the
   section title: *"At least one of First Name, Last Name, or Email is required."*
3. If the user leaves all three blank and tries to save/publish, the backend verification error
   (`hubspot_create_contact.identity_missing`, `path` = `nodes[i].config`, not a specific field)
   should highlight the node as a whole rather than any single field — there's no one field to
   blame.
4. `emailtemplate`'s inline validation catches malformed input (`not-an-email`, `{{bad syntax}}`)
   before the user even tries to save — the backend re-validates the same thing at publish time as
   a safety net, not the primary UX.

### Validation error mapping

| code | meaning | which field/scope to highlight |
|---|---|---|
| `hubspot_create_contact.identity_missing` | all three of firstName/lastName/email are blank | node-level, not a specific field (`path` is `nodes[i].config`) |
| `hubspot_create_contact.invalid_template_variable` | malformed `{{...}}` in firstName/lastName/phone | whichever field the issue's `path` points at |
| `hubspot_create_contact.email_invalid` | email is neither a valid literal address nor a single well-formed template variable | Email field |
| `hubspot_create_contact.connection_missing` | tenant has no HubSpot `IntegrationConnection` at publish time | node-level — same "connect HubSpot" messaging pattern as ClickUp's connection-missing case |

Flows through `useFieldValidationErrors` like every other node type — no new plumbing.

## 6. Verification checklist

1. `npm run build` and `npm run lint` (no new types, so this should be a no-op risk-wise, but
   confirm the schema entry itself type-checks).
2. Manually: drag the node, confirm the four fields render with `templatetext`/`emailtemplate`
   inline validation, save with only `email` filled (should succeed), save with only `phone`
   filled and try to publish (should surface `identity_missing` at the node).
3. Against a tenant **without** HubSpot connected: publish a workflow using this node and confirm
   `connection_missing` surfaces clearly rather than a generic error.
4. Trigger a real (or sandbox) run through this node and confirm the contact appears in HubSpot —
   backend side already covered by `tests/Unit/Execution/HubSpotCreateContactExecutorTest.php`,
   `tests/Unit/HubSpotClientTest.php`, and `tests/Unit/HubSpotCreateContactNodeTypeRuleTest.php`,
   but an end-to-end run is the only way to catch a frontend/backend field-key mismatch.
