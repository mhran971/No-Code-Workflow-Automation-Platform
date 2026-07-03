import { CheckCircle2, Loader2, Plug } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_API_BASE_URL } from '@/lib/api/config';
import { normalizeToken } from '@/lib/api/utils';
import type { ApiConfigState } from '@/hooks/useApiConfig';

interface ApiConnectionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  apiConfig: ApiConfigState;
}

export function ApiConnectionDialog({ open, onOpenChange, apiConfig }: ApiConnectionDialogProps) {
  const {
    apiBaseUrl,
    setApiBaseUrl,
    accessToken,
    setAccessToken,
    isConnected,
    connectionError,
    isValidating,
    validateConnection,
    resetSettings,
    nodeDefinitions,
  } = apiConfig;

  const cleanToken = normalizeToken(accessToken);
  const requestUrl = `${apiBaseUrl.replace(/\/$/, '')}/workflows/nodes`;

  const handleValidate = async () => {
    const success = await validateConnection();
    if (success) {
      onOpenChange(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plug className="h-4 w-4 text-primary" />
            API Connection
          </DialogTitle>
          <DialogDescription>
            Connect to your Laravel workflow API. Requires a JWT from Manager or Business Owner login.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="api-base-url">API base URL</Label>
            <Input
              id="api-base-url"
              type="url"
              value={apiBaseUrl}
              onChange={(event) => setApiBaseUrl(event.target.value)}
              placeholder={DEFAULT_API_BASE_URL}
            />
            <p className="text-[11px] text-muted-foreground">
              Must end with <code className="text-foreground/80">/api/v1</code>. Dev proxy:{' '}
              <code className="text-foreground/80">/api/v1</code>
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="access-token">Access token</Label>
            <Textarea
              id="access-token"
              rows={3}
              value={accessToken}
              onChange={(event) => setAccessToken(event.target.value)}
              placeholder="Paste JWT token here"
              className="font-mono text-xs"
            />
          </div>

          <div className="rounded-md bg-muted/50 px-3 py-2 space-y-1">
            <p className="text-[11px] text-muted-foreground">
              Request URL: <span className="font-mono text-foreground/80">{requestUrl}</span>
            </p>
            <p className="text-[11px] text-muted-foreground">
              Token length: {cleanToken ? `${cleanToken.length} chars` : '0 chars'}
            </p>
            {isConnected && (
              <p className="text-[11px] text-success flex items-center gap-1">
                <CheckCircle2 className="h-3 w-3" />
                Connected — {nodeDefinitions.length} nodes loaded
              </p>
            )}
          </div>

          {connectionError && (
            <p className="text-xs text-destructive">{connectionError}</p>
          )}
        </div>

        <DialogFooter className="flex-col sm:flex-row gap-2">
          <Button type="button" variant="outline" onClick={resetSettings} className="sm:mr-auto">
            Reset saved settings
          </Button>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            onClick={handleValidate}
            disabled={isValidating || !cleanToken}
          >
            {isValidating ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                Validating…
              </>
            ) : (
              'Validate connection'
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
