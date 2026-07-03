import { useState } from 'react';
import type { ConfigField } from '@/config/types';
import type { KnowledgeBaseDocument, TenantUser } from '@/lib/api/types';

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
