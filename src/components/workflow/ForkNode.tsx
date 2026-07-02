import { memo } from 'react';
import { Handle, Position, useReactFlow, type NodeProps } from '@xyflow/react';
import { Trash2, AlertCircle, GitFork } from 'lucide-react';
import type { ExecutionStatus } from '@/types/workflow';

const colorClasses: Record<string, { bg: string; text: string; border: string; handle: string }> = {
  amber: { bg: 'bg-amber-500/10', text: 'text-amber-400', border: 'border-amber-500/30', handle: '!bg-amber-400' },
  indigo: { bg: 'bg-indigo-500/10', text: 'text-indigo-400', border: 'border-indigo-500/30', handle: '!bg-indigo-400' },
  violet: { bg: 'bg-violet-500/10', text: 'text-violet-400', border: 'border-violet-500/30', handle: '!bg-violet-400' },
  blue: { bg: 'bg-blue-500/10', text: 'text-blue-400', border: 'border-blue-500/30', handle: '!bg-blue-400' },
  emerald: { bg: 'bg-emerald-500/10', text: 'text-emerald-400', border: 'border-emerald-500/30', handle: '!bg-emerald-400' },
  rose: { bg: 'bg-rose-500/10', text: 'text-rose-400', border: 'border-rose-500/30', handle: '!bg-rose-400' },
};

const HEADER_H = 44;
const ROW_H = 28;
const EMPTY_H = 36;
const BOTTOM_PAD = 8;

interface BranchItem {
  name: string;
  key: string;
}

interface ForkNodeData {
  label: string;
  color: string;
  nodeType: string;
  config?: Record<string, unknown>;
  name?: string;
  hasErrors?: boolean;
  executionStatus?: ExecutionStatus;
  [key: string]: unknown;
}

function ForkNodeComponent({ id, data, selected }: NodeProps) {
  const { deleteElements } = useReactFlow();
  const d = data as unknown as ForkNodeData;
  const colors = colorClasses[d.color] ?? colorClasses.indigo;
  const hasErrors = d.hasErrors;
  const status = d.executionStatus;

  const rawBranches = d.config?.branches;
  const branches: BranchItem[] = Array.isArray(rawBranches)
    ? (rawBranches as unknown[]).filter(
        (b): b is BranchItem =>
          typeof b === 'object' && b !== null &&
          typeof (b as BranchItem).name === 'string' &&
          (b as BranchItem).name.trim() !== '',
      )
    : [];

  const hasBranches = branches.length > 0;

  const statusClass =
    status === 'running' ? 'node-executing' :
    status === 'success' ? 'node-success' :
    status === 'failed' ? 'node-error' : '';

  const contentH = hasBranches ? branches.length * ROW_H + BOTTOM_PAD : EMPTY_H;
  const nodeH = HEADER_H + contentH;

  const branchTop = (i: number) => HEADER_H + i * ROW_H + ROW_H / 2;

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
          <GitFork className="h-3.5 w-3.5" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-xs font-semibold text-foreground truncate block">{d.label}</span>
          {d.name && <span className="text-[10px] text-muted-foreground truncate block">{d.name}</span>}
        </div>
        {hasErrors && <AlertCircle className="h-3.5 w-3.5 text-destructive flex-shrink-0" />}
      </div>

      {/* Branch rows */}
      {hasBranches ? (
        branches.map((branch, i) => {
          const top = HEADER_H + i * ROW_H;
          return (
            <div key={branch.key || i}>
              <div
                className={`absolute left-0 right-0 flex items-center px-3 ${i < branches.length - 1 ? 'border-b border-border/20' : ''}`}
                style={{ top, height: ROW_H }}
              >
                <span className="text-[10px] text-foreground/80 truncate pr-5">{branch.name}</span>
              </div>
              <Handle
                type="source"
                position={Position.Right}
                id={`branch-${branch.key || i}`}
                className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.handle}`}
                style={{ top: branchTop(i) }}
              />
            </div>
          );
        })
      ) : (
        <div
          className="absolute left-0 right-0 flex items-center px-3"
          style={{ top: HEADER_H, height: EMPTY_H }}
        >
          <p className="text-[10px] text-muted-foreground italic">Add branches to create paths</p>
        </div>
      )}
    </div>
  );
}

export const ForkNode = memo(ForkNodeComponent);
