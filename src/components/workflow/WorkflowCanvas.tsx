import { useCallback, useRef, useState } from 'react';
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
  BackgroundVariant,
  ConnectionLineType,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { WorkflowNode } from './WorkflowNode';
import type { NodeTypeDefinition } from '@/types/workflow';

const nodeTypes = {
  workflowNode: WorkflowNode,
};

const defaultNodes: Node[] = [
  {
    id: 'trigger-1',
    type: 'workflowNode',
    position: { x: 100, y: 200 },
    data: {
      label: 'Webhook Received',
      icon: 'Webhook',
      color: 'amber',
      nodeType: 'webhook-trigger',
      description: 'POST /api/leads/incoming',
      inputs: 0,
      outputs: 1,
      executionStatus: 'success',
    },
  },
  {
    id: 'classifier-1',
    type: 'workflowNode',
    position: { x: 380, y: 150 },
    data: {
      label: 'AI Classifier',
      icon: 'Tags',
      color: 'violet',
      nodeType: 'ai-classifier',
      description: 'Classify lead priority',
      inputs: 1,
      outputs: 1,
      executionStatus: 'success',
    },
  },
  {
    id: 'if-1',
    type: 'workflowNode',
    position: { x: 650, y: 150 },
    data: {
      label: 'If: High Priority',
      icon: 'GitBranch',
      color: 'indigo',
      nodeType: 'if-node',
      description: 'priority === "high"',
      inputs: 1,
      outputs: 2,
      executionStatus: 'success',
    },
  },
  {
    id: 'hubspot-1',
    type: 'workflowNode',
    position: { x: 920, y: 80 },
    data: {
      label: 'HubSpot: Create Contact',
      icon: 'UserPlus',
      color: 'blue',
      nodeType: 'hubspot-contact',
      description: 'Create contact in CRM',
      inputs: 1,
      outputs: 1,
      executionStatus: 'success',
    },
  },
  {
    id: 'email-1',
    type: 'workflowNode',
    position: { x: 1190, y: 80 },
    data: {
      label: 'Send Email',
      icon: 'Send',
      color: 'rose',
      nodeType: 'send-email',
      description: 'Notify sales team',
      inputs: 1,
      outputs: 1,
      executionStatus: 'success',
    },
  },
  {
    id: 'task-1',
    type: 'workflowNode',
    position: { x: 920, y: 280 },
    data: {
      label: 'Human Review',
      icon: 'UserCheck',
      color: 'rose',
      nodeType: 'task-node',
      description: 'Manual qualification review',
      inputs: 1,
      outputs: 2,
      executionStatus: 'idle',
    },
  },
];

const defaultEdges: Edge[] = [
  { id: 'e1-2', source: 'trigger-1', target: 'classifier-1', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(270 60% 60%)' } },
  { id: 'e2-3', source: 'classifier-1', target: 'if-1', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(240 60% 60%)' } },
  { id: 'e3-4', source: 'if-1', target: 'hubspot-1', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(217 91% 60%)' } },
  { id: 'e4-5', source: 'hubspot-1', target: 'email-1', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(350 60% 60%)' } },
  { id: 'e3-6', source: 'if-1', target: 'task-1', sourceHandle: 'output-1', targetHandle: 'input-0', style: { stroke: 'hsl(215 20% 35%)', strokeDasharray: '5 5' } },
];

export function WorkflowCanvas() {
  const reactFlowWrapper = useRef<HTMLDivElement>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState(defaultNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(defaultEdges);
  const [reactFlowInstance, setReactFlowInstance] = useState<any>(null);

  const onConnect = useCallback(
    (connection: Connection) => {
      setEdges(eds => addEdge({
        ...connection,
        animated: true,
        style: { stroke: 'hsl(217 91% 60%)' },
      }, eds));
    },
    [setEdges]
  );

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

      const newNode: Node = {
        id: `${nodeType.type}-${Date.now()}`,
        type: 'workflowNode',
        position,
        data: {
          label: nodeType.label,
          icon: nodeType.icon,
          color: nodeType.color,
          nodeType: nodeType.type,
          description: nodeType.description,
          inputs: nodeType.inputs,
          outputs: nodeType.outputs,
          executionStatus: 'idle',
        },
      };

      setNodes(nds => [...nds, newNode]);
    },
    [reactFlowInstance, setNodes]
  );

  return (
    <div ref={reactFlowWrapper} className="flex-1 h-full workflow-canvas">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onInit={setReactFlowInstance}
        onDrop={onDrop}
        onDragOver={onDragOver}
        nodeTypes={nodeTypes}
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
          color="hsl(217 33% 17% / 0.4)"
        />
        <Controls showInteractive={false} />
        <MiniMap
          nodeColor={() => 'hsl(217 91% 60%)'}
          maskColor="hsl(222 47% 4% / 0.8)"
          style={{ border: 'none' }}
        />
      </ReactFlow>
    </div>
  );
}
