import React, { useState } from "react";
import { colorFor, toNum } from "./chartUtils";

/**
 * Pure SVG donut chart with a legend and hover tooltip.
 *
 * Props:
 *  - data: Array<object>
 *  - labelKey: string (default "label")
 *  - valueKey: string (default "value")
 *  - height: number (default 200)
 *  - centerLabel / centerValue: text rendered in the middle
 *  - ariaLabel
 */
export default function DonutChart({
  data = [],
  labelKey = "label",
  valueKey = "value",
  height = 200,
  centerLabel = null,
  centerValue = null,
  ariaLabel = "Donut chart",
}) {
  const [hover, setHover] = useState(null);
  const rows = (Array.isArray(data) ? data : []).filter((r) => toNum(r[valueKey]) > 0);

  if (rows.length === 0) {
    return <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No data yet</div>;
  }

  const total = rows.reduce((sum, r) => sum + toNum(r[valueKey]), 0);
  const size = 170;
  const cx = size / 2;
  const cy = size / 2;
  const radius = 62;
  const thickness = 20;

  const arcPath = (startAngle, endAngle) => {
    const large = endAngle - startAngle > Math.PI ? 1 : 0;
    const x1 = cx + radius * Math.cos(startAngle);
    const y1 = cy + radius * Math.sin(startAngle);
    const x2 = cx + radius * Math.cos(endAngle);
    const y2 = cy + radius * Math.sin(endAngle);
    return `M ${x1} ${y1} A ${radius} ${radius} 0 ${large} 1 ${x2} ${y2}`;
  };

  let cursor = -Math.PI / 2;
  const gap = rows.length > 1 ? 0.02 : 0;
  const arcs = rows.map((row, i) => {
    const fraction = toNum(row[valueKey]) / total;
    const start = cursor + gap / 2;
    const end = cursor + fraction * Math.PI * 2 - gap / 2;
    cursor += fraction * Math.PI * 2;
    return { row, i, start, end: Math.max(end, start + 0.001), fraction };
  });

  return (
    <div className="relative flex flex-wrap items-center gap-4" onMouseLeave={() => setHover(null)}>
      <svg viewBox={`0 0 ${size} ${size}`} width={height} height={height} role="img" aria-label={ariaLabel} style={{ flexShrink: 0 }}>
        {arcs.map((arc) => (
          <path
            key={arc.i}
            d={arcPath(arc.start, arc.end)}
            fill="none"
            stroke={colorFor(arc.row[labelKey], arc.i)}
            strokeWidth={hover === arc.i ? thickness + 5 : thickness}
            strokeLinecap="butt"
            opacity={hover === null || hover === arc.i ? 1 : 0.4}
            onMouseEnter={() => setHover(arc.i)}
            tabIndex={0}
            role="img"
            aria-label={`${arc.row[labelKey]}: ${toNum(arc.row[valueKey])} (${(arc.fraction * 100).toFixed(0)}%)`}
            onFocus={() => setHover(arc.i)}
          />
        ))}
        {centerValue !== null && (
          <text x={cx} y={cy - 2} textAnchor="middle" fontSize="20" fontWeight="700" fill="#0f172a">
            {centerValue}
          </text>
        )}
        {centerLabel !== null && (
          <text x={cx} y={cy + 16} textAnchor="middle" fontSize="10" fill="#64748b">
            {centerLabel}
          </text>
        )}
      </svg>

      <ul className="min-w-[140px] flex-1 space-y-1.5" aria-label="Chart legend">
        {arcs.map((arc) => (
          <li
            key={arc.i}
            className={`flex items-center justify-between gap-3 rounded-md px-2 py-1 text-xs ${hover === arc.i ? "bg-slate-100" : ""}`}
            onMouseEnter={() => setHover(arc.i)}
          >
            <span className="flex min-w-0 items-center gap-2 text-slate-600">
              <span className="inline-block h-2.5 w-2.5 shrink-0 rounded-sm" style={{ background: colorFor(arc.row[labelKey], arc.i) }} />
              <span className="truncate capitalize">{arc.row[labelKey]}</span>
            </span>
            <span className="shrink-0 font-semibold text-slate-900">
              {toNum(arc.row[valueKey]).toLocaleString()}
              <span className="ml-1 font-normal text-slate-400">{(arc.fraction * 100).toFixed(0)}%</span>
            </span>
          </li>
        ))}
      </ul>

      {hover !== null && arcs[hover] && (
        <div className="pointer-events-none absolute left-2 top-2 z-20 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-xl">
          <div className="font-semibold capitalize">{arcs[hover].row[labelKey]}</div>
          <div className="text-slate-600">
            {toNum(arcs[hover].row[valueKey]).toLocaleString()} ({(arcs[hover].fraction * 100).toFixed(1)}%)
          </div>
        </div>
      )}
    </div>
  );
}
