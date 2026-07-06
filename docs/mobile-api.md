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

`open` and `expired` are mutually exclusive: an open task whose `due_at` has passed is reported as
`expired` (in both the `status` filter and the `status` field on each item) and is excluded from the
`open` bucket. This is purely a display/filter distinction — the task stays actionable (its parked
execution is unaffected) until it is submitted or its instance is cancelled. `completed` and
`cancelled` tasks are unaffected by `due_at` and always keep their real status.

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
    "response": null,
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

> `response` is the final submitted answer (a key-value map). It is `null` until the task is submitted.
>
> `draft_response` mirrors the `response` shape (a key-value map). It is `null` if no draft has been saved yet.
>
> `completed_by` is `null` while the task is open. Once submitted, it contains `{ id, name }`.
>
> `status` is `expired` for an open task whose `due_at` has passed. `completed` and `cancelled` are
> final and are never overridden by `due_at`. A task is also set to `cancelled` automatically when
> its workflow instance is cancelled.

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

The `response` object keys must match the `key` fields in `input_schema`. Partial payloads are accepted — only the provided keys are stored (the whole object is replaced on each call). `response` may be omitted or empty (e.g. `{}`) to save a blank draft.

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
| `422` | `response` is present but not an object, or task is already closed |

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

## Workflow Instances

A workflow instance is a single run of a published workflow. Each task carries an `instance_id` field — use it to navigate from a task to the instance detail view. The node executions embedded in the instance serve as the execution timeline.

---

### GET /workflows/{workflow}/instances

Return a paginated list of instances for a workflow, most recent first.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `workflow` | Workflow ID (integer) |

**Query parameters**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | `pending` \| `running` \| `waiting` \| `paused` \| `completed` \| `failed` \| `cancelled` | — | Filter by instance status |
| `started_from` | `YYYY-MM-DD` | — | Include instances started on or after this date |
| `started_to` | `YYYY-MM-DD` | — | Include instances started on or before this date |
| `finished_from` | `YYYY-MM-DD` | — | Include instances finished on or after this date |
| `finished_to` | `YYYY-MM-DD` | — | Include instances finished on or before this date |
| `page` | integer | `1` | Page number (20 items per page) |

**Response `200 OK`**

```json
{
  "data": [
    {
      "id": 7,
      "status": "waiting",
      "started_at": "2026-07-04T08:00:00+00:00",
      "finished_at": null
    }
  ],
  "links": {
    "first": "https://<host>/api/v1/workflows/3/instances?page=1",
    "last": "https://<host>/api/v1/workflows/3/instances?page=4",
    "prev": null,
    "next": "https://<host>/api/v1/workflows/3/instances?page=2"
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

### GET /workflows/instances/{instance}

Return full detail for a single workflow instance, including all node executions that form the execution timeline.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `instance` | Instance ID (integer) |

**Response `200 OK`**

```json
{
  "id": 7,
  "workflow_id": 3,
  "workflow_version_id": 2,
  "parent_instance_id": null,
  "tenant_id": 1,
  "status": "waiting",
  "trigger_type": "manual",
  "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
  "payload": {
    "submitted_by": 1
  },
  "context": {
    "submitted_by": 1,
    "task_response": null
  },
  "error": null,
  "paused_reason": null,
  "started_at": "2026-07-04T08:00:00+00:00",
  "finished_at": null,
  "created_at": "2026-07-04T08:00:00+00:00",
  "updated_at": "2026-07-04T08:05:00+00:00",
  "node_executions": [
    {
      "id": 101,
      "instance_id": 7,
      "node_key": "trigger_1",
      "node_type": "manual-trigger",
      "status": "succeeded",
      "attempt": 1,
      "input": {},
      "output": { "submitted_by": 1 },
      "error": null,
      "wait_until": null,
      "wait_type": null,
      "started_at": "2026-07-04T08:00:00+00:00",
      "finished_at": "2026-07-04T08:00:01+00:00"
    },
    {
      "id": 102,
      "instance_id": 7,
      "node_key": "task_1",
      "node_type": "task-node",
      "status": "waiting",
      "attempt": 1,
      "input": {},
      "output": null,
      "error": null,
      "wait_until": "2026-07-05T08:00:00+00:00",
      "wait_type": "task_sla",
      "started_at": "2026-07-04T08:00:02+00:00",
      "finished_at": null
    }
  ]
}
```

**Instance status values**

| Value | Meaning |
|-------|---------|
| `pending` | Admitted, not yet started |
| `running` | Actively executing nodes |
| `waiting` | All live tokens parked (e.g. awaiting a task submission) |
| `paused` | Manually held or under review |
| `completed` | All paths finished successfully |
| `failed` | Terminal failure |
| `cancelled` | Manually cancelled |

**Trigger type values:** `manual` | `form` | `webhook`

**Node execution status values**

| Value | Meaning |
|-------|---------|
| `pending` | Created, not yet run |
| `running` | Executing now |
| `succeeded` | Finished successfully |
| `failed` | Terminated with an error |
| `waiting` | Parked on a durable wait (`task_sla` or `merge_timeout`) |
| `skipped` | Branch not taken (e.g. the other side of a conditional) |
| `consumed` | Token was absorbed by a merge node |

**Context data**

The `context` object is the shared runtime state of the workflow. It accumulates outputs from each node as the instance progresses. Each key is set by a node's output and becomes available to downstream nodes. The initial `payload` holds the data that triggered the run (form fields, webhook body, or manual trigger metadata).

> The timeline is derived from `node_executions`, ordered by `started_at`. Nodes with `status: waiting` and a non-null `wait_until` are the currently blocked steps.

**Error responses**

| Status | Condition |
|--------|-----------|
| `403` | Instance belongs to another tenant |
| `404` | Instance not found |

---

### GET /workflows/instances/{instance}/failures

Return only the failed node executions for an instance. Use this to show error detail when the instance status is `failed`.

**Path parameters**

| Parameter | Description |
|-----------|-------------|
| `instance` | Instance ID (integer) |

**Response `200 OK`**

```json
{
  "instance_id": 7,
  "instance_status": "failed",
  "instance_error": {
    "message": "Max retries exceeded on node send_email_1",
    "node_key": "send_email_1"
  },
  "failed_nodes": [
    {
      "id": 105,
      "node_key": "send_email_1",
      "node_type": "send-email",
      "attempt": 3,
      "error": {
        "message": "SMTP connection timed out",
        "code": "CONNECTION_TIMEOUT"
      },
      "started_at": "2026-07-04T09:10:00+00:00",
      "finished_at": "2026-07-04T09:10:05+00:00"
    }
  ]
}
```

> `instance_error` is `null` when the instance has not failed at the top level. `failed_nodes` is an empty array if no individual nodes are in a failed state.

**Error responses**

| Status | Condition |
|--------|-----------|
| `403` | Instance belongs to another tenant |
| `404` | Instance not found |

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
