import { useState, useCallback, useEffect } from 'react';
import { X, AlertCircle } from 'lucide-react';
import type { ApiConfigField, KnowledgeBaseDocument, TenantUser, ValidationIssue } from '@/lib/api/types';
import { useNodeConfigSchema, buildInitialValues } from '@/hooks/useNodeConfigSchema';
import { useFieldValidationErrors } from '@/hooks/useFieldValidationErrors';
import { iconMap, colorBg, DefaultIcon } from './config-icons';
import { ConfigSection } from './config-fields/ConfigSection';

interface SelectedNode {
  id: string;
  label: string;
  icon: string;
  color: string;
  nodeType: string;
  description: string;
  config?: Record<string, unknown>;
  name?: string;
}

interface NodeConfigPanelProps {
  node: SelectedNode;
  apiConfigFields?: ApiConfigField[];
  onClose: () => void;
  onDelete?: () => void;
  onConfigSave?: (config: Record<string, unknown>, name?: string) => void;
  validationIssues?: ValidationIssue[];
  tenantUsers?: TenantUser[];
  kbDocuments?: KnowledgeBaseDocument[];
}

export function NodeConfigPanel({
  node,
  apiConfigFields,
  onClose,
  onDelete,
  onConfigSave,
  validationIssues,
  tenantUsers = [],
  kbDocuments = [],
}: NodeConfigPanelProps) {
  const schema = useNodeConfigSchema(node.nodeType, apiConfigFields);

  const [values, setValues] = useState<Record<string, unknown>>(() =>
    buildInitialValues(schema, node.config),
  );
  const [name, setName] = useState(node.name ?? '');
  const [expandedSections, setExpandedSections] = useState<Set<number>>(
    new Set(schema?.sections.map((_, i) => i) || []),
  );

  useEffect(() => {
    setValues(buildInitialValues(schema, node.config));
    setName(node.name ?? '');
    setExpandedSections(new Set(schema?.sections.map((_, i) => i) || []));
  }, [node.id, node.config, node.name, schema]);

  const { fieldErrors, nestedErrors, nodeErrors } = useFieldValidationErrors(validationIssues);

  const Icon = iconMap[node.icon] || DefaultIcon;
  const colors = colorBg[node.color] || colorBg.blue;

  const updateValue = useCallback((key: string, val: unknown) => {
    setValues(prev => ({ ...prev, [key]: val }));
  }, []);

  const toggleSection = useCallback((idx: number) => {
    setExpandedSections(prev => {
      const next = new Set(prev);
      if (next.has(idx)) next.delete(idx);
      else next.add(idx);
      return next;
    });
  }, []);

  const totalErrors = Object.keys(fieldErrors).length + nodeErrors.length;

  return (
    <div className="flex flex-col h-full">
      {/* Node header */}
      <div className="flex items-center gap-2.5 px-4 py-3 border-b border-border">
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${colors}`}>
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

      {/* Validation error banner */}
      {totalErrors > 0 && (
        <div className="mx-3 mt-3 rounded-md border border-destructive/30 bg-destructive/8 p-2.5 space-y-1.5">
          <div className="flex items-center gap-1.5">
            <AlertCircle className="h-3.5 w-3.5 text-destructive flex-shrink-0" />
            <span className="text-[11px] font-semibold text-destructive">
              {totalErrors} validation error{totalErrors !== 1 ? 's' : ''}
            </span>
          </div>
          {nodeErrors.map((issue, i) => (
            <p key={i} className="text-[11px] text-destructive/90 pl-5">{issue.message}</p>
          ))}
        </div>
      )}

      {/* Config sections */}
      <div className="flex-1 overflow-y-auto">
        {/* Node name field — always shown */}
        <div className="px-4 pt-3 pb-2 border-b border-border/50">
          <label className="text-[11px] font-medium text-muted-foreground block mb-1">Node Name</label>
          <input
            type="text"
            value={name}
            onChange={e => setName(e.target.value)}
            placeholder="Enter a name for this node…"
            className="w-full h-8 px-2.5 text-xs bg-muted border border-border rounded-md text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary"
          />
        </div>

        {!schema ? (
          <div className="p-4 text-xs text-muted-foreground">
            No configuration available for this node type.
          </div>
        ) : (
          <div className="divide-y divide-border/50">
            {schema.sections.map((section, sIdx) => (
              <ConfigSection
                key={sIdx}
                section={section}
                index={sIdx}
                expanded={expandedSections.has(sIdx)}
                onToggle={toggleSection}
                values={values}
                fieldErrors={fieldErrors}
                nestedErrors={nestedErrors}
                onValueChange={updateValue}
                users={tenantUsers}
                kbDocuments={kbDocuments}
              />
            ))}
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="p-3 border-t border-border flex gap-2">
        {onDelete && (
          <button
            type="button"
            onClick={onDelete}
            className="h-8 px-3 text-xs rounded-md text-destructive border border-destructive/30 hover:bg-destructive/10 transition-colors"
          >
            Delete
          </button>
        )}
        <button
          type="button"
          onClick={() => onConfigSave?.(values, name.trim() || undefined)}
          className="flex-1 h-8 text-xs rounded-md bg-primary text-primary-foreground font-medium hover:bg-primary/90 transition-colors"
        >
          Save Changes
        </button>
        <button onClick={onClose} className="h-8 px-3 text-xs rounded-md bg-muted text-muted-foreground border border-border hover:bg-muted/80 transition-colors">
          Cancel
        </button>
      </div>
    </div>
  );
}
