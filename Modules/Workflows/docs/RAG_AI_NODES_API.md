# RAG Service API — AI Generator & AI Classification Nodes

Audience: the RAG service team. This is a contract spec for **two endpoints the RAG service needs to implement**. Nothing on the Laravel side is wired up to these yet — the workflow node executors that will call them are either unregistered (`ai-generator`) or not yet built (`ai-classification`). This document describes what Laravel will send and what it expects back, so RAG can build to the same conventions already used by the existing `/index-document`, `/toggle-status`, `/delete-document`, and `/api/v1/ai/workflows/generate` endpoints.

## Shared conventions (already in use, keep consistent)

- **Base URL**: `RAG_SERVICE_URL` (Laravel env), e.g. `http://127.0.0.1:8000`.
- **Auth header**: `X-API-Key: <RAG_SERVICE_API_KEY>` on every request.
  - ⚠️ Known gap today: the RAG service currently accepts requests regardless of whether this key is present or correct (verified live — both a missing key and a garbage key return `200`). If that's not intentional, it'd be good to fix as part of this work, but it's a pre-existing issue, not something new these two endpoints introduce.
- **Timeout**: Laravel calls with `RAG_SERVICE_TIMEOUT` (default 60s). If RAG can't respond within that window, Laravel treats it as a failed call — respond as fast as possible, and if generation/classification can genuinely take longer, tell us so we can raise the timeout for these specific calls.
- **Field naming**: snake_case in the JSON body (matches `tenant_id`, `article_id`, etc. in the existing endpoints).
- **`tenant_id`**: always present, always set server-side by Laravel from the authenticated user — never client input. Use it to scope any knowledge-base document grounding to that tenant only, same as the existing `/index-document` / `/api/v1/ai/workflows/generate` endpoints.
- **Error contract**: on failure, return a non-2xx status with a JSON body containing `detail` (a human-readable string) — Laravel already parses `response.json('detail')` on every RAG call and falls back to the raw body if it's missing.
- **Soft failures**: for the existing `/api/v1/ai/workflows/generate` endpoint, RAG returns HTTP `200` with `success: false` and `provider: "fallback"` when the LLM call itself fails but the request was otherwise valid (as opposed to a hard failure = non-2xx). Please follow the same pattern below — Laravel checks the `success` field, not just HTTP status.

---

## 1. AI Generator — text generation

Backs the `ai-generator` workflow node: user writes a prompt, optionally picks knowledge-base documents and a tone; the node writes the generated text to a workflow variable.

**Suggested endpoint:**

```http
POST /api/v1/ai/generate
```

(Matches the existing `/api/v1/ai/workflows/generate` prefix. Change if it doesn't fit your routing — just confirm the final path back to us.)

### Request

```json
{
  "prompt": "Write a friendly follow-up email about ...",
  "tenant_id": "42",
  "tone": "friendly",
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `prompt` | string | yes |  |
| `tenant_id` | string | yes | Server-set. Scope document grounding to this tenant only. |
| `tone` | string | no | Free-text. May be empty/absent. |

### Response — success

```json
{
  "success": true,
  "content": "Hi there, following up on...",
  "provider": "openai",
  "documents_used": ["1", "2"]
}
```

| Field | Type | Notes |
|---|---|---|
| `success` | bool | `true` for a normal completion. |
| `content` | string | The generated text. This is what gets written to the node's output variable. |
| `provider` | string | Which LLM/provider actually produced the content (e.g. `openai`); use `"fallback"` on soft failure (see below). |
| `documents_used` | array | IDs of documents actually used for grounding |

### Response — soft failure (HTTP 200)

```json
{
  "success": false,
  "content": "",
  "provider": "fallback",
  "error": "LLM provider timed out"
}
```

### Response — hard failure (non-2xx)

```json
{
  "detail": "tenant_id is required"
}
```

---

## 2. AI Classification — text classification

Backs a new `ai-classification` workflow node (not yet built on the Laravel side): user provides text and a list of classification options (categories); the node writes the chosen category (and its confidence) to workflow variables, so downstream nodes (e.g. `if-node`/`switch`) can branch on the result.

**Suggested endpoint:**

```http
POST /api/v1/ai/classify
```

### Request

```json
{
  "text": "I'd like a refund for my last order, it arrived damaged.",
  "categories": ["billing", "shipping", "product_defect", "general_inquiry"],
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `text` | string | yes | Already template-rendered by Laravel — the text to classify. |
| `categories` | array of strings | yes | The fixed set of classification options the user configured on the node. At least 2 expected. RAG should classify into exactly one of these — not invent a new label. |

### Response — success

```json
{
  "success": true,
  "classification": "shipping",
  "confidence": 0.87
}
```

| Field | Type | Notes |
|---|---|---|
| `success` | bool | `true` for a normal completion. |
| `classification` | string | Must be exactly one of the `categories` values sent in the request. |
| `confidence` | number | 0–1 float, RAG's confidence in the chosen classification. |

**Open question for us to confirm together:** should the node also let the user configure a *minimum* confidence threshold on the Laravel side (e.g. "if confidence < 0.6, treat as unclassified / take the fallback branch")? If so, that comparison would happen in Laravel using the `confidence` value you return — RAG wouldn't need to know about the threshold at all, it would just always return its actual confidence score. Flagging this now so the field name doesn't get overloaded to mean two different things.

### Response — soft failure (HTTP 200)

```json
{
  "success": false,
  "classification": null,
  "confidence": 0.0,
  "error": "Unable to classify: no matching category"
}
```

### Response — hard failure (non-2xx)

```json
{
  "detail": "categories must contain at least 2 values"
}
```

