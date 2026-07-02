import type { ApiNodeDefinition, InstanceSummary, KnowledgeBaseDocument, TenantUser, TriggerManualResponse, ValidationResult, WorkflowDefinition, WorkflowDetail, WorkflowSummary } from './types';
import { normalizeToken } from './utils';

export class ApiError extends Error {
  status: number;
  body: unknown;
  url: string;

  constructor(message: string, status: number, body: unknown, url: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.body = body;
    this.url = url;
  }
}

function buildUrl(baseUrl: string, path: string): string {
  const normalizedBase = baseUrl.replace(/\/$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${normalizedBase}${normalizedPath}`;
}

async function request<T>(
  baseUrl: string,
  token: string,
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const cleanToken = normalizeToken(token);
  const url = buildUrl(baseUrl, path);

  if (!cleanToken) {
    throw new ApiError(
      'No access token provided. Paste your JWT in the API connection dialog.',
      0,
      null,
      url,
    );
  }

  const response = await fetch(url, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${cleanToken}`,
      ...(options.headers ?? {}),
    },
  });

  const text = await response.text();
  let body: unknown = null;

  if (text) {
    try {
      body = JSON.parse(text);
    } catch {
      body = text;
    }
  }

  if (!response.ok) {
    const message =
      typeof body === 'object' &&
      body !== null &&
      'message' in body &&
      typeof (body as { message: unknown }).message === 'string'
        ? (body as { message: string }).message
        : `Request failed with status ${response.status} for ${url}`;
    throw new ApiError(message, response.status, body, url);
  }

  return body as T;
}

export function fetchNodeLibrary(
  baseUrl: string,
  token: string,
): Promise<{ data: ApiNodeDefinition[] }> {
  return request(baseUrl, token, '/workflows/nodes');
}

export function fetchTenantUsers(
  baseUrl: string,
  token: string,
): Promise<{ data: TenantUser[] }> {
  return request(baseUrl, token, '/team/users');
}

export function fetchKnowledgeBaseDocuments(
  baseUrl: string,
  token: string,
): Promise<{ data: KnowledgeBaseDocument[] }> {
  return request(baseUrl, token, '/documents');
}

export function listTeams(
  baseUrl: string,
  token: string,
): Promise<{ data: Array<{ id: number; name: string }> }> {
  return request(baseUrl, token, '/team/teams');
}

export function listWorkflows(
  baseUrl: string,
  token: string,
): Promise<{ data: WorkflowSummary[] }> {
  return request(baseUrl, token, '/workflows');
}

export function createWorkflow(
  baseUrl: string,
  token: string,
  payload: { name: string; description?: string; team_id?: number },
): Promise<{ workflow: WorkflowSummary }> {
  return request(baseUrl, token, '/workflows', {
    method: 'POST',
    body: JSON.stringify({ method: 'blank', ...payload }),
  });
}

export function loadWorkflow(
  baseUrl: string,
  token: string,
  id: string,
): Promise<{ data: WorkflowDetail }> {
  return request(baseUrl, token, `/workflows/${id}`);
}

export function saveDraft(
  baseUrl: string,
  token: string,
  id: string,
  definition: WorkflowDefinition,
  expectedRevision: number,
): Promise<{ draft_revision: number; saved: boolean }> {
  return request(baseUrl, token, `/workflows/${id}/draft`, {
    method: 'PATCH',
    body: JSON.stringify({ definition, expected_draft_revision: expectedRevision }),
  });
}

export function publishWorkflow(
  baseUrl: string,
  token: string,
  id: string,
): Promise<{ published_version: object }> {
  return request(baseUrl, token, `/workflows/${id}/publish`, {
    method: 'POST',
  });
}

export function validateWorkflowDefinition(
  baseUrl: string,
  token: string,
  definition: WorkflowDefinition,
): Promise<ValidationResult> {
  return request(baseUrl, token, '/workflows/validate', {
    method: 'POST',
    body: JSON.stringify({ definition }),
  });
}

// ─── Execution ────────────────────────────────────────────────────────────────

export function triggerManual(
  baseUrl: string,
  token: string,
  workflowId: string,
  payload: Record<string, unknown> = {},
): Promise<TriggerManualResponse> {
  return request(baseUrl, token, `/workflows/${workflowId}/trigger/manual`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function cancelInstance(
  baseUrl: string,
  token: string,
  instanceId: string,
): Promise<{ message: string }> {
  return request(baseUrl, token, `/workflows/instances/${instanceId}/cancel`, {
    method: 'POST',
  });
}

export function listInstances(
  baseUrl: string,
  token: string,
  workflowId: string,
): Promise<{ data: InstanceSummary[] }> {
  return request(baseUrl, token, `/workflows/${workflowId}/instances`);
}
