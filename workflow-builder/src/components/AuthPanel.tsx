import { DEFAULT_ACCESS_TOKEN, DEFAULT_API_BASE_URL } from '../config';
import { normalizeToken } from '../utils';

interface AuthPanelProps {
  apiBaseUrl: string;
  accessToken: string;
  onApiBaseUrlChange: (value: string) => void;
  onAccessTokenChange: (value: string) => void;
  onLoadNodes: () => void;
  onClearSavedSettings: () => void;
  loading: boolean;
  error: string | null;
}

export function AuthPanel({
  apiBaseUrl,
  accessToken,
  onApiBaseUrlChange,
  onAccessTokenChange,
  onLoadNodes,
  onClearSavedSettings,
  loading,
  error,
}: AuthPanelProps) {
  const cleanToken = normalizeToken(accessToken);
  const requestUrl = `${apiBaseUrl.replace(/\/$/, '')}/workflows/nodes`;

  return (
    <section className="panel auth-panel">
      <h2>API connection</h2>
      <p className="hint">
        Hard-code defaults in <code>src/config.ts</code>, or paste a JWT below. Requires Manager or
        Business Owner role.
      </p>

      <label>
        API base URL
        <input
          type="url"
          value={apiBaseUrl}
          onChange={(event) => onApiBaseUrlChange(event.target.value)}
          placeholder={DEFAULT_API_BASE_URL}
        />
      </label>

      <label>
        Access token
        <textarea
          rows={3}
          value={accessToken}
          onChange={(event) => onAccessTokenChange(event.target.value)}
          placeholder={DEFAULT_ACCESS_TOKEN || 'Paste JWT token here'}
        />
      </label>

      <div className="auth-actions">
        <button type="button" onClick={onLoadNodes} disabled={loading || !cleanToken}>
          {loading ? 'Loading nodes…' : 'Load node library'}
        </button>
        <button type="button" className="secondary-button" onClick={onClearSavedSettings}>
          Reset saved settings
        </button>
      </div>

      <div className="auth-debug">
        <small>Request URL: {requestUrl}</small>
        <small>Token length: {cleanToken ? cleanToken.length : 0} chars</small>
      </div>

      {error ? <p className="error">{error}</p> : null}
    </section>
  );
}
