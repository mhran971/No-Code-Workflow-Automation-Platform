// Recursively renders an execution step's output variables as a colorized JSON tree.
export function VariableTree({ data, depth = 0 }: { data: unknown; depth?: number }) {
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
