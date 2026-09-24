import React, { useState, useEffect } from "react";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import ViolationForm from "@/components/ViolationForm";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel, zoneLabel, weatherLabel } from "@/lib/constants";

export default function OfficerDashboard({ user, effectiveRole, onRoleChange }) {
  const [drivers, setDrivers] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [recent, setRecent] = useState([]);
  const [active, setActive] = useState("log");
  const [loading, setLoading] = useState(true);
  const [predictionDriverId, setPredictionDriverId] = useState("");
  const [predictingRisk, setPredictingRisk] = useState(false);
  const [predictionMessage, setPredictionMessage] = useState("");
  const [predictionResult, setPredictionResult] = useState(null);
  const [recentSearchInput, setRecentSearchInput] = useState("");
  const [recentSearchTerm, setRecentSearchTerm] = useState("");

  const load = async () => {
    setLoading(true);
    const [d, v, veh] = await Promise.all([
      appClient.entities.Driver.list("-demerit_balance", 200),
      appClient.entities.Violation.list("-timestamp", 20),
      appClient.entities.Vehicle.list("plate_number", 300),
    ]);
    setDrivers(d);
    setVehicles(veh);
    if (!predictionDriverId && d.length > 0) {
      setPredictionDriverId(String(d[0].id));
    }
    setRecent(v);
    setLoading(false);
  };
  useEffect(() => { load(); }, []);

  const predictAccidentRisk = async () => {
    if (!predictionDriverId) {
      setPredictionMessage("Select a driver first.");
      return;
    }

    setPredictingRisk(true);
    setPredictionMessage("");

    try {
      const response = await appClient.functions.invoke("predictAccidentRisk", {
        driver_id: Number(predictionDriverId),
      });

      const result = response?.data;
      if (!result) {
        throw new Error("Prediction response was empty.");
      }

      setPredictionResult(result);
      setPredictionMessage(`Predicted risk: ${(result.risk.score * 100).toFixed(1)}% (${String(result.risk.classification).toUpperCase()}).`);
    } catch (err) {
      setPredictionResult(null);
      setPredictionMessage(err.message || "Failed to generate risk prediction.");
    } finally {
      setPredictingRisk(false);
    }
  };

  const applyRecentSearch = () => {
    setRecentSearchTerm(recentSearchInput.trim().toLowerCase());
  };

  const clearRecentSearch = () => {
    setRecentSearchInput("");
    setRecentSearchTerm("");
  };

  const filteredRecent = recent.filter((v) => {
    if (!recentSearchTerm) return true;
    const text = [
      v.driver_name,
      offenceLabel(v.offence_type),
      zoneLabel(v.zone_type),
      weatherLabel(v.weather),
      v.location,
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return text.includes(recentSearchTerm);
  });

  return (
    <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Field Violation Console</h1>
        <p className="text-sm text-slate-500">Log a traffic violation. The system auto-posts demerit points and runs recidivism inference.</p>
      </header>

      {active === "log" && (
        <div className="max-w-2xl">
          <div className="rounded-2xl border border-slate-200 bg-white p-6">
            <ViolationForm drivers={drivers} vehicles={vehicles} officer={user} onLogged={load} />
          </div>
          <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div className="text-sm font-semibold text-slate-900">Predict Accident Risk</div>
            <div className="mt-1 text-xs text-slate-500">Run machine learning prediction from a driver's past records.</div>
            <div className="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <Select value={predictionDriverId} onValueChange={setPredictionDriverId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select driver" />
                </SelectTrigger>
                <SelectContent>
                  {drivers.map((driver) => (
                    <SelectItem key={driver.id} value={String(driver.id)}>
                      {driver.full_name} ({driver.license_number})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button onClick={predictAccidentRisk} disabled={predictingRisk}>
                {predictingRisk ? "Calculating..." : "Predict"}
              </Button>
            </div>
            {predictionMessage && (
              <div className="mt-3 text-sm text-slate-700">{predictionMessage}</div>
            )}
            {predictionResult && (
              <div className="mt-2 text-xs text-slate-500">
                Model {predictionResult.model_version} · Predicted {new Date(predictionResult.predicted_at).toLocaleString()}
              </div>
            )}
          </div>
        </div>
      )}

      {active === "recent" && (
        <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
          <div className="px-5 py-4 border-b border-slate-100 font-semibold text-slate-900">Recent Violations</div>
          <div className="px-5 py-3 border-b border-slate-100 flex flex-wrap gap-2">
            <Input
              value={recentSearchInput}
              onChange={(e) => setRecentSearchInput(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  applyRecentSearch();
                }
              }}
              placeholder="Search by driver, offence, zone, weather..."
              className="max-w-md"
            />
            <Button size="sm" onClick={applyRecentSearch}>Search</Button>
            <Button size="sm" variant="outline" onClick={clearRecentSearch}>Clear</Button>
          </div>
          {loading ? <div className="p-8 text-center text-slate-400 text-sm">Loading…</div> : filteredRecent.length === 0 ? (
            <div className="p-8 text-center text-slate-400 text-sm">No violations logged yet.</div>
          ) : (
            <div className="divide-y divide-slate-100">
              {filteredRecent.map((v) => (
                <div key={v.id} className="px-5 py-3 flex items-center justify-between gap-3">
                  <div>
                    <div className="font-medium text-slate-900 text-sm">{v.driver_name}</div>
                    <div className="text-xs text-slate-500">{offenceLabel(v.offence_type)} · {zoneLabel(v.zone_type)} · {weatherLabel(v.weather)} · {v.speed_recorded}km/h</div>
                  </div>
                  <div className="text-right">
                    <div className="text-sm font-semibold text-red-600">+{v.demerit_points}</div>
                    <div className="text-xs text-slate-400">{new Date(v.timestamp).toLocaleDateString()}</div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </AppLayout>
  );
}