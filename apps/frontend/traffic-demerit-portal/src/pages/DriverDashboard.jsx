import React, { useState, useEffect } from "react";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import LedgerTable from "@/components/LedgerTable";
import SanctionCard from "@/components/SanctionCard";
import LiveDemeritGauge from "@/components/LiveDemeritGauge";
import RiskExplanationPanel from "@/components/RiskExplanationPanel";
import NotificationCenter from "@/components/NotificationCenter";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel } from "@/lib/constants";

export default function DriverDashboard({ user, effectiveRole, onRoleChange }) {
  const [drivers, setDrivers] = useState([]);
  const [driver, setDriver] = useState(null);
  const [ledger, setLedger] = useState([]);
  const [sanctions, setSanctions] = useState([]);
  const [violations, setViolations] = useState([]);
  const [appeals, setAppeals] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [payments, setPayments] = useState([]);
  const [active, setActive] = useState("ledger");
  const [appealViolation, setAppealViolation] = useState("");
  const [appealReason, setAppealReason] = useState("");
  const [appealMsg, setAppealMsg] = useState("");
  const [paymentMsg, setPaymentMsg] = useState("");
  const [latestRiskAssessment, setLatestRiskAssessment] = useState(null);
  const [lastRefreshedAt, setLastRefreshedAt] = useState(null);

  const loadDrivers = async () => {
    const all = await appClient.entities.Driver.list("-demerit_balance", 200);
    setDrivers(all);
    const mine = all.find((d) => d.email?.toLowerCase() === user.email?.toLowerCase()) || all[0];
    if (mine) selectDriver(mine);
  };

  const selectDriver = async (d) => {
    setDriver(d);
    const [led, san, vio, app, riskAssessments, note, pay] = await Promise.all([
      appClient.entities.DemeritLedger.filter({ driver_id: d.id }, "-timestamp", 50),
      appClient.entities.Sanction.filter({ driver_id: d.id }, "-triggered_at", 10),
      appClient.entities.Violation.filter({ driver_id: d.id }, "-timestamp", 50),
      appClient.entities.Appeal.filter({ driver_id: d.id }, "-submitted_at", 50),
      appClient.entities.RecidivismScore.filter({ driver_id: d.id }, "-timestamp", 1),
      appClient.entities.Notification.list("-created_at", 100),
      appClient.entities.Payment.filter({ driver_id: d.id }, "-created_at", 50),
    ]);
    setLedger(led);
    setSanctions(san);
    setViolations(vio);
    setAppeals(app);
    setLatestRiskAssessment(riskAssessments[0] || null);
    setNotifications(note);
    setPayments(pay);
    setLastRefreshedAt(new Date());
  };

  useEffect(() => { loadDrivers(); }, []);

  useEffect(() => {
    if (!driver?.id) return;

    const timer = window.setInterval(async () => {
      const all = await appClient.entities.Driver.list("-demerit_balance", 200);
      setDrivers(all);
      const fresh = all.find((item) => item.id === driver.id);
      if (fresh) {
        await selectDriver(fresh);
      }
    }, 10000);

    return () => window.clearInterval(timer);
  }, [driver?.id]);

  const submitAppeal = async () => {
    if (!appealViolation || !appealReason.trim()) return;
    const v = violations.find((x) => x.id === appealViolation);
    await appClient.entities.Appeal.create({
      driver_id: driver.id, driver_name: driver.full_name,
      violation_id: appealViolation, offence_type: v?.offence_type,
      reason: appealReason, status: "pending", submitted_at: new Date().toISOString(),
    });
    setAppealReason(""); setAppealViolation("");
    setAppealMsg("Appeal submitted. NTSA will review it shortly.");
    await selectDriver(driver);
    setTimeout(() => setAppealMsg(""), 4000);
  };

  const payOutstandingViolation = async (violation) => {
    try {
      const paymentData = await appClient.payments.initiate(violation.id, "mpesa");
      const payment = paymentData.payment;
      await appClient.payments.verify(payment.id, {
        status: "completed",
        reference_number: `MPESA-${Date.now()}`,
        amount: Number(payment.amount || 0),
        currency: "KES",
      });
      setPaymentMsg("Payment confirmed. The driver has been cleared.");
      await selectDriver(driver);
      setTimeout(() => setPaymentMsg(""), 5000);
    } catch (error) {
      setPaymentMsg(error.message || "Payment could not be completed.");
      setTimeout(() => setPaymentMsg(""), 5000);
    }
  };

  const unreadNotificationCount = notifications.filter((item) => item.status === "unread").length;

  const toggleNotificationRead = async (notification, status) => {
    await appClient.entities.Notification.update(notification.id, { status });
    await selectDriver(driver);
  };

  if (!driver) return <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}><div className="text-slate-400">Loading…</div></AppLayout>;

  return (
    <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}>
      <header className="mb-6 flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          {driver.profile_photo_url ? (
            <img
              src={driver.profile_photo_url}
              alt={`${driver.full_name} profile`}
              className="w-14 h-14 rounded-full object-cover border border-slate-200"
            />
          ) : (
            <div className="w-14 h-14 rounded-full bg-slate-200" />
          )}
          <div>
            <h1 className="text-2xl font-bold text-slate-900">My Demerit Portal</h1>
            <p className="text-sm text-slate-500">View your demerit ledger, risk status, and submit appeals.</p>
          </div>
        </div>
        {effectiveRole === "driver" && drivers.length > 1 && (
          <Select value={driver.id} onValueChange={(id) => selectDriver(drivers.find((d) => d.id === id))}>
            <SelectTrigger className="w-56"><SelectValue /></SelectTrigger>
            <SelectContent>{drivers.map((d) => <SelectItem key={d.id} value={d.id}>{d.full_name}</SelectItem>)}</SelectContent>
          </Select>
        )}
      </header>

      <div className="grid grid-cols-3 gap-4 mb-6">
        <Stat label="Demerit Balance" value={driver.demerit_balance || 0} />
        <Stat label="Total Violations" value={driver.total_violations || 0} />
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Profile Status</div>
          <div className="mt-1 flex items-center gap-2">
            <span className={`text-sm font-bold ${driver.status === "locked" ? "text-red-600" : "text-emerald-600"}`}>
              {driver.status === "locked" ? "LOCKED" : "ACTIVE"}
            </span>
          </div>
        </div>
      </div>

      <div className="mb-6 rounded-2xl border border-slate-200 bg-white p-5">
        <div className="flex items-center justify-between gap-3">
          <div>
            <div className="text-sm font-semibold text-slate-900">Live Demerit Gauge</div>
            <div className="text-xs text-slate-500">Updates every 10 seconds from your latest profile and risk prediction.</div>
          </div>
          <div className="text-xs text-slate-500">
            Last sync: {lastRefreshedAt ? lastRefreshedAt.toLocaleTimeString() : "Loading..."}
          </div>
        </div>
        <div className="mt-3">
          <LiveDemeritGauge
            balance={driver.demerit_balance || 0}
            score={latestRiskAssessment?.risk_score}
            classification={latestRiskAssessment?.risk_classification}
          />
        </div>
      </div>

      <div className="mb-6">
        <RiskExplanationPanel riskRecord={latestRiskAssessment} />
      </div>

      {active === "ledger" && (
        <Section title="Demerit Ledger History">
          <LedgerTable entries={ledger} />
        </Section>
      )}

      {active === "sanctions" && (
        <Section title="Active Sanctions & Risk">
          <div className="space-y-3">
            {sanctions.length === 0 ? <div className="text-sm text-slate-500 py-6 text-center">No sanctions on record.</div> :
              sanctions.map((s) => <SanctionCard key={s.id} sanction={s} />)}
          </div>
        </Section>
      )}

      {active === "payments" && (
        <Section title="Payment Status">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <Stat label="Total Payments" value={payments.length} />
            <Stat label="Paid" value={payments.filter((p) => p.status === "completed").length} />
            <Stat label="Pending" value={payments.filter((p) => p.status !== "completed").length} />
          </div>

          {paymentMsg && <div className="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg">{paymentMsg}</div>}

          <div className="space-y-4">
            {violations.filter((v) => v.status !== "confirmed").length > 0 && (
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div className="mb-3 text-sm font-semibold text-slate-900">Pay outstanding violations</div>
                <div className="space-y-3">
                  {violations.filter((v) => v.status !== "confirmed").map((violation) => (
                    <div key={violation.id} className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-3 md:flex-row md:items-center md:justify-between">
                      <div>
                        <div className="font-medium text-slate-900">{offenceLabel(violation.offence_type || violation.offense_type)}</div>
                        <div className="text-xs text-slate-500">{new Date(violation.timestamp || violation.occurred_at).toLocaleDateString()} · KES {Number((violation.points_assigned || 0) * 500).toLocaleString()}</div>
                      </div>
                      <Button onClick={() => payOutstandingViolation(violation)} variant="secondary" className="bg-emerald-600 hover:bg-emerald-500 text-white">
                        Pay now
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {payments.length === 0 ? (
              <div className="text-sm text-slate-500 py-6 text-center">No payment records yet.</div>
            ) : (
              payments.map((payment) => (
                <div key={payment.id} className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <div className="font-medium text-slate-900">{payment.violation_type || "Violation payment"}</div>
                      <div className="text-xs text-slate-500">Ref: {payment.transaction_ref || payment.reference_number || "N/A"}</div>
                    </div>
                    <span className={`text-xs font-semibold px-2 py-1 rounded-full ${payment.status === "completed" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700"}`}>
                      {payment.status.toUpperCase()}
                    </span>
                  </div>
                  <div className="mt-3 flex items-center justify-between text-sm text-slate-600">
                    <span>Amount</span>
                    <span className="font-semibold text-slate-900">KES {Number(payment.amount || 0).toLocaleString()}</span>
                  </div>
                  {payment.paid_at && (
                    <div className="mt-1 text-xs text-slate-500">Paid on {new Date(payment.paid_at).toLocaleString()}</div>
                  )}
                </div>
              ))
            )}
          </div>
        </Section>
      )}

      {active === "notifications" && (
        <Section title="Notifications">
          <div className="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <Stat label="Total Notifications" value={notifications.length} />
            <Stat label="Unread" value={unreadNotificationCount} />
            <Stat label="Read" value={notifications.length - unreadNotificationCount} />
          </div>
          <NotificationCenter notifications={notifications} onToggleRead={toggleNotificationRead} />
        </Section>
      )}

      {active === "appeal" && (
        <Section title="Submit an Appeal">
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div className="space-y-3">
              <div>
                <label className="text-sm font-medium text-slate-700">Violation</label>
                <Select value={appealViolation} onValueChange={setAppealViolation}>
                  <SelectTrigger className="mt-1"><SelectValue placeholder="Select violation to appeal" /></SelectTrigger>
                  <SelectContent>
                    {violations.map((v) => <SelectItem key={v.id} value={v.id}>{offenceLabel(v.offence_type)} · {new Date(v.timestamp).toLocaleDateString()}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <label className="text-sm font-medium text-slate-700">Reason for appeal</label>
                <Textarea value={appealReason} onChange={(e) => setAppealReason(e.target.value)} className="mt-1" rows={4} placeholder="Explain why this violation should be reviewed..." />
              </div>
              {appealMsg && <div className="text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg">{appealMsg}</div>}
              <Button onClick={submitAppeal}>Submit Appeal</Button>
            </div>

            <div>
              <div className="mb-3">
                <div className="text-sm font-semibold text-slate-900">My Appeal Tracker</div>
                <div className="text-xs text-slate-500 mt-1">Follow each appeal from submission through review and final outcome.</div>
              </div>
              {appeals.length === 0 ? (
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                  No appeals yet. Submit your first appeal to start tracking.
                </div>
              ) : (
                <div className="space-y-3 max-h-[28rem] overflow-y-auto pr-1">
                  {appeals.map((appeal) => (
                    <AppealTimelineCard key={appeal.id} appeal={appeal} />
                  ))}
                </div>
              )}
            </div>
          </div>
        </Section>
      )}
    </AppLayout>
  );
}

function Stat({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">{label}</div>
      <div className="text-2xl font-bold text-slate-900 mt-1">{value}</div>
    </div>
  );
}

function Section({ title, children }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-5">
      <div className="font-semibold text-slate-900 mb-4">{title}</div>
      {children}
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
          <div className="text-sm font-semibold text-slate-900">{offenceLabel(appeal.offence_type)}</div>
          <div className="text-xs text-slate-500 mt-1">Submitted {appeal.submitted_at ? new Date(appeal.submitted_at).toLocaleString() : "--"}</div>
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

      <div className="mt-3 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
        {appeal.reason}
      </div>

      <div className="mt-2 text-xs text-slate-500 space-y-1">
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