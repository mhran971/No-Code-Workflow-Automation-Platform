import { NodeLibrary } from '@/components/workflow/NodeLibrary';
import { WorkflowCanvas } from '@/components/workflow/WorkflowCanvas';
import { ExecutionPanel } from '@/components/workflow/ExecutionPanel';
import { WorkflowHeader } from '@/components/workflow/WorkflowHeader';

const Index = () => {
  return (
    <div className="flex flex-col h-screen w-screen overflow-hidden bg-background">
      <WorkflowHeader />
      <div className="flex flex-1 overflow-hidden">
        <NodeLibrary />
        <WorkflowCanvas />
        <ExecutionPanel />
      </div>
    </div>
  );
};

export default Index;
