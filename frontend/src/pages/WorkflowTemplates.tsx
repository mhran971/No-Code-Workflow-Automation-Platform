import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, LayoutTemplate, Loader2, LogOut, Sparkles, Workflow } from 'lucide-react';
import { useAuth } from '@/contexts/AuthContext';
import { useApiConfig } from '@/hooks/useApiConfig';
import { ApiError, createWorkflowFromTemplate, listTeams, listTemplates } from '@/lib/api/client';
import type { WorkflowTemplate } from '@/lib/api/types';

const CATEGORY_STYLES: Record<string, string> = {
  hr: 'bg-primary/10 text-primary',
  finance: 'bg-success/15 text-success',
  operations: 'bg-warning/15 text-warning',
};

export default function WorkflowTemplates() {
  const navigate = useNavigate();
  const { user, apiBaseUrl, token, logout } = useAuth();
  const { isConnected } = useApiConfig();

  const [templates, setTemplates] = useState<WorkflowTemplate[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [selected, setSelected] = useState<WorkflowTemplate | null>(null);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [teamId, setTeamId] = useState<number | ''>('');
  const [teams, setTeams] = useState<Array<{ id: number; name: string }>>([]);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!isConnected) return;
    setLoading(true);
    setLoadError(null);
    listTemplates(apiBaseUrl, token)
      .then((res) => setTemplates(res.data))
      .catch((e) => setLoadError(e instanceof ApiError ? e.message : 'Failed to load templates'))
      .finally(() => setLoading(false));
  }, [isConnected, apiBaseUrl, token]);

  useEffect(() => {
    if (!isConnected) return;
    listTeams(apiBaseUrl, token)
      .then((res) => setTeams(res.data))
      .catch(() => { /* non-critical */ });
  }, [isConnected, apiBaseUrl, token]);

  const selectTemplate = (template: WorkflowTemplate) => {
    setSelected(template);
    setName(template.name);
    setDescription(template.description ?? '');
    setSubmitError(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selected || !name.trim()) return;

    if (!isConnected) return;

    setSubmitting(true);
    setSubmitError(null);

    try {
      const res = await createWorkflowFromTemplate(apiBaseUrl, token, {
        template_id: selected.id,
        name: name.trim(),
        description: description.trim() || undefined,
        team_id: teamId !== '' ? teamId : undefined,
      });
      navigate(`/workflows/${res.workflow.id}/canvas`);
    } catch (e) {
      setSubmitError(e instanceof ApiError ? e.message : 'Failed to create workflow');
    } finally {
      setSubmitting(false);
    }
  };

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
        <button
          onClick={() => (selected ? setSelected(null) : navigate('/workflows'))}
          className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors mb-8"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          {selected ? 'Choose a different template' : 'Back to workflows'}
        </button>

        {!selected && (
          <>
            <div className="mb-8">
              <h1 className="text-xl font-semibold text-foreground">Start from a Template</h1>
              <p className="text-sm text-muted-foreground mt-0.5">
                Pick a starting point and customize it on the canvas.
              </p>
            </div>

            {loading && (
              <div className="flex items-center justify-center py-20">
                <Loader2 className="h-6 w-6 text-muted-foreground animate-spin" />
              </div>
            )}

            {loadError && (
              <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                {loadError}
              </div>
            )}

            {!loading && !loadError && templates.length === 0 && (
              <div className="flex flex-col items-center justify-center py-20 text-center">
                <LayoutTemplate className="h-10 w-10 text-muted-foreground/30 mb-4" />
                <p className="text-sm font-medium text-foreground">No templates available</p>
                <p className="text-xs text-muted-foreground mt-1">Check back later or start from a blank canvas.</p>
              </div>
            )}

            {!loading && templates.length > 0 && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {templates.map((template) => (
                  <button
                    key={template.id}
                    onClick={() => selectTemplate(template)}
                    className="text-left rounded-lg border border-border p-5 hover:border-primary/50 hover:bg-muted/30 transition-colors group"
                  >
                    <div className="flex items-start justify-between gap-3 mb-2">
                      <LayoutTemplate className="h-4 w-4 text-muted-foreground shrink-0 mt-0.5" />
                      {template.category && (
                        <span
                          className={`text-[10px] px-1.5 py-0.5 rounded font-medium shrink-0 ${
                            CATEGORY_STYLES[template.category] ?? 'bg-muted text-muted-foreground'
                          }`}
                        >
                          {template.category}
                        </span>
                      )}
                    </div>
                    <p className="text-sm font-medium text-foreground">{template.name}</p>
                    {template.description && (
                      <p className="text-xs text-muted-foreground mt-1 line-clamp-2">{template.description}</p>
                    )}
                    <div className="flex items-center justify-between mt-4">
                      <span className="text-[10px] text-muted-foreground/60">
                        Used {template.usage_count} time{template.usage_count === 1 ? '' : 's'}
                      </span>
                      <span className="text-[11px] font-medium text-primary opacity-0 group-hover:opacity-100 transition-opacity">
                        Use this template →
                      </span>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </>
        )}

        {selected && (
          <div className="max-w-lg">
            <div className="flex items-center gap-2 mb-1">
              <Sparkles className="h-4 w-4 text-primary" />
              <h1 className="text-xl font-semibold text-foreground">New Workflow from "{selected.name}"</h1>
            </div>
            <p className="text-sm text-muted-foreground mb-8">
              This creates a draft copy of the template that you can freely edit.
            </p>

            <form onSubmit={handleSubmit} className="space-y-5">
              <div>
                <label className="block text-xs font-medium text-foreground mb-1.5">
                  Name <span className="text-destructive">*</span>
                </label>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g. Leave Request Approval"
                  required
                  className="w-full h-9 px-3 rounded-md border border-border bg-background text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-foreground mb-1.5">
                  Description <span className="text-muted-foreground font-normal">(optional)</span>
                </label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Describe what this workflow does…"
                  rows={3}
                  className="w-full px-3 py-2 rounded-md border border-border bg-background text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors resize-none"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-foreground mb-1.5">
                  Team
                </label>
                <select
                  value={teamId}
                  onChange={(e) => setTeamId(e.target.value === '' ? '' : Number(e.target.value))}
                  className="w-full h-9 px-3 rounded-md border border-border bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-colors"
                >
                  <option value="">— Select a team —</option>
                  {teams.map((team) => (
                    <option key={team.id} value={team.id}>{team.name}</option>
                  ))}
                </select>
              </div>

              {submitError && (
                <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                  {submitError}
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setSelected(null)}
                  className="h-9 px-4 rounded-lg border border-border text-xs font-medium text-foreground hover:bg-muted transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting || !name.trim()}
                  className="h-9 px-5 flex items-center gap-1.5 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {submitting && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  {submitting ? 'Creating…' : 'Create Workflow'}
                </button>
              </div>
            </form>
          </div>
        )}
      </main>
    </div>
  );
}
