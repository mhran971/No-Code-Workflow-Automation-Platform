import { useState } from 'react';
import type { WorkflowExecution, ExecutionStep } from '@/types/workflow';
import { CheckCircle2, XCircle, Clock, Loader2, ChevronDown, PlayCircle } from 'lucide-react';

const MOCK_EXECUTION: WorkflowExecution = {
  id: 'exec-001',
  status: 'success',
  startedAt: '2026-03-15T09:41:00Z',
  completedAt: '2026-03-15T09:41:08Z',
  steps: [
    {
      id: 's1', nodeId: 'node-1', nodeLabel: 'Webhook Received', nodeType: 'webhook-trigger',
      status: 'success', timestamp: '09:41:02', duration: 12,
      variables: { method: 'POST', contentType: 'application/json', body: { leadId: 'LD-4521', email: 'jane@acme.com', company: 'Acme Corp', score: 85 } },
      message: 'Webhook payload received (245 bytes)'
    },
    {
      id: 's2', nodeId: 'node-2', nodeLabel: 'AI Classifier', nodeType: 'ai-classifier',
      status: 'success', timestamp: '09:41:03', duration: 890,
      variables: { classification: { priority: 'high', sentiment: 'positive', confidence: 0.92 }, documentsUsed: ['sales-policy-v3.pdf'], tokensUsed: 1245 },
      message: 'Classified as "High Priority" (confidence: 0.92)'
    },
    {
      id: 's3', nodeId: 'node-3', nodeLabel: 'If: High Priority', nodeType: 'if-node',
      status: 'success', timestamp: '09:41:04', duration: 2,
      variables: { condition: 'priority === "high"', result: true, branch: 'true' },
      message: 'Condition evaluated: TRUE → taking priority path'
    },
    {
      id: 's4', nodeId: 'node-4', nodeLabel: 'HubSpot: Create Contact', nodeType: 'hubspot-contact',
      status: 'success', timestamp: '09:41:05', duration: 1200,
      variables: { contactId: 'HS-89012', email: 'jane@acme.com', lifecycleStage: 'opportunity', hubspotUrl: 'https://app.hubspot.com/contacts/89012' },
      message: 'Contact created: jane@acme.com (ID: HS-89012)'
    },
    {
      id: 's5', nodeId: 'node-5', nodeLabel: 'Send Email', nodeType: 'send-email',
      status: 'success', timestamp: '09:41:07', duration: 650,
      variables: { emailId: 'msg-7745', to: 'sales-team@company.com', subject: 'High Priority Lead: Acme Corp', sendStatus: 'delivered' },
      message: 'Email delivered to sales-team@company.com'
    },
  ],
};

const statusIcon: Record<string, React.ReactNode> = {
  success: <CheckCircle2 className="h-3.5 w-3.5 text-success" />,
  failed: <XCircle className="h-3.5 w-3.5 text-destructive" />,
  running: <Loader2 className="h-3.5 w-3.5 text-primary animate-spin" />,
  waiting: <Clock className="h-3.5 w-3.5 text-warning" />,
  idle: <Clock className="h-3.5 w-3.5 text-muted-foreground" />,
};

function VariableTree({ data, depth = 0 }: { data: unknown; depth?: number }) {
  if (data === null || data === undefined) {
    return <span className="text-muted-foreground/60 font-mono text-[11px]">null</span>;
  }
  if (typeof data === 'string') {
    return <span className="text-emerald-400 font-mono text-[11px]">"{data}"</span>;
  }
  if (typeof data === 'number') {
    return <span className="text-amber-400 font-mono text-[11px]">{data}</span>;
  }
  if (typeof data === 'boolean') {
    return <span className="text-blue-400 font-mono text-[11px]">{data.toString()}</span>;
  }
  if (Array.isArray(data)) {
    return (
      <div className="ml-3">
        {data.map((item, i) => (
          <div key={i} className="flex items-start gap-1">
            <span className="text-muted-foreground font-mono text-[11px]">[{i}]:</span>
            <VariableTree data={item} depth={depth + 1} />
          </div>
        ))}
      </div>
    );
  }
  if (typeof data === 'object') {
    return (
      <div className={depth > 0 ? 'ml-3' : ''}>
        {Object.entries(data as Record<string, unknown>).map(([key, value]) => (
          <div key={key} className="flex items-start gap-1">
            <span className="text-muted-foreground/80 font-mono text-[11px] shrink-0">{key}:</span>
            <VariableTree data={value} depth={depth + 1} />
          </div>
        ))}
      </div>
    );
  }
  return <span className="text-foreground font-mono text-[11px]">{String(data)}</span>;
}

function StepRow({ step }: { step: ExecutionStep }) {
  const [expanded, setExpanded] = useState(false);

  return (
    <div className="border-l-2 border-border ml-2 pl-3 relative">
      <div className="absolute -left-[5px] top-2.5 w-2 h-2 rounded-full bg-border" />
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full text-left py-2 group"
      >
        <div className="flex items-center gap-2">
          {statusIcon[step.status]}
          <span className="text-[10px] font-mono text-muted-foreground">[{step.timestamp}]</span>
          <span className="text-xs font-medium text-foreground flex-1 truncate">{step.nodeLabel}</span>
          {step.duration && <span className="text-[10px] text-muted-foreground">{step.duration}ms</span>}
          <ChevronDown className={`h-3 w-3 text-muted-foreground transition-transform ${expanded ? '' : '-rotate-90'}`} />
        </div>
        {step.message && (
          <p className="text-[10px] text-muted-foreground mt-0.5 ml-5 truncate">{step.message}</p>
        )}
      </button>

      {expanded && (
        <div className="pb-2 ml-5">
          <div className="text-[10px] font-medium text-muted-foreground mb-1 uppercase tracking-wider">Variables</div>
          <div className="bg-muted/40 rounded-md p-2 border border-border/50">
            <VariableTree data={step.variables} />
          </div>
        </div>
      )}
    </div>
  );
}

export function ExecutionPanel() {
  const [activeTab, setActiveTab] = useState<'execution' | 'configuration'>('execution');
  const execution = MOCK_EXECUTION;

  return (
    <div className="w-[320px] h-full bg-background border-l border-border flex flex-col">
      {/* Tabs */}
      <div className="flex border-b border-border">
        <button
          onClick={() => setActiveTab('configuration')}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors ${
            activeTab === 'configuration' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          Configuration
        </button>
        <button
          onClick={() => setActiveTab('execution')}
          className={`flex-1 px-4 py-2.5 text-xs font-medium transition-colors ${
            activeTab === 'execution' ? 'text-foreground border-b-2 border-primary' : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          Execution
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto">
        {activeTab === 'execution' ? (
          <div className="p-3">
            {/* Execution Header */}
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                {statusIcon[execution.status]}
                <span className="text-xs font-medium text-foreground">Run #{execution.id.split('-')[1]}</span>
              </div>
              <span className="text-[10px] text-muted-foreground font-mono">
                {execution.completedAt ? `${((new Date(execution.completedAt).getTime() - new Date(execution.startedAt).getTime()) / 1000).toFixed(1)}s` : '—'}
              </span>
            </div>

            {/* Steps */}
            <div className="space-y-0">
              {execution.steps.map(step => (
                <StepRow key={step.id} step={step} />
              ))}
            </div>
          </div>
        ) : (
          <div className="p-4 space-y-4">
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Workflow Name</label>
              <input
                type="text"
                defaultValue="Lead Qualification Pipeline"
                className="mt-1 w-full h-8 px-3 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Description</label>
              <textarea
                defaultValue="Automatically classify and route incoming leads to the sales team based on AI-driven scoring."
                rows={3}
                className="mt-1 w-full px-3 py-2 text-xs bg-muted border border-border rounded-md text-foreground focus:outline-none focus:ring-1 focus:ring-primary resize-none"
              />
            </div>
            <div>
              <label className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Mode</label>
              <div className="mt-1 flex gap-2">
                <button className="flex-1 h-8 text-xs rounded-md bg-primary/10 text-primary border border-primary/30 font-medium">Edit</button>
                <button className="flex-1 h-8 text-xs rounded-md bg-muted text-muted-foreground border border-border hover:bg-muted/80">Live</button>
              </div>
            </div>
            <div className="pt-2 border-t border-border">
              <div className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Global Variables</div>
              <div className="space-y-1.5 bg-muted/40 rounded-md p-2 border border-border/50 font-mono text-[11px]">
                <div className="flex justify-between"><span className="text-muted-foreground/80">env</span><span className="text-emerald-400">"production"</span></div>
                <div className="flex justify-between"><span className="text-muted-foreground/80">version</span><span className="text-amber-400">2.1</span></div>
                <div className="flex justify-between"><span className="text-muted-foreground/80">debug</span><span className="text-blue-400">false</span></div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Footer */}
      <div className="p-3 border-t border-border">
        <button className="w-full h-9 flex items-center justify-center gap-2 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-colors">
          <PlayCircle className="h-3.5 w-3.5" />
          Run Workflow
        </button>
      </div>
    </div>
  );
}
