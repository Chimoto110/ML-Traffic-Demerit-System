import React, { useState } from "react";
import { ChartTooltip } from "./ChartCard";
import { PALETTE, toNum } from "./chartUtils";

const VB_W = 640;

/**
 * Pure SVG bubble chart: x, y and size (radius ~ sqrt of the size value).
 *
 * Props:
 *  - data: Array<object>  with x/y/size fields
 *  - xKey, yKey, sizeKey: string
 *  - labelKey: string|null  (tooltip title, e.g. "label")
 *  - metaKeys: [string]     extra tooltip fields
 *  - height: number (default 260)
 *  - ariaLabel
 */
export default function BubbleChart({
  data = [],
  xKey = "x",
  yKey = "y",
  sizeKey = "size",
  labelKey = null,
  metaKeys = [],
  height = 260,
  ariaLabel = "Bubble chart",
}) {
  const [hover, setHover] = useState(null);
  const rows = (Array.isArray(data) ? data : []).filter(
    (r) => Number.isFinite(Number(r[xKey])) && Number.isFinite(Number(r[yKey]))
  );

  if (rows.length === 0) {
    return <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No data yet</div>;
  }

  const margin = { top: 14, right: 16, bottom: 32, left: 48 };
  const innerW = VB_W - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;

  const xs = rows.map((r) => toNum(r[xKey]));
  const ys = rows.map((r) => toNum(r[yKey]));
  const sizes = rows.map((r) => toNum(r[sizeKey]));
  const xMin = Math.min(...xs);
  const xMax = Math.max(...xs);
  const yMin = Math.min(...ys);
  const yMax = Math.max(...ys);
  const sizeMin = Math.min(...sizes);
  const sizeMax = Math.max(...sizes);
  const padX = (xMax - xMin) * 0.1 || 0.05;
  const padY = (yMax - yMin) * 0.1 || 0.05;
  const xLo = Math.max(0, xMin - padX);
  const xHi = xMax + padX;
  const yLo = Math.max(0, yMin - padY);
  const yHi = yMax + padY;

  const xAt = (v) => margin.left + ((toNum(v) - xLo) / (xHi - xLo || 1)) * innerW;
  const yAt = (v) => margin.top + innerH - ((toNum(v) - yLo) / (yHi - yLo || 1)) * innerH;
  const rAt = (v) => {
    if (sizeMax === sizeMin) return 10;
    return 6 + Math.sqrt((toNum(v) - sizeMin) / (sizeMax - sizeMin || 1)) * 14;
  };

  const xTicks = [0, 0.25, 0.5, 0.75, 1].map((f) => xLo + f * (xHi - xLo));
  const yTicksList = [0, 0.25, 0.5, 0.75, 1].map((f) => yLo + f * (yHi - yLo));

  return (
    <div className="relative" onMouseLeave={() => setHover(null)}>
      <svg
        viewBox={`0 0 ${VB_W} ${height}`}
        width="100%"
        role="img"
        aria-label={ariaLabel}
        style={{ display: "block" }}
        preserveAspectRatio="xMidYMid meet"
      >
        {yTicksList.map((t, i) => {
          const y = yAt(t);
          return (
            <g key={`yt-${i}`}>
              <line x1={margin.left} x2={VB_W - margin.right} y1={y} y2={y} stroke="#e2e8f0" strokeWidth="1" />
              <text x={margin.left - 6} y={y + 3} textAnchor="end" fontSize="9" fill="#94a3b8">
                {t.toFixed(2)}
              </text>
            </g>
          );
        })}
        {xTicks.map((t, i) => (
          <text key={`xt-${i}`} x={xAt(t)} y={height - 16} textAnchor="middle" fontSize="9" fill="#94a3b8">
            {t.toFixed(2)}
          </text>
        ))}
        <text x={margin.left + innerW / 2} y={height - 3} textAnchor="middle" fontSize="10" fill="#64748b">
          {xKey} →
        </text>
        <text x={12} y={margin.top + innerH / 2} fontSize="10" fill="#64748b" transform={`rotate(-90 12 ${margin.top + innerH / 2})`} textAnchor="middle">
          {yKey} ↑
        </text>

        {rows.map((row, i) => (
          <circle
            key={i}
            cx={xAt(row[xKey])}
            cy={yAt(row[yKey])}
            r={rAt(row[sizeKey])}
            fill={PALETTE[i % PALETTE.length]}
            opacity={hover === null || hover === i ? 0.55 : 0.22}
            stroke={PALETTE[i % PALETTE.length]}
            strokeWidth={hover === i ? 2.5 : 1}
            tabIndex={0}
            role="img"
            aria-label={`${row[labelKey] ?? `Bubble ${i + 1}`}: ${xKey} ${row[xKey]}, ${yKey} ${row[yKey]}, ${sizeKey} ${row[sizeKey]}`}
            onMouseEnter={() => setHover(i)}
            onFocus={() => setHover(i)}
          />
        ))}
      </svg>

      {hover !== null && rows[hover] && (
        <ChartTooltip x={xAt(rows[hover][xKey])} y={yAt(rows[hover][yKey]) - rAt(rows[hover][sizeKey])} width={VB_W}>
          <div className="font-semibold">{labelKey ? rows[hover][labelKey] : `Bubble ${hover + 1}`}</div>
          <div className="text-slate-600">
            {xKey}: {rows[hover][xKey]} · {yKey}: {rows[hover][yKey]}
          </div>
          <div className="text-slate-600">
            {sizeKey}: {rows[hover][sizeKey]}
          </div>
          {metaKeys.map((k) => (
            <div key={k} className="text-slate-500">
              {k}: {rows[hover][k]}
            </div>
          ))}
        </ChartTooltip>
      )}

      <div className="mt-1 px-1 text-[11px] text-slate-400">Bubble size ∝ {sizeKey}</div>
    </div>
  );
}
