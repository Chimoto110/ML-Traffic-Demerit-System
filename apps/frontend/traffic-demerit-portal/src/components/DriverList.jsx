import React from "react";
import RiskBadge from "./RiskBadge";

function initials(name = "") {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("") || "DR";
}

export default function DriverList({ drivers, scores = {}, onSelect, selectedId }) {
  return (
    <div className="space-y-2">
      {drivers.map((d) => {
        const score = scores[d.id];
        return (
          <button
            key={d.id}
            onClick={() => onSelect?.(d)}
            className={`w-full text-left flex items-center justify-between gap-3 px-4 py-3 rounded-xl border transition-colors ${
              selectedId === d.id ? "border-slate-900 bg-slate-50" : "border-slate-200 bg-white hover:bg-slate-50"
            }`}
          >
            <div className="min-w-0 flex items-center gap-3">
              {d.profile_photo_url ? (
                <img
                  src={d.profile_photo_url}
                  alt={`${d.full_name} profile`}
                  className="w-10 h-10 rounded-full object-cover border border-slate-200"
                />
              ) : (
                <div className="w-10 h-10 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-xs font-semibold border border-slate-300">
                  {initials(d.full_name)}
                </div>
              )}
              <div className="min-w-0">
                <div className="font-semibold text-slate-900 truncate">{d.full_name}</div>
                <div className="text-xs text-slate-500">{d.license_number} · {d.total_violations || 0} violations</div>
              </div>
            </div>
            <div className="flex items-center gap-3 shrink-0">
              <div className="text-right">
                <div className="text-[10px] uppercase text-slate-400 font-semibold">Balance</div>
                <div className="font-bold text-slate-900">{d.demerit_balance || 0}</div>
              </div>
              {score ? <RiskBadge score={score.risk_score} classification={score.risk_classification} /> : (
                <span className={`text-xs font-semibold px-2 py-1 rounded-full ${d.status === "locked" ? "bg-red-100 text-red-700" : "bg-emerald-100 text-emerald-700"}`}>
                  {d.status === "locked" ? "LOCKED" : "ACTIVE"}
                </span>
              )}
            </div>
          </button>
        );
      })}
    </div>
  );
}