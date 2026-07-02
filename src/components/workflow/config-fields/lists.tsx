import { useState } from 'react';
import { X, Plus, Trash2 } from 'lucide-react';
import type { ConfigField } from '@/config/types';
import type { NestedFieldErrors } from '@/hooks/useFieldValidationErrors';
import type { InputFieldItem, BranchItem } from './types';

// Small inline error shown directly under a nested input.
function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="mt-0.5 text-[10px] text-destructive">{message}</p>;
}

// Complex list editors: inputfieldlist (form/task field builder) and
// branchlist (fork outgoing branches with auto-slugified keys).

const INPUT_FIELD_TYPES = [
  { label: 'Text', value: 'text' },
  { label: 'Textarea', value: 'textarea' },
  { label: 'Number', value: 'number' },
  { label: 'Date', value: 'date' },
  { label: 'File', value: 'file' },
  { label: 'Checkbox', value: 'checkbox' },
  { label: 'Select', value: 'select' },
];

export function InputFieldListField({ value, onChange, nestedErrors }: { field: ConfigField; value: InputFieldItem[]; onChange: (v: InputFieldItem[]) => void; nestedErrors?: NestedFieldErrors }) {
  const items: InputFieldItem[] = value ?? [];

  const addItem = () => onChange([...items, { key: '', label: '', type: 'text', options: [] }]);
  const removeItem = (idx: number) => onChange(items.filter((_, i) => i !== idx));
  const updateItem = (idx: number, patch: Partial<InputFieldItem>) => {
    const next = [...items];
    next[idx] = { ...next[idx], ...patch };
    onChange(next);
  };
  const [optionInputs, setOptionInputs] = useState<Record<number, string>>({});

  const addOption = (idx: number) => {
    const raw = (optionInputs[idx] ?? '').trim();
    if (!raw) return;
    const item = items[idx];
    if ((item.options ?? []).includes(raw)) return;
    updateItem(idx, { options: [...(item.options ?? []), raw] });
    setOptionInputs(prev => ({ ...prev, [idx]: '' }));
  };

  const removeOption = (idx: number, opt: string) => {
    updateItem(idx, { options: (items[idx].options ?? []).filter(o => o !== opt) });
  };

  const needsOptions = (type: string) => type === 'select' || type === 'checkbox';

  return (
    <div className="space-y-2">
      {items.map((item, idx) => {
        const rowErrors = nestedErrors?.[idx];
        const hasRowError = rowErrors && Object.keys(rowErrors).length > 0;
        return (
        <div key={idx} className={`rounded-lg border p-2 space-y-1.5 ${hasRowError ? 'bg-destructive/5 border-destructive/40' : 'bg-muted/30 border-border/50'}`}>
          <div className="flex items-center justify-between">
            <span className="text-[10px] text-muted-foreground font-medium">Field #{idx + 1}</span>
            <button onClick={() => removeItem(idx)} className="h-5 w-5 flex items-center justify-center rounded hover:bg-destructive/10 transition-colors">
              <Trash2 className="h-3 w-3 text-muted-foreground" />
            </button>
          </div>

          <FieldError message={rowErrors?._} />

          <div className="grid grid-cols-2 gap-1.5">
            <div>
              <label className="text-[10px] text-muted-foreground mb-0.5 block">Key</label>
              <input
                value={item.key}
                onChange={e => updateItem(idx, { key: e.target.value })}
                placeholder="e.g. status"
                className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
              />
              <FieldError message={rowErrors?.key} />
            </div>
            <div>
              <label className="text-[10px] text-muted-foreground mb-0.5 block">Label</label>
              <input
                value={item.label}
                onChange={e => updateItem(idx, { label: e.target.value })}
                placeholder="e.g. Status"
                className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
              />
              <FieldError message={rowErrors?.label} />
            </div>
          </div>

          <div>
            <label className="text-[10px] text-muted-foreground mb-0.5 block">Type</label>
            <select
              value={item.type}
              onChange={e => updateItem(idx, { type: e.target.value, options: needsOptions(e.target.value) ? (item.options ?? []) : undefined })}
              className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none"
            >
              {INPUT_FIELD_TYPES.map(t => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
            <FieldError message={rowErrors?.type} />
          </div>

          {needsOptions(item.type) && (
            <div>
              <label className="text-[10px] text-muted-foreground mb-0.5 block">Options</label>
              <div className="flex flex-wrap gap-1 mb-1">
                {(item.options ?? []).map(opt => (
                  <span key={opt} className="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] bg-primary/10 text-primary border border-primary/20 rounded">
                    {opt}
                    <button onClick={() => removeOption(idx, opt)} className="hover:text-destructive">
                      <X className="h-2.5 w-2.5" />
                    </button>
                  </span>
                ))}
              </div>
              <div className="flex items-center gap-1">
                <input
                  value={optionInputs[idx] ?? ''}
                  onChange={e => setOptionInputs(prev => ({ ...prev, [idx]: e.target.value }))}
                  onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addOption(idx))}
                  placeholder="Add option…"
                  className="flex-1 h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
                />
                <button onClick={() => addOption(idx)} className="h-7 w-7 flex items-center justify-center rounded border border-border hover:bg-muted transition-colors">
                  <Plus className="h-3 w-3 text-muted-foreground" />
                </button>
              </div>
              <FieldError message={rowErrors?.options} />
            </div>
          )}
        </div>
        );
      })}
      <button onClick={addItem} className="flex items-center gap-1 text-[11px] text-primary hover:text-primary/80 transition-colors py-1">
        <Plus className="h-3 w-3" /> Add field
      </button>
    </div>
  );
}

function slugifyBranch(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export function BranchListField({ value, onChange }: { field: ConfigField; value: BranchItem[]; onChange: (v: BranchItem[]) => void }) {
  const items: BranchItem[] = value ?? [];

  const addItem = () => onChange([...items, { name: '', key: '' }]);
  const removeItem = (idx: number) => onChange(items.filter((_, i) => i !== idx));
  const updateName = (idx: number, name: string) => {
    const next = [...items];
    next[idx] = { name, key: slugifyBranch(name) };
    onChange(next);
  };

  return (
    <div className="space-y-1.5">
      {items.map((item, idx) => (
        <div key={idx} className="flex items-center gap-1.5">
          <input
            value={item.name}
            onChange={e => updateName(idx, e.target.value)}
            placeholder="Branch name"
            className="flex-1 h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <code className="min-w-[5rem] max-w-[8rem] h-7 px-2 flex items-center text-[10px] bg-muted/60 border border-border/50 rounded text-muted-foreground font-mono truncate">
            {item.key || '—'}
          </code>
          <button
            onClick={() => removeItem(idx)}
            className="h-7 w-7 flex items-center justify-center rounded border border-border hover:bg-destructive/10 hover:border-destructive/30 transition-colors"
          >
            <Trash2 className="h-3 w-3 text-muted-foreground" />
          </button>
        </div>
      ))}
      <button onClick={addItem} className="flex items-center gap-1 text-[11px] text-primary hover:text-primary/80 transition-colors py-1">
        <Plus className="h-3 w-3" /> Add branch
      </button>
    </div>
  );
}
