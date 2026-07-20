import type { ExecutionStep } from '@/types/workflow';
import { StepRow } from './StepRow';

interface ChildInstanceTimelineProps {
  steps: ExecutionStep[];
  childInstanceId: number;
}

export function ChildInstanceTimeline({ steps, childInstanceId }: ChildInstanceTimelineProps) {
  if (steps.length === 0) return null;

  const hasRunning = steps.some((s) => s.status === 'running');
  const hasFailed = steps.some((s) => s.status === 'failed');
  const completedCount = steps.filter((s) => s.status === 'success').length;

  return (
    <div className="ml-6 mt-1 mb-2 border-l-2 border-dashed border-teal-300/50 pl-3 relative">
      {/* Header label */}
      <div className="flex items-center gap-2 mb-1 -ml-[13px]">
        <div className={`w-2 h-2 rounded-full ${
          hasFailed ? 'bg-destructive' : hasRunning ? 'bg-primary animate-pulse' : 'bg-teal-500'
        }`} />
        <span className="text-[10px] font-medium text-muted-foreground">
          Sub-Flow #{childInstanceId}
          {!hasFailed && !hasRunning && (
            <span className="ml-1 text-success">{completedCount}/{steps.length} steps</span>
          )}
        </span>
      </div>

      {/* Child steps */}
      <div className="space-y-0">
        {steps.map((step, i) => (
          <StepRow key={step.id} step={step} isActive={step.status === 'running'} />
        ))}
      </div>
    </div>
  );
}
