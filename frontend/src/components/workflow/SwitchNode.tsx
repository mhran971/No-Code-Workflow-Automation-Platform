import { memo } from 'react';
import { Handle, Position, useReactFlow, type NodeProps } from '@xyflow/react';
import { Trash2, AlertCircle, Route } from 'lucide-react';
import type { ExecutionStatus } from '@/types/workflow';

const colorClasses: Record<string, { bg: string; text: string; border: string; handle: string }> = {
  amber: { bg: 'bg-amber-500/10', text: 'text-amber-400', border: 'border-amber-500/30', handle: '!bg-amber-400' },
  indigo: { bg: 'bg-indigo-500/10', text: 'text-indigo-400', border: 'border-indigo-500/30', handle: '!bg-indigo-400' },
  violet: { bg: 'bg-violet-500/10', text: 'text-violet-400', border: 'border-violet-500/30', handle: '!bg-violet-400' },
  blue: { bg: 'bg-blue-500/10', text: 'text-blue-400', border: 'border-blue-500/30', handle: '!bg-blue-400' },
  emerald: { bg: 'bg-emerald-500/10', text: 'text-emerald-400', border: 'border-emerald-500/30', handle: '!bg-emerald-400' },
  rose: { bg: 'bg-rose-500/10', text: 'text-rose-400', border: 'border-rose-500/30', handle: '!bg-rose-400' },
};

// Fixed pixel heights — handles must be precisely aligned with their label rows.
const HEADER_H = 44;
const VAR_H = 28;
const ROW_H = 28;
const EMPTY_H = 36;
const BOTTOM_PAD = 8;

interface SwitchNodeData {
  label: string;
  color: string;
  nodeType: string;
  config?: Record<string, unknown>;
  name?: string;
  hasErrors?: boolean;
  executionStatus?: ExecutionStatus;
  [key: string]: unknown;
}

function SwitchNodeComponent({ id, data, selected }: NodeProps) {
  const { deleteElements } = useReactFlow();
  const d = data as unknown as SwitchNodeData;
  const colors = colorClasses[d.color] ?? colorClasses.indigo;
  const hasErrors = d.hasErrors;
  const status = d.executionStatus;

  const rawOptions = d.config?.options;
  const options: string[] = Array.isArray(rawOptions)
    ? (rawOptions as unknown[]).filter((o): o is string => typeof o === 'string' && o.trim() !== '')
    : [];

  const variable = typeof d.config?.variable === 'string' ? d.config.variable.trim() : '';
  const hasVar = variable !== '';
  const hasOptions = options.length > 0;

  // branches = each option + always a "Default" at the bottom
  const branches = hasOptions ? [...options, 'Default'] : [];

  const statusClass =
    status === 'running' ? 'node-executing' :
    status === 'success' ? 'node-success' :
    status === 'failed' ? 'node-error' : '';

  const contentH = hasOptions
    ? branches.length * ROW_H + BOTTOM_PAD
    : EMPTY_H;

  const nodeH = HEADER_H + (hasVar ? VAR_H : 0) + contentH;

  // Center of each branch row relative to the full node top
  const branchTop = (i: number) => HEADER_H + (hasVar ? VAR_H : 0) + i * ROW_H + ROW_H / 2;

  const handleDelete = (e: React.MouseEvent) => {
    e.stopPropagation();
    deleteElements({ nodes: [{ id }] });
  };

  return (
    <div
      className={`
        relative min-w-[200px] max-w-[240px] rounded-xl bg-card
        ${hasErrors ? 'border border-destructive' : 'border border-border'}
        shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_8px_16px_-4px_rgba(0,0,0,0.4)]
        transition-all duration-150
        ${selected ? 'ring-2 ring-primary ring-offset-1 ring-offset-canvas' : ''}
        ${hasErrors && !selected ? 'ring-1 ring-destructive/40' : ''}
        ${statusClass}
      `}
      style={{ height: nodeH }}
    >
      {selected && (
        <button
          type="button"
          onClick={handleDelete}
          className="absolute -top-2 -right-2 z-10 w-6 h-6 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center shadow-md hover:bg-destructive/90 transition-colors"
        >
          <Trash2 className="h-3 w-3" />
        </button>
      )}

      {/* Single input handle — vertically centered */}
      <Handle
        type="target"
        position={Position.Left}
        id="input-0"
        className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.handle}`}
        style={{ top: '50%' }}
      />

      {/* Header */}
      <div
        className={`flex items-center gap-2 px-3 rounded-t-xl ${colors.bg} border-b ${hasErrors ? 'border-destructive/30' : 'border-border/50'}`}
        style={{ height: HEADER_H }}
      >
        <div className={`w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0 ${colors.text}`}>
          <Route className="h-3.5 w-3.5" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-xs font-semibold text-foreground truncate block">{d.label}</span>
          {d.name && (
            <span className="text-[10px] text-muted-foreground truncate block">{d.name}</span>
          )}
        </div>
        {hasErrors && <AlertCircle className="h-3.5 w-3.5 text-destructive flex-shrink-0" />}
      </div>

      {/* Variable display */}
      {hasVar && (
        <div
          className="px-3 flex items-center border-b border-border/30"
          style={{ height: VAR_H }}
        >
          <code className="text-[10px] text-primary/70 font-mono truncate">{variable}</code>
        </div>
      )}

      {/* Branch rows — absolutely positioned so handles align precisely */}
      {hasOptions ? (
        branches.map((branch, i) => {
          const isDefault = i === branches.length - 1;
          const top = HEADER_H + (hasVar ? VAR_H : 0) + i * ROW_H;
          const handleTopPx = branchTop(i);
          const handleId = isDefault ? 'default' : `option-${branch}`;

          return (
            <div key={branch}>
              {/* Label row */}
              <div
                className={`absolute left-0 right-0 flex items-center px-3 ${i < branches.length - 1 ? 'border-b border-border/20' : ''}`}
                style={{ top, height: ROW_H }}
              >
                <span
                  className={`text-[10px] truncate pr-5 ${
                    isDefault ? 'text-muted-foreground italic' : 'text-foreground/80'
                  }`}
                >
                  {branch}
                </span>
              </div>

              {/* Output handle aligned to row center */}
              <Handle
                type="source"
                position={Position.Right}
                id={handleId}
                className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.handle}`}
                style={{ top: handleTopPx }}
              />
            </div>
          );
        })
      ) : (
        <div
          className="absolute left-0 right-0 flex items-center px-3"
          style={{ top: HEADER_H + (hasVar ? VAR_H : 0), height: EMPTY_H }}
        >
          <p className="text-[10px] text-muted-foreground italic">Add options to create branches</p>
        </div>
      )}
    </div>
  );
}

export const SwitchNode = memo(SwitchNodeComponent);
