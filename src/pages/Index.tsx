import { useState, useCallback } from 'react';
import { NodeLibrary } from '@/components/workflow/NodeLibrary';
import { WorkflowCanvas } from '@/components/workflow/WorkflowCanvas';
import { ExecutionPanel } from '@/components/workflow/ExecutionPanel';
import { WorkflowHeader } from '@/components/workflow/WorkflowHeader';

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

  const handleNodeSelect = useCallback((node: SelectedNodeInfo | null) => {
    setSelectedNode(node);
  }, []);

  return (
    <div className="flex flex-col h-screen w-screen overflow-hidden bg-background">
      <WorkflowHeader />
      <div className="flex flex-1 overflow-hidden">
        <NodeLibrary />
        <WorkflowCanvas onNodeSelect={handleNodeSelect} />
        <ExecutionPanel selectedNode={selectedNode} onDeselectNode={() => setSelectedNode(null)} />
      </div>
    </div>
  );
};

export default Index;
