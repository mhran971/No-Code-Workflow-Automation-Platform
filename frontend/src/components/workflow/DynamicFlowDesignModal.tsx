import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Shuffle, Play, CheckCircle, Copy, Variable } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { NodeLibrary } from '@/components/workflow/NodeLibrary';
import { WorkflowCanvas, type WorkflowCanvasHandle } from '@/components/workflow/WorkflowCanvas';
import { NodeConfigPanel } from '@/components/workflow/NodeConfigPanel';
import { ValidationResultsDialog } from '@/components/workflow/ValidationResultsDialog';
import { useWorkflowValidation } from '@/hooks/useWorkflowValidation';
import {
  ApiError,
  fetchDynamicFlow,
  submitDynamicFlowDefinition,
} from '@/lib/api/client';
import type { ApiNodeDefinition, DynamicFlowDesignResponse, WorkflowSummary, TenantUser, KnowledgeBaseDocument } from '@/lib/api/types';
import { buildDefinitionFromCanvas } from '@/lib/api/utils';
import type { Node, Edge } from '@xyflow/react';
import type { SelectedNodeInfo } from '@/pages/Index';

interface DynamicFlowDesignModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  instanceId: string;
  apiBaseUrl: string;
  accessToken: string;
  nodeDefinitions: ApiNodeDefinition[];
  workflows?: WorkflowSummary[];
  tenantUsers?: TenantUser[];
  kbDocuments?: KnowledgeBaseDocument[];
  onSuccess?: () => void;
}

export function DynamicFlowDesignModal({
  open,
  onOpenChange,
  instanceId,
  apiBaseUrl,
  accessToken,
  nodeDefinitions,
  workflows = [],
  tenantUsers = [],
  kbDocuments = [],
  onSuccess,
}: DynamicFlowDesignModalProps) {
  const canvasRef = useRef<WorkflowCanvasHandle>(null);
  const [selectedNode, setSelectedNode] = useState<SelectedNodeInfo | null>(null);
  const [canvasNodes, setCanvasNodes] = useState<Node[]>([]);
  const [canvasEdges, setCanvasEdges] = useState<Edge[]>([]);
  const [loading, setLoading] = useState(true);
  const [designData, setDesignData] = useState<DynamicFlowDesignResponse | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Pre-populate the canvas with a dynamic-entry node once
  const entryPopulatedRef = useRef(false);

  // Fetch dynamic flow data when modal opens
  useEffect(() => {
    if (!open || !instanceId) return;

    setLoading(true);
    setDesignData(null);
    setSelectedNode(null);
    setCanvasNodes([]);
    setCanvasEdges([]);
    entryPopulatedRef.current = false;

    fetchDynamicFlow(apiBaseUrl, accessToken, instanceId)
      .then((data) => {
        setDesignData(data);
        if (data.dynamic_flow.status !== 'awaiting_design') {
          toast.error('This dynamic flow is no longer awaiting design.');
          onOpenChange(false);
        }
      })
      .catch((e) => {
        if (e instanceof ApiError) {
          toast.error(e.message);
        } else {
          toast.error('Failed to load dynamic flow data.');
        }
        onOpenChange(false);
      })
      .finally(() => setLoading(false));
  }, [open, instanceId, apiBaseUrl, accessToken, onOpenChange]);

  // Pre-populate canvas with dynamic-entry node
  useEffect(() => {
    if (loading || nodeDefinitions.length === 0 || canvasNodes.length > 0 || entryPopulatedRef.current) return;

    const entryDef = nodeDefinitions.find((d) => d.type === 'dynamic-entry');
    if (!entryDef) return;

    entryPopulatedRef.current = true;

    const entryNode: Node = {
      id: 'dynamic-entry-1',
      type: 'workflowNode',
      position: { x: 200, y: 100 },
      data: {
        nodeType: 'dynamic-entry',
        label: entryDef.label ?? 'Entry Point',
        icon: entryDef.icon ?? 'LogIn',
        color: entryDef.color ?? 'emerald',
        description: entryDef.description ?? 'Sub-flow entry point',
        inputs: 0,
        outputs: 1,
        config: {},
        executionStatus: 'idle',
      },
    };

    setCanvasNodes([entryNode]);
  }, [loading, nodeDefinitions, canvasNodes.length]);

  const handleNodeSelect = useCallback((node: SelectedNodeInfo | null) => {
    setSelectedNode(node);
  }, []);

  const handleNodesEdgesChange = useCallback((nodes: Node[], edges: Edge[]) => {
    setCanvasNodes(nodes);
    setCanvasEdges(edges);
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

  const {
    validationOpen,
    setValidationOpen,
    validationResult,
    validationError,
    validationLoading,
    handleVerify,
  } = useWorkflowValidation({ canvasRef, canvasNodes, canvasEdges, nodeDefinitions, apiBaseUrl, accessToken,
    extraTriggerVariables: Object.keys(designData?.parent_context ?? {}),
  });

  const handleSubmit = useCallback(async () => {
    if (!instanceId || !canvasRef.current) return;

    const snapshot = canvasRef.current.getSnapshot();
    const definition = buildDefinitionFromCanvas(snapshot.nodes, snapshot.edges, nodeDefinitions);

    setIsSubmitting(true);
    try {
      await submitDynamicFlowDefinition(apiBaseUrl, accessToken, instanceId, definition);
      toast.success('Dynamic flow submitted — workflow resumed');
      onOpenChange(false);
      onSuccess?.();
    } catch (e) {
      if (e instanceof ApiError) {
        toast.error(e.message);
      } else {
        toast.error('Failed to submit dynamic flow');
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [instanceId, nodeDefinitions, apiBaseUrl, accessToken, onOpenChange, onSuccess]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[95vw] w-[95vw] h-[90vh] p-0 gap-0 overflow-hidden">
        {/* Header */}
        <DialogHeader className="flex flex-row items-center justify-between px-4 py-2.5 border-b border-border space-y-0">
          <DialogTitle className="flex items-center gap-2 text-sm">
            <Shuffle className="h-4 w-4 text-teal-500" />
            Design Sub-Flow
            {designData && (
              <span className="text-xs text-muted-foreground font-normal">
                — {designData.dynamic_flow.node_key}
              </span>
            )}
          </DialogTitle>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handleVerify}
              disabled={canvasNodes.length === 0}
              className="h-7 text-xs"
            >
              <CheckCircle className="h-3.5 w-3.5 mr-1" />
              Verify
            </Button>
            <Button
              size="sm"
              onClick={handleSubmit}
              disabled={isSubmitting || canvasNodes.length === 0}
              className="h-7 text-xs"
            >
              <Play className="h-3.5 w-3.5 mr-1" />
              {isSubmitting ? 'Submitting...' : 'Submit & Resume'}
            </Button>
          </div>
        </DialogHeader>

        {/* Editor body */}
        <div className="flex flex-1 overflow-hidden h-[calc(90vh-48px)]">
          {/* Node library */}
          <NodeLibrary
            nodeDefinitions={nodeDefinitions}
            isConnected={true}
            isLoading={false}
          />

          {/* Canvas */}
          <div className="flex-1">
            {loading ? (
              <div className="flex items-center justify-center h-full text-muted-foreground text-sm">
                Loading dynamic flow data...
              </div>
            ) : (
              <WorkflowCanvas
                ref={canvasRef}
                onNodeSelect={handleNodeSelect}
                onNodesEdgesChange={handleNodesEdgesChange}
                nodeDefinitions={nodeDefinitions}
              />
            )}
          </div>

          {/* Config panel */}
          <div className="w-[320px] h-full bg-background border-l border-border flex flex-col">
            <div className="flex border-b border-border">
              <div className="flex-1 px-4 py-2.5 text-xs font-medium text-foreground border-b-2 border-primary">
                {selectedNode ? 'Node Config' : 'Configuration'}
              </div>
            </div>
            <div className="flex-1 overflow-y-auto">
              {selectedNode ? (
                <NodeConfigPanel
                  node={selectedNode}
                  apiConfigFields={
                    nodeDefinitions.find((d) => d.type === selectedNode.nodeType)?.config_fields
                  }
                  onClose={() => setSelectedNode(null)}
                  onDelete={handleDeleteSelectedNode}
                  onConfigSave={handleConfigSave}
                  tenantUsers={tenantUsers}
                  kbDocuments={kbDocuments}
                  workflows={workflows}
                  apiBaseUrl={apiBaseUrl}
                  accessToken={accessToken}
                />
              ) : (
                <div className="p-3 space-y-4">
                  <div className="flex flex-col items-center text-center mb-2">
                    <Shuffle className="h-8 w-8 text-muted-foreground/30 mb-3" />
                    <p className="text-xs text-muted-foreground">Select a node to configure</p>
                    <p className="text-[10px] text-muted-foreground/60 mt-1">
                      Drag nodes from the library to build your sub-flow
                    </p>
                  </div>

                  {/* Parent Context Variables */}
                  {designData?.parent_context && Object.keys(designData.parent_context).length > 0 && (
                    <div>
                      <div className="flex items-center gap-1.5 mb-2">
                        <Variable className="h-3 w-3 text-teal-500" />
                        <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">
                          Available Context Variables
                        </span>
                      </div>
                      <p className="text-[10px] text-muted-foreground/60 mb-2">
                        Use <code className="text-teal-600">{'{{context.variableName}}'}</code> in node configs
                      </p>
                      <div className="space-y-1">
                        {Object.entries(designData.parent_context).map(([key, value]) => (
                          <div
                            key={key}
                            className="flex items-center justify-between gap-2 px-2 py-1.5 bg-muted/40 rounded-md border border-border/50 group"
                          >
                            <div className="min-w-0 flex-1">
                              <span className="text-[11px] font-mono text-teal-600 block truncate">
                                {`{{context.${key}}}`}
                              </span>
                              <span className="text-[10px] text-muted-foreground truncate block">
                                {typeof value === 'string'
                                  ? value.length > 30 ? `${value.substring(0, 30)}...` : value
                                  : JSON.stringify(value)}
                              </span>
                            </div>
                            <button
                              type="button"
                              onClick={() => {
                                navigator.clipboard.writeText(`{{context.${key}}}`);
                                toast.success(`Copied {{context.${key}}}`);
                              }}
                              className="opacity-0 group-hover:opacity-100 transition-opacity p-1 hover:bg-muted rounded"
                              title="Copy variable reference"
                            >
                              <Copy className="h-3 w-3 text-muted-foreground" />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Validation dialog */}
        <ValidationResultsDialog
          open={validationOpen}
          onOpenChange={setValidationOpen}
          result={validationResult}
          error={validationError}
          loading={validationLoading}
        />
      </DialogContent>
    </Dialog>
  );
}
