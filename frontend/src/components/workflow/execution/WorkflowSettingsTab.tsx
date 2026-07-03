// Static workflow settings tab (name / description / mode / global variables).
// Placeholder UI — values are not yet wired to state.
export function WorkflowSettingsTab() {
  return (
    <div className="h-full overflow-y-auto p-4 space-y-4">
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
  );
}
