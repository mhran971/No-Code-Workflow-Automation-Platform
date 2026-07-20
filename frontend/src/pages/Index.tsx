import { useCallback, useEffect, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { toast } from 'sonner';
import { NodeLibrary } from '@/components/workflow/NodeLibrary';
import { WorkflowCanvas, type WorkflowCanvasHandle } from '@/components/workflow/WorkflowCanvas';
import { ExecutionPanel } from '@/components/workflow/ExecutionPanel';
import { WorkflowHeader } from '@/components/workflow/WorkflowHeader';
import { DynamicFlowDesignModal } from '@/components/workflow/DynamicFlowDesignModal';
import { ValidationResultsDialog } from '@/components/workflow/ValidationResultsDialog';
import { useWorkflowExecution } from '@/hooks/useWorkflowExecution';
import { useBackendExecution } from '@/hooks/useBackendExecution';
import { useApiConfig } from '@/hooks/useApiConfig';
import { useWorkflowValidation } from '@/hooks/useWorkflowValidation';
import { useAuth } from '@/contexts/AuthContext';
import {
  ApiError,
  fetchKnowledgeBaseDocuments,
  fetchTenantUsers,
  listWorkflows,
  loadWorkflow,
  publishWorkflow,
  saveDraft,
} from '@/lib/api/client';
import type { KnowledgeBaseDocument, TenantUser, WorkflowSummary } from '@/lib/api/types';
import { buildDefinitionFromCanvas } from '@/lib/api/utils';
import type { Node, Edge } from '@xyflow/react';

export interface SelectedNodeInfo {
  id: string;
  label: string;
  icon: string;
  color: string;
  nodeType: string;
  description: string;
  config?: Record<string, unknown>;
  name?: string;
}

const Index = () => {
  const { id: workflowId } = useParams<{ id: string }>();
  const { apiBaseUrl, token, logout } = useAuth();

  const canvasRef = useRef<WorkflowCanvasHandle>(null);
  const [selectedNode, setSelectedNode] = useState<SelectedNodeInfo | null>(null);
  const [canvasNodes, setCanvasNodes] = useState<Node[]>([]);
  const [canvasEdges, setCanvasEdges] = useState<Edge[]>([]);
  const [tenantUsers, setTenantUsers] = useState<TenantUser[]>([]);
  const [kbDocuments, setKbDocuments] = useState<KnowledgeBaseDocument[]>([]);
  const [workflows, setWorkflows] = useState<WorkflowSummary[]>([]);

  const [workflowName, setWorkflowName] = useState('');
  const [draftRevision, setDraftRevision] = useState<number | null>(null);
  const [publicToken, setPublicToken] = useState<string | null>(null);
  const [hasPublishedVersion, setHasPublishedVersion] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isPublishing, setIsPublishing] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  const [designModalOpen, setDesignModalOpen] = useState(false);
  const [designInstanceId, setDesignInstanceId] = useState<string | null>(null);

  const isLoadingDefinitionRef = useRef(false);
  const definitionLoadedRef = useRef(false);

  const { nodeDefinitions, isConnected, isValidating } = useApiConfig();

  const mockExec = useWorkflowExecution(canvasNodes, canvasEdges);
  const backendExec = useBackendExecution(workflowId, canvasNodes, {
    baseUrl: apiBaseUrl,
    accessToken: token,
  });

  const useBackend = isConnected && Boolean(workflowId);
  const activeExec = useBackend ? backendExec : mockExec;

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
  } = activeExec;
  const onCancel       = useBackend ? backendExec.cancel        : undefined;
  const runtimeContext = useBackend ? backendExec.runtimeContext : undefined;
  const instanceId     = useBackend ? backendExec.instanceId     : null;

  const {
    validationOpen,
    setValidationOpen,
    validationResult,
    validationError,
    validationLoading,
    lastDefinition,
    nodeValidationIssues,
    handleVerify,
  } = useWorkflowValidation({ canvasRef, canvasNodes, canvasEdges, nodeDefinitions, apiBaseUrl, accessToken: token });

  useEffect(() => {
    if (!isConnected) return;
    fetchTenantUsers(apiBaseUrl, token)
      .then((res) => setTenantUsers(res.data))
      .catch(() => { /* non-critical */ });
    fetchKnowledgeBaseDocuments(apiBaseUrl, token)
      .then((res) => setKbDocuments(res.data))
      .catch(() => { /* non-critical */ });
    listWorkflows(apiBaseUrl, token)
      .then((res) => setWorkflows(res.data))
      .catch(() => { /* non-critical */ });
  }, [isConnected, apiBaseUrl, token]);

  useEffect(() => {
    if (!isConnected || !workflowId || nodeDefinitions.length === 0 || definitionLoadedRef.current) return;
    definitionLoadedRef.current = true;

    loadWorkflow(apiBaseUrl, token, workflowId)
      .then(({ data }) => {
        setWorkflowName(data.name);
        setDraftRevision(data.draft_revision);
        setPublicToken(data.public_token);
        setHasPublishedVersion(data.current_version !== null);

        if (data.draft_definition && canvasRef.current) {
          isLoadingDefinitionRef.current = true;
          canvasRef.current.loadDefinition(data.draft_definition, nodeDefinitions);
          setTimeout(() => { isLoadingDefinitionRef.current = false; }, 100);
        }
      })
      .catch(() => {
        toast.error('Failed to load workflow');
      });
  }, [isConnected, workflowId, nodeDefinitions, apiBaseUrl, token]);

  const handleNodeSelect = useCallback((node: SelectedNodeInfo | null) => {
    setSelectedNode(node);
  }, []);

  const handleWaitingDynamicFlowClick = useCallback((_nodeId: string) => {
    if (!backendExec.instanceId) {
      toast.error('No running instance. Run the workflow first.');
      return;
    }
    setDesignInstanceId(backendExec.instanceId);
    setDesignModalOpen(true);
  }, [backendExec.instanceId]);

  const handleNodesEdgesChange = useCallback((nodes: Node[], edges: Edge[]) => {
    setCanvasNodes(nodes);
    setCanvasEdges(edges);
    if (!isLoadingDefinitionRef.current) {
      setHasUnsavedChanges(true);
    }
  }, []);

  const handleDeleteSelectedNode = useCallback(() => {
    if (!selectedNode) return;
    canvasRef.current?.deleteNode(selectedNode.id);
    setSelectedNode(null);
  }, [selectedNode]);

  const handleConfigSave = useCallback((config: Record<string, unknown>, name?: string) => {
    if (!selectedNode) return;
    canvasRef.current?.updateNodeData(selectedNode.id, { config, ...(name !== undefined ? { name } : {}) });
    setSelectedNode((current) => (current ? { ...current, config, ...(name !== undefined ? { name } : {}) } : null));
  }, [selectedNode]);

  const handleSave = useCallback(async () => {
    if (!workflowId) {
      toast.error('Workflow id is missing. Open a workflow canvas first.');
      return;
    }

    if (draftRevision === null) {
      toast.error('Workflow draft is still loading. Try again in a moment.');
      return;
    }

    const snapshot = canvasRef.current?.getSnapshot() ?? { nodes: canvasNodes, edges: canvasEdges };
    const definition = buildDefinitionFromCanvas(snapshot.nodes, snapshot.edges, nodeDefinitions);

    setIsSaving(true);
    try {
      const res = await saveDraft(apiBaseUrl, token, workflowId, definition, draftRevision);
      setDraftRevision(res.draft_revision);
      setHasUnsavedChanges(false);
      toast.success('Workflow saved');
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        toast.error('Conflict: someone else saved this workflow. Reload to get the latest version.');
      } else {
        toast.error('Failed to save workflow');
      }
    } finally {
      setIsSaving(false);
    }
  }, [workflowId, draftRevision, canvasNodes, canvasEdges, nodeDefinitions, apiBaseUrl, token]);

  const saveDisabled = !workflowId || draftRevision === null || isSaving;
  const publishDisabled = !workflowId || draftRevision === null || isPublishing;

  const handlePublish = useCallback(async () => {
    if (!workflowId) {
      toast.error('Workflow id is missing. Open a workflow canvas first.');
      return;
    }

    if (draftRevision === null) {
      toast.error('Workflow draft is still loading. Try again in a moment.');
      return;
    }

    setIsPublishing(true);
    try {
      await publishWorkflow(apiBaseUrl, token, workflowId);
      toast.success('Workflow published successfully');
    } catch (e) {
      if (e instanceof ApiError) {
        toast.error(e.message || 'Failed to publish workflow');
      } else {
        toast.error('Failed to publish workflow');
      }
    } finally {
      setIsPublishing(false);
    }
  }, [workflowId, draftRevision, apiBaseUrl, token]);

  const formTriggerNode = canvasNodes.find((node) => (node.data as { nodeType?: string })?.nodeType === 'form-trigger');
  const formTriggerConfig = formTriggerNode?.data?.config as Record<string, unknown> | undefined;
  const formLink =
    publicToken && hasPublishedVersion && formTriggerConfig?.accessLevel === 'public'
      ? `${window.location.origin}/forms/${publicToken}`
      : null;

  return (
    <div className="flex flex-col h-screen w-screen overflow-hidden bg-background">
      <WorkflowHeader
        workflowName={workflowName}
        formLink={formLink}
        onRunAll={runAll}
        executionMode={mode}
        onVerify={handleVerify}
        isVerifying={validationLoading}
        canVerify={isConnected && Boolean(token)}
        onSignOut={logout}
        onSave={handleSave}
        isSaving={isSaving}
        saveDisabled={saveDisabled}
        onPublish={handlePublish}
        isPublishing={isPublishing}
        publishDisabled={publishDisabled}
        hasUnsavedChanges={hasUnsavedChanges}
      />
      <div className="flex flex-1 overflow-hidden">
        <NodeLibrary
          nodeDefinitions={nodeDefinitions}
          isConnected={isConnected}
          isLoading={isValidating}
        />
        <WorkflowCanvas
          ref={canvasRef}
          onNodeSelect={handleNodeSelect}
          nodeStatuses={nodeStatuses}
          onNodesEdgesChange={handleNodesEdgesChange}
          nodeDefinitions={nodeDefinitions}
          onWaitingDynamicFlowClick={handleWaitingDynamicFlowClick}
        />
        <ExecutionPanel
          selectedNode={selectedNode}
          onDeselectNode={() => setSelectedNode(null)}
          onDeleteNode={handleDeleteSelectedNode}
          onConfigSave={handleConfigSave}
          apiConfigFields={
            selectedNode
              ? nodeDefinitions.find((definition) => definition.type === selectedNode.nodeType)?.config_fields
              : undefined
          }
          nodeValidationIssues={nodeValidationIssues}
          tenantUsers={tenantUsers}
          kbDocuments={kbDocuments}
          workflows={workflows}
          apiBaseUrl={apiBaseUrl}
          accessToken={token}
          execution={execution}
          executionMode={mode}
          currentStepIndex={currentStepIndex}
          onRunAll={runAll}
          onStepForward={stepForward}
          onPause={pause}
          onResume={resume}
          onReset={reset}
          onCancel={onCancel}
          onDesignSubFlow={instanceId ? () => {
            setDesignInstanceId(instanceId);
            setDesignModalOpen(true);
          } : undefined}
          isBackendMode={useBackend}
          runtimeContext={runtimeContext}
          instanceId={instanceId}
          instanceStatus={execution?.status === 'running' ? 'running' : execution?.status === 'failed' ? 'failed' : execution?.status === 'success' ? 'succeeded' : 'paused'}
          pausedReason={execution?.status === 'waiting' ? 'dynamic_flow:awaiting_design' : null}
        />
      </div>

      <ValidationResultsDialog
        open={validationOpen}
        onOpenChange={setValidationOpen}
        result={validationResult}
        error={validationError}
        loading={validationLoading}
        definition={lastDefinition}
      />

      <DynamicFlowDesignModal
        open={designModalOpen}
        onOpenChange={setDesignModalOpen}
        instanceId={designInstanceId ?? ''}
        apiBaseUrl={apiBaseUrl}
        accessToken={token}
        nodeDefinitions={nodeDefinitions}
        workflows={workflows}
        tenantUsers={tenantUsers}
        kbDocuments={kbDocuments}
        onSuccess={() => { /* workflow resumes via Echo */ }}
      />
    </div>
  );
};

export default Index;
