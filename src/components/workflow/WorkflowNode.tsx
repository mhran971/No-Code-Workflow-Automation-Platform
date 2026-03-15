import { memo } from 'react';
import { Handle, Position, type NodeProps } from '@xyflow/react';
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

const iconMap: Record<string, React.ElementType> = {
  Zap, GitBranch, Brain, Plug, Database, Play,
  MousePointerClick, Webhook, FormInput, Clock, Bell,
  GitMerge, Split, Merge, Timer, Route, Repeat, ListOrdered, Filter: FilterIcon,
  Signpost, Tags, FileText, Bot, Search, Heart, Wand2, ShieldCheck,
  Mail, MailOpen, UserPlus, Handshake, CheckSquare, HardDrive, Sheet, FileEdit,
  Variable, Braces, Calendar,
  Globe, Send, ExternalLink, UserCheck,
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

function WorkflowNodeComponent({ data, selected }: NodeProps) {
  const nodeData = data as unknown as WorkflowNodeData;
  const Icon = iconMap[nodeData.icon] || Zap;
  const colors = colorClasses[nodeData.color] || colorClasses.blue;
  const status = nodeData.executionStatus;

  const statusClass = status === 'running' ? 'node-executing' :
    status === 'success' ? 'node-success' :
    status === 'failed' ? 'node-error' : '';

  return (
    <div className={`
      relative min-w-[180px] max-w-[220px] rounded-xl
      bg-card border border-border
      shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_8px_16px_-4px_rgba(0,0,0,0.4)]
      transition-all duration-150
      ${selected ? 'ring-2 ring-primary ring-offset-1 ring-offset-canvas' : ''}
      ${statusClass}
    `}>
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
      <div className={`flex items-center gap-2 px-3 py-2.5 rounded-t-xl ${colors.bg} border-b border-border/50`}>
        <div className={`w-6 h-6 rounded-md flex items-center justify-center ${colors.text}`}>
          <Icon className="h-3.5 w-3.5" />
        </div>
        <span className="text-xs font-semibold text-foreground truncate">{nodeData.label}</span>
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
