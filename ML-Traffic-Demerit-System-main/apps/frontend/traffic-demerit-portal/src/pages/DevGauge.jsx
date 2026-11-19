import React, { useState } from "react";
import { RiskGauge } from "@/components/RiskGauge";

const SLIDER_MARKS = [0, 0.25, 0.4, 0.55, 0.7, 1];

export default function DevGauge() {
  const [score, setScore] = useState(0.5);

  return (
    <div className="py-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-slate-900">Dev: Risk Gauge Preview</h1>
        <div className="flex items-center gap-3">
          <span className="text-sm text-slate-600">Risk Score</span>
          <span className="text-lg font-mono font-bold">{score.toFixed(2)}</span>
        </div>
      </div>

      <div className="max-w-3xl mx-auto">
        <div className="rounded-xl border border-slate-200 bg-white p-6">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-sm font-semibold text-slate-900">Risk Gauge</h2>
            <div className="flex gap-2">
              <button
                className="text-sm font-medium text-slate-700 hover:text-red-700"
                onClick={() => setScore(0.1)}
              >
                Low (0.10)
              </button>
              <button
                className="text-sm font-medium text-slate-700 hover:text-amber-700"
                onClick={() => setScore(0.45)}
              >
                Moderate (0.45)
              </button>
              <button
                className="text-sm font-medium text-slate-700 hover:text-orange-700"
                onClick={() => setScore(0.60)}
              >
                Restricted (0.60)
              </button>
              <button
                className="text-sm font-medium text-slate-700 hover:text-red-700"
                onClick={() => setScore(0.74)}
              >
                Suspended (0.74)
              </button>
            </div>
          </div>

          <div className="mb-6">
            <RiskGauge
              score={score}
              size="lg"
              classification={score >= 0.7 ? "high" : score >= 0.4 ? "moderate" : "low"}
              tier={
                score >= 0.7
                  ? "suspended"
                  : score >= 0.55
                  ? "restricted"
                  : score >= 0.4
                  ? "warning"
                  : "good_standing"
              }
              modelVersion="rf_v2"
              floorApplied={score >= 0.4}
              floorScore={score >= 0.7 ? 0.70 : score >= 0.55 ? 0.55 : score >= 0.4 ? 0.40 : undefined}
            />
          </div>

          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-slate-900">Score Slider</h3>
            <div className="flex items-center gap-2">
              <input
                type="range"
                min="0"
                max="1"
                step="0.01"
                value={score}
                onChange={(e) => setScore(parseFloat(e.target.value))}
                className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-red-500"
              />
              <span className="text-sm font-mono">{score.toFixed(2)}</span>
            </div>
            <div className="flex justify-between text-[10px] text-slate-500">
              {[0, 0.25, 0.4, 0.55, 0.7, 1].map((mark) => (
                <span key={mark}>{mark.toFixed(2)}</span>
              ))}
            </div>
          </div>

          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-slate-900">Zone Reference</h3>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-slate-500 text-xs uppercase border-b border-slate-100">
                  <th className="text-left py-2">Score Range</th>
                  <th className="text-left py-2">Tier</th>
                  <th className="text-left py-2">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-b border-slate-50">
                  <td className="py-2">0.00 - 0.39</td>
                  <td className="py-2">
                    <span className="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-xs">
                      LOW
                    </span>
                  </td>
                  <td className="py-2 text-slate-600">Good standing</td>
                </tr>
                <tr className="border-b border-slate-50">
                  <td className="py-2">0.40 - 0.54</td>
                  <td className="py-2">
                    <span className="px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-xs">
                      MODERATE
                    </span>
                  </td>
                  <td className="py-2 text-slate-600">Warning</td>
                </tr>
                <tr className="border-b border-slate-50">
                  <td className="py-2">0.55 - 0.69</td>
                  <td className="py-2">
                    <span className="px-2 py-0.5 rounded bg-orange-50 text-orange-700 text-xs">
                      MODERATE
                    </span>
                  </td>
                  <td className="py-2 text-slate-600">Restricted</td>
                </tr>
                <tr className="border-b border-slate-50">
                  <td className="py-2">0.70 - 1.00</td>
                  <td className="py-2">
                    <span className="px-2 py-0.5 rounded bg-red-50 text-red-700 text-xs">
                      HIGH
                    </span>
                  </td>
                  <td className="py-2 text-slate-600">Suspended</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
