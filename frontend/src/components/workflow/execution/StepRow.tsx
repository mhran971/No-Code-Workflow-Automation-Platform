import { useState } from 'react';
import { CheckCircle2, XCircle, Clock, Loader2, ChevronDown, AlertCircle } from 'lucide-react';
import type { ExecutionStep } from '@/types/workflow';
import { VariableTree } from './VariableTree';

// Status → icon map, shared by StepRow and the execution header.
export const statusIcon: Record<string, React.ReactNode> = {
  success: <CheckCircle2 className="h-3.5 w-3.5 text-success" />,
  failed: <XCircle className="h-3.5 w-3.5 text-destructive" />,
  running: <Loader2 className="h-3.5 w-3.5 text-primary animate-spin" />,
  waiting: <Clock className="h-3.5 w-3.5 text-warning" />,
  idle: <Clock className="h-3.5 w-3.5 text-muted-foreground" />,
};

interface ErrorPayload {
  message?: string;
  exception?: string;
  file?: string;
}

function parseError(raw: string): ErrorPayload {
  try {
    return JSON.parse(raw) as ErrorPayload;
  } catch {
    return { message: raw };
  }
}

// One step in the execution timeline; expands to show its output variables.
export function StepRow({ step, isActive }: { step: ExecutionStep; isActive?: boolean }) {
  const [expanded, setExpanded] = useState(false);

  // Auto-expand running and failed steps
  const shouldExpand = expanded || step.status === 'running' || step.status === 'failed';

  const hasVariables = Object.keys(step.variables).length > 0;
  const errorInfo = step.error ? parseError(step.error) : null;

  return (
    <div className={`border-l-2 ml-2 pl-3 relative transition-colors ${
      isActive          ? 'border-primary' :
      step.status === 'success' ? 'border-success/40' :
      step.status === 'failed'  ? 'border-destructive/40' :
      'border-border'
    }`}>
      <div className={`absolute -left-[5px] top-2.5 w-2 h-2 rounded-full transition-colors ${
        step.status === 'running' ? 'bg-primary animate-pulse' :
        step.status === 'success' ? 'bg-success' :
        step.status === 'failed'  ? 'bg-destructive' : 'bg-border'
      }`} />
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full text-left py-2 group"
      >
        <div className="flex items-center gap-2">
          {statusIcon[step.status]}
          <span className="text-[10px] font-mono text-muted-foreground">[{step.timestamp}]</span>
          <span className="text-xs font-medium text-foreground flex-1 truncate">{step.nodeLabel}</span>
          {step.duration != null && step.status !== 'idle' && (
            <span className="text-[10px] text-muted-foreground">{step.duration}ms</span>
          )}
          <ChevronDown className={`h-3 w-3 text-muted-foreground transition-transform ${shouldExpand ? '' : '-rotate-90'}`} />
        </div>
        {step.message && step.status !== 'idle' && (
          <p className="text-[10px] text-muted-foreground mt-0.5 ml-5 truncate">{step.message}</p>
        )}
      </button>

      {shouldExpand && step.status !== 'idle' && (
        <div className="pb-2 ml-5 space-y-2">
          {/* Error details */}
          {errorInfo && (
            <div className="rounded-md bg-destructive/10 border border-destructive/20 p-2">
              <div className="flex items-start gap-1.5">
                <AlertCircle className="h-3 w-3 text-destructive mt-0.5 shrink-0" />
                <div className="min-w-0 space-y-0.5">
                  <p className="text-[11px] font-medium text-destructive break-words">
                    {errorInfo.message ?? 'Unknown error'}
                  </p>
                  {errorInfo.exception && (
                    <p className="text-[10px] text-muted-foreground font-mono break-all">
                      {errorInfo.exception}
                    </p>
                  )}
                  {errorInfo.file && (
                    <p className="text-[10px] text-muted-foreground font-mono break-all">
                      {errorInfo.file}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Variables */}
          {hasVariables && (
            <div>
              <div className="text-[10px] font-medium text-muted-foreground mb-1 uppercase tracking-wider">Variables</div>
              <div className="bg-muted/40 rounded-md p-2 border border-border/50">
                <VariableTree data={step.variables} />
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
