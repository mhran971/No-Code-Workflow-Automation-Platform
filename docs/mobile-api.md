# Mobile API Reference

Base URL: `https://<host>/api/v1`

All protected endpoints require a JWT in the `Authorization` header:

```
Authorization: Bearer <token>
```

Tokens are obtained from `POST /login` and are valid for the duration configured in `config/jwt.php` (`ttl`, default 1440 minutes / 24 hours).

---

## Authentication

### POST /login

Authenticate a user and receive a JWT.

**Request**

```json
{
  "email": "alice@example.com",
  "password": "secret"
}
```

**Response `200 OK`**

```json
{
  "message": "Login successful.",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_in_minutes": 1440,
  "user": {
    "id": 1,
    "email": "alice@example.com",
    "first_name": "Alice",
    "last_name": "Smith",
    "role": "Employee",
    "tenant": {
      "id": 1,
      "business_name": "Acme Corp"
    }
  }
}
```

**Error responses**

| Status | Condition |
|--------|-----------|
| `401` | Wrong email or password |
| `403` | Account is disabled |

---

### POST /logout

Invalidate the current JWT. Requires authentication.

**Response `200 OK`**

```json
{
  "message": "Logged out successfully."
}
```

---

### GET /me

Return the authenticated user's full profile including their role, tenant, and team membership. Use this on app startup to populate the user context.

**Response `200 OK`**

```json
{
  "id": 1,
  "email": "alice@example.com",
  "first_name": "Alice",
  "last_name": "Smith",
  "position": "Operations Manager",
  "role": "Employee",
  "tenant": {
    "id": 1,
    "business_name": "Acme Corp"
  },
  "team": {
    "id": 3,
    "name": "Sales Team"
  }
}
```

> `team` is `null` if the user is not a member of any team.

**Role values:** `BusinessOwner` | `Manager` | `Admin` | `Employee`

---

## Tasks

All task endpoints are scoped to the authenticated user's role:

| Role | Visible tasks |
|------|---------------|
| `Employee` | Only tasks assigned to themselves |
| `Manager` | All tasks assigned to members of their team |
| `BusinessOwner` | All tasks within the tenant |

---

### GET /workflows/tasks/summary

Return pending and overdue task counts for the home screen stat cards. Counts are scoped to the caller's role.

**Response `200 OK`**

```json
{
  "pending_count": 12,
  "overdue_count": 3
}
```

- `pending_count` — total open tasks in scope
- `overdue_count` — open tasks whose `due_at` has already passed

---

### GET /workflows/tasks

Return a paginated list of tasks. Defaults to `status=open`, sorted by `due_at` ascending.

**Query parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sort` | `due_asc` \| `due_desc` \| `created_asc` | `due_asc` | Sort order |
| `status` | `open` \| `completed` \| `expired` \| `cancelled` | `open` | Filter by status |
| `search` | string | — | Case-insensitive substring match against `title` and `description` (max 255 chars) |
| `assignee_id` | integer | — | Filter by a specific assignee. **Not available to `Employee` role.** |
| `page` | integer | `1` | Page number (20 items per page) |

**Response `200 OK`**

```json
{
  "data": [
    {
      "id": 42,
      "title": "Review Proposal",
      "description": "Check the Q3 proposal and approve or reject.",
      "status": "open",
      "due_at": "2026-07-05T09:00:00+00:00",
      "completed_at": null,
      "created_at": "2026-06-30T08:00:00+00:00",
      "assignee": {
        "id": 1,
        "name": "Alice Smith"
      },
      "instance_id": 7,
      "node_key": "task_1"
    }
  ],
  "links": {
    "first": "https://<host>/api/v1/workflows/tasks?page=1",
    "last": "https://<host>/api/v1/workflows/tasks?page=4",
    "prev": null,
    "next": "https://<host>/api/v1/workflows/tasks?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "per_page": 20,
    "to": 20,
    "total": 73
  }
}
```

**Error responses**

| Status | Condition |
|--------|-----------|
| `422` | Invalid query parameter value |

---

### GET /workflows/tasks/{task}

Return full detail for a single task, including the form schema and any saved draft.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `task` | Task ID (integer) |

**Response `200 OK`**

```json
{
  "data": {
    "id": 42,
    "title": "Review Proposal",
    "description": "Check the Q3 proposal and approve or reject.",
    "status": "open",
    "due_at": "2026-07-05T09:00:00+00:00",
    "completed_at": null,
    "created_at": "2026-06-30T08:00:00+00:00",
    "input_schema": [
      {
        "key": "decision",
        "label": "Decision",
        "type": "select",
        "required": true,
        "options": ["approve", "reject", "revise"]
      },
      {
        "key": "comments",
        "label": "Comments",
        "type": "textarea",
        "required": false
      }
    ],
    "draft_response": {
      "decision": "approve"
    },
    "assignee": {
      "id": 1,
      "name": "Alice Smith",
      "email": "alice@example.com",
      "position": "Operations Manager"
    },
    "completed_by": null,
    "instance_id": 7,
    "node_key": "task_1"
  }
}
```

**`input_schema` field types**

| Type | Description | Extra fields |
|------|-------------|--------------|
| `text` | Single-line text | — |
| `textarea` | Multi-line text | — |
| `number` | Numeric value | — |
| `date` | Date string (`YYYY-MM-DD`) | — |
| `file` | File URL / path string | — |
| `select` | Single choice from a list | `options: string[]` |
| `checkbox` | Multiple choices from a list | `options: string[]` |

> `draft_response` mirrors the `response` shape (a key-value map). It is `null` if no draft has been saved yet.
>
> `completed_by` is `null` while the task is open. Once submitted, it contains `{ id, name }`.

**Error responses**

| Status | Condition |
|--------|-----------|
| `403` | Task belongs to another tenant, or caller lacks access (wrong role) |
| `404` | Task not found |

---

### PATCH /workflows/tasks/{task}/draft

Save a partial response without submitting the task. The task remains `open`. Use this for auto-save while the user fills in the form.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `task` | Task ID (integer) |

**Request**

The `response` object keys must match the `key` fields in `input_schema`. Partial payloads are accepted — only the provided keys are stored (the whole object is replaced on each call).

```json
{
  "response": {
    "decision": "approve"
  }
}
```

**Response `200 OK`**

```json
{
  "message": "Draft saved."
}
```

**Error responses**

| Status | Condition |
|--------|-----------|
| `403` | Task belongs to another tenant, or caller lacks access (wrong role) |
| `404` | Task not found |
| `422` | `response` field missing or not an object / task is already closed |

---

### POST /workflows/tasks/{task}/submit

Submit the final response for a task. Validates the payload against `input_schema`, marks the task `completed`, and resumes the workflow execution.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `task` | Task ID (integer) |

**Request**

All fields marked `required: true` in `input_schema` must be present. Types are validated per the table above.

```json
{
  "response": {
    "decision": "approve",
    "comments": "Looks good to me."
  }
}
```

**Validation rules by field type**

| Type | Rule |
|------|------|
| `text` / `textarea` / `file` | Must be a string |
| `number` | Must be numeric |
| `date` | Must be a valid date (`YYYY-MM-DD` recommended) |
| `select` | Must be one of the field's `options` values |
| `checkbox` | Must be an array; each item must be one of the field's `options` values |

**Response `200 OK`**

```json
{
  "message": "Task submitted."
}
```

**Error responses**

| Status | Condition |
|--------|-----------|
| `403` | Task belongs to another tenant, or caller lacks access (wrong role) |
| `404` | Task not found |
| `422` | Validation failure (missing required fields, wrong type, invalid option) or task already closed |

Validation errors follow Laravel's standard format:

```json
{
  "message": "The Decision field is required.",
  "errors": {
    "response.decision": ["The Decision field is required."]
  }
}
```

---

## Common Headers

| Header | Value |
|--------|-------|
| `Authorization` | `Bearer <token>` |
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |

## Common Error Codes

| Status | Meaning |
|--------|---------|
| `401` | Missing or expired JWT — re-authenticate |
| `403` | Authenticated but not authorized for this resource |
| `404` | Resource not found |
| `422` | Validation error — see `errors` object in response body |
| `500` | Server error |
