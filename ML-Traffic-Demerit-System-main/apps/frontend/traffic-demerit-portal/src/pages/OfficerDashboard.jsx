import React, { useCallback, useEffect, useMemo, useState } from "react";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import DataTable from "@/components/crud/DataTable";
import { BarChart, ChartCard, LineChart } from "@/components/charts";
import { toast, ToastStack } from "@/components/ui/toasts";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel, OFFENCE_TYPES } from "@/lib/constants";

/* Backend enums (ViolationController store/update rules). */
const OFFENCE_OPTIONS = OFFENCE_TYPES.map((o) => ({ value: o.value, label: o.label }));
const ZONE_OPTIONS = [
  { value: "urban", label: "Urban" },
  { value: "highway", label: "Highway" },
  { value: "school_zone", label: "School zone" },
  { value: "residential", label: "Residential" },
];
const WEATHER_OPTIONS = [
  { value: "clear", label: "Clear" },
  { value: "rain", label: "Rain" },
  { value: "fog", label: "Fog" },
  { value: "night", label: "Night" },
];
const VIOLATION_STATUS_OPTIONS = [
  { value: "confirmed", label: "Confirmed" },
  { value: "disputed", label: "Disputed" },
];
const PAYMENT_STATUS_OPTIONS = [
  { value: "pending", label: "Pending" },
  { value: "completed", label: "Completed" },
  { value: "failed", label: "Failed" },
  { value: "voided", label: "Voided" },
];

/** Officers may edit their own violations within 24h of creation (ViolationPolicy). */
const EDIT_WINDOW_MS = 24 * 60 * 60 * 1000;
const canEditViolation = (row, userId) => {
  const mine = Number(row?.officer_id) === Number(userId);
  const created = row?.created_at ? new Date(row.created_at).getTime() : NaN;
  return mine && !Number.isNaN(created) && Date.now() - created <= EDIT_WINDOW_MS;
};

/** datetime-local value from a Date. */
const toLocalDateTimeValue = (date = new Date()) => {
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const EMPTY_FORM = {
  driver_id: "",
  vehicle_id: "",
  offence_type: "speeding",
  speed_recorded: "",
  speed_limit: "",
  zone_type: "urban",
  weather: "clear",
  location: "",
  evidence_reference: "",
  violation_date_time: toLocalDateTimeValue(),
};

export default function OfficerDashboard({ user, effectiveRole, onRoleChange }) {
  const [active, setActive] = useState("log");

  /* ---------------- overview charts (MySQL-backed) ---------------- */
  const [charts, setCharts] = useState(null);
  const [chartsLoading, setChartsLoading] = useState(true);
  const [chartsError, setChartsError] = useState("");

  const loadCharts = useCallback(async () => {
    setChartsLoading(true);
    setChartsError("");
    try {
      setCharts(await appClient.charts.officer());
    } catch (err) {
      setChartsError(err.message || "Failed to load your enforcement stats.");
    } finally {
      setChartsLoading(false);
    }
  }, []);
  useEffect(() => { loadCharts(); }, [loadCharts]);

  /* ---------------- lookup lists for the violation form ---------------- */
  const [drivers, setDrivers] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [lookupsLoading, setLookupsLoading] = useState(true);
  const [lookupsError, setLookupsError] = useState("");

  // The REST API caps per_page at 100, so walk every page: the officer
  // console needs the FULL driver/vehicle roster in its selects.
  const fetchAllPages = useCallback(async (api, params, maxPages = 12) => {
    const rows = [];
    for (let page = 1; page <= maxPages; page += 1) {
      const res = await api.list({ ...params, page });
      const chunk = res?.data || [];
      rows.push(...chunk);
      const perPage = res?.meta?.per_page || chunk.length || 100;
      if (chunk.length === 0 || chunk.length < perPage) break;
    }
    return rows;
  }, []);

  const loadLookups = useCallback(async () => {
    setLookupsLoading(true);
    setLookupsError("");
    try {
      const [d, v] = await Promise.all([
        fetchAllPages(appClient.crud.drivers, { per_page: 100, sort: "full_name" }),
        fetchAllPages(appClient.crud.vehicles, { per_page: 100, sort: "plate_number" }),
      ]);
      setDrivers(d);
      setVehicles(v);
    } catch (err) {
      setLookupsError(err.message || "Could not load drivers/vehicles.");
    } finally {
      setLookupsLoading(false);
    }
  }, [fetchAllPages]);
  useEffect(() => { loadLookups(); }, [loadLookups]);

  /* ---------------- violation creation ---------------- */
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");
  const [fieldErrors, setFieldErrors] = useState({});
  const [tableRefresh, setTableRefresh] = useState(0);
  const bumpRecent = useCallback(() => setTableRefresh((t) => t + 1), []);

  const setField = (name, value) => setForm((f) => ({ ...f, [name]: value }));

  // Vehicles owned by the selected driver's user account (backend enforces
  // that the vehicle belongs to the driver, so pre-filter to save rejections).
  const driverVehicles = useMemo(() => {
    const selected = drivers.find((d) => String(d.id) === String(form.driver_id));
    if (!selected?.user_id) return vehicles;
    return vehicles.filter((v) => String(v.owner_user_id) === String(selected.user_id));
  }, [drivers, vehicles, form.driver_id]);

  const validate = () => {
    const errs = {};
    if (!form.driver_id) errs.driver_id = "Select the driver.";
    if (!form.vehicle_id) errs.vehicle_id = "Select the vehicle.";
    if (!form.location.trim()) errs.location = "Location is required.";
    if (!form.evidence_reference.trim()) errs.evidence_reference = "Evidence reference is required.";
    if (form.speed_recorded === "" || Number(form.speed_recorded) < 0) errs.speed_recorded = "Enter the recorded speed.";
    if (form.speed_limit === "" || Number(form.speed_limit) < 0) errs.speed_limit = "Enter the posted speed limit.";
    if (!form.violation_date_time) errs.violation_date_time = "Date & time is required.";
    return errs;
  };

  const submitViolation = async (e) => {
    e?.preventDefault?.();
    const errs = validate();
    setFieldErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setSaving(true);
    setFormError("");
    try {
      // store() expects `offence_type` (create-only quirk in ViolationController).
      const response = await appClient.crud.violations.create({
        driver_id: Number(form.driver_id),
        vehicle_id: Number(form.vehicle_id),
        offence_type: form.offence_type,
        speed_recorded: Number(form.speed_recorded),
        speed_limit: Number(form.speed_limit),
        zone_type: form.zone_type,
        weather: form.weather,
        location: form.location.trim(),
        evidence_reference: form.evidence_reference.trim(),
        violation_date_time: form.violation_date_time,
      });

      const meta = response?.meta;
      const risk = meta?.risk;
      const description = meta
        ? `+${meta.demerit_points ?? "?"} points · balance ${meta.new_balance ?? "?"}` +
          (risk ? ` · risk ${String(risk.classification || "").toUpperCase()} (${Math.round((risk.score || 0) * 100)}%)` : "")
        : "Violation recorded.";
      toast.success("Violation logged", description);

      setForm({ ...EMPTY_FORM, violation_date_time: toLocalDateTimeValue() });
      bumpRecent();
      loadCharts();
    } catch (err) {
      const mapped = {};
      if (err?.errors && typeof err.errors === "object") {
        Object.entries(err.errors).forEach(([key, messages]) => {
          mapped[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
      }
      setFieldErrors(mapped);
      setFormError(Object.keys(mapped).length === 0 ? err.message : "");
      toast.error("Could not log violation", err.message);
    } finally {
      setSaving(false);
    }
  };

  /* ---------------- accident-risk predictor ---------------- */
  const [predictionDriverId, setPredictionDriverId] = useState("");
  const [predictingRisk, setPredictingRisk] = useState(false);
  const [predictionMessage, setPredictionMessage] = useState("");
  const [predictionResult, setPredictionResult] = useState(null);

  const predictAccidentRisk = async () => {
    if (!predictionDriverId) {
      toast.info("Select a driver first.");
      return;
    }
    setPredictingRisk(true);
    setPredictionMessage("");
    try {
      const response = await appClient.functions.invoke("predictAccidentRisk", {
        driver_id: Number(predictionDriverId),
      });
      const result = response?.data;
      if (!result) throw new Error("Prediction response was empty.");
      setPredictionResult(result);
      setPredictionMessage(`Predicted risk: ${(result.risk.score * 100).toFixed(1)}% (${String(result.risk.classification).toUpperCase()}).`);
    } catch (err) {
      setPredictionResult(null);
      setPredictionMessage(err.message || "Failed to generate risk prediction.");
      toast.error("Prediction failed", err.message);
    } finally {
      setPredictingRisk(false);
    }
  };

  /* ---------------- recent violations table config ---------------- */
  const statusPill = (status) => {
    const s = String(status || "").toLowerCase();
    let cls = "bg-slate-100 text-slate-600";
    if (["completed", "confirmed", "approved", "active"].includes(s)) cls = "bg-emerald-100 text-emerald-700";
    else if (["pending", "disputed", "under_review"].includes(s)) cls = "bg-amber-100 text-amber-700";
    else if (["rejected", "failed", "locked", "suspended", "voided"].includes(s)) cls = "bg-red-100 text-red-700";
    return <span className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${cls}`}>{status || "—"}</span>;
  };

  const editFields = useMemo(
    () => [
      { name: "offense_type", label: "Offence type", type: "select", options: OFFENCE_OPTIONS, required: true },
      { name: "speed_recorded", label: "Speed recorded (km/h)", type: "number", coerce: (v) => Number(v), min: 0 },
      { name: "speed_limit", label: "Posted speed limit (km/h)", type: "number", coerce: (v) => Number(v), min: 0 },
      { name: "zone_type", label: "Zone type", type: "select", options: ZONE_OPTIONS, required: true },
      { name: "weather", label: "Weather", type: "select", options: WEATHER_OPTIONS, required: true },
      { name: "location", label: "Location", required: true },
      { name: "evidence_reference", label: "Evidence reference", required: true },
      { name: "violation_date_time", label: "Violation date & time", type: "datetime-local", required: true },
      { name: "status", label: "Status", type: "select", options: VIOLATION_STATUS_OPTIONS },
    ],
    []
  );

  // The list rows carry weather under `weather_conditions` and the datetime
  // under `timestamp`; map both into the update contract (`weather`,
  // `violation_date_time`) so the edit modal round-trips cleanly.
  const editTransformRows = useCallback((rows) => (rows || []).map((row) => ({
    ...row,
    offense_type: row.offense_type || row.offence_type,
    weather: row.weather || row.weather_conditions,
    violation_date_time: row.timestamp
      ? toLocalDateTimeValue(new Date(row.timestamp))
      : row.violation_date_time || toLocalDateTimeValue(new Date(row.created_at)),
  })), []);

  /* ---------------- payments tab ---------------- */
  const verifyPayment = async (row) => {
    await appClient.payments.verify(row.id, {
      status: "completed",
      reference_number: row.reference_number || row.transaction_ref || `MPESA-${Date.now()}`,
      amount: Number(row.amount_paid || row.amount || 0),
      currency: row.currency || "KES",
    });
  };

  const paymentRowActions = useCallback((row, helpers) => {
    const status = String(row?.status || "").toLowerCase();
    if (status === "completed" || status === "voided") return [];
    return [
      {
        label: "Verify",
        variant: "outline",
        onClick: () =>
          helpers.confirm({
            title: "Verify payment",
            message: `Mark the payment for violation #${row.violation_id ?? row.id} (KES ${Number(row.amount || 0).toLocaleString()}) as completed?`,
            confirmLabel: "Verify",
            danger: false,
            row,
            onConfirm: (r) => verifyPayment(r || row),
            successTitle: "Payment verified",
            successMessage: "The payment was marked as completed.",
          }),
      },
    ];
  }, []);

  /* ---------------- render ---------------- */
  return (
    <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Field Violation Console</h1>
        <p className="text-sm text-slate-500">Log a traffic violation. The system auto-posts demerit points and runs recidivism inference.</p>
      </header>

      {active === "log" && (
        <div className="space-y-4">
          {/* ---- enforcement overview (MySQL-backed, ML-independent) ---- */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <ChartCard title="Violations Issued" loading={chartsLoading} error={chartsError} onRetry={loadCharts}>
              <div className="py-4 text-3xl font-bold text-slate-900">{charts?.totals?.violations ?? 0}</div>
            </ChartCard>
            <ChartCard title="Points Issued" loading={chartsLoading} error={chartsError} onRetry={loadCharts}>
              <div className="py-4 text-3xl font-bold text-orange-600">{charts?.totals?.points_issued ?? 0}</div>
            </ChartCard>
            <ChartCard title="Drivers Affected" loading={chartsLoading} error={chartsError} onRetry={loadCharts}>
              <div className="py-4 text-3xl font-bold text-cyan-600">{charts?.totals?.drivers_affected ?? 0}</div>
            </ChartCard>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ChartCard
              title="Violations Over Time"
              subtitle="Monthly volume and points issued."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadCharts}
              isEmpty={!chartsLoading && !chartsError && (charts?.violations_over_time || []).length === 0}
              emptyText="No violations logged yet."
            >
              <LineChart
                data={charts?.violations_over_time || []}
                xKey="month"
                series={[
                  { key: "total", label: "Violations", color: "#06b6d4" },
                  { key: "points", label: "Points", color: "#f97316", axis: "right" },
                ]}
                ariaLabel="Monthly violations and points line chart"
              />
            </ChartCard>

            <ChartCard
              title="Violations by Offence"
              subtitle="Your most frequently issued offence types."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadCharts}
              isEmpty={!chartsLoading && !chartsError && (charts?.by_type || []).length === 0}
              emptyText="No violations logged yet."
            >
              <BarChart
                horizontal
                data={(charts?.by_type || []).map((t) => ({ type: offenceLabel(t.type), total: t.total }))}
                xKey="type"
                series={[{ key: "total", label: "Violations", color: "#06b6d4" }]}
                ariaLabel="Violations by offence type horizontal bar chart"
              />
            </ChartCard>
          </div>

          {/* ---- violation creation form ---- */}
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <div className="text-sm font-semibold text-slate-900">Log a Violation</div>
            <div className="mt-1 text-xs text-slate-500">
              Demerit points, the driver's new balance and the ML risk score are computed automatically on save.
            </div>

            {lookupsLoading ? (
              <div className="mt-4 flex items-center gap-3 text-sm text-slate-500">
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
                Loading drivers and vehicles…
              </div>
            ) : lookupsError ? (
              <div className="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-700">
                <span>{lookupsError}</span>
                <Button size="sm" variant="outline" onClick={loadLookups}>Retry</Button>
              </div>
            ) : (
              <form className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" onSubmit={submitViolation} noValidate>
                <Field label="Driver" error={fieldErrors.driver_id} required>
                  <Select value={form.driver_id} onValueChange={(v) => setField("driver_id", v)}>
                    <SelectTrigger aria-label="Driver"><SelectValue placeholder="Select driver" /></SelectTrigger>
                    <SelectContent>
                      {drivers.length === 0 && <SelectItem value="__none" disabled>No drivers available</SelectItem>}
                      {drivers.map((d) => (
                        <SelectItem key={d.id} value={String(d.id)}>
                          {d.full_name} {d.license_no ? `(${d.license_no})` : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Vehicle" error={fieldErrors.vehicle_id} required>
                  <Select value={form.vehicle_id} onValueChange={(v) => setField("vehicle_id", v)} disabled={!form.driver_id}>
                    <SelectTrigger aria-label="Vehicle"><SelectValue placeholder={form.driver_id ? "Select vehicle" : "Select a driver first"} /></SelectTrigger>
                    <SelectContent>
                      {driverVehicles.length === 0 && <SelectItem value="__none" disabled>No vehicles for this driver</SelectItem>}
                      {driverVehicles.map((v) => (
                        <SelectItem key={v.id} value={String(v.id)}>
                          {v.plate_number}{v.make_model ? ` · ${v.make_model}` : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Offence type" error={fieldErrors.offence_type} required>
                  <Select value={form.offence_type} onValueChange={(v) => setField("offence_type", v)}>
                    <SelectTrigger aria-label="Offence type"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {OFFENCE_OPTIONS.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>

                <div className="grid grid-cols-2 gap-3">
                  <Field label="Speed recorded (km/h)" error={fieldErrors.speed_recorded} required>
                    <Input type="number" min="0" inputMode="numeric" value={form.speed_recorded} onChange={(e) => setField("speed_recorded", e.target.value)} />
                  </Field>
                  <Field label="Speed limit (km/h)" error={fieldErrors.speed_limit} required>
                    <Input type="number" min="0" inputMode="numeric" value={form.speed_limit} onChange={(e) => setField("speed_limit", e.target.value)} />
                  </Field>
                </div>

                <Field label="Zone type" error={fieldErrors.zone_type} required>
                  <Select value={form.zone_type} onValueChange={(v) => setField("zone_type", v)}>
                    <SelectTrigger aria-label="Zone type"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {ZONE_OPTIONS.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Weather" error={fieldErrors.weather} required>
                  <Select value={form.weather} onValueChange={(v) => setField("weather", v)}>
                    <SelectTrigger aria-label="Weather"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {WEATHER_OPTIONS.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Location" error={fieldErrors.location} required>
                  <Input value={form.location} onChange={(e) => setField("location", e.target.value)} placeholder="e.g. Mombasa Rd, km 12" />
                </Field>

                <Field label="Evidence reference" error={fieldErrors.evidence_reference} required>
                  <Input value={form.evidence_reference} onChange={(e) => setField("evidence_reference", e.target.value)} placeholder="e.g. GZIP-2026-001 / photo ref" />
                </Field>

                <Field label="Violation date & time" error={fieldErrors.violation_date_time} required>
                  <Input type="datetime-local" value={form.violation_date_time} onChange={(e) => setField("violation_date_time", e.target.value)} />
                </Field>

                {formError && (
                  <div className="md:col-span-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</div>
                )}

                <div className="md:col-span-2">
                  <Button type="submit" disabled={saving || drivers.length === 0}>
                    {saving ? "Saving…" : "Log violation"}
                  </Button>
                </div>
              </form>
            )}
          </div>

          {/* ---- accident-risk predictor ---- */}
          <div className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="text-sm font-semibold text-slate-900">Predict Accident Risk</div>
            <div className="mt-1 text-xs text-slate-500">Run machine learning prediction from a driver's past records.</div>
            <div className="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <Select value={predictionDriverId} onValueChange={setPredictionDriverId}>
                <SelectTrigger><SelectValue placeholder="Select driver" /></SelectTrigger>
                <SelectContent>
                  {drivers.map((driver) => (
                    <SelectItem key={driver.id} value={String(driver.id)}>
                      {driver.full_name} {driver.license_no ? `(${driver.license_no})` : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button onClick={predictAccidentRisk} disabled={predictingRisk}>
                {predictingRisk ? "Calculating..." : "Predict"}
              </Button>
            </div>
            {predictionMessage && <div className="mt-3 text-sm text-slate-700">{predictionMessage}</div>}
            {predictionResult && (
              <div className="mt-2 text-xs text-slate-500">
                Model {predictionResult.model_version} · Predicted {new Date(predictionResult.predicted_at).toLocaleString()}
              </div>
            )}
          </div>
        </div>
      )}

      {active === "recent" && (
        <DataTable
          resource={appClient.crud.violations}
          resourceLabel="violation"
          refreshSignal={tableRefresh}
          searchPlaceholder="Search location, evidence, status…"
          initialSort="-created_at"
          columns={[
            { key: "driver_name", label: "Driver", render: (row) => row.driver_name || `Driver #${row.driver_id}` },
            { key: "offense_type", label: "Offence", sortable: true, render: (row) => offenceLabel(row.offense_type || row.offence_type) },
            { key: "zone_type", label: "Zone", render: (row) => <span className="capitalize">{String(row.zone_type || "—").replace("_", " ")}</span> },
            { key: "speed_recorded", label: "Speed", align: "right", render: (row) => (row.speed_recorded != null ? `${row.speed_recorded}/${row.speed_limit ?? "?"} km/h` : "—") },
            { key: "points_assigned", label: "Points", sortable: true, align: "right", render: (row) => <span className="font-semibold text-red-600">+{row.points_assigned ?? 0}</span> },
            { key: "created_at", label: "Logged", sortable: true, render: (row) => <span className="text-slate-500">{row.created_at ? new Date(row.created_at).toLocaleString() : "—"}</span> },
            { key: "status", label: "Status", sortable: true, render: (row) => statusPill(row.status) },
          ]}
          filters={[
            { name: "status", label: "status", options: VIOLATION_STATUS_OPTIONS },
            { name: "offense_type", label: "offence", options: OFFENCE_OPTIONS },
            { name: "zone_type", label: "zone", options: ZONE_OPTIONS },
          ]}
          fields={editFields}
          transformRows={editTransformRows}
          canCreate={false}
          canEdit={(row) => canEditViolation(row, user?.id)}
          canDelete={() => false}
          editTitle="Edit violation (own violations within 24 hours)"
          getRowLabel={(row) => `violation #${row.id} (${row.driver_name || `driver #${row.driver_id}`})`}
          onSaved={() => { bumpRecent(); loadCharts(); }}
          emptyText="No violations found. Log one from the Log Violation tab."
        />
      )}

      {active === "payments" && (
        <DataTable
          resource={appClient.crud.payments}
          resourceLabel="payment"
          searchPlaceholder="Search reference, status, method…"
          initialSort="-created_at"
          columns={[
            { key: "driver_name", label: "Driver", render: (row) => row.driver_name || (row.driver_id ? `Driver #${row.driver_id}` : "—") },
            { key: "violation_id", label: "Violation", render: (row) => (row.violation_id ? `#${row.violation_id}` : "—") },
            { key: "method", label: "Method", render: (row) => <span className="capitalize">{row.method || row.payment_method || "—"}</span> },
            { key: "amount", label: "Amount", sortable: true, align: "right", render: (row) => <span className="font-semibold text-slate-900">KES {Number(row.amount || 0).toLocaleString()}</span> },
            { key: "reference_number", label: "Reference", render: (row) => row.reference_number || row.transaction_ref || row.mpesa_receipt || "—" },
            { key: "status", label: "Status", sortable: true, render: (row) => statusPill(row.status) },
          ]}
          filters={[
            { name: "status", label: "status", options: PAYMENT_STATUS_OPTIONS },
          ]}
          fields={[]}
          canCreate={false}
          canEdit={() => false}
          canDelete={() => false}
          rowActions={paymentRowActions}
          emptyText="No payment records found."
        />
      )}

      <ToastStack />
    </AppLayout>
  );
}

/** Small labeled-field wrapper used by the violation form. */
function Field({ label, error, required, children }) {
  return (
    <div>
      <Label className="text-sm text-slate-700">
        {label}
        {required && <span className="ml-0.5 text-red-500">*</span>}
      </Label>
      <div className="mt-1">{children}</div>
      {error && <div className="mt-1 text-xs text-red-600">{error}</div>}
    </div>
  );
}
