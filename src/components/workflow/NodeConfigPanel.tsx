import { useState, useCallback } from 'react';
import { X, Plus, Trash2, Copy, ChevronDown, Info } from 'lucide-react';
import { NODE_CONFIG_REGISTRY, type ConfigField, type NodeConfigSchema } from '@/types/nodeConfigs';
import {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter as FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
} from 'lucide-react';

const iconMap: Record<string, React.ElementType> = {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter: FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
};

const colorBg: Record<string, string> = {
  amber: 'bg-amber-500/15 text-amber-400',
  indigo: 'bg-indigo-500/15 text-indigo-400',
  violet: 'bg-violet-500/15 text-violet-400',
  blue: 'bg-blue-500/15 text-blue-400',
  emerald: 'bg-emerald-500/15 text-emerald-400',
  rose: 'bg-rose-500/15 text-rose-400',
};

interface SelectedNode {
  id: string;
  label: string;
  icon: string;
  color: string;
  nodeType: string;
  description: string;
}

interface NodeConfigPanelProps {
  node: SelectedNode;
  onClose: () => void;
}

// Field components

function TextField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
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

function TextareaField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
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

function CodeField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
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

function NumberField({ field, value, onChange }: { field: ConfigField; value: number | string; onChange: (v: number) => void }) {
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

function SelectField({ field, value, onChange }: { field: ConfigField; value: string; onChange: (v: string) => void }) {
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

function ToggleField({ field, value, onChange }: { field: ConfigField; value: boolean; onChange: (v: boolean) => void }) {
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

function ReadonlyField({ field, value }: { field: ConfigField; value: string }) {
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

function KeyValueField({ field, value, onChange }: { field: ConfigField; value: Array<{ key: string; value: string }>; onChange: (v: Array<{ key: string; value: string }>) => void }) {
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

function TagsField({ field, value, onChange }: { field: ConfigField; value: string[]; onChange: (v: string[]) => void }) {
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

function FieldListField({ field, value, onChange }: { field: ConfigField; value: Array<Record<string, unknown>>; onChange: (v: Array<Record<string, unknown>>) => void }) {
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

function ConfigFieldRenderer({ field, value, onChange }: { field: ConfigField; value: unknown; onChange: (v: unknown) => void }) {
  switch (field.type) {
    case 'text': return <TextField field={field} value={value as string} onChange={onChange} />;
    case 'textarea': return <TextareaField field={field} value={value as string} onChange={onChange} />;
    case 'code': return <CodeField field={field} value={value as string} onChange={onChange} />;
    case 'number': return <NumberField field={field} value={value as number} onChange={v => onChange(v)} />;
    case 'select': return <SelectField field={field} value={value as string} onChange={onChange} />;
    case 'toggle': return <ToggleField field={field} value={value as boolean} onChange={onChange} />;
    case 'readonly': return <ReadonlyField field={field} value={value as string} />;
    case 'keyvalue': return <KeyValueField field={field} value={value as Array<{ key: string; value: string }>} onChange={v => onChange(v)} />;
    case 'tags': return <TagsField field={field} value={value as string[]} onChange={v => onChange(v)} />;
    case 'fieldlist': return <FieldListField field={field} value={value as Array<Record<string, unknown>>} onChange={v => onChange(v)} />;
    default: return null;
  }
}

export function NodeConfigPanel({ node, onClose }: NodeConfigPanelProps) {
  const schema = NODE_CONFIG_REGISTRY[node.nodeType];
  const [values, setValues] = useState<Record<string, unknown>>({});
  const [expandedSections, setExpandedSections] = useState<Set<number>>(
    new Set(schema?.sections.map((_, i) => i) || [])
  );

  const Icon = iconMap[node.icon] || Zap;
  const colors = colorBg[node.color] || colorBg.blue;

  const updateValue = useCallback((key: string, val: unknown) => {
    setValues(prev => ({ ...prev, [key]: val }));
  }, []);

  const toggleSection = (idx: number) => {
    setExpandedSections(prev => {
      const next = new Set(prev);
      if (next.has(idx)) next.delete(idx);
      else next.add(idx);
      return next;
    });
  };

  return (
    <div className="flex flex-col h-full">
      {/* Node header */}
      <div className="flex items-center gap-2.5 px-4 py-3 border-b border-border">
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${colors}`}>
          <Icon className="h-4 w-4" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="text-sm font-semibold text-foreground truncate">{node.label}</h3>
          <p className="text-[10px] text-muted-foreground truncate">{node.description}</p>
        </div>
        <button
          onClick={onClose}
          className="h-7 w-7 flex items-center justify-center rounded-md hover:bg-muted transition-colors"
        >
          <X className="h-4 w-4 text-muted-foreground" />
        </button>
      </div>

      {/* Config sections */}
      <div className="flex-1 overflow-y-auto">
        {!schema ? (
          <div className="p-4 text-xs text-muted-foreground">
            No configuration available for this node type.
          </div>
        ) : (
          <div className="divide-y divide-border/50">
            {schema.sections.map((section, sIdx) => (
              <div key={sIdx}>
                <button
                  onClick={() => toggleSection(sIdx)}
                  className="w-full flex items-center justify-between px-4 py-2.5 hover:bg-muted/30 transition-colors"
                >
                  <span className="text-[11px] font-semibold text-foreground/80 uppercase tracking-wider">{section.title}</span>
                  <ChevronDown className={`h-3.5 w-3.5 text-muted-foreground transition-transform ${expandedSections.has(sIdx) ? '' : '-rotate-90'}`} />
                </button>

                {expandedSections.has(sIdx) && (
                  <div className="px-4 pb-3 space-y-3">
                    {section.fields.map(field => (
                      <div key={field.key}>
                        <div className="flex items-center justify-between mb-1">
                          <label className="text-[11px] font-medium text-muted-foreground flex items-center gap-1">
                            {field.label}
                            {field.required && <span className="text-destructive">*</span>}
                          </label>
                          {field.description && (
                            <div className="group relative">
                              <Info className="h-3 w-3 text-muted-foreground/40 cursor-help" />
                              <div className="absolute right-0 bottom-full mb-1 hidden group-hover:block z-50 w-48 p-2 text-[10px] text-foreground bg-popover border border-border rounded-md shadow-lg">
                                {field.description}
                              </div>
                            </div>
                          )}
                        </div>
                        <ConfigFieldRenderer
                          field={field}
                          value={values[field.key] ?? field.defaultValue}
                          onChange={v => updateValue(field.key, v)}
                        />
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="p-3 border-t border-border flex gap-2">
        <button className="flex-1 h-8 text-xs rounded-md bg-primary text-primary-foreground font-medium hover:bg-primary/90 transition-colors">
          Save Changes
        </button>
        <button onClick={onClose} className="h-8 px-3 text-xs rounded-md bg-muted text-muted-foreground border border-border hover:bg-muted/80 transition-colors">
          Cancel
        </button>
      </div>
    </div>
  );
}
