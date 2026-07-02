import { ChevronDown, Info } from 'lucide-react';
import type { NodeConfigSchema } from '@/config/types';
import type { KnowledgeBaseDocument, TenantUser } from '@/lib/api/types';
import type { NestedFieldErrors } from '@/hooks/useFieldValidationErrors';
import { FieldRenderer } from './FieldRenderer';

type Section = NodeConfigSchema['sections'][number];

interface ConfigSectionProps {
  section: Section;
  index: number;
  expanded: boolean;
  onToggle: (index: number) => void;
  values: Record<string, unknown>;
  fieldErrors: Record<string, string>;
  nestedErrors: Record<string, NestedFieldErrors>;
  onValueChange: (key: string, val: unknown) => void;
  users: TenantUser[];
  kbDocuments?: KnowledgeBaseDocument[];
}

// One collapsible config section: header toggle + its fields (each via FieldRenderer).
export function ConfigSection({
  section, index, expanded, onToggle, values, fieldErrors, nestedErrors, onValueChange, users, kbDocuments,
}: ConfigSectionProps) {
  return (
    <div>
      <button
        onClick={() => onToggle(index)}
        className="w-full flex items-center justify-between px-4 py-2.5 hover:bg-muted/30 transition-colors"
      >
        <span className="text-[11px] font-semibold text-foreground/80 uppercase tracking-wider">{section.title}</span>
        <ChevronDown className={`h-3.5 w-3.5 text-muted-foreground transition-transform ${expanded ? '' : '-rotate-90'}`} />
      </button>

      {expanded && (
        <div className="px-4 pb-3 space-y-3">
          {section.fields.map(field => {
            const fieldError = fieldErrors[field.key];
            const hasNestedError = Object.keys(nestedErrors[field.key] ?? {}).length > 0;
            return (
              <div
                key={field.key}
                className={fieldError || hasNestedError ? 'rounded-md border border-destructive/40 bg-destructive/5 px-2 pt-2 pb-1 -mx-2' : ''}
              >
                <div className="flex items-center justify-between mb-1">
                  <label className="text-[11px] font-medium text-muted-foreground flex items-center gap-1">
                    {field.label}
                    {field.required && <span className="text-destructive">*</span>}
                  </label>
                  {field.description && !fieldError && (
                    <div className="group relative">
                      <Info className="h-3 w-3 text-muted-foreground/40 cursor-help" />
                      <div className="absolute right-0 bottom-full mb-1 hidden group-hover:block z-50 w-48 p-2 text-[10px] text-foreground bg-popover border border-border rounded-md shadow-lg">
                        {field.description}
                      </div>
                    </div>
                  )}
                </div>
                <FieldRenderer
                  field={field}
                  value={values[field.key] ?? field.defaultValue}
                  onChange={v => onValueChange(field.key, v)}
                  users={users}
                  kbDocuments={kbDocuments}
                  nestedErrors={nestedErrors[field.key]}
                />
                {fieldError && (
                  <p className="mt-1 text-[10px] text-destructive">{fieldError}</p>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
