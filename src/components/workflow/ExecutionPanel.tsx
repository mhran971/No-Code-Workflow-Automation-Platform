import { useState } from 'react';
import type { WorkflowExecution } from '@/types/workflow';
import type { ApiConfigField, KnowledgeBaseDocument, TenantUser, ValidationIssue } from '@/lib/api/types';
import { Zap, AlertCircle } from 'lucide-react';
import { NodeConfigPanel } from './NodeConfigPanel';
import type { SelectedNodeInfo } from '@/pages/Index';
import type { ExecutionMode } from '@/hooks/useWorkflowExecution';
import { StepRow, statusIcon } from './execution/StepRow';
import { VariableTree } from './execution/VariableTree';
import { ExecutionControls } from './execution/ExecutionControls';
import { WorkflowSettingsTab } from './execution/WorkflowSettingsTab';

interface ExecutionPanelProps {
  selectedNode?: SelectedNodeInfo | null;
  onDeselectNode?: () => void;
  onDeleteNode?: () => void;
  onConfigSave?: (config: Record<string, unknown>, name?: string) => void;
  apiConfigFields?: ApiConfigField[];
  nodeValidationIssues?: Map<string, ValidationIssue[]>;
  tenantUsers?: TenantUser[];
  kbDocuments?: KnowledgeBaseDocument[];
  execution?: WorkflowExecution | null;
  executionMode?: ExecutionMode;
  currentStepIndex?: number;
  isBackendMode?: boolean;
  runtimeContext?: Record<string, unknown>;
  onRunAll?: () => void;
  onStepForward?: () => void;
  onPause?: () => void;
  onResume?: () => void;
  onReset?: () => void;
  onCancel?: () => void;
}

export function ExecutionPanel({
  selectedNode,
  onDeselectNode,
  onDeleteNode,
  onConfigSave,
  apiConfigFields,
  nodeValidationIssues,
  tenantUsers = [],
  kbDocuments = [],
  execution,
  executionMode = 'idle',
  currentStepIndex = -1,
  isBackendMode = false,
  runtimeContext,
  onRunAll,
  onStepForward,
  onPause,
  onResume,
  onReset,
  onCancel,
}: ExecutionPanelProps) {
  const [activeTab, setActiveTab] = useState<'execution' | 'configuration'>('execution');

  const effectiveTab = selectedNode ? 'configuration' : activeTab;
  const isRunning = executionMode === 'running';
  const isCompleted = executionMode === 'completed';

  return (
    <div className="w-[320px] h-full bg-background border-l border-border flex flex-col">
      {/* Tabs */}
      <div className="flex border-b border-border">
        <button
          onClick={() => { setActiveTab('configuration'); }}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors ${
            effectiveTab === 'configuration' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          {selectedNode ? 'Node Config' : 'Configuration'}
        </button>
        <button
          onClick={() => { setActiveTab('execution'); if (onDeselectNode) onDeselectNode(); }}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors relative ${
            effectiveTab === 'execution' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          Execution
          {isRunning && (
            <span className="absolute top-2 right-3 w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
          )}
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-hidden">
        {effectiveTab === 'configuration' && selectedNode ? (
          <NodeConfigPanel
            node={selectedNode}
            apiConfigFields={apiConfigFields}
            onClose={() => onDeselectNode?.()}
            onDelete={onDeleteNode}
            onConfigSave={onConfigSave}
            validationIssues={selectedNode ? nodeValidationIssues?.get(selectedNode.id) : undefined}
            tenantUsers={tenantUsers}
            kbDocuments={kbDocuments}
          />
        ) : effectiveTab === 'execution' ? (
          <div className="h-full overflow-y-auto p-3">
            <ExecutionControls
              executionMode={executionMode}
              execution={execution}
              isBackendMode={isBackendMode}
              onRunAll={onRunAll}
              onStepForward={onStepForward}
              onPause={onPause}
              onResume={onResume}
              onReset={onReset}
              onCancel={onCancel}
            />

            {/* Execution Header */}
            {execution && (
              <>
                <div className="flex items-center justify-between mb-3">
                  <div className="flex items-center gap-2">
                    {execution.status === 'failed'  ? statusIcon.failed  :
                     execution.status === 'success' ? statusIcon.success :
                     isRunning ? statusIcon.running : statusIcon.idle}
                    <span className="text-xs font-medium text-foreground">Run #{execution.id}</span>
                  </div>
                  {execution.completedAt && (
                    <span className="text-[10px] text-muted-foreground font-mono">
                      {((new Date(execution.completedAt).getTime() - new Date(execution.startedAt).getTime()) / 1000).toFixed(1)}s
                    </span>
                  )}
                </div>

                {/* Failure banner */}
                {execution.status === 'failed' && (
                  <div className="mb-3 rounded-md bg-destructive/10 border border-destructive/20 p-2.5 flex items-center gap-2">
                    <AlertCircle className="h-3.5 w-3.5 text-destructive shrink-0" />
                    <p className="text-xs text-destructive font-medium">
                      Workflow failed — see highlighted node(s) below
                    </p>
                  </div>
                )}

                {/* Steps */}
                <div className="space-y-0">
                  {execution.steps.map((step, i) => (
                    <StepRow key={step.id} step={step} isActive={i === currentStepIndex} />
                  ))}
                </div>

                {/* Runtime Context — live variable store, backend mode only */}
                {runtimeContext && Object.keys(runtimeContext).length > 0 && (
                  <div className="mt-4">
                    <div className="text-[10px] font-medium text-muted-foreground mb-1 uppercase tracking-wider">
                      Runtime Context
                    </div>
                    <div className="bg-muted/40 rounded-md p-2 border border-border/50">
                      <VariableTree data={runtimeContext} />
                    </div>
                  </div>
                )}
              </>
            )}

            {/* Empty state */}
            {!execution && (
              <div className="flex flex-col items-center justify-center h-48 text-center">
                <Zap className="h-8 w-8 text-muted-foreground/30 mb-3" />
                <p className="text-xs text-muted-foreground">No execution yet</p>
                <p className="text-[10px] text-muted-foreground/60 mt-1">Click "Run All" or "Step" to start</p>
              </div>
            )}
          </div>
        ) : (
          <WorkflowSettingsTab />
        )}
      </div>
    </div>
  );
}
