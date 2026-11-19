import React from "react";
import { toNum } from "./chartUtils";

const CLASS_COLORS = { low: "#10b981", moderate: "#f59e0b", high: "#ef4444" };

/**
 * Pure SVG semicircle gauge (driver demerit balance) with warning and
 * suspension threshold markers.
 *
 * Props:
 *  - value: number            current demerit balance
 *  - max: number (100)
 *  - thresholds: [{ value, label, color }]
 *  - classification: 'low'|'moderate'|'high' (drives the value arc color)
 *  - label: string under the readout
 *  - status: string           small caption (e.g. driver account status)
 *  - height: number (default 180)
 */
export default function GaugeChart({
  value = 0,
  max = 100,
  thresholds = [],
  classification = null,
  label = "demerit points",
  status = null,
  height = 180,
}) {
  const v = Math.min(Math.max(toNum(value), 0), toNum(max) || 100);
  const m = toNum(max) || 100;
  const pct = v / m;
  const color = (classification && CLASS_COLORS[String(classification).toLowerCase()]) || "#06b6d4";

  const size = 260;
  const cx = size / 2;
  const cy = size / 2 + 4;
  const radius = 96;
  const thickness = 18;

  const polar = (angle, r) => [cx + r * Math.cos(angle), cy + r * Math.sin(angle)];
  // Semicircle from 180° (left) to 360°/0° (right), going clockwise on screen.
  const arcPath = (fromFrac, toFrac, r) => {
    const a0 = Math.PI + fromFrac * Math.PI;
    const a1 = Math.PI + toFrac * Math.PI;
    const [x0, y0] = polar(a0, r);
    const [x1, y1] = polar(a1, r);
    const large = toFrac - fromFrac > 0.5 ? 1 : 0;
    return `M ${x0} ${y0} A ${r} ${r} 0 ${large} 1 ${x1} ${y1}`;
  };

  return (
    <div className="flex flex-col items-center" role="img" aria-label={`Demerit gauge: ${v} of ${m} ${label}, ${classification || "unclassified"}`}>
      <svg viewBox={`0 0 ${size} ${cy + 12}`} width={height * 1.5} height={height} style={{ maxWidth: "100%" }}>
        {/* track */}
        <path d={arcPath(0, 1, radius)} fill="none" stroke="#e2e8f0" strokeWidth={thickness} strokeLinecap="round" />

        {/* threshold zones (faint) */}
        {thresholds.map((t, i) => {
          const frac = Math.min(toNum(t.value) / m, 1);
          const start = i === 0 ? 0 : Math.min(toNum(thresholds[i - 1].value) / m, 1);
          return (
            <path
              key={`zone-${i}`}
              d={arcPath(start, frac, radius)}
              fill="none"
              stroke={t.color || "#f59e0b"}
              strokeWidth={thickness}
              opacity="0.16"
            />
          );
        })}

        {/* value arc */}
        <path d={arcPath(0, pct, radius)} fill="none" stroke={color} strokeWidth={thickness} strokeLinecap="round" />

        {/* threshold ticks + labels */}
        {thresholds.map((t) => {
          const frac = Math.min(toNum(t.value) / m, 1);
          const angle = Math.PI + frac * Math.PI;
          const [tx0, ty0] = polar(angle, radius - thickness / 2 - 2);
          const [tx1, ty1] = polar(angle, radius + thickness / 2 + 2);
          const [lx, ly] = polar(angle, radius + thickness / 2 + 14);
          return (
            <g key={`tick-${t.label || t.value}`}>
              <line x1={tx0} y1={ty0} x2={tx1} y2={ty1} stroke="#475569" strokeWidth="1.6" />
              <text x={lx} y={ly} textAnchor="middle" fontSize="9" fontWeight="600" fill={t.color || "#64748b"}>
                {t.label || t.value}
              </text>
            </g>
          );
        })}

        {/* readout */}
        <text x={cx} y={cy - 8} textAnchor="middle" fontSize="34" fontWeight="700" fill="#0f172a">
          {v}
          <tspan fontSize="16" fill="#94a3b8">{` / ${m}`}</tspan>
        </text>
        <text x={cx} y={cy + 12} textAnchor="middle" fontSize="11" fill="#64748b">
          {label}
        </text>
        {status && (
          <text x={cx} y={cy + 28} textAnchor="middle" fontSize="10" fontWeight="600" fill={String(status).toLowerCase() === "active" ? "#10b981" : "#ef4444"}>
            {String(status).toUpperCase()}
          </text>
        )}
      </svg>
    </div>
  );
}
