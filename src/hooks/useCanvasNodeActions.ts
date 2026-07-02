import { useCallback } from 'react';
import type { Edge, Node } from '@xyflow/react';

type SetNodes = React.Dispatch<React.SetStateAction<Node[]>>;
type SetEdges = React.Dispatch<React.SetStateAction<Edge[]>>;

// Imperative node/edge mutations exposed by WorkflowCanvas via its ref.
export function useCanvasNodeActions(setNodes: SetNodes, setEdges: SetEdges) {
  const deleteNode = useCallback(
    (nodeId: string) => {
      setNodes((nds) => nds.filter((node) => node.id !== nodeId));
      setEdges((eds) => eds.filter((edge) => edge.source !== nodeId && edge.target !== nodeId));
    },
    [setNodes, setEdges],
  );

  const updateNodeConfig = useCallback(
    (nodeId: string, config: Record<string, unknown>) => {
      setNodes((nds) =>
        nds.map((node) =>
          node.id === nodeId
            ? { ...node, data: { ...node.data, config } }
            : node,
        ),
      );
    },
    [setNodes],
  );

  const updateNodeData = useCallback(
    (nodeId: string, updates: Record<string, unknown>) => {
      setNodes((nds) =>
        nds.map((node) =>
          node.id === nodeId
            ? { ...node, data: { ...node.data, ...updates } }
            : node,
        ),
      );

      // Remove edges whose source handle no longer exists when switch options change
      const config = updates.config as Record<string, unknown> | undefined;
      if (config && Array.isArray(config.options)) {
        const validHandleIds = new Set<string>([
          'default',
          ...(config.options as unknown[])
            .filter((o): o is string => typeof o === 'string' && o.trim() !== '')
            .map((o) => `option-${o}`),
        ]);
        setEdges((eds) =>
          eds.filter(
            (edge) =>
              edge.source !== nodeId ||
              edge.sourceHandle == null ||
              validHandleIds.has(edge.sourceHandle),
          ),
        );
      }
    },
    [setNodes, setEdges],
  );

  const setNodeErrors = useCallback(
    (errorNodeIds: Set<string>) => {
      setNodes((nds) =>
        nds.map((node) => ({
          ...node,
          data: { ...node.data, hasErrors: errorNodeIds.has(node.id) },
        })),
      );
    },
    [setNodes],
  );

  return { deleteNode, updateNodeConfig, updateNodeData, setNodeErrors };
}
