import type { ValidationResult, WorkflowDefinition } from '../types';

interface ValidationResultsProps {
  definition: WorkflowDefinition | null;
  result: ValidationResult | null;
  error: string | null;
  loading: boolean;
}

export function ValidationResults({ definition, result, error, loading }: ValidationResultsProps) {
  return (
    <section className="panel validation-panel">
      <h2>Verification</h2>

      {loading ? <p className="hint">Sending definition to backend…</p> : null}
      {error ? <p className="error">{error}</p> : null}

      {result ? (
        <div className={`validation-summary ${result.is_publishable ? 'valid' : 'invalid'}`}>
          <strong>{result.is_publishable ? 'Publishable' : 'Not publishable'}</strong>
          <span>
            {result.summary.errors} error(s), {result.summary.warnings} warning(s)
          </span>
        </div>
      ) : null}

      {result?.errors.length ? (
        <div className="issue-group">
          <h3>Errors</h3>
          <ul>
            {result.errors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {result?.warnings.length ? (
        <div className="issue-group">
          <h3>Warnings</h3>
          <ul>
            {result.warnings.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {result?.issues.length ? (
        <div className="issue-group">
          <h3>Issues</h3>
          <ul className="issue-details">
            {result.issues.map((issue, index) => (
              <li key={`${issue.code}-${index}`}>
                <span className={`severity ${issue.severity}`}>{issue.severity}</span>
                <code>{issue.code}</code>
                <p>{issue.message}</p>
                {issue.location?.node_id || issue.node_id ? (
                  <small>Node: {issue.location?.node_id ?? issue.node_id}</small>
                ) : null}
                {issue.location?.path || issue.path ? (
                  <small>Path: {issue.location?.path ?? issue.path}</small>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {definition ? (
        <details>
          <summary>Payload sent to POST /workflows/validate</summary>
          <pre>{JSON.stringify({ definition }, null, 2)}</pre>
        </details>
      ) : null}

      {result ? (
        <details>
          <summary>Raw response</summary>
          <pre>{JSON.stringify(result, null, 2)}</pre>
        </details>
      ) : null}
    </section>
  );
}
