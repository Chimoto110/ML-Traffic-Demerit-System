import React, { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { appClient } from "@/api/appClient";
import { OFFENCE_TYPES, ZONE_TYPES, WEATHER_TYPES } from "@/lib/constants";

const DRAFT_STORAGE_PREFIX = "violation_draft_v1";

function defaultDateTime() {
  return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function buildDefaultForm() {
  return {
    driver_id: "",
    vehicle_id: "",
    offence_type: "speeding",
    speed_recorded: "",
    speed_limit: "",
    zone_type: "urban",
    weather: "clear",
    location: "",
    evidence_reference: "",
    violation_date_time: defaultDateTime(),
  };
}

function validateField(name, value, form) {
  if (name === "driver_id" && !value) return "Driver is required.";
  if (name === "vehicle_id" && !value) return "Vehicle is required.";
  if (name === "location" && !String(value || "").trim()) return "Location is required.";
  if (name === "evidence_reference" && !String(value || "").trim()) return "Evidence reference is required.";
  if (name === "violation_date_time" && !value) return "Violation date and time is required.";

  if (name === "speed_recorded") {
    if (value === "" || value === null || value === undefined) return "Speed recorded is required.";
    const n = Number(value);
    if (Number.isNaN(n)) return "Speed recorded must be a number.";
    if (n < 0) return "Speed recorded cannot be negative.";
  }

  if (name === "speed_limit") {
    if (value === "" || value === null || value === undefined) return "Posted speed limit is required.";
    const n = Number(value);
    if (Number.isNaN(n)) return "Posted speed limit must be a number.";
    if (n <= 0) return "Posted speed limit must be greater than zero.";
    if (n > 200) return "Posted speed limit looks too high.";

    const recorded = Number(form.speed_recorded);
    if (!Number.isNaN(recorded) && recorded > 260) {
      return "Speed recorded looks too high. Confirm the value.";
    }
  }

  return "";
}

function validateForm(form) {
  const fields = [
    "driver_id",
    "vehicle_id",
    "offence_type",
    "speed_recorded",
    "speed_limit",
    "zone_type",
    "weather",
    "location",
    "evidence_reference",
    "violation_date_time",
  ];

  const errors = /** @type {Record<string, string>} */ ({});
  fields.forEach((field) => {
    const message = validateField(field, form[field], form);
    if (message) errors[field] = message;
  });
  return errors;
}

export default function ViolationForm({ drivers, vehicles, officer, onLogged }) {
  const [form, setForm] = useState(buildDefaultForm);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState(/** @type {Record<string, string>} */ ({}));
  const [touched, setTouched] = useState(/** @type {Record<string, boolean>} */ ({}));
  const [autosaveState, setAutosaveState] = useState({ status: "idle", at: null });

  const selectedDriver = drivers.find((d) => String(d.id) === String(form.driver_id));
  const driverUserId = selectedDriver?.user_id ? String(selectedDriver.user_id) : null;
  const selectableVehicles = (vehicles || []).filter((vehicle) => {
    if (!driverUserId) return false;
    return String(vehicle.owner_user_id) === driverUserId;
  });

  const draftKey = useMemo(() => {
    const suffix = officer?.id ? String(officer.id) : "unknown";
    return `${DRAFT_STORAGE_PREFIX}_${suffix}`;
  }, [officer?.id]);

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(draftKey);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") return;
      const restored = {
        ...buildDefaultForm(),
        ...parsed,
      };
      setForm(restored);
      setAutosaveState({ status: "restored", at: Date.now() });
    } catch {
      // Ignore bad draft payload and continue with defaults.
    }
  }, [draftKey]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      try {
        window.localStorage.setItem(draftKey, JSON.stringify(form));
        setAutosaveState({ status: "saved", at: Date.now() });
      } catch {
        setAutosaveState({ status: "error", at: Date.now() });
      }
    }, 500);

    return () => window.clearTimeout(timer);
  }, [form, draftKey]);

  const setField = (k, v) => {
    setForm((f) => {
      const next = { ...f, [k]: v };
      if (k === "driver_id") {
        next.vehicle_id = "";
      }

      if (touched[k]) {
        setFieldErrors((prev) => ({
          ...prev,
          [k]: validateField(k, next[k], next),
        }));
      }
      if (k === "speed_recorded" && touched.speed_limit) {
        setFieldErrors((prev) => ({
          ...prev,
          speed_limit: validateField("speed_limit", next.speed_limit, next),
        }));
      }

      return next;
    });
  };

  const markTouched = (field) => {
    setTouched((prev) => ({ ...prev, [field]: true }));
    setFieldErrors((prev) => ({
      ...prev,
      [field]: validateField(field, form[field], form),
    }));
  };

  const clearDraft = () => {
    try {
      window.localStorage.removeItem(draftKey);
    } catch {
      // Best effort only.
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    setError("");

    const errors = validateForm(form);
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) {
      setTouched({
        driver_id: true,
        vehicle_id: true,
        offence_type: true,
        speed_recorded: true,
        speed_limit: true,
        zone_type: true,
        weather: true,
        location: true,
        evidence_reference: true,
        violation_date_time: true,
      });
      setError("Review the highlighted fields before submitting.");
      return;
    }

    setSubmitting(true);
    try {
      const payload = {
        driver_id: form.driver_id,
        vehicle_id: form.vehicle_id,
        officer_id: officer.id,
        offence_type: form.offence_type,
        speed_recorded: Number(form.speed_recorded),
        speed_limit: Number(form.speed_limit),
        zone_type: form.zone_type,
        weather: form.weather,
        location: form.location,
        evidence_reference: form.evidence_reference,
        violation_date_time: form.violation_date_time,
      };
      const res = await appClient.functions.invoke("processViolation", payload);
      setResult(res.data);
      setForm(buildDefaultForm());
      setTouched({});
      setFieldErrors({});
      clearDraft();
      setAutosaveState({ status: "idle", at: null });
      onLogged?.(res.data);
    } catch (err) {
      const backendMessage = err?.response?.data?.message;
      const backendErrors = err?.response?.data?.errors;

      if (backendErrors && typeof backendErrors === "object") {
        const firstFieldErrors = Object.values(backendErrors).find((value) => Array.isArray(value) && value.length > 0);
        if (firstFieldErrors) {
          setError(firstFieldErrors[0]);
          return;
        }
      }

      setError(backendMessage || err.message || "Could not log this violation.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs text-slate-600">
        Mandatory fields: offence type, speed recorded, posted speed limit, zone, weather, violation date/time, location, evidence reference, driver, and vehicle.
      </div>

      <div className="rounded-lg border border-cyan-100 bg-cyan-50/60 px-3 py-2 text-xs text-slate-600">
        {autosaveState.status === "saved" && autosaveState.at ? `Draft autosaved at ${new Date(autosaveState.at).toLocaleTimeString()}` : null}
        {autosaveState.status === "restored" && autosaveState.at ? `Restored unsent draft at ${new Date(autosaveState.at).toLocaleTimeString()}` : null}
        {autosaveState.status === "error" ? "Draft could not be autosaved on this browser." : null}
      </div>

      <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4">
        <div className="md:col-span-2 space-y-1.5">
          <Label>Driver</Label>
          <Select value={form.driver_id} onValueChange={(v) => { setField("driver_id", v); markTouched("driver_id"); markTouched("vehicle_id"); }}>
            <SelectTrigger className="h-11"><SelectValue placeholder="Select driver" /></SelectTrigger>
            <SelectContent>
              {drivers.map((d) => (
                <SelectItem key={d.id} value={d.id}>{d.full_name} · {d.license_number}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          {touched.driver_id && fieldErrors.driver_id && <p className="text-xs text-red-600">{fieldErrors.driver_id}</p>}
        </div>
        <div className="md:col-span-2 space-y-1.5">
          <Label>Vehicle</Label>
          <Select value={form.vehicle_id} onValueChange={(v) => { setField("vehicle_id", v); markTouched("vehicle_id"); }}>
            <SelectTrigger className="h-11"><SelectValue placeholder={selectedDriver ? "Select vehicle" : "Select driver first"} /></SelectTrigger>
            <SelectContent>
              {selectableVehicles.map((v) => (
                <SelectItem key={v.id} value={v.id}>{v.plate_number} · {v.make_model}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          {touched.vehicle_id && fieldErrors.vehicle_id && <p className="text-xs text-red-600">{fieldErrors.vehicle_id}</p>}
          {selectedDriver && selectableVehicles.length === 0 && (
            <p className="text-xs text-amber-700">No vehicle record is linked to this driver account yet.</p>
          )}
        </div>
        <div className="space-y-1.5">
          <Label>Offence Type</Label>
          <Select value={form.offence_type} onValueChange={(v) => setField("offence_type", v)}>
            <SelectTrigger className="h-11"><SelectValue /></SelectTrigger>
            <SelectContent>
              {OFFENCE_TYPES.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Zone Type</Label>
          <Select value={form.zone_type} onValueChange={(v) => setField("zone_type", v)}>
            <SelectTrigger className="h-11"><SelectValue /></SelectTrigger>
            <SelectContent>
              {ZONE_TYPES.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Speed Recorded (km/h)</Label>
          <Input
            type="number"
            inputMode="numeric"
            className="h-11"
            value={form.speed_recorded}
            onChange={(e) => setField("speed_recorded", e.target.value)}
            onBlur={() => markTouched("speed_recorded")}
            placeholder="0"
          />
          {touched.speed_recorded && fieldErrors.speed_recorded && <p className="text-xs text-red-600">{fieldErrors.speed_recorded}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Speed Limit (km/h)</Label>
          <Input
            type="number"
            inputMode="numeric"
            className="h-11"
            value={form.speed_limit}
            onChange={(e) => setField("speed_limit", e.target.value)}
            onBlur={() => markTouched("speed_limit")}
            placeholder="0"
          />
          {touched.speed_limit && fieldErrors.speed_limit && <p className="text-xs text-red-600">{fieldErrors.speed_limit}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Weather</Label>
          <Select value={form.weather} onValueChange={(v) => setField("weather", v)}>
            <SelectTrigger className="h-11"><SelectValue /></SelectTrigger>
            <SelectContent>
              {WEATHER_TYPES.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label>Location</Label>
          <Input
            className="h-11"
            value={form.location}
            onChange={(e) => setField("location", e.target.value)}
            onBlur={() => markTouched("location")}
            placeholder="e.g. Thika Superhighway, Exit 12"
          />
          {touched.location && fieldErrors.location && <p className="text-xs text-red-600">{fieldErrors.location}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Violation Date & Time</Label>
          <Input
            className="h-11"
            type="datetime-local"
            value={form.violation_date_time}
            onChange={(e) => setField("violation_date_time", e.target.value)}
            onBlur={() => markTouched("violation_date_time")}
          />
          {touched.violation_date_time && fieldErrors.violation_date_time && <p className="text-xs text-red-600">{fieldErrors.violation_date_time}</p>}
        </div>
        <div className="md:col-span-2 space-y-1.5">
          <Label>Evidence Reference</Label>
          <Input
            className="h-11"
            value={form.evidence_reference}
            onChange={(e) => setField("evidence_reference", e.target.value)}
            onBlur={() => markTouched("evidence_reference")}
            placeholder="e.g. cam-KE-THK-2026-08-31-0012"
          />
          {touched.evidence_reference && fieldErrors.evidence_reference && <p className="text-xs text-red-600">{fieldErrors.evidence_reference}</p>}
        </div>
      </div>

      {error && <div className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{error}</div>}

      {result && (
        <div className="text-sm bg-emerald-50 border border-emerald-200 rounded-lg p-3 space-y-1">
          <div className="font-semibold text-emerald-800">Violation logged successfully</div>
          <div className="text-emerald-700">Points added: +{result.demerit_points}. New balance: {result.new_balance}</div>
          <div className="text-emerald-700">Recidivism risk: {result.risk.classification.toUpperCase()} ({(result.risk.score * 100).toFixed(0)}%)</div>
          {result.sanction_triggered && <div className="text-red-700 font-semibold">Automatic sanction triggered. Profile locked.</div>}
        </div>
      )}

      <Button type="submit" disabled={submitting} className="h-11 w-full text-sm font-semibold">
        {submitting ? "Saving..." : "Log Violation"}
      </Button>
    </form>
  );
}