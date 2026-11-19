import React, { useState } from "react";
import { ChartTooltip } from "./ChartCard";
import { PALETTE, toNum } from "./chartUtils";

const VB_W = 640;

/**
 * Pure SVG scatter plot with a per-point tooltip and a group (segment)
 * legend. Points are colored by `groupKey`.
 *
 * Props:
 *  - data: Array<object>
 *  - xKey, yKey: string
 *  - groupKey: string|null (e.g. "segment_label")
 *  - labelKey: string|null (tooltip title, e.g. "driver_name")
 *  - metaKeys: [string]   extra fields shown in the tooltip
 *  - height: number (default 280)
 *  - ariaLabel
 */
export default function ScatterPlot({
  data = [],
  xKey = "x",
  yKey = "y",
  groupKey = null,
  labelKey = null,
  metaKeys = [],
  height = 280,
  ariaLabel = "Scatter plot",
}) {
  const [hover, setHover] = useState(null);
  const rows = Array.isArray(data) ? data : [];

  if (rows.length === 0) {
    return <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No data yet</div>;
  }

  const groups = groupKey ? [...new Set(rows.map((r) => String(r[groupKey] ?? "group")))] : ["points"];
  const colorOf = (row, i) => {
    const key = groupKey ? String(row[groupKey] ?? "group") : "points";
    return PALETTE[Math.max(0, groups.indexOf(key)) % PALETTE.length];
  };

  const margin = { top: 14, right: 14, bottom: 32, left: 48 };
  const innerW = VB_W - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;

  const xs = rows.map((r) => toNum(r[xKey]));
  const ys = rows.map((r) => toNum(r[yKey]));
  const xMin = Math.min(...xs);
  const xMax = Math.max(...xs);
  const yMin = Math.min(...ys);
  const yMax = Math.max(...ys);
  const padX = (xMax - xMin) * 0.06 || 1;
  const padY = (yMax - yMin) * 0.06 || 1;
  const xLo = xMin - padX;
  const xHi = xMax + padX;
  const yLo = yMin - padY;
  const yHi = yMax + padY;

  const xAt = (v) => margin.left + ((toNum(v) - xLo) / (xHi - xLo || 1)) * innerW;
  const yAt = (v) => margin.top + innerH - ((toNum(v) - yLo) / (yHi - yLo || 1)) * innerH;

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
                {t.toFixed(1)}
              </text>
            </g>
          );
        })}
        {xTicks.map((t, i) => (
          <text key={`xt-${i}`} x={xAt(t)} y={height - 14} textAnchor="middle" fontSize="9" fill="#94a3b8">
            {t.toFixed(1)}
          </text>
        ))}
        <text x={margin.left + innerW / 2} y={height - 2} textAnchor="middle" fontSize="10" fill="#64748b">
          {xKey}
        </text>
        <text x={12} y={margin.top + innerH / 2} fontSize="10" fill="#64748b" transform={`rotate(-90 12 ${margin.top + innerH / 2})`} textAnchor="middle">
          {yKey}
        </text>

        {rows.map((row, i) => (
          <circle
            key={i}
            cx={xAt(row[xKey])}
            cy={yAt(row[yKey])}
            r={hover === i ? 7 : 4.5}
            fill={colorOf(row, i)}
            opacity={hover === null || hover === i ? 0.85 : 0.45}
            stroke={hover === i ? "#0f172a" : "none"}
            strokeWidth="1"
            tabIndex={0}
            role="img"
            aria-label={`${row[labelKey] ?? `Point ${i + 1}`}: ${xKey} ${row[xKey]}, ${yKey} ${row[yKey]}`}
            onMouseEnter={() => setHover(i)}
            onFocus={() => setHover(i)}
          />
        ))}
      </svg>

      {hover !== null && rows[hover] && (
        <ChartTooltip x={xAt(rows[hover][xKey])} y={yAt(rows[hover][yKey])} width={VB_W}>
          <div className="font-semibold">{labelKey ? rows[hover][labelKey] : `Point ${hover + 1}`}</div>
          <div className="text-slate-600">
            {xKey}: {rows[hover][xKey]} · {yKey}: {rows[hover][yKey]}
          </div>
          {groupKey && <div className="text-slate-600">Segment: {rows[hover][groupKey]}</div>}
          {metaKeys.map((k) => (
            <div key={k} className="text-slate-500">
              {k}: {rows[hover][k]}
            </div>
          ))}
        </ChartTooltip>
      )}

      {groupKey && (
        <ul className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-1" aria-label="Segment legend">
          {groups.map((g, i) => (
            <li key={g} className="flex items-center gap-1.5 text-[11px] text-slate-600">
              <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: PALETTE[i % PALETTE.length] }} />
              {g}
              <span className="text-slate-400">({rows.filter((r) => String(r[groupKey] ?? "group") === g).length})</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
