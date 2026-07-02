import { memo } from 'react';
import { Handle, Position, useReactFlow, type NodeProps } from '@xyflow/react';
import { Trash2, AlertCircle } from 'lucide-react';
import {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter as FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
} from 'lucide-react';
import type { ExecutionStatus } from '@/types/workflow';

const GmailIcon = ({ className }: { className?: string }) => (
  <svg viewBox="0 0 24 24" fill="none" className={className} xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="5" width="20" height="14" rx="1.5" stroke="currentColor" strokeWidth="1.5"/>
    <path d="M2 8L9 13.5L12 11L15 13.5L22 8" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
    <path d="M9 13.5V19M15 13.5V19" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"/>
  </svg>
);

const iconMap: Record<string, React.ElementType> = {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter: FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
  Gmail: GmailIcon,
};

const colorClasses: Record<string, { bg: string; text: string; border: string; handle: string }> = {
  amber: { bg: 'bg-amber-500/10', text: 'text-amber-400', border: 'border-amber-500/30', handle: '!bg-amber-400' },
  indigo: { bg: 'bg-indigo-500/10', text: 'text-indigo-400', border: 'border-indigo-500/30', handle: '!bg-indigo-400' },
  violet: { bg: 'bg-violet-500/10', text: 'text-violet-400', border: 'border-violet-500/30', handle: '!bg-violet-400' },
  blue: { bg: 'bg-blue-500/10', text: 'text-blue-400', border: 'border-blue-500/30', handle: '!bg-blue-400' },
  emerald: { bg: 'bg-emerald-500/10', text: 'text-emerald-400', border: 'border-emerald-500/30', handle: '!bg-emerald-400' },
  rose: { bg: 'bg-rose-500/10', text: 'text-rose-400', border: 'border-rose-500/30', handle: '!bg-rose-400' },
};

interface WorkflowNodeData {
  label: string;
  icon: string;
  color: string;
  nodeType: string;
  description: string;
  inputs: number;
  outputs: number;
  executionStatus?: ExecutionStatus;
  [key: string]: unknown;
}

function WorkflowNodeComponent({ id, data, selected }: NodeProps) {
  const { deleteElements } = useReactFlow();
  const nodeData = data as unknown as WorkflowNodeData;
  const Icon = iconMap[nodeData.icon] || Zap;
  const colors = colorClasses[nodeData.color] || colorClasses.blue;
  const status = nodeData.executionStatus;

  const handleDelete = (event: React.MouseEvent) => {
    event.stopPropagation();
    deleteElements({ nodes: [{ id }] });
  };

  const statusClass = status === 'running' ? 'node-executing' :
    status === 'success' ? 'node-success' :
    status === 'failed' ? 'node-error' : '';

  const hasErrors = nodeData.hasErrors as boolean | undefined;

  return (
    <div className={`
      relative min-w-[180px] max-w-[220px] rounded-xl
      bg-card
      ${hasErrors ? 'border border-destructive' : 'border border-border'}
      shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_8px_16px_-4px_rgba(0,0,0,0.4)]
      transition-all duration-150
      ${selected ? 'ring-2 ring-primary ring-offset-1 ring-offset-canvas' : ''}
      ${hasErrors && !selected ? 'ring-1 ring-destructive/40' : ''}
      ${statusClass}
    `}>
      {selected && (
        <button
          type="button"
          onClick={handleDelete}
          className="absolute -top-2 -right-2 z-10 w-6 h-6 rounded-full bg-destructive text-destructive-foreground flex items-center justify-center shadow-md hover:bg-destructive/90 transition-colors"
          title="Delete node"
        >
          <Trash2 className="h-3 w-3" />
        </button>
      )}

      {/* Input handles */}
      {nodeData.inputs > 0 && Array.from({ length: nodeData.inputs }).map((_, i) => (
        <Handle
          key={`input-${i}`}
          type="target"
          position={Position.Left}
          id={`input-${i}`}
          className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.handle}`}
          style={{
            top: nodeData.inputs === 1 ? '50%' : `${((i + 1) / (nodeData.inputs + 1)) * 100}%`,
          }}
        />
      ))}

      {/* Header */}
      <div className={`flex items-center gap-2 px-3 py-2.5 rounded-t-xl ${colors.bg} border-b ${hasErrors ? 'border-destructive/30' : 'border-border/50'}`}>
        <div className={`w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0 ${colors.text}`}>
          <Icon className="h-3.5 w-3.5" />
        </div>
        <div className="flex-1 min-w-0">
          <span className="text-xs font-semibold text-foreground truncate block">{nodeData.label}</span>
          {nodeData.name && (
            <span className="text-[10px] text-muted-foreground truncate block">{nodeData.name as string}</span>
          )}
        </div>
        {hasErrors && (
          <AlertCircle className="h-3.5 w-3.5 text-destructive flex-shrink-0" />
        )}
      </div>

      {/* Body */}
      <div className="px-3 py-2">
        <p className="text-[10px] text-muted-foreground leading-relaxed">{nodeData.description}</p>
        {status && status !== 'idle' && (
          <div className="mt-1.5 flex items-center gap-1.5">
            <div className={`w-1.5 h-1.5 rounded-full ${
              status === 'running' ? 'bg-primary animate-pulse-glow' :
              status === 'success' ? 'bg-success' :
              status === 'failed' ? 'bg-destructive' :
              status === 'waiting' ? 'bg-warning' : 'bg-muted-foreground'
            }`} />
            <span className="text-[10px] text-muted-foreground capitalize">{status}</span>
          </div>
        )}
      </div>

      {/* Output handles */}
      {nodeData.outputs > 0 && Array.from({ length: nodeData.outputs }).map((_, i) => (
        <Handle
          key={`output-${i}`}
          type="source"
          position={Position.Right}
          id={`output-${i}`}
          className={`!w-2.5 !h-2.5 !border-2 !border-card ${colors.handle}`}
          style={{
            top: nodeData.outputs === 1 ? '50%' : `${((i + 1) / (nodeData.outputs + 1)) * 100}%`,
          }}
        />
      ))}
    </div>
  );
}

export const WorkflowNode = memo(WorkflowNodeComponent);
