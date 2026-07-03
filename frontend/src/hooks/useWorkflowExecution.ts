import { useState, useCallback, useRef } from 'react';
import type { ExecutionStep, ExecutionStatus, WorkflowExecution } from '@/types/workflow';
import type { Edge, Node } from '@xyflow/react';
import { MOCK_VARIABLES, MOCK_MESSAGES, getRandomDuration } from './execution/mockData';
import { buildExecutionOrder } from './execution/executionOrder';
import type { ExecutionMode, UseWorkflowExecutionReturn } from './execution/types';

export type { ExecutionMode, UseWorkflowExecutionReturn } from './execution/types';

export function useWorkflowExecution(nodes: Node[], edges: Edge[]): UseWorkflowExecutionReturn {
  const [execution, setExecution] = useState<WorkflowExecution | null>(null);
  const [mode, setMode] = useState<ExecutionMode>('idle');
  const [currentStepIndex, setCurrentStepIndex] = useState(-1);
  const [nodeStatuses, setNodeStatuses] = useState<Map<string, ExecutionStatus>>(new Map());

  const executionOrderRef = useRef<Node[]>([]);
  const stepsRef = useRef<ExecutionStep[]>([]);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const modeRef = useRef<ExecutionMode>('idle');

  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  const buildSteps = useCallback(() => {
    const order = buildExecutionOrder(nodes, edges);
    executionOrderRef.current = order;

    const now = new Date();
    let timeOffset = 0;
    const steps: ExecutionStep[] = order.map((node, i) => {
      const d = node.data as Record<string, unknown>;
      const nodeType = (d.nodeType as string) || 'unknown';
      const duration = getRandomDuration(nodeType);
      timeOffset += duration;
      const ts = new Date(now.getTime() + timeOffset);
      const timestamp = `${ts.getHours().toString().padStart(2, '0')}:${ts.getMinutes().toString().padStart(2, '0')}:${ts.getSeconds().toString().padStart(2, '0')}`;

      const varGen = MOCK_VARIABLES[nodeType];
      return {
        id: `step-${i}`,
        nodeId: node.id,
        nodeLabel: (d.label as string) || 'Unknown',
        nodeType,
        status: 'idle' as ExecutionStatus,
        timestamp,
        duration,
        variables: varGen ? varGen() : { result: 'ok' },
        message: MOCK_MESSAGES[nodeType] || `Executed ${d.label}`,
      };
    });

    stepsRef.current = steps;
    return steps;
  }, [nodes, edges]);

  const executeStep = useCallback((stepIndex: number) => {
    const steps = stepsRef.current;
    if (stepIndex >= steps.length) {
      // Execution complete
      setMode('completed');
      modeRef.current = 'completed';
      setExecution(prev => prev ? { ...prev, status: 'success', completedAt: new Date().toISOString() } : prev);
      return;
    }

    // Mark current step as running
    steps[stepIndex] = { ...steps[stepIndex], status: 'running' };
    setExecution(prev => prev ? { ...prev, steps: [...steps] } : prev);
    setCurrentStepIndex(stepIndex);

    // Update node status
    setNodeStatuses(prev => {
      const next = new Map(prev);
      next.set(steps[stepIndex].nodeId, 'running');
      return next;
    });

    // After simulated duration, mark as success
    const duration = Math.min(steps[stepIndex].duration || 500, 1500);
    const simulatedDelay = Math.max(300, Math.min(duration, 1200));

    timerRef.current = setTimeout(() => {
      steps[stepIndex] = { ...steps[stepIndex], status: 'success' };
      setExecution(prev => prev ? { ...prev, steps: [...steps] } : prev);

      setNodeStatuses(prev => {
        const next = new Map(prev);
        next.set(steps[stepIndex].nodeId, 'success');
        return next;
      });

      const currentMode = modeRef.current;
      if (currentMode === 'running') {
        // Auto-advance
        executeStep(stepIndex + 1);
      } else if (currentMode === 'stepping') {
        // Wait for next stepForward call
        setMode('paused');
        modeRef.current = 'paused';
        setCurrentStepIndex(stepIndex);
        // Check if that was the last step
        if (stepIndex + 1 >= steps.length) {
          setMode('completed');
          modeRef.current = 'completed';
          setExecution(prev => prev ? { ...prev, status: 'success', completedAt: new Date().toISOString() } : prev);
        }
      }
    }, simulatedDelay);
  }, []);

  const initExecution = useCallback(() => {
    clearTimer();
    const steps = buildSteps();

    const ex: WorkflowExecution = {
      id: `exec-${Date.now().toString(36)}`,
      status: 'running',
      startedAt: new Date().toISOString(),
      steps: [...steps],
    };

    setExecution(ex);
    setCurrentStepIndex(-1);

    // Reset all node statuses
    const statuses = new Map<string, ExecutionStatus>();
    nodes.forEach(n => statuses.set(n.id, 'idle'));
    setNodeStatuses(statuses);

    return steps;
  }, [buildSteps, clearTimer, nodes]);

  const runAll = useCallback(() => {
    initExecution();
    setMode('running');
    modeRef.current = 'running';
    // Start from step 0 after a brief delay
    setTimeout(() => executeStep(0), 200);
  }, [initExecution, executeStep]);

  const stepForward = useCallback(() => {
    if (mode === 'idle' || mode === 'completed') {
      // Fresh start in step mode
      initExecution();
      setMode('stepping');
      modeRef.current = 'stepping';
      setTimeout(() => executeStep(0), 200);
    } else if (mode === 'paused') {
      // Continue to next step
      setMode('stepping');
      modeRef.current = 'stepping';
      const nextIndex = currentStepIndex + 1;
      if (nextIndex < stepsRef.current.length) {
        executeStep(nextIndex);
      }
    }
  }, [mode, currentStepIndex, initExecution, executeStep]);

  const pause = useCallback(() => {
    clearTimer();
    setMode('paused');
    modeRef.current = 'paused';
  }, [clearTimer]);

  const resume = useCallback(() => {
    setMode('running');
    modeRef.current = 'running';
    const nextIndex = currentStepIndex + 1;
    if (nextIndex < stepsRef.current.length) {
      executeStep(nextIndex);
    }
  }, [currentStepIndex, executeStep]);

  const reset = useCallback(() => {
    clearTimer();
    setExecution(null);
    setMode('idle');
    modeRef.current = 'idle';
    setCurrentStepIndex(-1);
    setNodeStatuses(new Map());
  }, [clearTimer]);

  return {
    execution,
    mode,
    currentStepIndex,
    runAll,
    stepForward,
    reset,
    pause,
    resume,
    nodeStatuses,
  };
}
