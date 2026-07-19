import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Workflow, Plus, ChevronRight, Loader2, LayoutTemplate, LogOut } from 'lucide-react';
import { useAuth } from '@/contexts/AuthContext';
import { useApiConfig } from '@/hooks/useApiConfig';
import { ApiError, listWorkflows } from '@/lib/api/client';
import type { WorkflowSummary } from '@/lib/api/types';

const STATUS_STYLES: Record<string, string> = {
  active: 'bg-success/15 text-success',
  draft: 'bg-muted text-muted-foreground',
  archived: 'bg-destructive/10 text-destructive',
};

export default function WorkflowList() {
  const navigate = useNavigate();
  const { user, apiBaseUrl, token, logout } = useAuth();
  const { isConnected } = useApiConfig();

  const [workflows, setWorkflows] = useState<WorkflowSummary[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isConnected) return;
    setLoading(true);
    setError(null);
    listWorkflows(apiBaseUrl, token)
      .then((res) => setWorkflows(res.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : 'Failed to load workflows'))
      .finally(() => setLoading(false));
  }, [isConnected, apiBaseUrl, token]);

  return (
    <div className="min-h-screen bg-background">
      <header className="h-12 border-b border-border flex items-center justify-between px-6">
        <div className="flex items-center gap-2">
          <Workflow className="h-5 w-5 text-primary" />
          <span className="text-sm font-semibold text-foreground tracking-tight">FlowEngine</span>
        </div>
        <div className="flex items-center gap-3">
          {user && (
            <span className="text-xs text-muted-foreground">{user.email}</span>
          )}
          <button
            onClick={logout}
            className="h-8 px-3 flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors"
          >
            <LogOut className="h-3.5 w-3.5" />
            Sign out
          </button>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-6 py-10">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-xl font-semibold text-foreground">Workflows</h1>
            <p className="text-sm text-muted-foreground mt-0.5">Design and manage your automation workflows</p>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => navigate('/workflows/templates')}
              className="h-9 px-4 flex items-center gap-1.5 rounded-lg border border-border text-xs font-semibold text-foreground hover:bg-muted transition-colors"
            >
              <LayoutTemplate className="h-3.5 w-3.5" />
              From Template
            </button>
            <button
              onClick={() => navigate('/workflows/new')}
              className="h-9 px-4 flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors"
            >
              <Plus className="h-3.5 w-3.5" />
              New Workflow
            </button>
          </div>
        </div>

        {loading && (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="h-6 w-6 text-muted-foreground animate-spin" />
          </div>
        )}

        {error && (
          <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
            {error}
          </div>
        )}

        {!loading && !error && workflows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-20 text-center">
            <Workflow className="h-10 w-10 text-muted-foreground/30 mb-4" />
            <p className="text-sm font-medium text-foreground">No workflows yet</p>
            <p className="text-xs text-muted-foreground mt-1 mb-4">Create your first workflow to get started</p>
            <div className="flex items-center gap-2">
              <button
                onClick={() => navigate('/workflows/templates')}
                className="h-9 px-4 flex items-center gap-1.5 rounded-lg border border-border text-xs font-semibold text-foreground hover:bg-muted transition-colors"
              >
                <LayoutTemplate className="h-3.5 w-3.5" />
                From Template
              </button>
              <button
                onClick={() => navigate('/workflows/new')}
                className="h-9 px-4 flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors"
              >
                <Plus className="h-3.5 w-3.5" />
                New Workflow
              </button>
            </div>
          </div>
        )}

        {!loading && workflows.length > 0 && (
          <div className="rounded-lg border border-border overflow-hidden">
            {workflows.map((wf, i) => (
              <button
                key={wf.id}
                onClick={() => navigate(`/workflows/${wf.id}/canvas`)}
                className={`w-full flex items-center justify-between px-5 py-4 text-left hover:bg-muted/50 transition-colors group ${
                  i > 0 ? 'border-t border-border' : ''
                }`}
              >
                <div className="flex items-start gap-3 min-w-0">
                  <Workflow className="h-4 w-4 text-muted-foreground mt-0.5 shrink-0" />
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-foreground truncate">{wf.name}</p>
                    {wf.description && (
                      <p className="text-xs text-muted-foreground truncate mt-0.5">{wf.description}</p>
                    )}
                    <p className="text-[10px] text-muted-foreground/60 mt-1">
                      Updated {new Date(wf.updated_at).toLocaleDateString()}
                      {wf.version_label && ` · ${wf.version_label}`}
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-3 shrink-0 ml-4">
                  <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${STATUS_STYLES[wf.status] ?? STATUS_STYLES.draft}`}>
                    {wf.status}
                  </span>
                  <ChevronRight className="h-4 w-4 text-muted-foreground/40 group-hover:text-muted-foreground transition-colors" />
                </div>
              </button>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}
