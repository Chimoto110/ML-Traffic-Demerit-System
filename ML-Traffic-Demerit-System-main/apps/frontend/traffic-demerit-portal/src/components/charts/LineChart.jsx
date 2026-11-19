import React, { useState } from "react";
import { ChartTooltip } from "./ChartCard";
import { PALETTE, formatCompact, niceMax, toNum, truncate } from "./chartUtils";

const VB_W = 640;

/**
 * Pure SVG line chart with optional filled area, multi-series support and
 * an optional secondary (right) axis for series with different units.
 *
 * Props:
 *  - data: Array<object>
 *  - xKey: string                  category key (e.g. "month", "date", "k")
 *  - series: [{ key, label?, color?, area?, axis?: 'left'|'right' }]
 *  - height: number (default 220)
 *  - valueFormat: (value, key) => string
 *  - yLabel / ariaLabel
 */
export default function LineChart({
  data = [],
  xKey = "x",
  series = [],
  height = 220,
  valueFormat = (v) => formatCompact(v),
  ariaLabel = "Line chart",
}) {
  const [hover, setHover] = useState(null);
  const rows = Array.isArray(data) ? data : [];
  const seriesList = (series.length > 0 ? series : [{ key: "y" }]).map((s, i) => ({
    ...s,
    color: s.color || PALETTE[i % PALETTE.length],
  }));

  if (rows.length === 0) {
    return <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No data yet</div>;
  }

  const margin = { top: 14, right: seriesList.some((s) => s.axis === "right") ? 48 : 12, bottom: 30, left: 46 };
  const innerW = VB_W - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;

  const leftSeries = seriesList.filter((s) => s.axis !== "right");
  const rightSeries = seriesList.filter((s) => s.axis === "right");

  const maxFor = (list) =>
    niceMax(Math.max(...list.flatMap((s) => rows.map((r) => toNum(r[s.key]))), 0));

  const leftMax = maxFor(leftSeries.length ? leftSeries : seriesList);
  const rightMax = rightSeries.length ? maxFor(rightSeries) : 0;

  const xAt = (i) => (rows.length === 1 ? margin.left + innerW / 2 : margin.left + (i / (rows.length - 1)) * innerW);
  const yAt = (value, max) => margin.top + innerH - (toNum(value) / (max || 1)) * innerH;

  const ticks = [0, 0.25, 0.5, 0.75, 1].map((f) => leftMax * f);

  const labelEvery = Math.max(1, Math.ceil(rows.length / Math.floor(innerW / 56)));

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
        {ticks.map((t, i) => {
          const y = yAt(t, leftMax);
          return (
            <g key={i}>
              <line x1={margin.left} x2={VB_W - margin.right} y1={y} y2={y} stroke="#e2e8f0" strokeWidth="1" />
              <text x={margin.left - 6} y={y + 3} textAnchor="end" fontSize="10" fill="#94a3b8">
                {formatCompact(t)}
              </text>
            </g>
          );
        })}

        {rightSeries.map((s) =>
          [0, 0.5, 1].map((f) => {
            const y = yAt(rightMax * f, rightMax);
            return (
              <text key={`${s.key}-${f}`} x={VB_W - margin.right + 6} y={y + 3} fontSize="10" fill={s.color}>
                {formatCompact(rightMax * f)}
              </text>
            );
          })
        )}

        {rows.map((row, i) =>
          i % labelEvery === 0 || i === rows.length - 1 ? (
            <text key={i} x={xAt(i)} y={height - 12} textAnchor="middle" fontSize="10" fill="#64748b">
              {truncate(String(row[xKey] ?? ""), 10)}
            </text>
          ) : null
        )}

        {seriesList.map((s, si) => {
          const max = s.axis === "right" ? rightMax : leftMax;
          const points = rows.map((r, i) => [xAt(i), yAt(r[s.key], max)]);
          const path = points.map(([px, py], i) => `${i === 0 ? "M" : "L"}${px.toFixed(1)},${py.toFixed(1)}`).join(" ");
          const areaPath = `${path} L${points[points.length - 1][0].toFixed(1)},${margin.top + innerH} L${points[0][0].toFixed(1)},${margin.top + innerH} Z`;

          return (
            <g key={s.key}>
              {s.area && <path d={areaPath} fill={s.color} opacity="0.12" />}
              <path d={path} fill="none" stroke={s.color} strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
              {points.length === 1 && <circle cx={points[0][0]} cy={points[0][1]} r="4" fill={s.color} />}
              {hover !== null && points[hover] && (
                <circle cx={points[hover][0]} cy={points[hover][1]} r="4" fill="#fff" stroke={s.color} strokeWidth="2.5" />
              )}
              <title>{`${s.label || s.key} — ${rows.map((r) => `${r[xKey]}: ${valueFormat(r[s.key], s.key)}`).join("; ")}`}</title>
              {/* invisible hit area for keyboard accessibility */}
              {si === 0 &&
                rows.map((_, i) => (
                  <rect
                    key={i}
                    x={xAt(i) - innerW / (rows.length * 2 || 1)}
                    y={margin.top}
                    width={Math.max(innerW / (rows.length || 1), 8)}
                    height={innerH}
                    fill="transparent"
                    tabIndex={0}
                    role="button"
                    aria-label={`${rows[i][xKey]}: ${seriesList.map((sp) => `${sp.label || sp.key} ${valueFormat(rows[i][sp.key], sp.key)}`).join(", ")}`}
                    onMouseEnter={() => setHover(i)}
                    onFocus={() => setHover(i)}
                  />
                ))}
            </g>
          );
        })}

        {hover !== null && (
          <line x1={xAt(hover)} x2={xAt(hover)} y1={margin.top} y2={margin.top + innerH} stroke="#cbd5e1" strokeWidth="1" strokeDasharray="3 3" />
        )}
      </svg>

      {hover !== null && rows[hover] && (
        <ChartTooltip x={xAt(hover)} y={margin.top + 8} width={VB_W}>
          <div className="font-semibold">{rows[hover][xKey]}</div>
          {seriesList.map((s) => (
            <div key={s.key} className="mt-0.5 flex items-center gap-1.5 text-slate-600">
              <span className="inline-block h-2 w-2 rounded-full" style={{ background: s.color }} />
              {s.label || s.key}: <span className="font-semibold text-slate-900">{valueFormat(rows[hover][s.key], s.key)}</span>
            </div>
          ))}
        </ChartTooltip>
      )}

      <div className="mt-2 flex flex-wrap items-center gap-3 px-1">
        {seriesList.map((s) => (
          <span key={s.key} className="flex items-center gap-1.5 text-[11px] text-slate-600">
            <span className="inline-block h-0.5 w-4 rounded-full" style={{ background: s.color }} />
            {s.label || s.key}
          </span>
        ))}
      </div>
    </div>
  );
}
