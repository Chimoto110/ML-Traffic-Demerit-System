import React, { createContext, useState, useContext, useEffect, useCallback, useMemo } from 'react';
import { appClient } from '@/api/appClient';

const AuthContext = createContext(null);

/**
 * Auth state for the portal-aware login flow.
 * Holds { user, loading, error } plus login/register/logout/refreshUser.
 * Only the API token is persisted (localStorage, managed by appClient) -
 * the user object itself lives in memory for this session.
 */
export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [authChecked, setAuthChecked] = useState(false);

  /** Restore / re-validate the current user from GET /api/auth/me. */
  const refreshUser = useCallback(async () => {
    const token = appClient.auth.getToken();
    if (!token) {
      setUser(null);
      setLoading(false);
      setAuthChecked(true);
      return null;
    }

    setLoading(true);
    try {
      const me = await appClient.auth.me();
      setUser(me);
      setError(null);
      return me;
    } catch (err) {
      // Expired/revoked token - drop it so routes fall back to login.
      if (err?.status === 401) {
        appClient.auth.setToken(null);
      }
      setUser(null);
      return null;
    } finally {
      setLoading(false);
      setAuthChecked(true);
    }
  }, []);

  // On boot: restore the session if a token exists, otherwise settle quickly.
  useEffect(() => {
    refreshUser();
  }, [refreshUser]);

  /** Portal-scoped login (driver | officer | admin). Throws on failure. */
  const login = useCallback(async (portal, email, password) => {
    setError(null);
    const result = await appClient.auth.loginPortal(portal, email, password);
    setUser(result.user);
    return result;
  }, []);

  /** Driver-only self-registration. Throws on validation failure. */
  const register = useCallback(async ({ name, email, password }) => {
    setError(null);
    const result = await appClient.auth.register({ name, email, password });
    setUser(result.user);
    return result;
  }, []);

  const logout = useCallback(async () => {
    setUser(null);
    setError(null);
    await appClient.auth.logout();
  }, []);

  const value = useMemo(
    () => ({
      user,
      loading,
      error,
      setError,
      login,
      register,
      logout,
      refreshUser,
      // Compatibility aliases used by older components.
      isAuthenticated: !!user,
      isLoadingAuth: loading,
      authChecked,
      authError: error,
      isLoadingPublicSettings: false,
      checkUserAuth: refreshUser,
      navigateToLogin: () => appClient.auth.redirectToLogin(user?.role || ''),
    }),
    [user, loading, error, login, register, logout, refreshUser, authChecked]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
