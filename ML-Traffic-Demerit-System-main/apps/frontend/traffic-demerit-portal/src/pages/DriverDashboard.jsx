import React, { useCallback, useEffect, useMemo, useState } from "react";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import DataTable from "@/components/crud/DataTable";
import SanctionCard from "@/components/SanctionCard";
import RiskExplanationPanel from "@/components/RiskExplanationPanel";
import NotificationCenter from "@/components/NotificationCenter";
import { ChartCard, GaugeChart, LineChart } from "@/components/charts";
import { toast, ToastStack } from "@/components/ui/toasts";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel } from "@/lib/constants";

const FINE_PER_POINT = 500; // KES - mirrors PaymentController::$finePerPoint

export default function DriverDashboard({ user, effectiveRole, onRoleChange }) {
  const [active, setActive] = useState("ledger");

  /* ---------------- driver profile (own) ---------------- */
  const [profile, setProfile] = useState(null);
  const [profileLoading, setProfileLoading] = useState(true);
  const [profileError, setProfileError] = useState("");

  const loadProfile = useCallback(async () => {
    setProfileLoading(true);
    setProfileError("");
    try {
      const payload = await appClient.crud.drivers.list({ filter: { user_id: user?.id }, per_page: 1 });
      setProfile(payload?.data?.[0] || null);
    } catch (err) {
      setProfileError(err.message || "Could not load your driver profile.");
    } finally {
      setProfileLoading(false);
    }
  }, [user?.id]);
  useEffect(() => { loadProfile(); }, [loadProfile]);

  /* ---------------- MySQL-backed charts (gauge + ledger series) ---------------- */
  const [charts, setCharts] = useState(null);
  const [chartsLoading, setChartsLoading] = useState(true);
  const [chartsError, setChartsError] = useState("");

  const loadCharts = useCallback(async () => {
    setChartsLoading(true);
    setChartsError("");
    try {
      setCharts(await appClient.charts.driver());
    } catch (err) {
      setChartsError(err.message || "Could not load your demerit stats.");
    } finally {
      setChartsLoading(false);
    }
  }, []);
  useEffect(() => { loadCharts(); }, [loadCharts]);

  /* ---------------- own violations (motorist scope is automatic) ---------------- */
  const [violations, setViolations] = useState([]);
  const [violationsLoading, setViolationsLoading] = useState(true);
  const [violationsError, setViolationsError] = useState("");

  const loadViolations = useCallback(async () => {
    setViolationsLoading(true);
    setViolationsError("");
    try {
      const payload = await appClient.crud.violations.list({ per_page: 50, sort: "-created_at" });
      setViolations(payload?.data || []);
    } catch (err) {
      setViolationsError(err.message || "Could not load your violations.");
    } finally {
      setViolationsLoading(false);
    }
  }, []);
  useEffect(() => { loadViolations(); }, [loadViolations]);

  /* ---------------- latest risk record (for the explanation panel) ---------------- */
  const [riskRecord, setRiskRecord] = useState(null);
  useEffect(() => {
    if (!profile?.id) return;
    appClient.entities.RecidivismScore.filter({ driver_id: profile.id }, "-timestamp", 1)
      .then((rows) => setRiskRecord(rows?.[0] || null))
      .catch(() => setRiskRecord(null));
  }, [profile?.id, violations.length]);

  /* ---------------- sanctions ---------------- */
  const [sanctions, setSanctions] = useState([]);
  const [sanctionsLoading, setSanctionsLoading] = useState(true);
  const [sanctionsError, setSanctionsError] = useState("");

  const loadSanctions = useCallback(async () => {
    setSanctionsLoading(true);
    setSanctionsError("");
    try {
      const rows = await appClient.entities.Sanction.filter({ driver_id: profile?.id }, "-triggered_at", 10);
      setSanctions(rows || []);
    } catch (err) {
      setSanctionsError(err.message || "Could not load sanctions.");
    } finally {
      setSanctionsLoading(false);
    }
  }, [profile?.id]);
  useEffect(() => { loadSanctions(); }, [loadSanctions]);

  /* ---------------- notifications ---------------- */
  const [notifications, setNotifications] = useState([]);
  const [notificationsLoading, setNotificationsLoading] = useState(true);
  const [notificationsError, setNotificationsError] = useState("");

  const loadNotifications = useCallback(async () => {
    setNotificationsLoading(true);
    setNotificationsError("");
    try {
      const rows = await appClient.entities.Notification.list("-created_at", 100);
      setNotifications(rows || []);
    } catch (err) {
      setNotificationsError(err.message || "Could not load notifications.");
    } finally {
      setNotificationsLoading(false);
    }
  }, []);
  useEffect(() => { loadNotifications(); }, [loadNotifications]);

  const unreadCount = useMemo(() => notifications.filter((n) => n.status === "unread").length, [notifications]);
  const toggleNotificationRead = async (notification, status) => {
    try {
      await appClient.entities.Notification.update(notification.id, { status });
      setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, status } : n)));
    } catch (err) {
      toast.error("Could not update notification", err.message);
    }
  };

  /* ---------------- payments (M-Pesa) ---------------- */
  const [payments, setPayments] = useState([]);
  const [paymentsLoading, setPaymentsLoading] = useState(true);
  const [paymentsError, setPaymentsError] = useState("");
  const [payingId, setPayingId] = useState(null);
  const [payPhone, setPayPhone] = useState("");

  const loadPayments = useCallback(async () => {
    setPaymentsLoading(true);
    setPaymentsError("");
    try {
      const payload = await appClient.crud.payments.list({ per_page: 50, sort: "-created_at" });
      setPayments(payload?.data || []);
    } catch (err) {
      setPaymentsError(err.message || "Could not load payments.");
    } finally {
      setPaymentsLoading(false);
    }
  }, []);
  useEffect(() => { loadPayments(); }, [loadPayments]);

  const paidViolationIds = useMemo(
    () => new Set(payments.filter((p) => String(p.status).toLowerCase() === "completed").map((p) => Number(p.violation_id))),
    [payments]
  );
  const outstandingViolations = useMemo(
    () => violations.filter((v) => !paidViolationIds.has(Number(v.id))),
    [violations, paidViolationIds]
  );

  const payOutstandingViolation = async (violation, phone) => {
    setPayingId(violation.id);
    try {
      const response = await appClient.payments.initiate(violation.id, "mpesa", phone);
      const payment = response?.payment;
      if (!payment) throw new Error("The payment could not be initiated.");

      // Poll the status endpoint (simulate mode completes quickly).
      let finalPayment = payment;
      for (let i = 0; i < 6; i++) {
        await new Promise((r) => setTimeout(r, 1000));
        const statusResp = await appClient.payments.status(payment.id);
        finalPayment = statusResp?.payment || statusResp;
        if (String(finalPayment?.status).toLowerCase() === "completed") break;
        if (String(finalPayment?.status).toLowerCase() === "failed") break;
      }

      if (String(finalPayment?.status).toLowerCase() === "completed") {
        toast.success("Payment confirmed", `KES ${Number(finalPayment.amount || 0).toLocaleString()} received for violation #${violation.id}.`);
      } else if (String(finalPayment?.status).toLowerCase() === "failed") {
        throw new Error(finalPayment?.failure_reason || finalPayment?.result_desc || "Payment failed.");
      } else {
        throw new Error("Payment is still pending. Please check your phone and try again.");
      }

      await Promise.all([loadPayments(), loadViolations(), loadCharts()]);
    } catch (err) {
      // Display the server's error message, not just a generic text.
      toast.error("Payment failed", err.message || "The payment could not be completed.");
    } finally {
      setPayingId(null);
    }
  };

  /* ---------------- appeals ---------------- */
  const [appeals, setAppeals] = useState([]);
  const [appealsLoading, setAppealsLoading] = useState(true);
  const [appealsError, setAppealsError] = useState("");

  const loadAppeals = useCallback(async () => {
    setAppealsLoading(true);
    setAppealsError("");
    try {
      const payload = await appClient.crud.appeals.list({ per_page: 50, sort: "-created_at" });
      setAppeals(payload?.data || []);
    } catch (err) {
      setAppealsError(err.message || "Could not load your appeals.");
    } finally {
      setAppealsLoading(false);
    }
  }, []);
  useEffect(() => { loadAppeals(); }, [loadAppeals]);

  const [appealViolationId, setAppealViolationId] = useState("");
  const [appealReason, setAppealReason] = useState("");
  const [submittingAppeal, setSubmittingAppeal] = useState(false);

  const submitAppeal = async () => {
    if (!profile?.id) {
      toast.error("No driver profile", "Your account has no linked driver profile yet.");
      return;
    }
    if (!appealReason.trim()) {
      toast.info("Reason required", "Explain why the violation should be reviewed.");
      return;
    }
    setSubmittingAppeal(true);
    try {
      await appClient.crud.appeals.create({
        driver_id: Number(profile.id),
        ...(appealViolationId ? { violation_id: Number(appealViolationId) } : {}),
        reason: appealReason.trim(),
      });
      toast.success("Appeal submitted", "NTSA will review it shortly.");
      setAppealReason("");
      setAppealViolationId("");
      await loadAppeals();
    } catch (err) {
      toast.error("Could not submit appeal", err.message);
    } finally {
      setSubmittingAppeal(false);
    }
  };

  /* ---------------- vehicles CRUD (owner-scoped by the backend) ---------------- */
  const vehicleFields = useMemo(
    () => [
      { name: "plate_number", label: "Plate number", required: true },
      { name: "make_model", label: "Make & model", required: true, help: "e.g. Toyota Fielder" },
      { name: "vehicle_class", label: "Class", required: true, placeholder: "e.g. Saloon, SUV, Matatu" },
      { name: "colour", label: "Colour" },
      { name: "year_of_manufacture", label: "Year of manufacture", type: "number", coerce: (v) => (v === "" || v == null ? null : Number(v)), min: 1950, max: 2100 },
      { name: "registration_expiry", label: "Registration expiry", type: "date" },
    ],
    []
  );

  /* ---------------- derived display data ---------------- */
  const gauge = charts?.gauge;
  const pointsOverTime = charts?.points_over_time || [];
  const recentLedger = charts?.recent_ledger || [];
  const balance = gauge?.balance ?? profile?.demerit_balance ?? 0;

  const statusPill = (status) => {
    const s = String(status || "").toLowerCase();
    let cls = "bg-slate-100 text-slate-600";
    if (["completed", "confirmed", "approved", "active", "lifted"].includes(s)) cls = "bg-emerald-100 text-emerald-700";
    else if (["pending", "disputed", "under_review"].includes(s)) cls = "bg-amber-100 text-amber-700";
    else if (["rejected", "failed", "locked", "suspended", "voided"].includes(s)) cls = "bg-red-100 text-red-700";
    return <span className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${cls}`}>{status || "—"}</span>;
  };

  return (
    <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}>
      <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          {profile?.profile_photo_url ? (
            <img src={profile.profile_photo_url} alt={`${profile.full_name} profile`} className="h-14 w-14 rounded-full border border-slate-200 object-cover" />
          ) : (
            <div className="h-14 w-14 rounded-full bg-slate-200" aria-hidden="true" />
          )}
          <div>
            <h1 className="text-2xl font-bold text-slate-900">My Demerit Portal</h1>
            <p className="text-sm text-slate-500">View your demerit ledger, risk status, vehicles, payments and appeals.</p>
          </div>
        </div>
        <Button size="sm" variant="outline" onClick={() => { loadProfile(); loadCharts(); loadViolations(); }} disabled={chartsLoading}>
          {chartsLoading ? "Refreshing…" : "Refresh data"}
        </Button>
      </header>

      {profileLoading ? (
        <div className="mb-6 flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
          <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
          Loading your driver profile…
        </div>
      ) : profileError ? (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{profileError}</span>
          <Button size="sm" variant="outline" onClick={loadProfile}>Retry</Button>
        </div>
      ) : !profile ? (
        <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          No driver profile is linked to this account yet. Your ledger will appear once NTSA creates your profile.
        </div>
      ) : null}

      {active === "ledger" && (
        <div className="space-y-4">
          {/* top row: gauge + key stats */}
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <ChartCard
              title="Demerit Balance"
              subtitle={gauge ? `Warning at ${gauge.threshold_warning} · suspension at ${gauge.threshold_suspension}` : "Your current demerit points."}
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadCharts}
              isEmpty={!chartsLoading && !chartsError && !gauge}
              emptyText="No balance data yet."
            >
              {gauge && (
                <GaugeChart
                  value={gauge.balance}
                  max={gauge.max_points || 100}
                  classification={gauge.classification}
                  thresholds={[
                    { value: gauge.threshold_warning ?? 40, label: "Warning", color: "#f59e0b" },
                    { value: gauge.threshold_suspension ?? 70, label: "Suspension", color: "#ef4444" },
                  ]}
                  label="demerit points"
                  status={gauge.status}
                />
              )}
            </ChartCard>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1">
              <Stat label="Current Balance" value={balance} tone={Number(balance) >= 70 ? "red" : Number(balance) >= 40 ? "amber" : "slate"} />
              <Stat label="Risk Classification" value={String(gauge?.classification || profile?.risk_classification || "—").toUpperCase()} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1">
              <Stat label="Total Violations" value={profile?.total_violations ?? violations.length} />
              <Stat label="Profile Status" value={String(profile?.status || "—").toUpperCase()} tone={profile?.status === "locked" ? "red" : "emerald"} />
            </div>
          </div>

          {/* risk explanation */}
          <RiskExplanationPanel riskRecord={riskRecord} />

          {/* points over time */}
          <ChartCard
            title="Points Over Time"
            subtitle="Running demerit balance with each applied entry."
            loading={chartsLoading}
            error={chartsError}
            onRetry={loadCharts}
            isEmpty={!chartsLoading && !chartsError && pointsOverTime.length === 0}
            emptyText="No ledger entries yet — your balance history appears here after your first violation."
          >
            <LineChart
              data={pointsOverTime}
              xKey="date"
              series={[
                { key: "running_balance", label: "Running balance", color: "#06b6d4", area: true },
                { key: "points_applied", label: "Points applied", color: "#f97316", axis: "right" },
              ]}
              ariaLabel="Running demerit balance over time line chart"
            />
          </ChartCard>

          {/* recent ledger */}
          <ChartCard
            title="Recent Ledger Entries"
            subtitle="The last 10 demerit movements on your account."
            loading={chartsLoading}
            error={chartsError}
            onRetry={loadCharts}
            isEmpty={!chartsLoading && !chartsError && recentLedger.length === 0}
            emptyText="No ledger entries yet."
          >
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                  <tr>
                    <th className="px-3 py-2.5 text-left">Date</th>
                    <th className="px-3 py-2.5 text-left">Reason</th>
                    <th className="px-3 py-2.5 text-right">Points</th>
                    <th className="px-3 py-2.5 text-right">Balance</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {recentLedger.map((entry) => (
                    <tr key={entry.id} className="hover:bg-slate-50">
                      <td className="px-3 py-2.5 text-slate-500">{entry.date ? new Date(entry.date).toLocaleDateString() : "—"}</td>
                      <td className="px-3 py-2.5 text-slate-700">{entry.reason || entry.reason_code || "—"}</td>
                      <td className={`px-3 py-2.5 text-right font-semibold ${Number(entry.points_applied) > 0 ? "text-red-600" : "text-emerald-600"}`}>
                        {Number(entry.points_applied) > 0 ? `+${entry.points_applied}` : entry.points_applied}
                      </td>
                      <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{entry.running_balance}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </ChartCard>

          {/* my vehicles */}
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-sm font-semibold text-slate-900">My Vehicles</div>
            <div className="mt-1 text-xs text-slate-500">Keep your registered vehicles up to date — officers must pick one of these when logging a violation.</div>
          </div>
          <DataTable
            resource={appClient.crud.vehicles}
            resourceLabel="vehicle"
            searchPlaceholder="Search plate, make, class…"
            initialSort="-created_at"
            columns={[
              { key: "plate_number", label: "Plate", sortable: true, render: (row) => <span className="font-semibold text-slate-900">{row.plate_number}</span> },
              { key: "make_model", label: "Make & model", sortable: true },
              { key: "vehicle_class", label: "Class", sortable: true, render: (row) => <span className="capitalize">{row.vehicle_class || "—"}</span> },
              { key: "colour", label: "Colour", render: (row) => row.colour || "—" },
              { key: "year_of_manufacture", label: "Year", sortable: true, align: "right" },
              { key: "registration_expiry", label: "Reg. expiry", render: (row) => (row.registration_expiry ? new Date(row.registration_expiry).toLocaleDateString() : "—") },
            ]}
            fields={vehicleFields}
            defaultValues={{ vehicle_class: "saloon" }}
            getRowLabel={(row) => `vehicle ${row.plate_number}`}
            emptyText="No vehicles yet. Add your first vehicle so officers can log violations against it."
          />
        </div>
      )}

      {active === "sanctions" && (
        <div className="space-y-3">
          {sanctionsLoading ? (
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
              <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
              Loading sanctions…
            </div>
          ) : sanctionsError ? (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              <span>{sanctionsError}</span>
              <Button size="sm" variant="outline" onClick={loadSanctions}>Retry</Button>
            </div>
          ) : sanctions.length === 0 ? (
            <div className="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">
              No sanctions on record. Keep driving safely!
            </div>
          ) : (
            sanctions.map((s) => <SanctionCard key={s.id} sanction={s} />)
          )}
        </div>
      )}

      {active === "notifications" && (
        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-4">
            <Stat label="Total Notifications" value={notifications.length} />
            <Stat label="Unread" value={unreadCount} tone="amber" />
            <Stat label="Read" value={notifications.length - unreadCount} tone="emerald" />
          </div>
          {notificationsLoading ? (
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
              <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
              Loading notifications…
            </div>
          ) : notificationsError ? (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              <span>{notificationsError}</span>
              <Button size="sm" variant="outline" onClick={loadNotifications}>Retry</Button>
            </div>
          ) : (
            <NotificationCenter notifications={notifications} onToggleRead={toggleNotificationRead} />
          )}
        </div>
      )}

      {active === "payments" && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Stat label="Total Payments" value={payments.length} />
            <Stat label="Paid" value={payments.filter((p) => String(p.status).toLowerCase() === "completed").length} tone="emerald" />
            <Stat label="Outstanding Violations" value={outstandingViolations.length} tone="amber" />
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-sm font-semibold text-slate-900">Pay outstanding violations</div>
            <div className="mt-1 text-xs text-slate-500">M-Pesa push · KES {FINE_PER_POINT.toLocaleString()} per demerit point.</div>
          </div>

          {violationsLoading ? (
            <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
              <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
              Loading violations…
            </div>
          ) : violationsError ? (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
              <span>{violationsError}</span>
              <Button size="sm" variant="outline" onClick={loadViolations}>Retry</Button>
            </div>
          ) : outstandingViolations.length === 0 ? (
            <div className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">
              All your violations are settled. 🎉
            </div>
          ) : (
            <div className="space-y-3">
              {outstandingViolations.map((violation) => {
                const amount = Number(violation.points_assigned || 0) * FINE_PER_POINT;
                return (
                  <div key={violation.id} className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                      <div className="font-medium text-slate-900">
                        {offenceLabel(violation.offense_type || violation.offence_type)}{" "}
                        <span className="text-xs text-slate-400">#{violation.id}</span>
                      </div>
                      <div className="text-xs text-slate-500">
                        {(violation.timestamp || violation.occurred_at || violation.created_at) &&
                          `${new Date(violation.timestamp || violation.occurred_at || violation.created_at).toLocaleDateString()} · `}
                        +{violation.points_assigned ?? 0} pts · KES {amount.toLocaleString()}
                      </div>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                      <input
                        type="tel"
                        value={payPhone}
                        onChange={(e) => setPayPhone(e.target.value.replace(/\D/g, ""))}
                        placeholder="07XX XXX XXX"
                        maxLength="10"
                        className="w-full sm:w-48 px-3 py-2 border border-slate-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        disabled={payingId === violation.id}
                      />
                      <Button
                        onClick={() => payOutstandingViolation(violation, payPhone)}
                        disabled={payingId === violation.id || !payPhone}
                        className="bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-50"
                      >
                        {payingId === violation.id ? "Processing…" : "Pay now"}
                      </Button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          <ChartCard
            title="Payment History"
            subtitle="Every fine payment on your account."
            loading={paymentsLoading}
            error={paymentsError}
            onRetry={loadPayments}
            isEmpty={!paymentsLoading && !paymentsError && payments.length === 0}
            emptyText="No payment records yet."
          >
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                  <tr>
                    <th className="px-3 py-2.5 text-left">Violation</th>
                    <th className="px-3 py-2.5 text-left">Reference</th>
                    <th className="px-3 py-2.5 text-left">Method</th>
                    <th className="px-3 py-2.5 text-right">Amount</th>
                    <th className="px-3 py-2.5 text-left">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {payments.map((payment) => (
                    <tr key={payment.id} className="hover:bg-slate-50">
                      <td className="px-3 py-2.5 text-slate-700">{payment.violation_id ? `#${payment.violation_id}` : "—"}</td>
                      <td className="px-3 py-2.5 text-slate-600">{payment.reference_number || payment.transaction_ref || payment.mpesa_receipt || "—"}</td>
                      <td className="px-3 py-2.5 capitalize text-slate-600">{payment.method || payment.payment_method || "—"}</td>
                      <td className="px-3 py-2.5 text-right font-semibold text-slate-900">KES {Number(payment.amount || 0).toLocaleString()}</td>
                      <td className="px-3 py-2.5">{statusPill(payment.status)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </ChartCard>
        </div>
      )}

      {active === "appeal" && (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="text-sm font-semibold text-slate-900">Submit an Appeal</div>
            <div className="mt-1 text-xs text-slate-500">Challenge a violation — NTSA reviews every submission.</div>
            <div className="mt-4 space-y-3">
              <div>
                <label className="text-sm font-medium text-slate-700" htmlFor="appeal-violation">Violation</label>
                <Select value={appealViolationId} onValueChange={setAppealViolationId}>
                  <SelectTrigger className="mt-1" id="appeal-violation"><SelectValue placeholder="Select violation (optional)" /></SelectTrigger>
                  <SelectContent>
                    {violations.length === 0 && <SelectItem value="__none" disabled>No violations loaded</SelectItem>}
                    {violations.map((v) => (
                      <SelectItem key={v.id} value={String(v.id)}>
                        #{v.id} · {offenceLabel(v.offense_type || v.offence_type)} · {new Date(v.timestamp || v.created_at).toLocaleDateString()}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="text-sm font-medium text-slate-700" htmlFor="appeal-reason">Reason for appeal</label>
                <Textarea
                  id="appeal-reason"
                  value={appealReason}
                  onChange={(e) => setAppealReason(e.target.value)}
                  className="mt-1"
                  rows={4}
                  placeholder="Explain why this violation should be reviewed…"
                />
              </div>
              <Button onClick={submitAppeal} disabled={submittingAppeal}>
                {submittingAppeal ? "Submitting…" : "Submit Appeal"}
              </Button>
            </div>
          </div>

          <div>
            <div className="mb-3">
              <div className="text-sm font-semibold text-slate-900">My Appeal Tracker</div>
              <div className="mt-1 text-xs text-slate-500">Follow each appeal from submission through review and final outcome.</div>
            </div>
            {appealsLoading ? (
              <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
                Loading appeals…
              </div>
            ) : appealsError ? (
              <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span>{appealsError}</span>
                <Button size="sm" variant="outline" onClick={loadAppeals}>Retry</Button>
              </div>
            ) : appeals.length === 0 ? (
              <div className="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500">
                No appeals yet. Submit your first appeal to start tracking.
              </div>
            ) : (
              <div className="max-h-[36rem] space-y-3 overflow-y-auto pr-1">
                {appeals.map((appeal) => (
                  <AppealTimelineCard key={appeal.id} appeal={appeal} />
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      <ToastStack />
    </AppLayout>
  );
}

function Stat({ label, value, tone = "slate" }) {
  const tones = {
    slate: "text-slate-900",
    emerald: "text-emerald-600",
    amber: "text-amber-600",
    red: "text-red-600",
  };
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{label}</div>
      <div className={`mt-1 text-2xl font-bold ${tones[tone] || tones.slate}`}>{value}</div>
    </div>
  );
}

function resolveAppealStage(status) {
  const normalized = String(status || "pending").toLowerCase();
  if (["approved", "rejected", "resolved", "closed"].includes(normalized)) return "resolved";
  if (["under_review", "in_review", "reviewing"].includes(normalized)) return "under_review";
  return "submitted";
}

function stepComplete(step, stage) {
  if (stage === "resolved") return true;
  if (stage === "under_review") return step === "submitted" || step === "under_review";
  return step === "submitted";
}

function stepCurrent(step, stage) {
  if (stage === "submitted") return step === "submitted";
  if (stage === "under_review") return step === "under_review";
  return step === "resolved";
}

function AppealTimelineCard({ appeal }) {
  const stage = resolveAppealStage(appeal.status);
  const steps = [
    { id: "submitted", label: "Submitted" },
    { id: "under_review", label: "Under Review" },
    { id: "resolved", label: "Resolved" },
  ];

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-sm font-semibold text-slate-900">
            {appeal.violation_id ? `Violation #${appeal.violation_id}` : "General appeal"}
            {appeal.offence_type ? ` · ${offenceLabel(appeal.offence_type)}` : ""}
          </div>
          <div className="mt-1 text-xs text-slate-500">
            Submitted {appeal.submitted_at || appeal.created_at ? new Date(appeal.submitted_at || appeal.created_at).toLocaleString() : "--"}
          </div>
        </div>
        <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${appealStatusClass(appeal.status)}`}>
          {String(appeal.status || "pending").toUpperCase()}
        </span>
      </div>

      <div className="mt-3 grid grid-cols-3 gap-2">
        {steps.map((step, index) => {
          const complete = stepComplete(step.id, stage);
          const current = stepCurrent(step.id, stage);
          return (
            <div key={step.id} className="relative">
              {index < steps.length - 1 && (
                <div className={`absolute left-[58%] top-3 h-[2px] w-[90%] ${stepComplete(steps[index + 1].id, stage) ? "bg-cyan-500" : "bg-slate-200"}`} />
              )}
              <div className="relative z-10 flex items-center gap-2">
                <span className={`inline-flex h-6 w-6 items-center justify-center rounded-full border text-[11px] font-bold ${complete ? "border-cyan-500 bg-cyan-500 text-white" : current ? "border-amber-500 bg-amber-50 text-amber-700" : "border-slate-300 bg-white text-slate-400"}`}>
                  {complete ? "\u2713" : index + 1}
                </span>
                <span className={`text-xs font-medium ${complete ? "text-slate-900" : "text-slate-500"}`}>{step.label}</span>
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-3 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">{appeal.reason}</div>

      <div className="mt-2 space-y-1 text-xs text-slate-500">
        {appeal.reviewed_at && <div>Reviewed at: {new Date(appeal.reviewed_at).toLocaleString()}</div>}
        {appeal.review_notes && <div>Review note: {appeal.review_notes}</div>}
      </div>
    </div>
  );
}

function appealStatusClass(status) {
  const normalized = String(status || "pending").toLowerCase();
  if (["approved", "resolved", "closed"].includes(normalized)) return "bg-emerald-100 text-emerald-700";
  if (["rejected"].includes(normalized)) return "bg-red-100 text-red-700";
  if (["under_review", "in_review", "reviewing"].includes(normalized)) return "bg-cyan-100 text-cyan-700";
  return "bg-amber-100 text-amber-700";
}
