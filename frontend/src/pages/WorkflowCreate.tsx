import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Workflow, ArrowLeft, Loader2 } from 'lucide-react';
import { ApiConnectionDialog } from '@/components/workflow/ApiConnectionDialog';
import { useApiConfig } from '@/hooks/useApiConfig';
import { ApiError, createWorkflow, listTeams } from '@/lib/api/client';
import { normalizeToken } from '@/lib/api/utils';

export default function WorkflowCreate() {
  const navigate = useNavigate();
  const apiConfig = useApiConfig();
  const { isConnected, dialogOpen, setDialogOpen, accessToken, apiBaseUrl } = apiConfig;

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [teamId, setTeamId] = useState<number | ''>('');
  const [teams, setTeams] = useState<Array<{ id: number; name: string }>>([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!normalizeToken(accessToken)) return;
    listTeams(apiBaseUrl, accessToken)
      .then((res) => setTeams(res.data))
      .catch(() => { /* non-critical */ });
  }, [apiBaseUrl, accessToken]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    if (!isConnected) {
      setDialogOpen(true);
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      const res = await createWorkflow(apiBaseUrl, accessToken, {
        name: name.trim(),
        description: description.trim() || undefined,
        team_id: teamId !== '' ? teamId : undefined,
      });
      navigate(`/workflows/${res.workflow.id}/canvas`);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Failed to create workflow');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="h-12 border-b border-border flex items-center justify-between px-6">
        <div className="flex items-center gap-2">
          <Workflow className="h-5 w-5 text-primary" />
          <span className="text-sm font-semibold text-foreground tracking-tight">FlowEngine</span>
        </div>
        <button
          onClick={() => setDialogOpen(true)}
          className="h-8 px-3 text-xs font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded-md transition-colors"
        >
          {isConnected ? 'API Connected' : 'Connect API'}
        </button>
      </header>

      <main className="max-w-lg mx-auto px-6 py-12">
        <button
          onClick={() => navigate('/workflows')}
          className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition-colors mb-8"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          Back to workflows
        </button>

        <h1 className="text-xl font-semibold text-foreground mb-1">New Workflow</h1>
        <p className="text-sm text-muted-foreground mb-8">Start with a blank canvas and build your automation.</p>

        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-xs font-medium text-foreground mb-1.5">
              Name <span className="text-destructive">*</span>
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Lead Qualification Pipeline"
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

          {error && (
            <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
              {error}
            </div>
          )}

          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={() => navigate('/workflows')}
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
      </main>

      <ApiConnectionDialog open={dialogOpen} onOpenChange={setDialogOpen} apiConfig={apiConfig} />
    </div>
  );
}
