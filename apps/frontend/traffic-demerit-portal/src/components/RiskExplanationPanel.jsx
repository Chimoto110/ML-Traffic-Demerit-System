import React, { useMemo, useState } from "react";

function humanizeFeatureName(raw = "") {
  const token = String(raw)
    .replace(/^num__/, "")
    .replace(/^cat__/, "")
    .split("_")
    .filter(Boolean)
    .map((piece) => piece.charAt(0).toUpperCase() + piece.slice(1));

  return token.join(" ") || "Unknown Factor";
}

function normalizeFactors(factors = []) {
  const cleaned = factors
    .map((factor, index) => ({
      id: `${factor.feature || "factor"}-${index}`,
      feature: factor.feature || `factor_${index + 1}`,
      contribution: Number(factor.contribution) || 0,
      weight_percentage: Number(factor.weight_percentage),
    }))
    .filter((factor) => factor.contribution >= 0);

  if (cleaned.length === 0) return [];

  const hasProvidedWeight = cleaned.some((factor) => Number.isFinite(factor.weight_percentage));
  if (hasProvidedWeight) {
    return cleaned
      .map((factor) => ({
        ...factor,
        weight: Number.isFinite(factor.weight_percentage) ? factor.weight_percentage : 0,
      }))
      .sort((a, b) => b.weight - a.weight);
  }

  const total = cleaned.reduce((sum, factor) => sum + factor.contribution, 0);
  return cleaned
    .map((factor) => ({
      ...factor,
      weight: total > 0 ? (factor.contribution / total) * 100 : 0,
    }))
    .sort((a, b) => b.weight - a.weight);
}

export default function RiskExplanationPanel({ riskRecord, title = "Interactive Risk Breakdown" }) {
  const factors = useMemo(() => normalizeFactors(riskRecord?.factor_contributions || []), [riskRecord]);
  const [activeFactorId, setActiveFactorId] = useState(null);

  const activeFactor = useMemo(() => {
    if (factors.length === 0) return null;
    if (!activeFactorId) return factors[0];
    return factors.find((factor) => factor.id === activeFactorId) || factors[0];
  }, [factors, activeFactorId]);

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <div className="text-sm font-semibold text-slate-900">{title}</div>
          <div className="text-xs text-slate-500">Click a factor to inspect how strongly it influenced this score.</div>
        </div>
        <span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">
          {factors.length} factor{factors.length === 1 ? "" : "s"}
        </span>
      </div>

      {factors.length === 0 ? (
        <div className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
          No factor explanations available yet. Run a prediction to populate this chart.
        </div>
      ) : (
        <>
          <div className="mt-4 space-y-2">
            {factors.map((factor) => {
              const isActive = factor.id === (activeFactor?.id || factors[0].id);
              return (
                <button
                  key={factor.id}
                  type="button"
                  onClick={() => setActiveFactorId(factor.id)}
                  className={`w-full rounded-lg border px-3 py-2 text-left transition ${isActive ? "border-orange-300 bg-orange-50" : "border-slate-200 bg-white hover:bg-slate-50"}`}
                >
                  <div className="mb-1 flex items-center justify-between gap-3">
                    <span className="text-xs font-semibold text-slate-700">{humanizeFeatureName(factor.feature)}</span>
                    <span className="text-xs font-bold text-slate-900">{factor.weight.toFixed(1)}%</span>
                  </div>
                  <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                    <div
                      className={`h-full rounded-full transition-all duration-300 ${isActive ? "bg-orange-500" : "bg-cyan-500"}`}
                      style={{ width: `${Math.max(0, Math.min(100, factor.weight))}%` }}
                    />
                  </div>
                </button>
              );
            })}
          </div>

          {activeFactor && (
            <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
              <div className="text-xs uppercase tracking-wide text-slate-500">Selected factor</div>
              <div className="mt-1 text-sm font-semibold text-slate-900">{humanizeFeatureName(activeFactor.feature)}</div>
              <div className="mt-1 text-xs text-slate-600">
                Weighted contribution: {activeFactor.weight.toFixed(2)}% of this prediction signal.
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
