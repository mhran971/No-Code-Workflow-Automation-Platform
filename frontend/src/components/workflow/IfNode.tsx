import { memo } from 'react';
import { Handle, Position, useReactFlow, type NodeProps } from '@xyflow/react';
import { Trash2, AlertCircle, GitBranch } from 'lucide-react';
import type { ExecutionStatus } from '@/types/workflow';

const colorClasses: Record<string, { bg: string; text: string; border: string; handle: string; yes: string; no: string }> = {
  indigo: {
    bg: 'bg-indigo-500/10', text: 'text-indigo-400', border: 'border-indigo-500/30',
    handle: '!bg-indigo-400', yes: '!bg-emerald-400', no: '!bg-rose-400',
  },
};

const HEADER_H = 44;
const EXPR_H = 28;
const ROW_H = 28;
const BOTTOM_PAD = 4;

interface IfNodeData {
  label: string;
  color: string;
  nodeType: string;
  config?: Record<string, unknown>;
  name?: string;
  hasErrors?: boolean;
  executionStatus?: ExecutionStatus;
  [key: string]: unknown;
}

function IfNodeComponent({ id, data, selected }: NodeProps) {
  const { deleteElements } = useReactFlow();
  const d = data as unknown as IfNodeData;
  const colors = colorClasses[d.color] ?? colorClasses.indigo;
  const hasErrors = d.hasErrors;
  const status = d.executionStatus;

  const expr = typeof d.config?.conditionExpression === 'string' ? d.config.conditionExpression.trim() : '';
  const hasExpr = expr !== '';

  const statusClass =
    status === 'running' ? 'node-executing' :
    status === 'success' ? 'node-success' :
    status === 'failed' ? 'node-error' : '';

  const contentH = 2 * ROW_H + BOTTOM_PAD;
  const nodeH = HEADER_H + (hasExpr ? EXPR_H : 0) + contentH;

  const yesTop = HEADER_H + (hasExpr ? EXPR_H : 0) + 0 * ROW_H + ROW_H / 2;
  const noTop  = HEADER_H + (hasExpr ? EXPR_H : 0) + 1 * ROW_H + ROW_H / 2;

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
          onClick={e => { e.stopPropagation(); deleteElements({ nodes: [{ id }] }); }}
          className="absolute -top-2 -right-2 z-10 w-6 h-6 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center shadow-md hover:bg-destructive/90 transition-colors"
        >
          <Trash2 className="h-3 w-3" />
        </button>
      )}

      {/* Single input handle */}
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
          <GitBranch className="h-3.5 w-3.5" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-xs font-semibold text-foreground truncate block">{d.label}</span>
          {d.name && <span className="text-[10px] text-muted-foreground truncate block">{d.name}</span>}
        </div>
        {hasErrors && <AlertCircle className="h-3.5 w-3.5 text-destructive flex-shrink-0" />}
      </div>

      {/* Condition expression */}
      {hasExpr && (
        <div
          className="px-3 flex items-center border-b border-border/30"
          style={{ height: EXPR_H }}
        >
          <code className="text-[10px] text-primary/70 font-mono truncate">{expr}</code>
        </div>
      )}

      {/* Yes row */}
      <div
        className="absolute left-0 right-0 flex items-center px-3 border-b border-border/20"
        style={{ top: HEADER_H + (hasExpr ? EXPR_H : 0), height: ROW_H }}
      >
        <span className="text-[10px] font-medium text-emerald-400">Yes</span>
      </div>
      <Handle
        type="source"
        position={Position.Right}
        id="yes"
        className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.yes}`}
        style={{ top: yesTop }}
      />

      {/* No row */}
      <div
        className="absolute left-0 right-0 flex items-center px-3"
        style={{ top: HEADER_H + (hasExpr ? EXPR_H : 0) + ROW_H, height: ROW_H }}
      >
        <span className="text-[10px] font-medium text-rose-400">No</span>
      </div>
      <Handle
        type="source"
        position={Position.Right}
        id="no"
        className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.no}`}
        style={{ top: noTop }}
      />
    </div>
  );
}

export const IfNode = memo(IfNodeComponent);
