import { PlayCircle, StepForward, Pause, Play, RotateCcw, XCircle } from 'lucide-react';
import type { WorkflowExecution } from '@/types/workflow';
import type { ExecutionMode } from '@/hooks/useWorkflowExecution';

interface ExecutionControlsProps {
  executionMode: ExecutionMode;
  execution?: WorkflowExecution | null;
  isBackendMode?: boolean;
  onRunAll?: () => void;
  onStepForward?: () => void;
  onPause?: () => void;
  onResume?: () => void;
  onReset?: () => void;
  onCancel?: () => void;
}

// Run/Step/Pause/Resume/Reset/Cancel buttons plus the run progress bar.
export function ExecutionControls({
  executionMode, execution, isBackendMode = false,
  onRunAll, onStepForward, onPause, onResume, onReset, onCancel,
}: ExecutionControlsProps) {
  const isRunning   = executionMode === 'running';
  const isStepping  = executionMode === 'stepping';
  const isPaused    = executionMode === 'paused';
  const isCompleted = executionMode === 'completed';
  const isIdle      = executionMode === 'idle';

  const completedSteps = execution?.steps.filter(s => s.status === 'success').length ?? 0;
  const totalSteps     = execution?.steps.length ?? 0;

  return (
    <div className="mb-3 space-y-2">
      <div className="flex items-center gap-1.5">
        {isIdle && (
          <>
            <button
              onClick={onRunAll}
              className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors"
            >
              <PlayCircle className="h-3.5 w-3.5" />
              {isBackendMode ? 'Run in Backend' : 'Run All'}
            </button>
            {/* Step button only in mock mode */}
            {!isBackendMode && (
              <button
                onClick={onStepForward}
                className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 transition-colors"
              >
                <StepForward className="h-3.5 w-3.5" />
                Step
              </button>
            )}
          </>
        )}

        {isRunning && (
          isBackendMode ? (
            /* Backend running: show Cancel */
            <button
              onClick={onCancel}
              className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-destructive/10 text-destructive text-xs font-semibold hover:bg-destructive/20 transition-colors border border-destructive/30"
            >
              <XCircle className="h-3.5 w-3.5" />
              Cancel
            </button>
          ) : (
            /* Mock running: show Pause */
            <button
              onClick={onPause}
              className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-warning/15 text-warning text-xs font-semibold hover:bg-warning/25 transition-colors border border-warning/30"
            >
              <Pause className="h-3.5 w-3.5" />
              Pause
            </button>
          )
        )}

        {/* Paused state — only reachable in mock mode */}
        {isPaused && (
          <>
            <button
              onClick={onResume}
              className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors"
            >
              <Play className="h-3.5 w-3.5" />
              Resume
            </button>
            <button
              onClick={onStepForward}
              className="h-8 w-8 flex items-center justify-center rounded-lg bg-secondary text-secondary-foreground hover:bg-secondary/80 transition-colors"
              title="Next Step"
            >
              <StepForward className="h-3.5 w-3.5" />
            </button>
          </>
        )}

        {(isRunning || isPaused || isCompleted || isStepping) && (
          <button
            onClick={onReset}
            className="h-8 w-8 flex items-center justify-center rounded-lg bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground transition-colors"
            title="Reset"
          >
            <RotateCcw className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      {/* Progress */}
      {execution && (
        <div className="space-y-1">
          <div className="flex items-center justify-between text-[10px]">
            <span className="text-muted-foreground">
              {isCompleted ? 'Completed' : isRunning ? 'Running…' : isPaused ? 'Paused' : 'Ready'}
            </span>
            <span className="text-muted-foreground font-mono">{completedSteps}/{totalSteps} steps</span>
          </div>
          <div className="h-1 bg-muted rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-500 ${
                isCompleted ? 'bg-success' : 'bg-primary'
              }`}
              style={{ width: `${totalSteps > 0 ? (completedSteps / totalSteps) * 100 : 0}%` }}
            />
          </div>
        </div>
      )}
    </div>
  );
}
