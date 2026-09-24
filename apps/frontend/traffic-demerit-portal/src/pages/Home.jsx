import React, { useState, useMemo } from "react";
import { useAuth } from "@/lib/AuthContext";
import OfficerDashboard from "@/pages/OfficerDashboard";
import DriverDashboard from "@/pages/DriverDashboard";
import AdminDashboard from "@/pages/AdminDashboard";

export default function Home() {
  const { user, isLoadingAuth, isLoadingPublicSettings } = useAuth();
  const [effectiveRole, setEffectiveRole] = useState(null);

  const resolvedRole = useMemo(() => {
    if (!user) return null;
    return effectiveRole || user.role || "driver";
  }, [effectiveRole, user]);

  if (isLoadingAuth || isLoadingPublicSettings || !user || !resolvedRole) {
    return (
      <div className="fixed inset-0 app-shell ambient-grid flex items-center justify-center">
        <div className="glass-panel px-6 py-5 flex items-center gap-3">
          <div className="w-7 h-7 border-[3px] border-cyan-200 border-t-cyan-700 rounded-full animate-spin"></div>
          <div className="text-sm font-semibold text-slate-800">Loading dashboard</div>
        </div>
      </div>
    );
  }

  const props = { user, effectiveRole: resolvedRole, onRoleChange: setEffectiveRole };

  if (resolvedRole === "officer") return <OfficerDashboard {...props} />;
  if (resolvedRole === "driver") return <DriverDashboard {...props} />;
  return <AdminDashboard {...props} />;
}