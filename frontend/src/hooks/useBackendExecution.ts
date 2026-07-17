import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { Node } from '@xyflow/react';
import { cancelInstance, triggerManual } from '@/lib/api/client';
import { normalizeToken } from '@/lib/api/utils';
import { createEcho } from '@/lib/echo';
import type { ExecutionStep, ExecutionStatus, WorkflowExecution } from '@/types/workflow';
import type { ExecutionMode, UseWorkflowExecutionReturn } from './execution/types';
import type Echo from 'laravel-echo';

export interface UseBackendExecutionReturn extends UseWorkflowExecutionReturn {
  cancel: () => void;
  instanceId: string | null;
  runtimeContext: Record<string, unknown>;
}

interface ApiConfig {
  baseUrl: string;
  accessToken: string;
}

// Payload shapes matching broadcastWith() on each event class
interface NodeEventPayload {
  instance_id: number;
  node_key: string;
  node_type: string;
  status: string;
  attempt: number;
  input: Record<string, unknown> | null;
  output: Record<string, unknown> | null;
  error: unknown;
  will_retry?: boolean;
  wait_type?: string | null;
  wait_until?: string | null;
  started_at: string | null;
  finished_at: string | null;
}

interface InstanceEventPayload {
  instance_id: number;
  status: string;
  context?: Record<string, unknown>;
  error?: unknown;
  finished_at?: string | null;
}

function mapBackendStatus(status: string): ExecutionStatus {
  switch (status) {
    case 'running':   return 'running';
    case 'pending':   return 'running';
    case 'succeeded': return 'success';
    case 'failed':    return 'failed';
    case 'waiting':   return 'waiting';
    case 'skipped':   return 'idle';
    case 'consumed':  return 'success';
    default:          return 'idle';
  }
}

function buildStep(payload: NodeEventPayload, nodes: Node[]): ExecutionStep {
  const node = nodes.find((n) => n.id === payload.node_key);
  const label = (node?.data?.label as string | undefined) ?? payload.node_key;

  const duration =
    payload.finished_at && payload.started_at
      ? new Date(payload.finished_at).getTime() - new Date(payload.started_at).getTime()
      : undefined;

  const variables: Record<string, unknown> = {};
  if (payload.input && Object.keys(payload.input).length > 0)  variables.input  = payload.input;
  if (payload.output && Object.keys(payload.output).length > 0) variables.output = payload.output;

  const errorPayload = payload.error as Record<string, unknown> | null | undefined;
  const errorMessage =
    typeof errorPayload?.message === 'string' ? errorPayload.message : undefined;

  return {
    id:        `${payload.node_key}-${payload.attempt}`,
    nodeId:    payload.node_key,
    nodeLabel: label,
    nodeType:  payload.node_type,
    status:    mapBackendStatus(payload.status),
    timestamp: payload.started_at ?? new Date().toISOString(),
    duration,
    variables,
    message:   errorMessage ?? (payload.error ? 'Node failed' : undefined),
    error:     payload.error ? JSON.stringify(payload.error) : undefined,
  };
}

export function useBackendExecution(
  workflowId: string | undefined,
  nodes: Node[],
  apiConfig: ApiConfig,
): UseBackendExecutionReturn {
  const [execution, setExecution]           = useState<WorkflowExecution | null>(null);
  const [mode, setMode]                     = useState<ExecutionMode>('idle');
  const [nodeStatuses, setNodeStatuses]     = useState<Map<string, ExecutionStatus>>(new Map());
  const [instanceId, setInstanceId]         = useState<string | null>(null);
  const [runtimeContext, setRuntimeContext] = useState<Record<string, unknown>>({});

  // Echo instance lives for the lifetime of one run
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const echoRef       = useRef<Echo<any> | null>(null);
  const instanceRef   = useRef<string | null>(null);

  const teardownEcho = useCallback(() => {
    if (echoRef.current && instanceRef.current) {
      echoRef.current.leave(`workflow-instance.${instanceRef.current}`);
    }
    echoRef.current?.disconnect();
    echoRef.current = null;
  }, []);

  const reset = useCallback(() => {
    teardownEcho();
    setExecution(null);
    setMode('idle');
    setNodeStatuses(new Map());
    setInstanceId(null);
    setRuntimeContext({});
    instanceRef.current = null;
  }, [teardownEcho]);

  const handleNodeEvent = useCallback(
    (payload: NodeEventPayload) => {
      const fs = mapBackendStatus(payload.status);
      setNodeStatuses((prev) => new Map(prev).set(payload.node_key, fs));

      const step = buildStep(payload, nodes);
      setExecution((prev) => {
        if (!prev) return prev;
        const others = prev.steps.filter(
          (s) => !(s.nodeId === payload.node_key && s.id === step.id),
        );
        const sorted = [...others, step].sort(
          (a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime(),
        );
        return { ...prev, steps: sorted };
      });
    },
    [nodes],
  );

  const handleInstanceTerminal = useCallback(
    (finalStatus: ExecutionStatus, ctx?: Record<string, unknown>) => {
      setMode('completed');
      setExecution((prev) =>
        prev ? { ...prev, status: finalStatus, completedAt: new Date().toISOString() } : prev,
      );
      if (ctx) setRuntimeContext(ctx);
      teardownEcho();
    },
    [teardownEcho],
  );

  const cancel = useCallback(() => {
    const id = instanceRef.current;
    if (!id) return;
    teardownEcho();
    setMode('completed');
    setExecution((prev) =>
      prev ? { ...prev, status: 'failed', completedAt: new Date().toISOString() } : prev,
    );
    cancelInstance(apiConfig.baseUrl, apiConfig.accessToken, id).catch(() => {
      // best-effort
    });
  }, [teardownEcho, apiConfig]);

  const runAll = useCallback(() => {
    if (!workflowId) {
      toast.error('No workflow loaded.');
      return;
    }
    if (!normalizeToken(apiConfig.accessToken)) {
      toast.error('API not connected.');
      return;
    }

    const run = async () => {
      reset();

      // Seed idle statuses for all canvas nodes
      const initStatuses = new Map<string, ExecutionStatus>();
      nodes.forEach((n) => initStatuses.set(n.id, 'idle'));
      setNodeStatuses(initStatuses);
      setMode('running');

      const { instance_id } = await triggerManual(
        apiConfig.baseUrl, apiConfig.accessToken, workflowId,
      );

      const strId = String(instance_id);
      setInstanceId(strId);
      instanceRef.current = strId;

      setExecution({
        id:        strId,
        status:    'running',
        startedAt: new Date().toISOString(),
        steps:     [],
      });

      const echo = createEcho(apiConfig.accessToken, apiConfig.baseUrl);
      echoRef.current = echo;

      const channel = echo.private(`workflow-instance.${strId}`);

      channel
        .listen('.node.started',   (p: NodeEventPayload)     => handleNodeEvent(p))
        .listen('.node.completed', (p: NodeEventPayload)     => handleNodeEvent(p))
        .listen('.node.failed',    (p: NodeEventPayload)     => handleNodeEvent(p))
        .listen('.node.waiting',   (p: NodeEventPayload)     => handleNodeEvent(p))
        .listen('.node.retrying',  (p: NodeEventPayload)     => handleNodeEvent(p))
        .listen('.instance.completed', (p: InstanceEventPayload) =>
          handleInstanceTerminal('success', p.context),
        )
        .listen('.instance.failed', (p: InstanceEventPayload) =>
          handleInstanceTerminal('failed', p.context),
        )
        .listen('.instance.cancelled', () =>
          handleInstanceTerminal('failed'),
        );
    };

    run().catch((err: unknown) => {
      const message =
        err instanceof Error ? err.message : 'Failed to run workflow in backend.';
      toast.error(message);
      setMode('completed');
      setExecution((prev) =>
        prev ? { ...prev, status: 'failed', completedAt: new Date().toISOString() } : prev,
      );
      teardownEcho();
    });
  }, [workflowId, nodes, apiConfig, reset, handleNodeEvent, handleInstanceTerminal, teardownEcho]);

  // Cleanup on unmount
  useEffect(() => {
    return () => { teardownEcho(); };
  }, [teardownEcho]);

  return {
    execution,
    mode,
    currentStepIndex: -1,
    runAll,
    stepForward: () => {},
    pause:       () => {},
    resume:      () => {},
    reset,
    nodeStatuses,
    cancel,
    instanceId,
    runtimeContext,
  };
}
