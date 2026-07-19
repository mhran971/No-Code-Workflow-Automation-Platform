import { useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { fetchNodeLibrary } from '@/lib/api/client';
import type { ApiNodeDefinition } from '@/lib/api/types';
import { formatApiError } from '@/lib/api/utils';

export function useApiConfig() {
  const { token, apiBaseUrl } = useAuth();

  const [nodeDefinitions, setNodeDefinitions] = useState<ApiNodeDefinition[]>([]);
  const [isConnected, setIsConnected] = useState(false);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [isValidating, setIsValidating] = useState(false);

  const validateConnection = useCallback(async () => {
    if (!token) {
      setIsConnected(false);
      setNodeDefinitions([]);
      return false;
    }

    setIsValidating(true);
    setConnectionError(null);

    try {
      const response = await fetchNodeLibrary(apiBaseUrl, token);
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
  }, [apiBaseUrl, token]);

  useEffect(() => {
    if (token) {
      validateConnection();
    } else {
      setIsConnected(false);
      setNodeDefinitions([]);
    }
  }, [token, validateConnection]);

  return {
    nodeDefinitions,
    isConnected,
    connectionError,
    isValidating,
    validateConnection,
  };
}

export type ApiConfigState = ReturnType<typeof useApiConfig>;
