import { useCallback } from 'react';
import type { Node, ReactFlowInstance } from '@xyflow/react';
import type { NodeTypeDefinition } from '@/types/workflow';
import type { ApiNodeDefinition } from '@/lib/api/types';
import { defaultConfigForNode } from '@/lib/api/utils';

type SetNodes = React.Dispatch<React.SetStateAction<Node[]>>;

// Maps a node type key to the ReactFlow node component used to render it.
// Add a case here when a node type needs a custom canvas component.
function nodeComponentType(type: string): string {
  if (type === 'switch') return 'switchNode';
  if (type === 'and-node') return 'forkNode';
  if (type === 'merge') return 'mergeNode';
  return 'workflowNode';
}

interface UseCanvasDragDropArgs {
  reactFlowInstance: ReactFlowInstance | null;
  nodeDefinitions: ApiNodeDefinition[];
  setNodes: SetNodes;
}

// Handles dragging a node type from the library and dropping it onto the canvas,
// creating a positioned node seeded with its default config.
export function useCanvasDragDrop({ reactFlowInstance, nodeDefinitions, setNodes }: UseCanvasDragDropArgs) {
  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }, []);

  const onDrop = useCallback(
    (event: React.DragEvent) => {
      event.preventDefault();

      const dataStr = event.dataTransfer.getData('application/reactflow');
      if (!dataStr || !reactFlowInstance) return;

      const nodeType: NodeTypeDefinition = JSON.parse(dataStr);
      const position = reactFlowInstance.screenToFlowPosition({
        x: event.clientX,
        y: event.clientY,
      });

      const apiDefinition = nodeDefinitions.find((definition) => definition.type === nodeType.type);
      const config = apiDefinition ? defaultConfigForNode(apiDefinition) : {};

      const newNode: Node = {
        id: `${nodeType.type}-${Date.now()}`,
        type: nodeComponentType(nodeType.type),
        position,
        data: {
          label: nodeType.label,
          icon: nodeType.icon,
          color: nodeType.color,
          nodeType: nodeType.type,
          description: nodeType.description,
          inputs: nodeType.inputs,
          outputs: nodeType.outputs,
          config,
          executionStatus: 'idle',
        },
      };

      setNodes(nds => [...nds, newNode]);
    },
    [reactFlowInstance, setNodes, nodeDefinitions],
  );

  return { onDragOver, onDrop };
}
