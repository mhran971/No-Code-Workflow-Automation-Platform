import { useState } from 'react';
import type { WorkflowExecution, ExecutionStep } from '@/types/workflow';
import { CheckCircle2, XCircle, Clock, Loader2, ChevronDown, PlayCircle, StepForward, Pause, Play, RotateCcw, Zap } from 'lucide-react';
import { NodeConfigPanel } from './NodeConfigPanel';
import type { SelectedNodeInfo } from '@/pages/Index';
import type { ExecutionMode } from '@/hooks/useWorkflowExecution';

const statusIcon: Record<string, React.ReactNode> = {
  success: <CheckCircle2 className="h-3.5 w-3.5 text-success" />,
  failed: <XCircle className="h-3.5 w-3.5 text-destructive" />,
  running: <Loader2 className="h-3.5 w-3.5 text-primary animate-spin" />,
  waiting: <Clock className="h-3.5 w-3.5 text-warning" />,
  idle: <Clock className="h-3.5 w-3.5 text-muted-foreground" />,
};

function VariableTree({ data, depth = 0 }: { data: unknown; depth?: number }) {
  if (data === null || data === undefined) {
    return <span className="text-muted-foreground/60 font-mono text-[11px]">null</span>;
  }
  if (typeof data === 'string') {
    return <span className="text-emerald-400 font-mono text-[11px]">"{data}"</span>;
  }
  if (typeof data === 'number') {
    return <span className="text-amber-400 font-mono text-[11px]">{data}</span>;
  }
  if (typeof data === 'boolean') {
    return <span className="text-blue-400 font-mono text-[11px]">{data.toString()}</span>;
  }
  if (Array.isArray(data)) {
    return (
      <div className="ml-3">
        {data.map((item, i) => (
          <div key={i} className="flex items-start gap-1">
            <span className="text-muted-foreground font-mono text-[11px]">[{i}]:</span>
            <VariableTree data={item} depth={depth + 1} />
          </div>
        ))}
      </div>
    );
  }
  if (typeof data === 'object') {
    return (
      <div className={depth > 0 ? 'ml-3' : ''}>
        {Object.entries(data as Record<string, unknown>).map(([key, value]) => (
          <div key={key} className="flex items-start gap-1">
            <span className="text-muted-foreground/80 font-mono text-[11px] shrink-0">{key}:</span>
            <VariableTree data={value} depth={depth + 1} />
          </div>
        ))}
      </div>
    );
  }
  return <span className="text-foreground font-mono text-[11px]">{String(data)}</span>;
}

function StepRow({ step, isActive }: { step: ExecutionStep; isActive?: boolean }) {
  const [expanded, setExpanded] = useState(false);

  // Auto-expand running step
  const shouldExpand = expanded || (step.status === 'running');

  return (
    <div className={`border-l-2 ml-2 pl-3 relative transition-colors ${
      isActive ? 'border-primary' : step.status === 'success' ? 'border-success/40' : 'border-border'
    }`}>
      <div className={`absolute -left-[5px] top-2.5 w-2 h-2 rounded-full transition-colors ${
        step.status === 'running' ? 'bg-primary animate-pulse' :
        step.status === 'success' ? 'bg-success' :
        step.status === 'failed' ? 'bg-destructive' : 'bg-border'
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
        <div className="pb-2 ml-5">
          <div className="text-[10px] font-medium text-muted-foreground mb-1 uppercase tracking-wider">Variables</div>
          <div className="bg-muted/40 rounded-md p-2 border border-border/50">
            <VariableTree data={step.variables} />
          </div>
        </div>
      )}
    </div>
  );
}

interface ExecutionPanelProps {
  selectedNode?: SelectedNodeInfo | null;
  onDeselectNode?: () => void;
  execution?: WorkflowExecution | null;
  executionMode?: ExecutionMode;
  currentStepIndex?: number;
  onRunAll?: () => void;
  onStepForward?: () => void;
  onPause?: () => void;
  onResume?: () => void;
  onReset?: () => void;
}

export function ExecutionPanel({
  selectedNode,
  onDeselectNode,
  execution,
  executionMode = 'idle',
  currentStepIndex = -1,
  onRunAll,
  onStepForward,
  onPause,
  onResume,
  onReset,
}: ExecutionPanelProps) {
  const [activeTab, setActiveTab] = useState<'execution' | 'configuration'>('execution');

  const effectiveTab = selectedNode ? 'configuration' : activeTab;
  const isRunning = executionMode === 'running';
  const isStepping = executionMode === 'stepping';
  const isPaused = executionMode === 'paused';
  const isCompleted = executionMode === 'completed';
  const isIdle = executionMode === 'idle';

  const completedSteps = execution?.steps.filter(s => s.status === 'success').length || 0;
  const totalSteps = execution?.steps.length || 0;

  return (
    <div className="w-[320px] h-full bg-background border-l border-border flex flex-col">
      {/* Tabs */}
      <div className="flex border-b border-border">
        <button
          onClick={() => { setActiveTab('configuration'); }}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors ${
            effectiveTab === 'configuration' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          {selectedNode ? 'Node Config' : 'Configuration'}
        </button>
        <button
          onClick={() => { setActiveTab('execution'); if (onDeselectNode) onDeselectNode(); }}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors relative ${
            effectiveTab === 'execution' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          Execution
          {isRunning && (
            <span className="absolute top-2 right-3 w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
          )}
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-hidden">
        {effectiveTab === 'configuration' && selectedNode ? (
          <NodeConfigPanel node={selectedNode} onClose={() => onDeselectNode?.()} />
        ) : effectiveTab === 'execution' ? (
          <div className="h-full overflow-y-auto p-3">
            {/* Execution Controls */}
            <div className="mb-3 space-y-2">
              <div className="flex items-center gap-1.5">
                {isIdle && (
                  <>
                    <button
                      onClick={onRunAll}
                      className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors"
                    >
                      <PlayCircle className="h-3.5 w-3.5" />
                      Run All
                    </button>
                    <button
                      onClick={onStepForward}
                      className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 transition-colors"
                    >
                      <StepForward className="h-3.5 w-3.5" />
                      Step
                    </button>
                  </>
                )}
                {isRunning && (
                  <button
                    onClick={onPause}
                    className="flex-1 h-8 flex items-center justify-center gap-1.5 rounded-lg bg-warning/15 text-warning text-xs font-semibold hover:bg-warning/25 transition-colors border border-warning/30"
                  >
                    <Pause className="h-3.5 w-3.5" />
                    Pause
                  </button>
                )}
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
                      {isCompleted ? 'Completed' : isRunning ? 'Running...' : isPaused ? 'Paused' : 'Ready'}
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

            {/* Execution Header */}
            {execution && (
              <>
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    {isCompleted ? statusIcon.success : isRunning ? statusIcon.running : statusIcon.idle}
                    <span className="text-xs font-medium text-foreground">Run #{execution.id.split('-')[1]?.slice(0, 6)}</span>
                  </div>
                  {execution.completedAt && (
                    <span className="text-[10px] text-muted-foreground font-mono">
                      {((new Date(execution.completedAt).getTime() - new Date(execution.startedAt).getTime()) / 1000).toFixed(1)}s
                    </span>
                  )}
                </div>

                {/* Steps */}
                <div className="space-y-0">
                  {execution.steps.map((step, i) => (
                    <StepRow key={step.id} step={step} isActive={i === currentStepIndex} />
                  ))}
                </div>
              </>
            )}

            {/* Empty state */}
            {!execution && (
              <div className="flex flex-col items-center justify-center h-48 text-center">
                <Zap className="h-8 w-8 text-muted-foreground/30 mb-3" />
                <p className="text-xs text-muted-foreground">No execution yet</p>
                <p className="text-[10px] text-muted-foreground/60 mt-1">Click "Run All" or "Step" to start</p>
              </div>
            )}
          </div>
        ) : (
          <div className="h-full overflow-y-auto p-4 space-y-4">
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Workflow Name</label>
              <input
                type="text"
                defaultValue="Lead Qualification Pipeline"
                className="mt-1 w-full h-8 px-3 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Description</label>
              <textarea
                defaultValue="Automatically classify and route incoming leads to the sales team based on AI-driven scoring."
                rows={3}
                className="mt-1 w-full px-3 py-2 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary resize-none"
              />
            </div>
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Mode</label>
              <div className="mt-1 flex gap-2">
                <button className="flex-1 h-8 text-xs rounded-md bg-primary/10 text-primary border border-primary/30 font-medium">Edit</button>
                <button className="flex-1 h-8 text-xs rounded-md bg-muted text-muted-foreground border border-border hover:bg-muted/80">Live</button>
              </div>
            </div>
            <div className="pt-2 border-t border-border">
              <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Global Variables</div>
              <div className="space-y-1.5 bg-muted/40 rounded-md p-2 border border-border/50 font-mono text-[11px]">
                <div className="flex justify-between"><span className="text-muted-foreground/80">env</span><span className="text-emerald-400">"production"</span></div>
                <div className="flex justify-between"><span className="text-muted-foreground/80">version</span><span className="text-amber-400">2.1</span></div>
                <div className="flex justify-between"><span className="text-muted-foreground/80">debug</span><span className="text-blue-400">false</span></div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
