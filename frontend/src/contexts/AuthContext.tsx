import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { fetchCurrentUser, loginUser, logoutUser } from '@/lib/api/client';
import type { CurrentUser } from '@/lib/api/types';
import { STORAGE_KEYS } from '@/lib/api/config';

interface AuthState {
  user: CurrentUser | null;
  token: string;
  apiBaseUrl: string;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  setApiBaseUrl: (url: string) => void;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [token, setToken] = useState(() =>
    typeof window !== 'undefined' ? localStorage.getItem(STORAGE_KEYS.accessToken) ?? '' : '',
  );
  const [apiBaseUrl, setApiBaseUrlState] = useState(() =>
    typeof window !== 'undefined'
      ? localStorage.getItem(STORAGE_KEYS.apiBaseUrl) || '/api/v1'
      : '/api/v1',
  );
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEYS.accessToken, token);
  }, [token]);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEYS.apiBaseUrl, apiBaseUrl);
  }, [apiBaseUrl]);

  const setApiBaseUrl = useCallback((url: string) => {
    setApiBaseUrlState(url);
  }, []);

  useEffect(() => {
    if (!token) {
      setIsLoading(false);
      return;
    }

    fetchCurrentUser(apiBaseUrl, token)
      .then((profile) => {
        setUser(profile);
        setIsLoading(false);
      })
      .catch(() => {
        setToken('');
        setUser(null);
        localStorage.removeItem(STORAGE_KEYS.accessToken);
        setIsLoading(false);
      });
  }, [apiBaseUrl, token]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await loginUser(apiBaseUrl, email, password);
    setToken(res.token);
    setUser(res.user);
  }, [apiBaseUrl]);

  const logout = useCallback(async () => {
    try {
      if (token) {
        await logoutUser(apiBaseUrl, token);
      }
    } catch {
      // best-effort — clear local state regardless
    }
    setToken('');
    setUser(null);
    localStorage.removeItem(STORAGE_KEYS.accessToken);
  }, [apiBaseUrl, token]);

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        apiBaseUrl,
        isAuthenticated: !!user && !!token,
        isLoading,
        login,
        logout,
        setApiBaseUrl,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
}
