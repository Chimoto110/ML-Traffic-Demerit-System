import React from "react";
import RiskBadge from "./RiskBadge";
import { Button } from "@/components/ui/button";

export default function SanctionCard({ sanction, onLift = null, canLift = false }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="font-semibold text-slate-900">{sanction.driver_name}</div>
          <div className="text-xs text-slate-500">Triggered {new Date(sanction.triggered_at).toLocaleString()}</div>
        </div>
        <div className="flex items-center gap-2">
          <RiskBadge score={sanction.risk_score} classification={sanction.risk_classification} />
          <span className={`text-xs font-semibold px-2 py-1 rounded-full ${sanction.status === "active" ? "bg-red-100 text-red-700" : "bg-slate-100 text-slate-500"}`}>
            {sanction.status === "active" ? "PROFILE LOCKED" : "LIFTED"}
          </span>
        </div>
      </div>

      <div className="text-xs text-slate-600 leading-relaxed bg-slate-50 rounded-lg p-3">{sanction.explanation}</div>

      {sanction.factors?.length > 0 && (
        <div className="space-y-1.5">
          <div className="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Behavioural factor breakdown</div>
          {sanction.factors.map((f, i) => (
            <div key={i} className="flex items-center gap-2 text-xs">
              <span className={`w-2 h-2 rounded-full ${f.direction === "risk" ? "bg-red-500" : "bg-emerald-500"}`} />
              <span className="flex-1 text-slate-700">{f.feature}: <span className="font-medium">{f.value}</span></span>
              <span className="text-slate-400">contribution {f.contribution >= 0 ? "+" : ""}{(f.contribution * 100).toFixed(0)}%</span>
            </div>
          ))}
        </div>
      )}

      {canLift && sanction.status === "active" && onLift && (
        <Button size="sm" variant="outline" onClick={() => onLift(sanction)}>Lift Sanction</Button>
      )}
    </div>
  );
}