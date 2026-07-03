import type { ExecutionStatus, WorkflowExecution } from '@/types/workflow';

export type ExecutionMode = 'idle' | 'running' | 'stepping' | 'paused' | 'completed';

export interface UseWorkflowExecutionReturn {
  execution: WorkflowExecution | null;
  mode: ExecutionMode;
  currentStepIndex: number;
  runAll: () => void;
  stepForward: () => void;
  reset: () => void;
  pause: () => void;
  resume: () => void;
  nodeStatuses: Map<string, ExecutionStatus>;
}
