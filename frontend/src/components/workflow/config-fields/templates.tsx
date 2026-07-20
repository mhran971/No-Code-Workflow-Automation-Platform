import { useState, useEffect, useRef } from 'react';
import type { ConfigField } from '@/config/types';
import type { KnowledgeBaseDocument, TenantUser, WorkflowSummary, WorkflowDefinition } from '@/lib/api/types';
import { loadWorkflow } from '@/lib/api/client';

// Field renderers with inline validation:
// templatetext, templatetextarea, emailtemplate, identifier, userselect.

const TEMPLATE_VAR_RE = /\{\{([^}]+)\}\}/g;
const VALID_TEMPLATE_VAR_RE = /^(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*$/;

function validateTemplateVars(text: string): string[] {
  const errors: string[] = [];
  let match: RegExpExecArray | null;
  const re = new RegExp(TEMPLATE_VAR_RE.source, 'g');
  while ((match = re.exec(text)) !== null) {
    const variable = match[1].trim();
    if (!VALID_TEMPLATE_VAR_RE.test(variable)) {
      errors.push(`"{{${variable}}}" is invalid. Use {{context.<key>}} or {{customer.<key>}} with a single identifier.`);
    }
  }
  return errors;
}

export function TemplateTextField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  const [errors, setErrors] = useState<string[]>(() => validateTemplateVars(value ?? ''));
  const handleChange = (v: string) => { onChange(v); setErrors(validateTemplateVars(v)); };
  return (
    <div className="space-y-1">
      <input
        type="text"
        value={value || ''}
        onChange={e => handleChange(e.target.value)}
        placeholder={field.placeholder}
        className={`w-full h-8 px-2.5 text-xs bg-muted border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 ${
          errors.length > 0 ? 'border-destructive/60 focus:ring-destructive' : 'border-border focus:ring-primary'
        }`}
      />
      {errors.map((e, i) => <p key={i} className="text-[10px] text-destructive">{e}</p>)}
    </div>
  );
}

export function TemplateTextareaField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  const [errors, setErrors] = useState<string[]>(() => validateTemplateVars(value ?? ''));
  const handleChange = (v: string) => { onChange(v); setErrors(validateTemplateVars(v)); };
  return (
    <div className="space-y-1">
      <textarea
        value={value || ''}
        onChange={e => handleChange(e.target.value)}
        placeholder={field.placeholder}
        rows={4}
        className={`w-full px-2.5 py-2 text-xs bg-muted border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 resize-none font-mono ${
          errors.length > 0 ? 'border-destructive/60 focus:ring-destructive' : 'border-border focus:ring-primary'
        }`}
      />
      {errors.map((e, i) => <p key={i} className="text-[10px] text-destructive">{e}</p>)}
    </div>
  );
}

// Matches the entire value being exactly one template variable: {{context.key}} or {{customer.key}}
const FULL_TEMPLATE_VAR_RE = /^\{\{\s*(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}$/;
// Basic email format check
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validateEmailOrVar(value: string): string | null {
  const v = value.trim();
  if (!v) return null;
  if (v.startsWith('{{')) {
    if (!FULL_TEMPLATE_VAR_RE.test(v)) {
      return 'Variable must be {{context.<key>}} or {{customer.<key>}} — one identifier only.';
    }
    return null;
  }
  if (!EMAIL_RE.test(v)) {
    return 'Enter a valid email address or a variable like {{context.email}}.';
  }
  return null;
}

export function EmailTemplateField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  const [error, setError] = useState<string | null>(() => validateEmailOrVar(value ?? ''));
  const handleChange = (v: string) => { onChange(v); setError(validateEmailOrVar(v)); };
  return (
    <div className="space-y-1">
      <input
        type="text"
        value={value || ''}
        onChange={e => handleChange(e.target.value)}
        placeholder={field.placeholder}
        className={`w-full h-8 px-2.5 text-xs bg-muted border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 ${
          error ? 'border-destructive/60 focus:ring-destructive' : 'border-border focus:ring-primary'
        }`}
      />
      {error && <p className="text-[10px] text-destructive">{error}</p>}
    </div>
  );
}

const IDENTIFIER_RE = /^[a-zA-Z_]+$/;

export function IdentifierField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  const trimmed = (value ?? '').trimEnd();
  const invalid = trimmed !== '' && !IDENTIFIER_RE.test(trimmed);
  return (
    <div className="space-y-1">
      <input
        type="text"
        value={value ?? ''}
        onChange={e => onChange(e.target.value)}
        onBlur={e => onChange(e.target.value.trim())}
        placeholder={field.placeholder}
        className={`w-full h-8 px-2.5 text-xs bg-muted border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 ${
          invalid ? 'border-destructive/60 focus:ring-destructive' : 'border-border focus:ring-primary'
        }`}
      />
      {invalid && (
        <p className="text-[10px] text-destructive">Only letters (a–z, A–Z) and underscores allowed — no numbers or special characters.</p>
      )}
    </div>
  );
}

export function UserSelectField({ value, onChange, users }: { field: ConfigField; value: string | number; onChange: (v: string) => void; users: TenantUser[] }) {
  return (
    <select
      value={String(value ?? '')}
      onChange={e => onChange(e.target.value)}
      className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none cursor-pointer"
    >
      <option value="">— Select a user —</option>
      {users.map(u => (
        <option key={u.id} value={String(u.id)}>
          {u.first_name} {u.last_name} ({u.email})
        </option>
      ))}
    </select>
  );
}

export function KbDocumentsField({ value, onChange, kbDocuments }: { field: ConfigField; value: number[]; onChange: (v: number[]) => void; kbDocuments: KnowledgeBaseDocument[] }) {
  const selected = Array.isArray(value) ? value : [];

  const toggle = (id: number) => {
    if (selected.includes(id)) {
      onChange(selected.filter(x => x !== id));
    } else {
      onChange([...selected, id]);
    }
  };

  if (kbDocuments.length === 0) {
    return (
      <p className="text-[11px] text-muted-foreground italic">No documents available.</p>
    );
  }

  return (
    <div className="space-y-1 max-h-40 overflow-y-auto rounded-md border border-border bg-muted p-2">
      {kbDocuments.map(doc => (
        <label key={doc.id} className="flex items-center gap-2 cursor-pointer group">
          <input
            type="checkbox"
            checked={selected.includes(doc.id)}
            onChange={() => toggle(doc.id)}
            className="h-3.5 w-3.5 rounded border-border text-primary focus:ring-primary cursor-pointer"
          />
          <span className="text-xs text-foreground group-hover:text-primary transition-colors truncate">{doc.title}</span>
        </label>
      ))}
    </div>
  );
}

export function WorkflowSelectField({ value, onChange, workflows }: { field: ConfigField; value: string | number; onChange: (v: string) => void; workflows: WorkflowSummary[] }) {
  const published = workflows.filter(w => w.version_number !== null && w.version_number > 0);

  return (
    <select
      value={String(value ?? '')}
      onChange={e => onChange(e.target.value)}
      className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none cursor-pointer"
    >
      <option value="">— Select a workflow —</option>
      {published.map(w => (
        <option key={w.id} value={String(w.id)}>
          {w.name}{w.version_label ? ` (${w.version_label})` : ''}
        </option>
      ))}
    </select>
  );
}

// ─── Trigger Mapping ─────────────────────────────────────────────────────────
// Renders a structured form mapping parent context values to the child workflow's
// trigger input variables. Each row: readonly variable name + value input.

interface TriggerVariable {
  key: string;
  label: string;
}

const TMPL_RE = /\{\{([^}]*)\}\}/g;
const VALID_TMPL_RE = /^(context|customer)\.[a-zA-Z_][a-zA-Z0-9_]*$/;

function validateTemplateValue(text: string): string | null {
  if (!text.includes('{{')) return null;

  // Check for unclosed braces
  let m: RegExpExecArray | null;
  const re = new RegExp(TMPL_RE.source, 'g');
  let hasMatch = false;
  while ((m = re.exec(text)) !== null) {
    hasMatch = true;
    const inner = m[1].trim();
    if (!VALID_TMPL_RE.test(inner)) {
      return `"{{${inner}}}" is invalid. Use {{context.<key>}} or {{customer.<key>}}.`;
    }
  }

  if (!hasMatch) {
    // Has {{ but no matched }} — unclosed
    return 'Unclosed template expression. Use {{context.<key>}}.';
  }

  return null;
}

function extractTriggerVariables(definition: WorkflowDefinition | null): TriggerVariable[] {
  if (!definition?.trigger) return [];
  const type = definition.trigger.type;
  const config = definition.trigger.config ?? {};

  if (type === 'manual-trigger') {
    const vars = config.variables;
    if (!Array.isArray(vars)) return [];
    return vars
      .filter((v): v is { key: string; value?: unknown } => typeof v === 'object' && v !== null && typeof (v as Record<string, unknown>).key === 'string')
      .map(v => ({ key: v.key, label: v.key }));
  }

  if (type === 'form-trigger') {
    const fields = config.formFields;
    if (!Array.isArray(fields)) return [];
    return fields
      .filter((f): f is { key: string; label?: string } => typeof f === 'object' && f !== null && typeof (f as Record<string, unknown>).key === 'string')
      .map(f => ({ key: f.key, label: f.label || f.key }));
  }

  return [];
}

export function TriggerMappingField({ value, onChange, apiBaseUrl, accessToken, allValues }: {
  field: ConfigField;
  value: Record<string, string> | undefined;
  onChange: (v: Record<string, string>) => void;
  apiBaseUrl?: string;
  accessToken?: string;
  allValues?: Record<string, unknown>;
}) {
  const workflowId = allValues?.workflowId as string | undefined;
  const [variables, setVariables] = useState<TriggerVariable[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fetchedRef = useRef<string>('');

  useEffect(() => {
    if (!workflowId || !apiBaseUrl || !accessToken) {
      setVariables([]);
      setError(null);
      fetchedRef.current = '';
      return;
    }

    if (fetchedRef.current === workflowId) return;
    fetchedRef.current = workflowId;

    setLoading(true);
    setError(null);
    loadWorkflow(apiBaseUrl, accessToken, workflowId)
      .then(({ data }) => {
        const vars = extractTriggerVariables(data.draft_definition);
        setVariables(vars);
        if (vars.length === 0) {
          setError('This workflow has no trigger input variables defined.');
        }
      })
      .catch(() => {
        setError('Failed to load workflow definition.');
        setVariables([]);
      })
      .finally(() => setLoading(false));
  }, [workflowId, apiBaseUrl, accessToken]);

  if (!workflowId) {
    return <p className="text-[11px] text-muted-foreground italic">Select a workflow first to configure inputs.</p>;
  }

  if (loading) {
    return <p className="text-[11px] text-muted-foreground italic">Loading trigger variables...</p>;
  }

  if (error) {
    return <p className="text-[11px] text-muted-foreground italic">{error}</p>;
  }

  const mapping = value ?? {};

  const updateValue = (key: string, val: string) => {
    onChange({ ...mapping, [key]: val });
  };

  return (
    <div className="space-y-2">
      {variables.map(v => {
        const fieldValue = mapping[v.key] ?? '';
        const validationError = validateTemplateValue(fieldValue);
        return (
          <div key={v.key} className="space-y-1">
            <div className="flex items-center gap-2">
              <div className="flex-shrink-0 min-w-0 w-28">
                <span className="inline-flex items-center h-7 px-2 text-[11px] font-mono font-medium text-teal-400 bg-teal-500/10 border border-teal-500/20 rounded truncate" title={v.key}>
                  {v.label}
                </span>
              </div>
              <input
                type="text"
                value={fieldValue}
                onChange={e => updateValue(v.key, e.target.value)}
                placeholder={`{{context.${v.key}}} or static value`}
                className={`flex-1 h-7 px-2 text-[11px] bg-muted border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 font-mono ${
                  validationError ? 'border-destructive/60 focus:ring-destructive' : 'border-border focus:ring-primary'
                }`}
              />
            </div>
            {validationError && (
              <p className="text-[10px] text-destructive pl-30">{validationError}</p>
            )}
          </div>
        );
      })}
    </div>
  );
}

// ─── Workflow Output ─────────────────────────────────────────────────────────
// Analyzes the last node(s) of the child workflow and presents possible output
// variable names as a dropdown.

function extractOutputSuggestions(definition: WorkflowDefinition | null): string[] {
  if (!definition?.nodes?.length || !definition?.edges) return [];
  const nodeMap = new Map(definition.nodes.map(n => [n.id, n]));
  const sourceNodes = new Set(definition.edges.map(e => e.source_node_key));
  const lastNodes = definition.nodes.filter(n => !sourceNodes.has(n.id));

  const suggestions: string[] = [];
  for (const node of lastNodes) {
    const config = node.config ?? {};
    if (typeof config.outputVariable === 'string' && config.outputVariable.trim() !== '') {
      suggestions.push(config.outputVariable.trim());
    } else if (node.type === 'ai-generator') {
      suggestions.push((config.outputVariable as string) || 'ai_output');
    }
    if (Array.isArray(config.outputVariables)) {
      for (const v of config.outputVariables) {
        if (typeof v === 'string' && v.trim() !== '' && !suggestions.includes(v.trim())) {
          suggestions.push(v.trim());
        }
      }
    }
  }
  return [...new Set(suggestions)];
}

export function WorkflowOutputField({ value, onChange, apiBaseUrl, accessToken, allValues }: {
  field: ConfigField;
  value: string | undefined;
  onChange: (v: string) => void;
  apiBaseUrl?: string;
  accessToken?: string;
  allValues?: Record<string, unknown>;
}) {
  const workflowId = allValues?.workflowId as string | undefined;
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const fetchedRef = useRef<string>('');

  useEffect(() => {
    if (!workflowId || !apiBaseUrl || !accessToken) {
      setSuggestions([]);
      fetchedRef.current = '';
      return;
    }

    if (fetchedRef.current === workflowId) return;
    fetchedRef.current = workflowId;

    setLoading(true);
    loadWorkflow(apiBaseUrl, accessToken, workflowId)
      .then(({ data }) => {
        setSuggestions(extractOutputSuggestions(data.draft_definition));
      })
      .catch(() => {
        setSuggestions([]);
      })
      .finally(() => setLoading(false));
  }, [workflowId, apiBaseUrl, accessToken]);

  if (!workflowId) {
    return <p className="text-[11px] text-muted-foreground italic">Select a workflow first.</p>;
  }

  if (loading) {
    return <p className="text-[11px] text-muted-foreground italic">Loading output variables...</p>;
  }

  const currentValue = value ?? '';

  if (suggestions.length === 0) {
    return (
      <input
        type="text"
        value={currentValue}
        onChange={e => onChange(e.target.value)}
        placeholder="e.g., subWorkflowResult"
        className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary font-mono"
      />
    );
  }

  const isCustom = currentValue !== '' && !suggestions.includes(currentValue);

  return (
    <div className="space-y-1.5">
      <select
        value={isCustom ? '__custom__' : currentValue}
        onChange={e => {
          if (e.target.value === '__custom__') {
            onChange('');
          } else {
            onChange(e.target.value);
          }
        }}
        className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none cursor-pointer"
      >
        <option value="">— Select an output variable —</option>
        {suggestions.map(s => (
          <option key={s} value={s}>{s}</option>
        ))}
        {isCustom && <option value="__custom__">Custom value...</option>}
      </select>
      {(isCustom || currentValue === '') && (
        <input
          type="text"
          value={currentValue}
          onChange={e => onChange(e.target.value)}
          placeholder="Enter custom output variable name"
          className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary font-mono"
        />
      )}
    </div>
  );
}
