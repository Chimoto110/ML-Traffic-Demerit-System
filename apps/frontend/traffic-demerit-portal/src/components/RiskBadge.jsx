import React from "react";

export default function RiskBadge({ score, classification }) {
  const cls = classification ?? (score >= 0.7 ? "high" : score >= 0.4 ? "moderate" : "low");
  const styles = {
    high: "bg-red-100 text-red-700 border-red-200",
    moderate: "bg-amber-100 text-amber-700 border-amber-200",
    low: "bg-emerald-100 text-emerald-700 border-emerald-200",
  };
  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${styles[cls]}`}>
      <span className="w-1.5 h-1.5 rounded-full bg-current" />
      {cls.toUpperCase()} · {(score * 100).toFixed(0)}%
    </span>
  );
}