import React from "react";

const COLOR_STYLES = {
  high: {
    track: "bg-red-100",
    fill: "bg-red-500",
    text: "text-red-700",
    badge: "bg-red-50 border-red-200 text-red-700",
    label: "RED RISK",
  },
  moderate: {
    track: "bg-amber-100",
    fill: "bg-amber-500",
    text: "text-amber-700",
    badge: "bg-amber-50 border-amber-200 text-amber-700",
    label: "AMBER RISK",
  },
  low: {
    track: "bg-emerald-100",
    fill: "bg-emerald-500",
    text: "text-emerald-700",
    badge: "bg-emerald-50 border-emerald-200 text-emerald-700",
    label: "GREEN RISK",
  },
};

function resolveRiskClass({ classification, score, balance }) {
  if (classification === "high" || classification === "moderate" || classification === "low") {
    return classification;
  }

  if (typeof score === "number") {
    if (score >= 0.7) return "high";
    if (score >= 0.4) return "moderate";
    return "low";
  }

  if (balance >= 24) return "high";
  if (balance >= 12) return "moderate";
  return "low";
}

export default function LiveDemeritGauge({
  balance = 0,
  classification,
  score,
  cap = 30,
  compact = false,
}) {
  const safeBalance = Number(balance) || 0;
  const cappedRatio = Math.max(0, Math.min(safeBalance / cap, 1));
  const percent = Math.round(cappedRatio * 100);
  const riskClass = resolveRiskClass({ classification, score, balance: safeBalance });
  const colors = COLOR_STYLES[riskClass];

  return (
    <div className={compact ? "space-y-2" : "space-y-3"}>
      <div className="flex items-center justify-between gap-3">
        <div className={`text-xs font-semibold uppercase tracking-wide ${colors.text}`}>
          {colors.label}
        </div>
        <span className={`text-[11px] font-semibold px-2 py-1 rounded-full border ${colors.badge}`}>
          {safeBalance} pts
        </span>
      </div>

      <div className={`h-2.5 w-full rounded-full overflow-hidden ${colors.track}`}>
        <div
          className={`h-full rounded-full transition-all duration-500 ${colors.fill}`}
          style={{ width: `${percent}%` }}
        />
      </div>

      <div className="flex items-center justify-between text-[11px] text-slate-500">
        <span>{percent}% of {cap}-point watch limit</span>
        <span>{typeof score === "number" ? `${Math.round(score * 100)}% model risk` : "Awaiting model score"}</span>
      </div>
    </div>
  );
}
