import { Copy } from 'lucide-react';
import type { ConfigField } from '@/config/types';

// Primitive field renderers: text, textarea, code, number, select, toggle, readonly.

export function TextField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  return (
    <input
      type="text"
      value={value || ''}
      onChange={e => onChange(e.target.value)}
      placeholder={field.placeholder}
      className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
    />
  );
}

export function TextareaField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  return (
    <textarea
      value={value || ''}
      onChange={e => onChange(e.target.value)}
      placeholder={field.placeholder}
      rows={3}
      className="w-full px-2.5 py-2 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary resize-none font-mono"
    />
  );
}

export function CodeField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  return (
    <textarea
      value={value || ''}
      onChange={e => onChange(e.target.value)}
      placeholder={field.placeholder}
      rows={3}
      className="w-full px-2.5 py-2 text-[11px] bg-muted border border-border rounded-md text-emerald-400 placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary resize-none font-mono leading-relaxed"
    />
  );
}

export function NumberField({ field, value, onChange }: { field: ConfigField; value: number | string; onChange: (v: number) => void }) {
  return (
    <input
      type="number"
      value={String(value ?? field.defaultValue ?? '')}
      onChange={e => onChange(Number(e.target.value))}
      min={field.min}
      max={field.max}
      className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
    />
  );
}

export function SelectField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
  return (
    <select
      value={value ?? (field.defaultValue as string) ?? ''}
      onChange={e => onChange(e.target.value)}
      className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary appearance-none cursor-pointer"
    >
      {field.options?.map(opt => (
        <option key={opt.value} value={opt.value}>{opt.label}</option>
      ))}
    </select>
  );
}

export function ToggleField({ field, value, onChange }: { field: ConfigField; value: boolean; onChange: (v: boolean) => void }) {
  const isOn = value ?? (field.defaultValue as boolean) ?? false;
  return (
    <button
      onClick={() => onChange(!isOn)}
      className={`relative w-9 h-5 rounded-full transition-colors ${isOn ? 'bg-primary' : 'bg-muted-foreground/30'}`}
    >
      <div className={`absolute top-0.5 w-4 h-4 rounded-full bg-white transition-transform ${isOn ? 'left-[18px]' : 'left-0.5'}`} />
    </button>
  );
}

export function ReadonlyField({ field, value }: { field: ConfigField; value: string }) {
  const displayValue = value || (field.defaultValue as string) || '';
  return (
    <div className="flex items-center gap-1.5">
      <div className="flex-1 h-8 px-2.5 flex items-center text-xs bg-muted/50 border border-border/50 rounded-md text-muted-foreground font-mono truncate">
        {displayValue}
      </div>
      <button
        onClick={() => navigator.clipboard.writeText(displayValue)}
        className="h-8 w-8 flex items-center justify-center rounded-md border border-border hover:bg-muted transition-colors"
      >
        <Copy className="h-3 w-3 text-muted-foreground" />
      </button>
    </div>
  );
}
