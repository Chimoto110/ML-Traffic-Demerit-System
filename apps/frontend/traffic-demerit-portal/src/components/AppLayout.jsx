import React, { useState } from "react";
import { Shield, Users, Scale, FileBarChart, LogOut, BadgeAlert, Building2, Bell, ScrollText, CreditCard } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { appClient } from "@/api/appClient";

const logo = new URL("../assets/traffic-demerit-logo.svg", import.meta.url).href;
const operationsGridPhoto = new URL("../assets/operations-grid-photo.svg", import.meta.url).href;

const WEBSITE_BACKGROUND_IMAGE = "https://images.pexels.com/photos/15496495/pexels-photo-15496495.jpeg?auto=compress&cs=tinysrgb&w=1800";

const NAV = {
  officer: [{ icon: Shield, label: "Log Violation", id: "log" }, { icon: FileBarChart, label: "Recent Violations", id: "recent" }, { icon: CreditCard, label: "Payments", id: "payments" }],
  driver: [{ icon: Scale, label: "Demerit Ledger", id: "ledger" }, { icon: BadgeAlert, label: "Sanctions", id: "sanctions" }, { icon: Bell, label: "Notifications", id: "notifications" }, { icon: CreditCard, label: "Payments", id: "payments" }, { icon: Shield, label: "Submit Appeal", id: "appeal" }],
  admin: [{ icon: Users, label: "Drivers", id: "drivers" }, { icon: Shield, label: "Violations", id: "violations" }, { icon: BadgeAlert, label: "Sanctions", id: "sanctions" }, { icon: CreditCard, label: "Payments", id: "payments" }, { icon: Bell, label: "Notifications", id: "notifications" }, { icon: Scale, label: "Appeals", id: "appeals" }, { icon: Building2, label: "Operations", id: "operations" }, { icon: ScrollText, label: "Audit Trail", id: "audit" }, { icon: FileBarChart, label: "Compliance Reports", id: "reports" }],
};

const ROLE_META = {
  officer: { label: "Traffic Officer", color: "text-cyan-200", bg: "bg-cyan-500/15" },
  driver: { label: "Motorist", color: "text-emerald-200", bg: "bg-emerald-500/15" },
  admin: { label: "NTSA Administrator", color: "text-orange-200", bg: "bg-orange-500/15" },
};

export default function AppLayout({ role, effectiveRole, onRoleChange, active, onNavigate, children }) {
  const meta = ROLE_META[effectiveRole] ?? ROLE_META.driver;
  const items = NAV[effectiveRole] ?? [];
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const now = new Date().toLocaleDateString(undefined, { weekday: "short", month: "short", day: "numeric", year: "numeric" });

  const RoleSwitcher = () => (
    <div className="flex gap-1">
      {["admin", "officer", "driver"].map((r) => (
        <button
          key={r}
          onClick={() => onRoleChange(r)}
          className={`flex-1 text-[11px] py-1.5 rounded-md font-medium capitalize transition-colors ${
            effectiveRole === r ? "bg-orange-500 text-white" : "bg-slate-800 text-slate-200 hover:bg-slate-700"
          }`}
        >
          {r}
        </button>
      ))}
    </div>
  );

  return (
    <div className="app-shell ambient-grid">
      <div
        className="pointer-events-none absolute inset-0 bg-cover bg-center opacity-55"
        style={{ backgroundImage: `url(${WEBSITE_BACKGROUND_IMAGE})` }}
      />
      <div className="pointer-events-none absolute inset-0 bg-slate-950/52" />
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -left-20 top-20 h-60 w-60 rounded-full bg-orange-400/25 blur-3xl" />
        <div className="absolute -right-16 top-16 h-64 w-64 rounded-full bg-cyan-400/25 blur-3xl" />
      </div>

      <div className="relative z-10 md:flex md:gap-5 md:px-4 md:py-4 lg:gap-6 lg:px-6">
        <div className="md:hidden sticky top-0 z-30 border-b border-cyan-100/20 bg-slate-950/80 px-4 py-3 backdrop-blur">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <img src={logo} alt="Traffic Demerit System logo" className="h-8 w-8 rounded-lg" />
              <span className="brand-title text-sm font-semibold">Traffic Demerit System</span>
            </div>
            <Button variant="outline" size="sm" className="border-cyan-100/20 bg-slate-900/70 text-slate-100 hover:bg-slate-800" onClick={() => setMobileNavOpen((s) => !s)}>Menu</Button>
          </div>

          {mobileNavOpen && (
            <div className="mt-3 space-y-3 enter-up">
              <Select value={active} onValueChange={(v) => { onNavigate(v); setMobileNavOpen(false); }}>
                <SelectTrigger className="h-9 border-cyan-100/20 bg-slate-900/85 text-slate-100"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {items.map((it) => <SelectItem key={it.id} value={it.id}>{it.label}</SelectItem>)}
                </SelectContent>
              </Select>
              {role === "admin" && <div><div className="text-[10px] uppercase text-slate-300 font-semibold mb-1">Demo role</div><RoleSwitcher /></div>}
              <div className={`px-3 py-1.5 rounded-lg ${meta.bg} flex items-center justify-between`}>
                <span className="text-[10px] uppercase text-slate-300 font-semibold">Signed in as</span>
                <span className={`text-xs font-semibold ${meta.color}`}>{meta.label}</span>
              </div>
              <Button variant="outline" size="sm" className="w-full border-cyan-100/20 bg-slate-900/70 text-slate-100 hover:bg-slate-800" onClick={() => appClient.auth.logout("/login")}>Sign out</Button>
            </div>
          )}
        </div>

        <aside className="hidden md:flex w-72 shrink-0 flex-col glass-panel h-[calc(100vh-2rem)] sticky top-4 overflow-hidden">
          <div className="px-5 py-5 border-b border-cyan-100/15">
            <div className="flex items-center gap-2">
              <img src={logo} alt="Traffic Demerit System logo" className="w-10 h-10 rounded-xl" />
              <div>
                <div className="brand-title text-base font-bold leading-tight">Traffic Demerit System</div>
                <div className="text-[11px] text-slate-300 tracking-wide">Safety and Compliance Grid</div>
              </div>
            </div>
          </div>

          <div className="px-4 py-3">
            <div className="hero-chip">National Traffic Operations</div>
          </div>

          <nav className="flex-1 px-3 pb-4 space-y-1 overflow-y-auto">
            {items.map((it) => (
              <button
                key={it.id}
                onClick={() => onNavigate(it.id)}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                  active === it.id ? "bg-orange-500 text-white shadow-lg shadow-orange-900/40" : "text-slate-200 hover:bg-cyan-400/10"
                }`}
              >
                <span className={`rounded-lg p-1 ${active === it.id ? "bg-white/20" : "bg-slate-700/60"}`}>
                  <it.icon className="w-4 h-4" />
                </span>
                {it.label}
              </button>
            ))}
          </nav>

          <div className="px-3 py-3 border-t border-cyan-100/15 space-y-2 bg-slate-900/45">
            {role === "admin" && (
              <div className="px-2">
                <div className="text-[10px] uppercase tracking-wide text-slate-300 mb-1.5 font-semibold">Demo role</div>
                <RoleSwitcher />
              </div>
            )}
            <div className={`mx-1 px-3 py-2 rounded-lg ${meta.bg} border border-cyan-100/15`}>
              <div className="text-[10px] uppercase tracking-wide text-slate-300 font-semibold">Signed in as</div>
              <div className={`text-xs font-semibold ${meta.color}`}>{meta.label}</div>
            </div>
            <Button variant="ghost" size="sm" className="w-full justify-start text-slate-200 hover:bg-cyan-400/10" onClick={() => appClient.auth.logout("/login")}>
              <LogOut className="w-4 h-4 mr-2" /> Sign out
            </Button>
          </div>
        </aside>

        <main className="flex-1 min-w-0">
          <div className="mx-auto max-w-[1180px] px-4 py-4 md:px-0 md:py-0">
            <div className="glass-panel enter-up min-h-[calc(100vh-2rem)] p-4 md:p-6 bg-white/92 border-white/80 text-slate-900">
              <div className="mb-5 rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <span className="inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    <span className="text-xs font-semibold uppercase tracking-wide text-slate-600">National Control Desk</span>
                  </div>
                  <div className="text-xs text-slate-500">{meta.label} · {now}</div>
                </div>
              </div>

              <div className="mb-5 overflow-hidden rounded-xl border border-slate-200/80">
                <img
                  src={operationsGridPhoto}
                  alt="Operations grid traffic map"
                  className="h-24 w-full object-cover md:h-28"
                />
              </div>

              {children}
            </div>
          </div>
        </main>
      </div>
    </div>
  );
}