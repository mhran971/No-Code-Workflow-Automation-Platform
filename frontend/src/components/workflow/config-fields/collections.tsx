import { useState } from 'react';
import { X, Plus, Trash2 } from 'lucide-react';
import type { ConfigField } from '@/config/types';
import { ToggleField } from './basic';

// Renderers for repeating / collection field types: keyvalue, tags, fieldlist.
// The more complex list editors (inputfieldlist, branchlist) live in lists.tsx.

export function KeyValueField({ value, onChange }: { field: ConfigField; value: Array<{ key: string; value: string }>; onChange: (v: Array<{ key: string; value: string }>) => void }) {
  const items = value || [];
  const addItem = () => onChange([...items, { key: '', value: '' }]);
  const removeItem = (idx: number) => onChange(items.filter((_, i) => i !== idx));
  const updateItem = (idx: number, k: string, v: string) => {
    const next = [...items];
    next[idx] = { key: k, value: v };
    onChange(next);
  };

  return (
    <div className="space-y-1.5">
      {items.map((item, idx) => (
        <div key={idx} className="flex items-center gap-1.5">
          <input
            value={item.key}
            onChange={e => updateItem(idx, e.target.value, item.value)}
            placeholder="Key"
            className="flex-1 h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <input
            value={item.value}
            onChange={e => updateItem(idx, item.key, e.target.value)}
            placeholder="Value"
            className="flex-1 h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
          />
          <button onClick={() => removeItem(idx)} className="h-7 w-7 flex items-center justify-center rounded border border-border hover:bg-destructive/10 hover:border-destructive/30 transition-colors">
            <Trash2 className="h-3 w-3 text-muted-foreground" />
          </button>
        </div>
      ))}
      <button onClick={addItem} className="flex items-center gap-1 text-[11px] text-primary hover:text-primary/80 transition-colors py-1">
        <Plus className="h-3 w-3" /> Add entry
      </button>
    </div>
  );
}

export function TagsField({ value, onChange }: { field: ConfigField; value: string[]; onChange: (v: string[]) => void }) {
  const tags = value || [];
  const [input, setInput] = useState('');

  const addTag = () => {
    if (input.trim() && !tags.includes(input.trim())) {
      onChange([...tags, input.trim()]);
      setInput('');
    }
  };

  return (
    <div className="space-y-1.5">
      <div className="flex flex-wrap gap-1">
        {tags.map(tag => (
          <span key={tag} className="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-primary/10 text-primary border border-primary/20 rounded-md">
            {tag}
            <button onClick={() => onChange(tags.filter(t => t !== tag))} className="hover:text-destructive">
              <X className="h-2.5 w-2.5" />
            </button>
          </span>
        ))}
      </div>
      <div className="flex items-center gap-1.5">
        <input
          value={input}
          onChange={e => setInput(e.target.value)}
          onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addTag())}
          placeholder="Type and press Enter"
          className="flex-1 h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
        />
        <button onClick={addTag} className="h-7 w-7 flex items-center justify-center rounded border border-border hover:bg-muted transition-colors">
          <Plus className="h-3 w-3 text-muted-foreground" />
        </button>
      </div>
    </div>
  );
}

export function FieldListField({ field, value, onChange }: { field: ConfigField; value: Array<Record<string, unknown>>; onChange: (v: Array<Record<string, unknown>>) => void }) {
  const items = value || [];
  const subFields = field.fields || [];

  const addItem = () => {
    const newItem: Record<string, unknown> = {};
    subFields.forEach(f => { newItem[f.key] = f.defaultValue ?? ''; });
    onChange([...items, newItem]);
  };

  const removeItem = (idx: number) => onChange(items.filter((_, i) => i !== idx));

  const updateItem = (idx: number, key: string, val: unknown) => {
    const next = [...items];
    next[idx] = { ...next[idx], [key]: val };
    onChange(next);
  };

  return (
    <div className="space-y-2">
      {items.map((item, idx) => (
        <div key={idx} className="bg-muted/30 rounded-lg border border-border/50 p-2 space-y-1.5">
          <div className="flex items-center justify-between mb-1">
            <span className="text-[10px] text-muted-foreground font-medium">#{idx + 1}</span>
            <button onClick={() => removeItem(idx)} className="h-5 w-5 flex items-center justify-center rounded hover:bg-destructive/10 transition-colors">
              <Trash2 className="h-3 w-3 text-muted-foreground" />
            </button>
          </div>
          {subFields.map(sf => (
            <div key={sf.key}>
              <label className="text-[10px] text-muted-foreground mb-0.5 block">{sf.label}</label>
              {sf.type === 'select' ? (
                <select
                  value={(item[sf.key] as string) || ''}
                  onChange={e => updateItem(idx, sf.key, e.target.value)}
                  className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none"
                >
                  {sf.options?.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              ) : sf.type === 'toggle' ? (
                <ToggleField field={sf} value={item[sf.key] as boolean} onChange={v => updateItem(idx, sf.key, v)} />
              ) : sf.type === 'code' ? (
                <input
                  value={(item[sf.key] as string) || ''}
                  onChange={e => updateItem(idx, sf.key, e.target.value)}
                  placeholder={sf.placeholder}
                  className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-emerald-400 font-mono placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
                />
              ) : (
                <input
                  value={(item[sf.key] as string) || ''}
                  onChange={e => updateItem(idx, sf.key, e.target.value)}
                  placeholder={sf.placeholder}
                  className="w-full h-7 px-2 text-[11px] bg-muted border border-border rounded text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
                />
              )}
            </div>
          ))}
        </div>
      ))}
      <button onClick={addItem} className="flex items-center gap-1 text-[11px] text-primary hover:text-primary/80 transition-colors py-1">
        <Plus className="h-3 w-3" /> Add item
      </button>
    </div>
  );
}
