import { useCallback, useEffect, useState } from 'react';
import { fetchNodeLibrary } from '@/lib/api/client';
import { DEFAULT_ACCESS_TOKEN, DEFAULT_API_BASE_URL, STORAGE_KEYS } from '@/lib/api/config';
import type { ApiNodeDefinition } from '@/lib/api/types';
import { formatApiError, readStoredValue } from '@/lib/api/utils';

export function useApiConfig() {
  const [apiBaseUrl, setApiBaseUrl] = useState(() =>
    readStoredValue(STORAGE_KEYS.apiBaseUrl, DEFAULT_API_BASE_URL),
  );
  const [accessToken, setAccessToken] = useState(() =>
    readStoredValue(STORAGE_KEYS.accessToken, DEFAULT_ACCESS_TOKEN),
  );
  const [nodeDefinitions, setNodeDefinitions] = useState<ApiNodeDefinition[]>([]);
  const [isConnected, setIsConnected] = useState(false);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEYS.apiBaseUrl, apiBaseUrl);
  }, [apiBaseUrl]);

  useEffect(() => {
    window.localStorage.setItem(STORAGE_KEYS.accessToken, accessToken);
  }, [accessToken]);

  const validateConnection = useCallback(async () => {
    setIsValidating(true);
    setConnectionError(null);

    try {
      const response = await fetchNodeLibrary(apiBaseUrl, accessToken);
      setNodeDefinitions(response.data);
      setIsConnected(true);
      return true;
    } catch (error) {
      setIsConnected(false);
      setNodeDefinitions([]);
      setConnectionError(formatApiError(error));
      return false;
    } finally {
      setIsValidating(false);
    }
  }, [apiBaseUrl, accessToken]);

  const resetSettings = useCallback(() => {
    window.localStorage.removeItem(STORAGE_KEYS.apiBaseUrl);
    window.localStorage.removeItem(STORAGE_KEYS.accessToken);
    setApiBaseUrl(DEFAULT_API_BASE_URL);
    setAccessToken(DEFAULT_ACCESS_TOKEN);
    setNodeDefinitions([]);
    setIsConnected(false);
    setConnectionError(null);
  }, []);

  return {
    apiBaseUrl,
    setApiBaseUrl,
    accessToken,
    setAccessToken,
    nodeDefinitions,
    isConnected,
    connectionError,
    isValidating,
    dialogOpen,
    setDialogOpen,
    validateConnection,
    resetSettings,
  };
}

export type ApiConfigState = ReturnType<typeof useApiConfig>;
