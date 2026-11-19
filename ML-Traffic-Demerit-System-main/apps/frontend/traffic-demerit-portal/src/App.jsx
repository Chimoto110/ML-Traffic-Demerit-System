import { Toaster } from "@/components/ui/toaster"
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClientInstance } from '@/lib/query-client'
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import PageNotFound from './lib/PageNotFound';
import { AuthProvider, useAuth } from '@/lib/AuthContext';
import ScrollToTop from './components/ScrollToTop';
import ProtectedRoute from '@/components/ProtectedRoute';
import Login from '@/pages/Login';
import Register from '@/pages/Register';
import ForgotPassword from '@/pages/ForgotPassword';
import ResetPassword from '@/pages/ResetPassword';
import Home from '@/pages/Home';
import About from '@/pages/About';
import DriverDashboard from '@/pages/DriverDashboard';
import OfficerDashboard from '@/pages/OfficerDashboard';
import AdminDashboard from '@/pages/AdminDashboard';
import MlInsightsPage from '@/pages/MlInsightsPage';
import DevGauge from '@/pages/DevGauge';
// Add page imports here

/**
 * Wraps a portal dashboard: the route is already role-guarded by
 * ProtectedRoute, so the user is guaranteed to match `role`.
 * Dashboards keep their existing props; role switching is gone.
 */
const PortalPage = ({ component: Component, role }) => {
  const { user } = useAuth();
  if (!user) return null;
  return <Component user={user} effectiveRole={role} />;
};

const BootSpinner = () => (
  <div className="fixed inset-0 app-shell ambient-grid flex items-center justify-center">
    <div className="glass-panel px-6 py-5 flex items-center gap-3">
      <div className="w-7 h-7 border-[3px] border-cyan-200 border-t-cyan-700 rounded-full animate-spin"></div>
      <div>
        <div className="text-sm font-semibold text-slate-100">Loading workspace</div>
        <div className="text-xs text-slate-300">Preparing your traffic operations console</div>
      </div>
    </div>
  </div>
);

const AuthenticatedApp = () => {
  const { loading, authChecked } = useAuth();

  // One session restore pass on boot (token → /api/auth/me).
  if (loading || !authChecked) {
    return <BootSpinner />;
  }

  return (
    <Routes>
      {/* Public */}
      <Route path="/" element={<Home />} />
      <Route path="/login" element={<Login />} />
      <Route path="/login/:portal" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/forgot-password" element={<ForgotPassword />} />
      <Route path="/reset-password" element={<ResetPassword />} />
      <Route path="/about" element={<About />} />

      {/* Portal dashboards - protected, role-specific */}
      <Route element={<ProtectedRoute role="driver" />}>
        <Route path="/driver" element={<PortalPage component={DriverDashboard} role="driver" />} />
      </Route>
      <Route element={<ProtectedRoute role="officer" />}>
        <Route path="/officer" element={<PortalPage component={OfficerDashboard} role="officer" />} />
      </Route>
      <Route element={<ProtectedRoute role="admin" />}>
        <Route path="/admin" element={<PortalPage component={AdminDashboard} role="admin" />} />
        <Route path="/ml-insights" element={<MlInsightsPage />} />
      </Route>

      {/* Dev pages */}
      <Route path="/dev/gauge" element={<DevGauge />} />

      {/* Catch-all */}
      <Route path="*" element={<PageNotFound />} />
    </Routes>
  );
};


function App() {

  return (
    <AuthProvider>
      <QueryClientProvider client={queryClientInstance}>
        <Router>
          <ScrollToTop />
          <AuthenticatedApp />
        </Router>
        <Toaster />
      </QueryClientProvider>
    </AuthProvider>
  )
}

export default App
