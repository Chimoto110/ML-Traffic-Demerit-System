import React from "react";
import { tierForScore, tierConfig } from "@/lib/constants";

export default function RiskBadge({ score, classification, tier, showTier = true }) {
  const riskCls = classification ?? (score >= 0.7 ? "high" : score >= 0.4 ? "moderate" : "low");
  const tierLabel = tier ?? tierForScore(score);
  const tierCfg = tierConfig(tierLabel);

  const badgeStyles = {
    high: "bg-red-100 text-red-700 border-red-200",
    moderate: "bg-amber-100 text-amber-700 border-amber-200",
    low: "bg-emerald-100 text-emerald-700 border-emerald-200",
  };

  const tierStyles = {
    good_standing: "bg-emerald-50 border-emerald-200 text-emerald-700",
    warning: "bg-amber-50 border-amber-200 text-amber-700",
    restricted: "bg-orange-50 border-orange-200 text-orange-700",
    suspended: "bg-red-50 border-red-200 text-red-700",
  };

  return (
    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border ${badgeStyles[riskCls]}">
      <span className="w-1.5 h-1.5 rounded-full bg-current" />
      {riskCls.toUpperCase()} &middot; {(score * 100).toFixed(0)}%
    </span>
  );
}

RiskBadge.displayName = "RiskBadge";

export function TierBadge({ score, tier }) {
  const tierLabel = tier ?? tierForScore(score);
  const tierCfg = tierConfig(tierLabel);

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border ${tierCfg.color === "red" ? "bg-red-50 border-red-200 text-red-700" : tierCfg.color === "orange" ? "bg-orange-50 border-orange-200 text-orange-700" : tierCfg.color === "amber" ? "bg-amber-50 border-amber-200 text-amber-700" : "bg-emerald-50 border-emerald-200 text-emerald-700"}`}>
      {tierLabel.charAt(0).toUpperCase() + tierLabel.slice(1).replace(/_/g, " ")}
    </span>
  );
}

TierBadge.displayName = "TierBadge";

export function PointsTierBadge({ points }) {
  let tier = "good_standing";
  if (points >= 30) tier = "suspended";
  else if (points >= 20) tier = "restricted";
  else if (points >= 10) tier = "warning";

  const tierCfg = tierConfig(tier);

  return (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold border ${tierCfg.color === "red" ? "bg-red-50 border-red-200 text-red-700" : tierCfg.color === "orange" ? "bg-orange-50 border-orange-200 text-orange-700" : tierCfg.color === "amber" ? "bg-amber-50 border-amber-200 text-amber-700" : "bg-emerald-50 border-emerald-200 text-emerald-700"}`}>
      {tierCfg.label}
    </span>
  );
}

PointsTierBadge.displayName = "PointsTierBadge";
