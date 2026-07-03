import { Workflow, Save, PlayCircle, Settings, ChevronDown, Loader2, ShieldCheck, Plug, Upload, Link2 } from 'lucide-react';
import { toast } from 'sonner';
import type { ExecutionMode } from '@/hooks/useWorkflowExecution';

interface WorkflowHeaderProps {
  workflowName?: string;
  formLink?: string | null;
  onRunAll?: () => void;
  executionMode?: ExecutionMode;
  onVerify?: () => void;
  isVerifying?: boolean;
  canVerify?: boolean;
  isApiConnected?: boolean;
  onApiSettingsClick?: () => void;
  onSave?: () => void;
  isSaving?: boolean;
  saveDisabled?: boolean;
  onPublish?: () => void;
  isPublishing?: boolean;
  publishDisabled?: boolean;
  hasUnsavedChanges?: boolean;
}

export function WorkflowHeader({
  workflowName,
  formLink,
  onRunAll,
  executionMode = 'idle',
  onVerify,
  isVerifying = false,
  canVerify = false,
  isApiConnected = false,
  onApiSettingsClick,
  onSave,
  isSaving = false,
  saveDisabled = false,
  onPublish,
  isPublishing = false,
  publishDisabled = false,
  hasUnsavedChanges = false,
}: WorkflowHeaderProps) {
  const isRunning = executionMode === 'running' || executionMode === 'stepping';

  const handleCopyFormLink = async () => {
    if (!formLink) return;
    try {
      await navigator.clipboard.writeText(formLink);
      toast.success('Form link copied to clipboard');
    } catch {
      toast.error('Could not copy the link — copy it manually', { description: formLink });
    }
  };

  return (
    <header className="h-12 bg-background border-b border-border flex items-center justify-between px-4">
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2">
          <Workflow className="h-5 w-5 text-primary" />
          <span className="text-sm font-semibold text-foreground tracking-tight">FlowEngine</span>
        </div>
        <div className="w-px h-5 bg-border" />
        <div className="flex items-center gap-1.5">
          <span className="text-sm text-foreground font-medium">{workflowName ?? 'Untitled Workflow'}</span>
          <ChevronDown className="h-3 w-3 text-muted-foreground" />
        </div>
        {hasUnsavedChanges && (
          <span className="h-1.5 w-1.5 rounded-full bg-amber-400" title="Unsaved changes" />
        )}
      </div>

      <div className="flex items-center gap-2">
        {formLink && (
          <button
            onClick={handleCopyFormLink}
            className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
            title={formLink}
          >
            <Link2 className="h-3.5 w-3.5" />
            Copy Form Link
          </button>
        )}
        <button
          onClick={onApiSettingsClick}
          className={`h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium transition-colors ${
            isApiConnected
              ? 'text-success hover:bg-success/10'
              : 'text-muted-foreground hover:text-foreground hover:bg-muted'
          }`}
        >
          <Plug className="h-3.5 w-3.5" />
          {isApiConnected ? 'API Connected' : 'Connect API'}
        </button>
        <button
          onClick={onSave}
          disabled={isSaving || saveDisabled}
          className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isSaving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
          {isSaving ? 'Saving…' : 'Save'}
        </button>
        <button
          onClick={onPublish}
          disabled={isPublishing || publishDisabled}
          className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isPublishing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
          {isPublishing ? 'Publishing…' : 'Publish Changes'}
        </button>
        <button
          onClick={onVerify}
          disabled={isVerifying || !canVerify}
          className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isVerifying ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          ) : (
            <ShieldCheck className="h-3.5 w-3.5" />
          )}
          {isVerifying ? 'Verifying…' : 'Verify'}
        </button>
        <button className="h-8 px-3 flex items-center gap-1.5 rounded-md text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted transition-colors">
          <Settings className="h-3.5 w-3.5" />
          Settings
        </button>
        <button
          onClick={onRunAll}
          disabled={isRunning}
          className="h-8 px-4 flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isRunning ? (
            <Loader2 className="h-3.5 w-3.5 animate-spin" />
          ) : (
            <PlayCircle className="h-3.5 w-3.5" />
          )}
          {isRunning ? 'Running...' : 'Run'}
        </button>
      </div>
    </header>
  );
}
