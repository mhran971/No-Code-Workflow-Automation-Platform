import { useCallback, useRef, useState, useEffect } from 'react';
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
import type { NodeTypeDefinition, ExecutionStatus } from '@/types/workflow';
import type { SelectedNodeInfo } from '@/pages/Index';

const nodeTypes = {
  workflowNode: WorkflowNode,
};

const defaultNodes: Node[] = [
  // ─── Employee Onboarding Workflow ──────────────────────────────────────
  { id: 'onb-form', type: 'workflowNode', position: { x: 80, y: 240 }, data: { label: 'New Hire Form Submitted', icon: 'ClipboardList', color: 'amber', nodeType: 'form-trigger', description: 'HR submits new employee form', inputs: 0, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-extract', type: 'workflowNode', position: { x: 360, y: 240 }, data: { label: 'Extract Employee Data', icon: 'FileSearch', color: 'violet', nodeType: 'ai-extractor', description: 'Parse name, role, dept, start date', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-vars', type: 'workflowNode', position: { x: 640, y: 240 }, data: { label: 'Set Onboarding Vars', icon: 'Variable', color: 'cyan', nodeType: 'set-variables', description: 'employee_id, team, manager_email', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-if', type: 'workflowNode', position: { x: 920, y: 240 }, data: { label: 'If: Full-Time?', icon: 'GitBranch', color: 'indigo', nodeType: 'if-node', description: 'employment_type === "full_time"', inputs: 1, outputs: 2, executionStatus: 'idle' } },
  { id: 'onb-split', type: 'workflowNode', position: { x: 1200, y: 160 }, data: { label: 'Provision in Parallel', icon: 'Split', color: 'indigo', nodeType: 'parallel-split', description: 'IT + CRM + Tasks', inputs: 1, outputs: 3, executionStatus: 'idle' } },
  { id: 'onb-http', type: 'workflowNode', position: { x: 1480, y: 40 }, data: { label: 'Create IT Accounts', icon: 'Globe', color: 'emerald', nodeType: 'http-request', description: 'POST /api/iam/provision', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-hubspot', type: 'workflowNode', position: { x: 1480, y: 180 }, data: { label: 'Add to HRIS', icon: 'UserPlus', color: 'blue', nodeType: 'hubspot-contact', description: 'Register employee record', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-clickup', type: 'workflowNode', position: { x: 1480, y: 320 }, data: { label: 'Onboarding Checklist', icon: 'CheckSquare', color: 'rose', nodeType: 'clickup-task', description: 'Create 30/60/90 day tasks', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-join', type: 'workflowNode', position: { x: 1780, y: 180 }, data: { label: 'Wait for Provisioning', icon: 'Merge', color: 'indigo', nodeType: 'parallel-join', description: 'All branches must complete', inputs: 3, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-gen', type: 'workflowNode', position: { x: 2060, y: 180 }, data: { label: 'Draft Welcome Email', icon: 'Sparkles', color: 'violet', nodeType: 'ai-generator', description: 'Personalized welcome content', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-email', type: 'workflowNode', position: { x: 2340, y: 180 }, data: { label: 'Send Welcome Email', icon: 'Send', color: 'rose', nodeType: 'send-email', description: 'To new hire + manager CC', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-review', type: 'workflowNode', position: { x: 2620, y: 180 }, data: { label: 'Manager Day-1 Review', icon: 'UserCheck', color: 'rose', nodeType: 'task-node', description: 'Manager confirms setup complete', inputs: 1, outputs: 1, executionStatus: 'idle' } },
  { id: 'onb-contractor', type: 'workflowNode', position: { x: 1200, y: 440 }, data: { label: 'Contractor: Send NDA', icon: 'Send', color: 'rose', nodeType: 'gmail-send', description: 'Email NDA + W-9 forms', inputs: 1, outputs: 1, executionStatus: 'idle' } },
];

const defaultEdges: Edge[] = [
  { id: 'onb-e1', source: 'onb-form', target: 'onb-extract', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(270 60% 60%)' } },
  { id: 'onb-e2', source: 'onb-extract', target: 'onb-vars', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(190 70% 55%)' } },
  { id: 'onb-e3', source: 'onb-vars', target: 'onb-if', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(240 60% 60%)' } },
  { id: 'onb-e4', source: 'onb-if', target: 'onb-split', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(240 60% 60%)' } },
  { id: 'onb-e5', source: 'onb-split', target: 'onb-http', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(150 70% 50%)' } },
  { id: 'onb-e6', source: 'onb-split', target: 'onb-hubspot', sourceHandle: 'output-1', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(217 91% 60%)' } },
  { id: 'onb-e7', source: 'onb-split', target: 'onb-clickup', sourceHandle: 'output-2', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(350 60% 60%)' } },
  { id: 'onb-e8', source: 'onb-http', target: 'onb-join', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(150 70% 50%)' } },
  { id: 'onb-e9', source: 'onb-hubspot', target: 'onb-join', sourceHandle: 'output-0', targetHandle: 'input-1', animated: true, style: { stroke: 'hsl(217 91% 60%)' } },
  { id: 'onb-e10', source: 'onb-clickup', target: 'onb-join', sourceHandle: 'output-0', targetHandle: 'input-2', animated: true, style: { stroke: 'hsl(350 60% 60%)' } },
  { id: 'onb-e11', source: 'onb-join', target: 'onb-gen', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(270 60% 60%)' } },
  { id: 'onb-e12', source: 'onb-gen', target: 'onb-email', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(350 60% 60%)' } },
  { id: 'onb-e13', source: 'onb-email', target: 'onb-review', sourceHandle: 'output-0', targetHandle: 'input-0', animated: true, style: { stroke: 'hsl(350 60% 60%)' } },
  { id: 'onb-e14', source: 'onb-if', target: 'onb-contractor', sourceHandle: 'output-1', targetHandle: 'input-0', style: { stroke: 'hsl(215 20% 55%)', strokeDasharray: '5 5' } },
];

interface WorkflowCanvasProps {
  onNodeSelect?: (node: SelectedNodeInfo | null) => void;
  nodeStatuses?: Map<string, ExecutionStatus>;
  onNodesEdgesChange?: (nodes: Node[], edges: Edge[]) => void;
}

export function WorkflowCanvas({ onNodeSelect, nodeStatuses, onNodesEdgesChange }: WorkflowCanvasProps) {
  const reactFlowWrapper = useRef<HTMLDivElement>(null);
  const [nodes, setNodes, onNodesChange] = useNodesState(defaultNodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(defaultEdges);
  const [reactFlowInstance, setReactFlowInstance] = useState<any>(null);

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

  const onNodeClick = useCallback(
    (_: React.MouseEvent, node: Node) => {
      const d = node.data as Record<string, unknown>;
      onNodeSelect?.({
        id: node.id,
        label: d.label as string,
        icon: d.icon as string,
        color: d.color as string,
        nodeType: d.nodeType as string,
        description: d.description as string,
      });
    },
    [onNodeSelect]
  );

  const onPaneClick = useCallback(() => {
    onNodeSelect?.(null);
  }, [onNodeSelect]);

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
        onNodeClick={onNodeClick}
        onPaneClick={onPaneClick}
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
}
