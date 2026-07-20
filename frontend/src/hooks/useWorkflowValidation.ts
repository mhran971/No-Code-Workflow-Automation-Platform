import { useCallback, useState, type RefObject } from 'react';
import type { Node, Edge } from '@xyflow/react';
import type { WorkflowCanvasHandle } from '@/components/workflow/WorkflowCanvas';
import { ApiError, validateWorkflowDefinition } from '@/lib/api/client';
import { buildDefinitionFromCanvas, formatApiError, isTriggerNode } from '@/lib/api/utils';
import type {
  ApiNodeDefinition, ValidationIssue, ValidationResult, WorkflowDefinition,
} from '@/lib/api/types';

interface UseWorkflowValidationArgs {
  canvasRef: RefObject<WorkflowCanvasHandle>;
  canvasNodes: Node[];
  canvasEdges: Edge[];
  nodeDefinitions: ApiNodeDefinition[];
  apiBaseUrl: string;
  accessToken: string;
  /** Extra context variable keys (from a parent instance) to inject into the
   *  trigger config so the verifier knows they will be available at runtime. */
  extraTriggerVariables?: string[];
}

// Owns the "Verify" flow: builds a workflow definition from the canvas, calls the
// backend validator, and maps the returned issues back onto the canvas nodes.
export function useWorkflowValidation({
  canvasRef, canvasNodes, canvasEdges, nodeDefinitions, apiBaseUrl, accessToken,
  extraTriggerVariables,
}: UseWorkflowValidationArgs) {
  const [validationOpen, setValidationOpen] = useState(false);
  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [validationLoading, setValidationLoading] = useState(false);
  const [lastDefinition, setLastDefinition] = useState<WorkflowDefinition | null>(null);
  const [nodeValidationIssues, setNodeValidationIssues] = useState<Map<string, ValidationIssue[]>>(new Map());

  const applyValidationResult = useCallback((result: ValidationResult) => {
    setValidationResult(result);

    const issuesByNode = new Map<string, ValidationIssue[]>();
    const errorNodeIds = new Set<string>();

    // Trigger-level issues carry a `trigger.config.*` path but no node_id, so
    // attribute them to the trigger node on the canvas to surface them in its panel.
    const snapshot = canvasRef.current?.getSnapshot();
    const triggerNodeId = (snapshot?.nodes ?? canvasNodes)
      .find(node => isTriggerNode(node, nodeDefinitions))?.id ?? null;

    for (const issue of result.issues) {
      const path = issue.location?.path ?? issue.path ?? null;
      const isTriggerIssue = typeof path === 'string' && path.startsWith('trigger.');
      const nodeId = issue.location?.node_id ?? issue.node_id ?? (isTriggerIssue ? triggerNodeId : null);
      if (nodeId) {
        const existing = issuesByNode.get(nodeId) ?? [];
        issuesByNode.set(nodeId, [...existing, issue]);
        if (issue.severity === 'error') {
          errorNodeIds.add(nodeId);
        }
      }
    }

    setNodeValidationIssues(issuesByNode);
    canvasRef.current?.setNodeErrors(errorNodeIds);
  }, [canvasRef, canvasNodes, nodeDefinitions]);

  const handleVerify = useCallback(async () => {
    setValidationOpen(true);
    setValidationLoading(true);
    setValidationError(null);
    setValidationResult(null);
    setNodeValidationIssues(new Map());
    canvasRef.current?.setNodeErrors(new Set());

    const snapshot = canvasRef.current?.getSnapshot() ?? { nodes: canvasNodes, edges: canvasEdges };
    const definition = buildDefinitionFromCanvas(snapshot.nodes, snapshot.edges, nodeDefinitions);

    // Inject parent context variables so the verifier knows they are available
    if (extraTriggerVariables && extraTriggerVariables.length > 0) {
      definition.trigger.config.variables = extraTriggerVariables.map((key) => ({ key }));
    }

    setLastDefinition(definition);

    try {
      const result = await validateWorkflowDefinition(apiBaseUrl, accessToken, definition);
      applyValidationResult(result);
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.body && typeof error.body === 'object' && 'is_publishable' in (error.body as object)) {
          applyValidationResult(error.body as ValidationResult);
        } else if (error.body && typeof error.body === 'object' && 'message' in (error.body as object)) {
          setValidationError(String((error.body as { message: string }).message));
        } else {
          setValidationError(`${error.message} (${error.status})`);
        }
      } else {
        setValidationError(formatApiError(error));
      }
    } finally {
      setValidationLoading(false);
    }
  }, [apiBaseUrl, accessToken, canvasNodes, canvasEdges, nodeDefinitions, extraTriggerVariables, applyValidationResult, canvasRef]);

  return {
    validationOpen,
    setValidationOpen,
    validationResult,
    validationError,
    validationLoading,
    lastDefinition,
    nodeValidationIssues,
    handleVerify,
  };
}
