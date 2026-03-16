import { Workflow, Save, PlayCircle, Settings, ChevronDown, Loader2 } from 'lucide-react';
import type { ExecutionMode } from '@/hooks/useWorkflowExecution';

interface WorkflowHeaderProps {
  onRunAll?: () => void;
  executionMode?: ExecutionMode;
}

export function WorkflowHeader({ onRunAll, executionMode = 'idle' }: WorkflowHeaderProps) {
  const isRunning = executionMode === 'running' || executionMode === 'stepping';

  return (
    <header className="h-12 bg-background border-b border-border flex items-center justify-between px-4">
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2">
          <Workflow className="h-5 w-5 text-primary" />
          <span className="text-sm font-semibold text-foreground tracking-tight">FlowEngine</span>
        </div>
        <div className="w-px h-5 bg-border" />
        <div className="flex items-center gap-1.5">
          <span className="text-sm text-foreground font-medium">Lead Qualification Pipeline</span>
          <ChevronDown className="h-3 w-3 text-muted-foreground" />
        </div>
        <span className="text-[10px] px-1.5 py-0.5 rounded bg-success/15 text-success font-medium">v2.1</span>
      </div>

      <div className="flex items-center gap-2">
        <button className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
          <Save className="h-3.5 w-3.5" />
          Save
        </button>
        <button className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
          <Settings className="h-3.5 w-3.5" />
          Settings
        </button>
        <button
          onClick={onRunAll}
          disabled={isRunning}
          className="h-8 px-4 flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isRunning ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          ) : (
            <PlayCircle className="h-3.5 w-3.5" />
          )}
          {isRunning ? 'Running...' : 'Run'}
        </button>
      </div>
    </header>
  );
}
