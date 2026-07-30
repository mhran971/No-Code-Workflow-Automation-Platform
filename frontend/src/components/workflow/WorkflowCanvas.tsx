import { forwardRef, useCallback, useImperativeHandle, useRef, useState, useEffect } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  addEdge,
  useNodesState,
  useEdgesState,
  type Connection,
  type Edge,
  type Node,
  type ReactFlowInstance,
  BackgroundVariant,
  ConnectionLineType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { WorkflowNode } from './WorkflowNode';
import { IfNode } from './IfNode';
import { SwitchNode } from './SwitchNode';
import { ForkNode } from './ForkNode';
import { MergeNode } from './MergeNode';
import type { ExecutionStatus } from '@/types/workflow';
import type { SelectedNodeInfo } from '@/pages/Index';
import type { ApiNodeDefinition, WorkflowDefinition } from '@/lib/api/types';
import { useCanvasNodeActions } from '@/hooks/useCanvasNodeActions';
import { useCanvasDragDrop } from '@/hooks/useCanvasDragDrop';
import { mapApiNodeToUiNode } from '@/lib/api/utils';

const nodeTypes = {
  workflowNode: WorkflowNode,
  ifNode: IfNode,
  switchNode: SwitchNode,
  forkNode: ForkNode,
  mergeNode: MergeNode,
};

const defaultNodes: Node[] = [
  // ─── Employee Onboarding Workflow ──────────────────────────────────────
];

const defaultEdges: Edge[] = [
];

interface WorkflowCanvasProps {
  onNodeSelect?: (node: SelectedNodeInfo | null) => void;
  nodeStatuses?: Map<string, ExecutionStatus>;
  onNodesEdgesChange?: (nodes: Node[], edges: Edge[]) => void;
  nodeDefinitions?: ApiNodeDefinition[];
  readOnly?: boolean;
  highlightNodeId?: string;
  onWaitingDynamicFlowClick?: (nodeId: string) => void;
}

function restoreSourceHandle(branchType: string | undefined, sourceNodeType?: string): string | undefined {
  const bt = branchType ?? 'default';
  switch (sourceNodeType) {
    case 'if-node':
      // 'yes' handle = true branch,  'no' handle = false/else branch
      // Legacy: 'branch_1' was the true branch before named handles
      if (bt === 'true' || bt === 'branch_1') return 'yes';
      return 'no'; // 'else', 'default', or any unrecognised value
    case 'switch':
      // Named handles: 'default' or 'option-{value}'
      return bt === 'default' ? 'default' : `option-${bt}`;
    case 'and-node':
      // Named handles: 'branch-{key}'  (default = no specific handle)
      return bt === 'default' ? undefined : `branch-${bt}`;
    default:
      // Single-output nodes have one unnamed handle; no sourceHandle needed
      return undefined;
  }
}

function rfNodeType(type: string): string {
  if (type === 'if-node') return 'ifNode';
  if (type === 'switch') return 'switchNode';
  if (type === 'and-node') return 'forkNode';
  if (type === 'merge') return 'mergeNode';
  return 'workflowNode';
}

export interface WorkflowCanvasHandle {
  deleteNode: (nodeId: string) => void;
  updateNodeConfig: (nodeId: string, config: Record<string, unknown>) => void;
  updateNodeData: (nodeId: string, updates: Record<string, unknown>) => void;
  setNodeErrors: (errorNodeIds: Set<string>) => void;
  getSnapshot: () => { nodes: Node[]; edges: Edge[] };
  loadDefinition: (definition: WorkflowDefinition, apiNodeDefs: ApiNodeDefinition[]) => void;
}

export const WorkflowCanvas = forwardRef<WorkflowCanvasHandle, WorkflowCanvasProps>(function WorkflowCanvas(
  { onNodeSelect, nodeStatuses, onNodesEdgesChange, nodeDefinitions = [], readOnly = false, highlightNodeId, onWaitingDynamicFlowClick },
  ref,
) {
  const reactFlowWrapper = useRef<HTMLDivElement>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState(defaultNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(defaultEdges);
  const [reactFlowInstance, setReactFlowInstance] = useState<ReactFlowInstance | null>(null);

  // Sync nodes/edges up to parent
  useEffect(() => {
    onNodesEdgesChange?.(nodes, edges);
  }, [nodes, edges, onNodesEdgesChange]);

  // Apply execution statuses to nodes
  useEffect(() => {
    if (!nodeStatuses || nodeStatuses.size === 0) {
      // Reset all to idle if no statuses
      setNodes(nds => nds.map(n => {
        const d = n.data as Record<string, unknown>;
        if (d.executionStatus !== 'idle') {
          return { ...n, data: { ...d, executionStatus: 'idle' } };
        }
        return n;
      }));
      return;
    }

    setNodes(nds => nds.map(n => {
      const status = nodeStatuses.get(n.id);
      const d = n.data as Record<string, unknown>;
      if (status && d.executionStatus !== status) {
        return { ...n, data: { ...d, executionStatus: status } };
      }
      return n;
    }));
  }, [nodeStatuses, setNodes]);

  const { deleteNode, updateNodeConfig, updateNodeData, setNodeErrors } = useCanvasNodeActions(setNodes, setEdges);

  const getSnapshot = useCallback(() => ({ nodes, edges }), [nodes, edges]);

  const loadDefinition = useCallback((definition: WorkflowDefinition, apiNodeDefs: ApiNodeDefinition[]) => {
    const rfNodes: Node[] = definition.nodes.map((node, index) => {
      const apiDef = apiNodeDefs.find((d) => d.type === node.type);
      const ui = apiDef ? mapApiNodeToUiNode(apiDef) : null;
      return {
        id: node.id,
        type: rfNodeType(node.type),
        position: node.position ?? { x: index * 240, y: 80 },
        data: {
          nodeType: node.type,
          label: node.label ?? ui?.label ?? node.type,
          icon: ui?.icon ?? 'Zap',
          color: ui?.color ?? 'rose',
          description: ui?.description ?? '',
          inputs: ui?.inputs ?? 1,
          outputs: ui?.outputs ?? 1,
          config: node.config ?? {},
          name: node.name,
          executionStatus: 'idle',
        },
      };
    });

    const rfEdges: Edge[] = definition.edges.map((edge) => {
      const sourceNode = definition.nodes.find(n => n.id === edge.source_node_key);
      const sourceType = sourceNode?.type;
      return {
        id: edge.id,
        source: edge.source_node_key,
        target: edge.target_node_key,
        sourceHandle: restoreSourceHandle(edge.branch_type, sourceType),
        animated: true,
        style: { stroke: 'hsl(217 91% 60%)' },
      };
    });

    setNodes(rfNodes);
    setEdges(rfEdges);
  }, [setNodes, setEdges]);

  useImperativeHandle(
    ref,
    () => ({ deleteNode, updateNodeConfig, updateNodeData, setNodeErrors, getSnapshot, loadDefinition }),
    [deleteNode, updateNodeConfig, updateNodeData, setNodeErrors, getSnapshot, loadDefinition],
  );

  const onNodesDelete = useCallback(
    () => {
      onNodeSelect?.(null);
    },
    [onNodeSelect],
  );

  const onConnect = useCallback(
    (connection: Connection) => {
      if (readOnly) return;
      setEdges(eds => addEdge({
        ...connection,
        animated: true,
        style: { stroke: 'hsl(217 91% 60%)' },
      }, eds));
    },
    [setEdges, readOnly]
  );

  const onNodeClick = useCallback(
    (_: React.MouseEvent, node: Node) => {
      const d = node.data as Record<string, unknown>;
      const nodeType = d.nodeType as string;
      const execStatus = d.executionStatus as string | undefined;

      // If it's a dynamic-flow node in waiting state, fire the dedicated callback
      if (nodeType === 'dynamic-flow' && execStatus === 'waiting' && onWaitingDynamicFlowClick) {
        onWaitingDynamicFlowClick(node.id);
        return;
      }

      onNodeSelect?.({
        id: node.id,
        label: d.label as string,
        icon: d.icon as string,
        color: d.color as string,
        nodeType: d.nodeType as string,
        description: d.description as string,
        config: (d.config as Record<string, unknown>) ?? {},
        name: d.name as string | undefined,
      });
    },
    [onNodeSelect, onWaitingDynamicFlowClick]
  );

  const onPaneClick = useCallback(() => {
    onNodeSelect?.(null);
  }, [onNodeSelect]);

  const { onDragOver, onDrop } = useCanvasDragDrop({ reactFlowInstance, nodeDefinitions, setNodes });

  // Apply highlight styling to nodes
  const styledNodes = nodes.map(n => ({
    ...n,
    className: n.id === highlightNodeId
      ? 'ring-2 ring-primary ring-offset-2 ring-offset-background'
      : undefined,
  }));

  return (
    <div ref={reactFlowWrapper} className="flex-1 h-full workflow-canvas">
      <ReactFlow
        nodes={styledNodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onInit={setReactFlowInstance}
        onDrop={readOnly ? undefined : onDrop}
        onDragOver={readOnly ? undefined : onDragOver}
        onNodeClick={onNodeClick}
        onPaneClick={onPaneClick}
        onNodesDelete={onNodesDelete}
        nodeTypes={nodeTypes}
        nodesDraggable={!readOnly}
        nodesConnectable={!readOnly}
        elementsSelectable={!readOnly}
        deleteKeyCode={readOnly ? null : ['Backspace', 'Delete']}
        connectionLineType={ConnectionLineType.SmoothStep}
        fitView
        fitViewOptions={{ padding: 0.3 }}
        snapToGrid
        snapGrid={[24, 24]}
        defaultEdgeOptions={{
          type: 'smoothstep',
          animated: false,
        }}
      >
        <Background
          variant={BackgroundVariant.Dots}
          gap={24}
          size={1}
          color="hsl(220 13% 80% / 0.6)"
        />
        <Controls showInteractive={false} />
        <MiniMap
          nodeColor={() => 'hsl(217 91% 55%)'}
          maskColor="hsl(220 20% 92% / 0.7)"
          style={{ border: 'none' }}
        />
      </ReactFlow>
    </div>
  );
});
