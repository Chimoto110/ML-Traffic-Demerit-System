import React from "react";
import { Link, Navigate } from "react-router-dom";
import { useAuth } from "@/lib/AuthContext";
import { Car, Shield, Building2, UserPlus, Info } from "lucide-react";

const logo = new URL("../assets/traffic-demerit-logo.svg", import.meta.url).href;

const PORTALS = [
  {
    to: "/login/driver",
    label: "Driver Login",
    description: "Demerit ledger, sanctions, appeals and payments",
    icon: Car,
    iconClass: "bg-emerald-500/15 text-emerald-200 group-hover:bg-emerald-500/25",
    chipClass: "bg-emerald-500/10 border-emerald-200/25 text-emerald-100",
    chipLabel: "Motorists",
  },
  {
    to: "/login/officer",
    label: "Officer Login",
    description: "Log violations and review enforcement records",
    icon: Shield,
    iconClass: "bg-cyan-500/15 text-cyan-200 group-hover:bg-cyan-500/25",
    chipClass: "bg-cyan-500/10 border-cyan-200/25 text-cyan-100",
    chipLabel: "Enforcement",
  },
  {
    to: "/login/admin",
    label: "Admin Login",
    description: "National console, compliance reports and ML insights",
    icon: Building2,
    iconClass: "bg-orange-500/15 text-orange-200 group-hover:bg-orange-500/25",
    chipClass: "bg-orange-500/10 border-orange-200/25 text-orange-100",
    chipLabel: "Administration",
  },
];

export default function Home() {
  const { user, loading } = useAuth();

  // Signed in → straight to your portal.
  if (!loading && user) {
    return <Navigate to={["driver", "officer", "admin"].includes(user.role) ? `/${user.role}` : "/"} replace />;
  }

  if (loading) {
    return (
      <div className="fixed inset-0 app-shell ambient-grid flex items-center justify-center">
        <div className="glass-panel px-6 py-5 flex items-center gap-3">
          <div className="w-7 h-7 border-[3px] border-cyan-200 border-t-cyan-700 rounded-full animate-spin"></div>
          <div className="text-sm font-semibold text-slate-100">Loading…</div>
        </div>
      </div>
    );
  }

  return (
    <div className="app-shell ambient-grid min-h-screen">
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -left-20 top-10 h-72 w-72 rounded-full bg-orange-400/25 blur-3xl" />
        <div className="absolute -right-16 top-16 h-72 w-72 rounded-full bg-cyan-400/25 blur-3xl" />
      </div>

      <div className="relative z-10 mx-auto flex min-h-screen w-full max-w-5xl flex-col px-4 py-6 md:px-6">
        <header className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <img src={logo} alt="Traffic Demerit System logo" className="h-10 w-10 rounded-xl" />
            <div>
              <div className="brand-title text-base font-bold leading-tight">Traffic Demerit System</div>
              <div className="text-[11px] text-slate-300 tracking-wide">Safety and Compliance Grid</div>
            </div>
          </div>
          <Link to="/about" className="flex items-center gap-1.5 text-xs font-medium text-slate-200 hover:text-white">
            <Info className="w-4 h-4" aria-hidden="true" /> About us
          </Link>
        </header>

        <main className="flex flex-1 flex-col items-center justify-center py-12 text-center">
          <span className="hero-chip">National Traffic Operations</span>
          <h1 className="mt-5 max-w-3xl font-display text-4xl font-bold leading-tight text-orange-50 md:text-5xl">
            Traffic Demerit Management System
          </h1>
          <p className="mt-4 max-w-2xl text-sm text-slate-200/90 md:text-base">
            Machine-assisted enforcement, demerit tracking and compliance workflows for drivers,
            traffic officers and NTSA administrators. Choose your portal to continue.
          </p>

          <div className="mt-10 grid w-full max-w-3xl gap-4 sm:grid-cols-3">
            {PORTALS.map((portal) => (
              <Link
                key={portal.to}
                to={portal.to}
                className="group glass-panel flex flex-col items-center gap-3 px-5 py-6 text-center transition-all hover:-translate-y-1 hover:border-cyan-100/45 hover:bg-slate-900/85"
              >
                <span className={`inline-flex h-14 w-14 items-center justify-center rounded-2xl transition-colors ${portal.iconClass}`}>
                  <portal.icon className="h-7 w-7" aria-hidden="true" />
                </span>
                <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${portal.chipClass}`}>
                  {portal.chipLabel}
                </span>
                <span className="font-display text-lg font-bold text-orange-50">{portal.label}</span>
                <span className="text-xs text-slate-300">{portal.description}</span>
              </Link>
            ))}
          </div>

          <div className="mt-8 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-slate-300">
            <span>New driver?</span>
            <Link
              to="/register"
              className="inline-flex items-center gap-2 rounded-lg border border-cyan-100/25 bg-cyan-300/10 px-4 py-2 font-semibold text-cyan-100 transition-colors hover:bg-cyan-300/20"
            >
              <UserPlus className="w-4 h-4" aria-hidden="true" />
              Register (Drivers)
            </Link>
          </div>
        </main>

        <footer className="border-t border-cyan-100/15 pt-4 text-center text-xs text-slate-400">
          © {new Date().getFullYear()} Traffic Demerit Management System · Road safety through accountable driving
        </footer>
      </div>
    </div>
  );
}
