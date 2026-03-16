import { useState, useCallback } from 'react';
import { NodeLibrary } from '@/components/workflow/NodeLibrary';
import { WorkflowCanvas } from '@/components/workflow/WorkflowCanvas';
import { ExecutionPanel } from '@/components/workflow/ExecutionPanel';
import { WorkflowHeader } from '@/components/workflow/WorkflowHeader';
import { useWorkflowExecution } from '@/hooks/useWorkflowExecution';
import type { Node, Edge } from '@xyflow/react';

export interface SelectedNodeInfo {
  id: string;
  label: string;
  icon: string;
  color: string;
  nodeType: string;
  description: string;
}

const Index = () => {
  const [selectedNode, setSelectedNode] = useState<SelectedNodeInfo | null>(null);
  const [canvasNodes, setCanvasNodes] = useState<Node[]>([]);
  const [canvasEdges, setCanvasEdges] = useState<Edge[]>([]);

  const {
    execution,
    mode,
    currentStepIndex,
    runAll,
    stepForward,
    pause,
    resume,
    reset,
    nodeStatuses,
  } = useWorkflowExecution(canvasNodes, canvasEdges);

  const handleNodeSelect = useCallback((node: SelectedNodeInfo | null) => {
    setSelectedNode(node);
  }, []);

  const handleNodesEdgesChange = useCallback((nodes: Node[], edges: Edge[]) => {
    setCanvasNodes(nodes);
    setCanvasEdges(edges);
  }, []);

  return (
    <div className="flex flex-col h-screen w-screen overflow-hidden bg-background">
      <WorkflowHeader onRunAll={runAll} executionMode={mode} />
      <div className="flex flex-1 overflow-hidden">
        <NodeLibrary />
        <WorkflowCanvas
          onNodeSelect={handleNodeSelect}
          nodeStatuses={nodeStatuses}
          onNodesEdgesChange={handleNodesEdgesChange}
        />
        <ExecutionPanel
          selectedNode={selectedNode}
          onDeselectNode={() => setSelectedNode(null)}
          execution={execution}
          executionMode={mode}
          currentStepIndex={currentStepIndex}
          onRunAll={runAll}
          onStepForward={stepForward}
          onPause={pause}
          onResume={resume}
          onReset={reset}
        />
      </div>
    </div>
  );
};

export default Index;
