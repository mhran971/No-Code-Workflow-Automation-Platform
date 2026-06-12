import type { NodeDefinition, ValidationResult, WorkflowDefinition } from './types';
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
      'No access token provided. Paste your JWT in the token field or src/config.ts.',
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

export function fetchNodeLibrary(baseUrl: string, token: string): Promise<{ data: NodeDefinition[] }> {
  return request(baseUrl, token, '/workflows/nodes');
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
