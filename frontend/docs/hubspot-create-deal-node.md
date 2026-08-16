# Frontend implementation guide: HubSpot "Create Deal" node

Companion to [`frontend-architecture.md`](frontend-architecture.md),
[`clickup-create-task-node.md`](clickup-create-task-node.md), and
[`hubspot-create-contact-node.md`](hubspot-create-contact-node.md) — read those first, this doc
assumes the same background. Field-type-wise this is closer to ClickUp than to Contact: some
fields are literal, tenant-specific IDs with **no live picker built yet** (same situation ClickUp's
`workspaceId`/`listId` started in before those got dedicated endpoints), not free template text.

## 1. What this node does

Placed on a workflow canvas (Action category), it creates a deal in the connected HubSpot account,
optionally associated with an existing contact — the natural pairing with the Create Contact node.

| key | HubSpot property | required? | nature |
|---|---|---|---|
| `dealName` | `dealname` | **yes** | per-execution value, template-capable |
| `dealStage` | `dealstage` | **yes** | literal ID, chosen once at design time |
| `pipeline` | `pipeline` | no | literal ID, chosen once at design time |
| `amount` | `amount` | no | per-execution value, template-capable |
| `closeDate` | `closedate` | no | per-execution value, template-capable (ISO 8601 string) |
| `ownerId` | `hubspot_owner_id` | no | literal ID, chosen once at design time |
| `contactId` | *(association, not a property)* | no | per-execution value, template-capable |

### ⚠️ The literal-ID / template-value split is deliberate — don't blur it

`dealStage`, `pipeline`, and `ownerId` are **never rendered through the template engine** on the
backend (`HubSpotCreateDealExecutor` reads them with plain `trim((string) ($config[...] ?? ''))`,
no `$context->render()` call) — they're meant to be pasted-in literal IDs from the tenant's own
HubSpot account (Settings → Pipelines, etc.), the same way ClickUp's `workspaceId`/`listId` are
literal IDs, not `{{context.x}}` templates. **Do not offer `{{...}}` autocomplete or template
validation on these three fields** — it would suggest a capability the backend doesn't honor; a
`{{context.dealStageId}}` typed into `dealStage` would be sent to HubSpot literally as that string
and fail as an invalid stage.

`dealName`, `amount`, `closeDate`, and `contactId` **are** rendered via `$context->render()` and do
support `{{context.x}}`/`{{customer.x}}` template variables, exactly like `hubspot-create-contact`'s
fields.

### ⚠️ `contactId` only supports a single flat `context.<key>`, not a nested path

The natural instinct is a placeholder like `{{context.createContact.hubspot_contact_id}}` —
reaching into a specific prior node's output. **This does not validate.** The shared backend
template-variable regex (`Modules/Workflows/app/Services/Verification/Rules/NodeType/Concerns/VariableAvailability.php`)
only accepts a single identifier segment after `context.`/`customer.` — `context.foo`, not
`context.foo.bar`. This was actually caught as a bug while building this node (an earlier draft of
the seeder's own placeholder used the nested form and failed its own test). The seeded placeholder
is `{{context.contactId}}` — i.e. the intended pattern is: an earlier step (a trigger variable, or
a `Parse JSON` node) sets a flat `context.contactId` value, and this field references that. There
is currently no built-in way to reference "the specific `hubspot_contact_id` field from a specific
earlier node" through `{{...}}` syntax — don't design a picker UI that implies otherwise.

## 2. Backend contract

`GET /api/v1/workflows/nodes` returns this definition (already seeded):

```json
{
  "type": "hubspot-create-deal",
  "label": "HubSpot: Create Deal",
  "category": "action",
  "configFields": [
    { "key": "dealName", "label": "Deal Name", "type": "text", "required": true, "placeholder": "{{context.dealName}}" },
    { "key": "dealStage", "label": "Deal Stage ID", "type": "text", "required": true, "placeholder": "Internal stage ID" },
    { "key": "pipeline", "label": "Pipeline ID", "type": "text", "placeholder": "Internal pipeline ID (optional)" },
    { "key": "amount", "label": "Amount", "type": "text", "placeholder": "{{context.amount}}" },
    { "key": "closeDate", "label": "Close Date", "type": "text", "placeholder": "{{context.closeDate}} (ISO 8601)" },
    { "key": "ownerId", "label": "Owner ID", "type": "text", "placeholder": "HubSpot owner ID" },
    { "key": "contactId", "label": "Associate Contact ID", "type": "text", "placeholder": "{{context.contactId}}" }
  ]
}
```

`HubSpotCreateDealExecutor` reads exactly these seven keys. `contactId` isn't sent as a deal
property — if it renders to a numeric value, the executor builds a HubSpot association payload
(`{to: {id}, types: [{associationCategory: 'HUBSPOT_DEFINED', associationTypeId: 3}]}`, type `3` =
Deal→Contact) and attaches it to the create-deal request; a non-numeric or blank value is silently
skipped (not an error — the association is optional and best-effort).

### ⚠️ Node type key — do not reuse `hubspot-deal`

Use **`hubspot-create-deal`**, not `hubspot-deal`. Same collision as `hubspot-contact`: the
existing `hubspot-deal` entry in `src/config/schemas/integrations.ts` is an unwired v0 mock
(different fields, a `connection` select, `associateCompany`, `outputVariable`) with no backend
behind it. Leave it alone; add a new entry.

## 3. No new API endpoints, but a real gap this leaves in the UX

Same situation as Contact — no picker-dependent endpoints exist for this node. But **unlike**
Contact, that actually matters here: `dealStage` and `pipeline` are opaque internal IDs the user
has no way to look up inside this app. HubSpot's own API doc notes both need
`GET /crm/v3/pipelines/deals` to resolve to human-readable stage/pipeline names — that endpoint
isn't wrapped by this app's backend at all yet.

**Recommendation**: for this first version, treat `dealStage`/`pipeline`/`ownerId` as plain text
inputs with a clear helper hint pointing the user at where to find these values in HubSpot itself
(e.g. *"Find your Pipeline/Stage IDs under HubSpot Settings → Objects → Deals → Pipelines"*) rather
than trying to fake a picker with no backing data. If this needs a proper live picker later, it
would require new Integrations endpoints (`GET /api/v1/integrations/hubspot/pipelines` or similar,
wrapping `GET /crm/v3/pipelines/deals`) — the same shape of work ClickUp's workspace/list endpoints
were, but that's out of scope here; flag it back if this node's usability turns out to be a
problem in practice.

## 4. Schema entry

Add to `src/config/schemas/integrations.ts` (a **new** entry keyed `'hubspot-create-deal'`):

```ts
'hubspot-create-deal': {
  nodeType: 'hubspot-create-deal',
  sections: [
    {
      title: 'Deal',
      fields: [
        { key: 'dealName', label: 'Deal Name', type: 'templatetext', required: true, placeholder: '{{context.dealName}}' },
        { key: 'dealStage', label: 'Deal Stage ID', type: 'text', required: true, placeholder: 'Internal stage ID', description: 'Find this under HubSpot Settings → Objects → Deals → Pipelines.' },
        { key: 'pipeline', label: 'Pipeline ID', type: 'text', placeholder: 'Internal pipeline ID (optional)' },
      ],
    },
    {
      title: 'Details',
      fields: [
        { key: 'amount', label: 'Amount', type: 'templatetext', placeholder: '{{context.amount}}' },
        { key: 'closeDate', label: 'Close Date', type: 'templatetext', placeholder: '{{context.closeDate}} (ISO 8601)' },
        { key: 'ownerId', label: 'Owner ID', type: 'text', placeholder: 'HubSpot owner ID' },
      ],
    },
    {
      title: 'Association',
      fields: [
        { key: 'contactId', label: 'Associate Contact ID', type: 'templatetext', placeholder: '{{context.contactId}}', description: 'Optional. Must resolve to a numeric HubSpot contact ID.' },
      ],
    },
  ],
},
```

Only one field type is used, and it's already implemented — no new `ConfigFieldType`, renderer, or
`FieldRenderer.tsx` case:

- **`templatetext`** for the four per-execution fields (`dealName`, `amount`, `closeDate`,
  `contactId`) — same type used by `hubspot-create-contact`'s `firstName`/`lastName`/`phone` and
  `send-email`'s `subject`.
- Plain **`text`** (no template validation) for `dealStage`/`pipeline`/`ownerId`, matching their
  literal-ID nature described in §1 — don't upgrade these to `templatetext`, it would validate
  `{{...}}` syntax the backend never actually renders.

### Icon and canvas node — already work, no changes required

- **Icon**: seeded as `Handshake`, already in `iconMap` (`src/components/workflow/WorkflowNode.tsx`).
- **Canvas component**: no entry needed in `nodeComponentType()` — generic `workflowNode`.

## 5. Recommended UX

1. Group fields as in the schema above — Deal (name/stage/pipeline), Details (amount/close
   date/owner), Association (contact) — mirrors HubSpot's own Create Deal form grouping reasonably
   well and separates "required to create anything" from "optional enrichment."
2. Add a visible hint near `dealStage`/`pipeline`/`ownerId` that these are HubSpot-internal IDs,
   not display names, and where to find them (see §3) — this is the single biggest usability risk
   for this node given there's no live picker.
3. `contactId` should read as clearly optional and clearly about *associating* the deal, not a
   required part of deal creation — a short description line under the field (as in the schema
   snippet above) is enough; no special validation UI needed beyond what `templatetext` already
   gives for template-syntax errors (it won't catch "not numeric," that's backend/execution-time
   only, since it depends on what the template resolves to at runtime).
4. No loading states, no dependent-field fetches — everything renders immediately like Contact.

### Validation error mapping

| code | meaning | which field to highlight |
|---|---|---|
| `hubspot_create_deal.dealname_missing` | `dealName` blank | Deal Name |
| `hubspot_create_deal.dealstage_missing` | `dealStage` blank | Deal Stage ID |
| `hubspot_create_deal.invalid_template_variable` | malformed `{{...}}` in dealName/amount/closeDate/contactId | whichever field the issue's `path` points at |
| `hubspot_create_deal.connection_missing` | tenant has no HubSpot `IntegrationConnection` at publish time | node-level, same pattern as the Contact/ClickUp nodes |

Note there's no `hubspot_create_deal.contactid_invalid`-style code — a non-numeric `contactId`
isn't a verification error, it's silently ignored at execution time (see §2). Don't build a
matching frontend validation state for it; there isn't a backend error to map it to.

## 6. Verification checklist

1. `npm run build` and `npm run lint`.
2. Manually: drag the node, fill only `dealName`+`dealStage`, save and publish (should succeed).
   Leave `dealStage` blank and try to publish (should surface `dealstage_missing`).
3. Set `contactId` to `{{context.contactId}}` where `context.contactId` is a real numeric HubSpot
   contact ID and confirm the created deal shows the association in HubSpot; set it to something
   non-numeric and confirm the deal still creates successfully, just without an association.
4. Against a tenant **without** HubSpot connected: publish and confirm `connection_missing`.
5. Trigger a real (or sandbox) run and confirm the deal appears in HubSpot with the right
   stage/pipeline — backend side already covered by
   `tests/Unit/Execution/HubSpotCreateDealExecutorTest.php`,
   `tests/Unit/HubSpotCreateDealNodeTypeRuleTest.php`, and the `createDeal()` cases in
   `tests/Unit/HubSpotClientTest.php`, but an end-to-end run is the only way to catch a
   frontend/backend field-key mismatch, and the only way to confirm a `dealStage`/`pipeline` ID
   the user typed in is actually valid for their account (nothing else checks that beforehand).
