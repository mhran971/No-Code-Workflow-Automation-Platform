import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { Node } from '@xyflow/react';
import { cancelInstance, fetchInstance, triggerManual } from '@/lib/api/client';
import type { InstanceDetail, NodeExecutionSummary } from '@/lib/api/types';
import { normalizeToken } from '@/lib/api/utils';
import { createEcho } from '@/lib/echo';
import type { ExecutionStep, ExecutionStatus, WorkflowExecution } from '@/types/workflow';
import type { ExecutionMode, UseWorkflowExecutionReturn } from './execution/types';
import type Echo from 'laravel-echo';

export interface UseBackendExecutionReturn extends UseWorkflowExecutionReturn {
  cancel: () => void;
  instanceId: string | null;
  runtimeContext: Record<string, unknown>;
  loadInstance: (instanceId: string) => Promise<void>;
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

interface ChildNodeEventPayload extends NodeEventPayload {
  child_instance_id: number;
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

        // Find the existing step being replaced — preserve its childInstanceId if any
        const existing = prev.steps.find(
          (s) => s.nodeId === payload.node_key && s.id === step.id,
        );
        if (existing?.childInstanceId) {
          step.childInstanceId = existing.childInstanceId;
        }

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

  const handleChildNodeEvent = useCallback(
    (payload: ChildNodeEventPayload) => {
      console.log('[DynamicFlow] Child event received:', payload.child_instance_id, payload.node_key, payload.status);
      const childKey = `child:${payload.child_instance_id}:${payload.node_key}`;
      const fs = mapBackendStatus(payload.status);
      setNodeStatuses((prev) => new Map(prev).set(childKey, fs));

      // Instance-level events (child.instance.started/failed/completed) don't have node_key
      if (!payload.node_key) return;

      const step = buildStep(payload, nodes);
      step.childInstanceId = payload.child_instance_id;
      step.id = `child-${payload.child_instance_id}-${payload.node_key}-${payload.attempt}`;

      setExecution((prev) => {
        if (!prev) return prev;

        // Find the dynamic-flow parent step and attach childInstanceId
        const updatedSteps = prev.steps.map((s) => {
          if (s.nodeType === 'dynamic-flow' && s.status === 'waiting' && !s.childInstanceId) {
            return { ...s, childInstanceId: payload.child_instance_id };
          }
          return s;
        });

        const others = updatedSteps.filter(
          (s) => !(s.childInstanceId === payload.child_instance_id && s.nodeId === payload.node_key && s.id === step.id),
        );
        const sorted = [...others, step].sort(
          (a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime(),
        );
        return { ...prev, steps: sorted };
      });
    },
    [nodes],
  );

  const handleChildInstanceTerminal = useCallback(
    (childInstanceId: number, finalStatus: ExecutionStatus) => {
      console.log('[DynamicFlow] Child instance terminal:', childInstanceId, finalStatus);
      setExecution((prev) => {
        if (!prev) return prev;

        // Update the parent dynamic-flow step to reflect the child's terminal status
        const updatedSteps = prev.steps.map((s) => {
          if (s.nodeType === 'dynamic-flow' && s.childInstanceId === childInstanceId) {
            return { ...s, status: finalStatus };
          }
          return s;
        });

        return { ...prev, steps: updatedSteps };
      });
    },
    [],
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

  const buildStepFromApi = useCallback(
    (ne: NodeExecutionSummary, childInstanceId?: number): ExecutionStep => {
      const node = nodes.find((n) => n.id === ne.node_key);
      const label = (node?.data?.label as string | undefined) ?? ne.node_key;

      const duration =
        ne.finished_at && ne.started_at
          ? new Date(ne.finished_at).getTime() - new Date(ne.started_at).getTime()
          : undefined;

      const variables: Record<string, unknown> = {};
      if (ne.input && Object.keys(ne.input).length > 0)  variables.input  = ne.input;
      if (ne.output && Object.keys(ne.output).length > 0) variables.output = ne.output;

      const errorMessage =
        typeof ne.error?.message === 'string' ? ne.error.message : undefined;

      const step: ExecutionStep = {
        id:      childInstanceId
          ? `child-${childInstanceId}-${ne.node_key}-${ne.attempt}`
          : `${ne.node_key}-${ne.attempt}`,
        nodeId:    ne.node_key,
        nodeLabel: label,
        nodeType:  ne.node_type,
        status:    mapBackendStatus(ne.status),
        timestamp: ne.started_at ?? new Date().toISOString(),
        duration,
        variables,
        message:   errorMessage ?? (ne.error ? 'Node failed' : undefined),
        error:     ne.error ? JSON.stringify(ne.error) : undefined,
      };

      if (childInstanceId !== undefined) {
        step.childInstanceId = childInstanceId;
      }

      return step;
    },
    [nodes],
  );

  const loadInstance = useCallback(
    async (targetInstanceId: string) => {
      if (!normalizeToken(apiConfig.accessToken)) {
        toast.error('API not connected.');
        return;
      }

      try {
        const instanceData: InstanceDetail = await fetchInstance(
          apiConfig.baseUrl, apiConfig.accessToken, targetInstanceId,
        );

        setInstanceId(targetInstanceId);
        instanceRef.current = targetInstanceId;

        const allSteps: ExecutionStep[] = [];

        // Build parent node execution steps
        for (const ne of instanceData.node_executions) {
          // Skip consumed merge-coordination rows
          if (ne.status === 'consumed') continue;
          allSteps.push(buildStepFromApi(ne));
        }

        // Build child instance steps from dynamic flows
        const dynamicFlows = instanceData.dynamic_flows ?? [];
        for (const df of dynamicFlows) {
          // Attach childInstanceId to the parent dynamic-flow step
          if (df.child_instance_id) {
            const parentStep = allSteps.find(
              (s) => s.nodeId === df.node_key && s.nodeType === 'dynamic-flow',
            );
            if (parentStep) {
              parentStep.childInstanceId = df.child_instance_id;
            }
          }

          // Build child steps
          if (df.child_instance?.node_executions) {
            for (const ne of df.child_instance.node_executions) {
              if (ne.status === 'consumed') continue;
              allSteps.push(buildStepFromApi(ne, df.child_instance_id ?? undefined));
            }
          }
        }

        allSteps.sort(
          (a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime(),
        );

        const mapInstanceStatus = (s: string): ExecutionStatus => {
          switch (s) {
            case 'running':   return 'running';
            case 'completed': return 'success';
            case 'failed':    return 'failed';
            case 'paused':    return 'waiting';
            case 'waiting':   return 'waiting';
            default:          return 'idle';
          }
        };

        const execStatus = mapInstanceStatus(instanceData.status);

        setExecution({
          id:          targetInstanceId,
          status:      execStatus,
          startedAt:   instanceData.started_at ?? new Date().toISOString(),
          completedAt: instanceData.finished_at ?? undefined,
          steps:       allSteps,
        });

        if (execStatus === 'running') {
          setMode('running');
        } else {
          setMode('completed');
        }

        if (instanceData.context) {
          setRuntimeContext(instanceData.context);
        }

        // Build node statuses
        const statuses = new Map<string, ExecutionStatus>();
        nodes.forEach((n) => statuses.set(n.id, 'idle'));
        for (const step of allSteps) {
          statuses.set(step.nodeId, step.status);
        }
        setNodeStatuses(statuses);

        // If instance is still running, connect to WebSocket for live updates
        if (execStatus === 'running' || execStatus === 'waiting') {
          const echo = createEcho(apiConfig.accessToken, apiConfig.baseUrl);
          echoRef.current = echo;

          const channel = echo.private(`workflow-instance.${targetInstanceId}`);

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
            )
            .listen('.child.node.started',   (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
            .listen('.child.node.completed', (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
            .listen('.child.node.failed',    (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
            .listen('.child.node.waiting',   (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
            .listen('.child.node.retrying',  (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
            .listen('.child.instance.started',   () => {})
            .listen('.child.instance.failed',    (p: InstanceEventPayload & { child_instance_id: number }) =>
              handleChildInstanceTerminal(p.child_instance_id, 'failed'),
            )
            .listen('.child.instance.completed', (p: InstanceEventPayload & { child_instance_id: number }) =>
              handleChildInstanceTerminal(p.child_instance_id, 'success'),
            );
        }
      } catch (err: unknown) {
        const message = err instanceof Error ? err.message : 'Failed to load instance.';
        toast.error(message);
      }
    },
    [apiConfig, nodes, buildStepFromApi, handleNodeEvent, handleChildNodeEvent, handleChildInstanceTerminal, handleInstanceTerminal],
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
        )
        // Child instance events (forwarded from child workflow)
        .listen('.child.node.started',   (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
        .listen('.child.node.completed', (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
        .listen('.child.node.failed',    (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
        .listen('.child.node.waiting',   (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
        .listen('.child.node.retrying',  (p: ChildNodeEventPayload) => handleChildNodeEvent(p))
        // Child instance-level events — update the parent dynamic-flow step status
        .listen('.child.instance.started',   () => {})
        .listen('.child.instance.failed',    (p: InstanceEventPayload & { child_instance_id: number }) =>
          handleChildInstanceTerminal(p.child_instance_id, 'failed'),
        )
        .listen('.child.instance.completed', (p: InstanceEventPayload & { child_instance_id: number }) =>
          handleChildInstanceTerminal(p.child_instance_id, 'success'),
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
  }, [workflowId, nodes, apiConfig, reset, handleNodeEvent, handleChildNodeEvent, handleChildInstanceTerminal, handleInstanceTerminal, teardownEcho]);

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
    loadInstance,
  };
}
