import { useEffect } from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/lib/AuthContext';
import { appClient } from '@/api/appClient';

export const PORTAL_PATH = {
  driver: '/driver',
  officer: '/officer',
  admin: '/admin',
};

const DefaultFallback = () => (
  <div className="fixed inset-0 app-shell ambient-grid flex items-center justify-center">
    <div className="glass-panel px-6 py-5 flex items-center gap-3">
      <div className="w-7 h-7 border-[3px] border-cyan-200 border-t-cyan-700 rounded-full animate-spin"></div>
      <div className="text-sm font-semibold text-slate-100">Checking your session…</div>
    </div>
  </div>
);

/**
 * Guards a portal route.
 * - no token            → redirect to /login/{requiredRole}
 * - token present       → validate via appClient.auth.me() (spinner meanwhile)
 * - /me fails (401)     → token cleared by AuthContext → redirect to login
 * - role mismatch       → redirect the user to their own portal
 */
export default function ProtectedRoute({ role, fallback = <DefaultFallback /> }) {
  const { user, loading, authChecked, refreshUser } = useAuth();
  const hasToken = Boolean(appClient.auth.getToken());

  useEffect(() => {
    if (hasToken && !authChecked && !loading) {
      refreshUser();
    }
  }, [hasToken, authChecked, loading, refreshUser]);

  if (!hasToken) {
    return <Navigate to={`/login/${role}`} replace />;
  }

  if (loading || !authChecked) {
    return fallback;
  }

  if (!user) {
    return <Navigate to={`/login/${role}`} replace />;
  }

  if (role && user.role !== role) {
    return <Navigate to={PORTAL_PATH[user.role] || '/'} replace />;
  }

  return <Outlet />;
}
