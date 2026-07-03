import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ValidationResult, WorkflowDefinition } from '@/lib/api/types';

interface ValidationResultsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  result: ValidationResult | null;
  error: string | null;
  loading: boolean;
  definition: WorkflowDefinition | null;
}

export function ValidationResultsDialog({
  open,
  onOpenChange,
  result,
  error,
  loading,
  definition,
}: ValidationResultsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[85vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>Workflow Verification</DialogTitle>
          <DialogDescription>
            Result from POST /workflows/validate
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className="flex-1 pr-4 -mr-4">
          <div className="space-y-4 pb-2">
            {loading && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Sending definition to backend…
              </div>
            )}

            {error && (
              <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-3">
                <XCircle className="h-4 w-4 text-destructive shrink-0 mt-0.5" />
                <p className="text-sm text-destructive">{error}</p>
              </div>
            )}

            {result && (
              <>
                <div
                  className={`flex items-center gap-2 rounded-md border p-3 ${
                    result.is_publishable
                      ? 'border-success/30 bg-success/10'
                      : 'border-destructive/30 bg-destructive/10'
                  }`}
                >
                  {result.is_publishable ? (
                    <CheckCircle2 className="h-4 w-4 text-success" />
                  ) : (
                    <XCircle className="h-4 w-4 text-destructive" />
                  )}
                  <div>
                    <p className="text-sm font-medium">
                      {result.is_publishable ? 'Publishable' : 'Not publishable'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {result.summary.errors} error(s), {result.summary.warnings} warning(s)
                    </p>
                  </div>
                </div>

                {result.errors.length > 0 && (
                  <div className="space-y-2">
                    <h4 className="text-xs font-semibold text-foreground">Errors</h4>
                    <ul className="space-y-1">
                      {result.errors.map((message) => (
                        <li key={message} className="text-xs text-destructive">
                          {message}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {result.warnings.length > 0 && (
                  <div className="space-y-2">
                    <h4 className="text-xs font-semibold text-foreground">Warnings</h4>
                    <ul className="space-y-1">
                      {result.warnings.map((message) => (
                        <li key={message} className="text-xs text-warning">
                          {message}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {(() => {
                  const workflowIssues = result.issues.filter(
                    (issue) => !(issue.location?.node_id ?? issue.node_id),
                  );
                  const nodeIssueCount = result.issues.length - workflowIssues.length;
                  return (
                    <>
                      {nodeIssueCount > 0 && (
                        <p className="text-[11px] text-muted-foreground italic">
                          {nodeIssueCount} node-specific error{nodeIssueCount !== 1 ? 's' : ''} — open the node to view details.
                        </p>
                      )}
                      {workflowIssues.length > 0 && (
                        <div className="space-y-2">
                          <h4 className="text-xs font-semibold text-foreground">Workflow Issues</h4>
                          <ul className="space-y-2">
                            {workflowIssues.map((issue, index) => (
                              <li
                                key={`${issue.code}-${index}`}
                                className="rounded-md border border-border bg-muted/30 p-2 space-y-1"
                              >
                                <div className="flex items-center gap-2">
                                  <Badge variant="outline" className="text-[10px] h-5">
                                    {issue.severity}
                                  </Badge>
                                  <code className="text-[10px] text-muted-foreground">{issue.code}</code>
                                </div>
                                <p className="text-xs">{issue.message}</p>
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}
                    </>
                  );
                })()}
              </>
            )}

            {definition && (
              <details className="text-xs">
                <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                  Payload sent to API
                </summary>
                <pre className="mt-2 rounded-md bg-muted p-3 overflow-auto max-h-64 text-[10px] font-mono">
                  {JSON.stringify({ definition }, null, 2)}
                </pre>
              </details>
            )}
          </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
}
