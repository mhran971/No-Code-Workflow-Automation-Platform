# Integrations API

This document covers the Integrations module endpoints used by the frontend.
The API is tenant-aware: the authenticated user determines the tenant context.

## Base path

All routes live under:

```http
/api/v1/integrations
```

## Auth rules

- `GET /` and the connect/disconnect endpoints require `auth:api`
- OAuth callback endpoints are public because the provider redirects back to them
- The callback validates the encrypted `state` payload before saving the connection

## Provider key values

Use the provider key in the URL path as the `{provider}` parameter.
Current supported values are:

- `clickup`
- `hubspot`
- `google`

> These come from the seeded providers in `Modules/Integrations/database/seeders/IntegrationsDatabaseSeeder.php`.

## Connection status values

The frontend receives connection status from `IntegrationProviderResource`.
Current values are:

- `connected`
- `not_connected`

## Endpoints

### List providers

```http
GET /api/v1/integrations
```

Returns the available providers and their connection status for the current tenant.

Example response:

```json
[
  { "id": "clickup", "name": "ClickUp", "status": "connected" },
  { "id": "google", "name": "Google", "status": "not_connected" }
]
```

### Start OAuth connection

```http
POST /api/v1/integrations/{provider}/connect
```

Frontend use:
- call this endpoint when the user clicks **Connect**
- the API responds with an HTTP redirect to the provider authorization page
- do not expect a JSON response body

Small example:

```js
window.location.href = `/api/v1/integrations/google/connect`;
```

In practice, the browser follows this redirect automatically and the user is taken to the provider consent screen.

### OAuth callback

```http
GET /api/v1/integrations/{provider}/callback?code=...&state=...
```

This is the redirect URI used by the OAuth provider.
The backend:
1. validates the `code` and `state`
2. resolves the tenant from the encrypted state
3. exchanges the authorization code for tokens
4. stores `auth_config` and `config` in `integration_connections`

### How the frontend should handle the callback

The callback is handled by the backend, not by the frontend.

Recommended flow:

1. Start the OAuth flow with a full-page redirect to `POST /api/v1/integrations/{provider}/connect`
2. Let the browser move through the provider consent screen
3. Allow the provider to redirect back to the callback URL
4. After the backend saves the connection, it redirects the browser back to the integrations screen
5. Refresh `GET /api/v1/integrations` to update the displayed connection status

Example frontend behavior:

```js
window.location.href = `/api/v1/integrations/google/connect`;
// after redirect back, reload the integrations list
```

If you are using a SPA, treat the callback as a navigation event, not an AJAX request.
The backend finishes the OAuth exchange, then returns the user to the integrations page.

### Disconnect provider

```http
DELETE /api/v1/integrations/{provider}/disconnect
```

Removes the saved connection for the authenticated tenant.

Example:

```js
await fetch('/api/v1/integrations/google/disconnect', { method: 'DELETE' });
```

### List ClickUp workspaces

```http
GET /api/v1/integrations/clickup/workspaces
```

Requires `auth:api` + `role:business_owner,manager` (looser than the connect/disconnect/index
endpoints above, since this backs workflow-design UI — e.g. populating the `clickup-create-task`
node's workspace picker — which Managers also use, not just Business Owners).

Returns the tenant's connected ClickUp workspaces (ClickUp calls them "teams"), fetched live from
ClickUp on every call:

```json
{ "data": [{ "id": "900", "name": "Acme Workspace" }] }
```

`404` with `{"message": "..."}` if the tenant has no ClickUp connection yet.

### List ClickUp lists in a workspace

```http
GET /api/v1/integrations/clickup/workspaces/{workspaceId}/lists
```

Same auth as above. Returns every List reachable from the given workspace — Lists inside Folders,
plus folderless Lists directly under a Space — flattened into one array (ClickUp has no single
"all lists in a workspace" endpoint, so this call fans out across Spaces/Folders internally):

```json
{
  "data": [
    { "id": "901", "name": "Backlog", "space": "Engineering", "folder": null },
    { "id": "902", "name": "Sprint 14", "space": "Engineering", "folder": "Sprints" }
  ]
}
```

`404` if not connected; any ClickUp API error surfaces with ClickUp's own HTTP status and message.

## Frontend flow

1. Fetch `GET /api/v1/integrations`
2. Render providers with their `status`
3. If status is `not_connected`, show a **Connect** button
4. On click, send the user to `POST /{provider}/connect`
5. After OAuth completes, refresh the integrations list

## Stored connection data

The `integration_connections` table stores:

- `integration_provider_id`
- `tenant_id`
- `auth_config` — tokens and OAuth auth data
- `config` — provider-specific config such as workspace or portal IDs

## Notes for the frontend

- Do not pass tenant IDs from the UI
- Use the authenticated session/token only
- Handle redirect responses from `connect` endpoints
- Refresh provider status after the callback completes

## Example provider keys in UI

```js
const providers = ['clickup', 'hubspot', 'google'];
```

## Example status handling in UI

```js
if (provider.status === 'connected') {
  // show disconnect / connected badge
} else {
  // show connect button
}
```
