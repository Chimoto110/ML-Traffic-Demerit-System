import React, { useState } from "react";
import { ChartTooltip } from "./ChartCard";
import { colorFor, formatCompact, niceMax, toNum, truncate, yTicks } from "./chartUtils";

const VB_W = 640;

/**
 * Pure SVG bar chart. Grouped by default, optional stacked, plus a
 * horizontal mode (single series, good for "violations by type").
 *
 * Props:
 *  - data: Array<object>
 *  - xKey: string                 category key on each row
 *  - series: [{ key, label?, color? }]
 *  - height: number (default 200) plot height (vertical mode)
 *  - horizontal: boolean          horizontal bars (single series)
 *  - stacked: boolean             stack series instead of grouping
 *  - valueFormat: (value, key) => string
 *  - ariaLabel: string
 */
export default function BarChart({
  data = [],
  xKey = "label",
  series = [],
  height = 200,
  horizontal = false,
  stacked = false,
  valueFormat = (v) => formatCompact(v),
  ariaLabel = "Bar chart",
}) {
  const [hover, setHover] = useState(null);
  const rows = Array.isArray(data) ? data : [];
  const seriesList = series.length > 0 ? series : [{ key: "total" }];

  if (rows.length === 0) {
    return <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No data yet</div>;
  }

  /* ---------------- horizontal ---------------- */
  if (horizontal) {
    const labelW = 168;
    const valueW = 46;
    const rowH = 26;
    const barH = 16;
    const svgH = rows.length * rowH + 8;
    const max = niceMax(Math.max(...rows.map((r) => toNum(r[seriesList[0].key]))));
    const trackW = VB_W - labelW - valueW;

    return (
      <div className="relative" onMouseLeave={() => setHover(null)}>
        <svg
          viewBox={`0 0 ${VB_W} ${svgH}`}
          width="100%"
          role="img"
          aria-label={ariaLabel}
          style={{ display: "block" }}
          preserveAspectRatio="xMinYMin meet"
        >
          {rows.map((row, i) => {
            const value = toNum(row[seriesList[0].key]);
            const w = Math.max(2, (value / max) * trackW);
            const color = seriesList[0].color || row.color || colorFor(row[xKey], i);
            const y = 4 + i * rowH;
            return (
              <g
                key={i}
                onMouseEnter={() => setHover({ index: i, row })}
                onFocus={() => setHover({ index: i, row })}
                tabIndex={0}
                role="listitem"
                aria-label={`${row[xKey]}: ${valueFormat(value, seriesList[0].key)}`}
              >
                <rect x={0} y={y - 4} width={VB_W} height={rowH} fill={hover?.index === i ? "#f8fafc" : "transparent"} />
                <text x={labelW - 8} y={y + barH - 4} textAnchor="end" fontSize="11" fill="#475569">
                  {truncate(String(row[xKey] ?? ""), 24)}
                </text>
                <rect x={labelW} y={y} width={w} height={barH} rx={4} fill={color} opacity={hover && hover.index !== i ? 0.45 : 1} />
                <text x={labelW + w + 6} y={y + barH - 4} fontSize="11" fontWeight="600" fill="#0f172a">
                  {valueFormat(value, seriesList[0].key)}
                </text>
              </g>
            );
          })}
        </svg>
        {hover && (
          <ChartTooltip x={VB_W * 0.5} y={12 + hover.index * rowH} width={VB_W}>
            <div className="font-semibold">{hover.row[xKey]}</div>
            {seriesList.map((s) => (
              <div key={s.key} className="mt-0.5 text-slate-600">
                {s.label || s.key}: <span className="font-semibold text-slate-900">{valueFormat(toNum(hover.row[s.key]), s.key)}</span>
              </div>
            ))}
          </ChartTooltip>
        )}
      </div>
    );
  }

  /* ---------------- vertical (grouped / stacked) ---------------- */
  const margin = { top: 12, right: 10, bottom: 30, left: 44 };
  const innerW = VB_W - margin.left - margin.right;
  const innerH = height - margin.top - margin.bottom;
  const bandW = innerW / rows.length;
  const groupW = Math.min(bandW * 0.68, 64);
  const barW = stacked ? groupW : Math.max(4, groupW / seriesList.length - (seriesList.length > 1 ? 2 : 0));

  const totals = rows.map((row) =>
    stacked
      ? seriesList.reduce((sum, s) => sum + toNum(row[s.key]), 0)
      : Math.max(...seriesList.map((s) => toNum(row[s.key])))
  );
  const max = niceMax(Math.max(...totals, 0));

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
        {yTicks(max).map((t, i) => {
          const y = margin.top + innerH - (t / max) * innerH;
          return (
            <g key={i}>
              <line x1={margin.left} x2={VB_W - margin.right} y1={y} y2={y} stroke="#e2e8f0" strokeWidth="1" />
              <text x={margin.left - 6} y={y + 3} textAnchor="end" fontSize="10" fill="#94a3b8">
                {formatCompact(t)}
              </text>
            </g>
          );
        })}

        {rows.map((row, i) => {
          const cx = margin.left + i * bandW + bandW / 2;
          const gx = cx - groupW / 2;
          let stackCursor = 0;

          return (
            <g
              key={i}
              onMouseEnter={() => setHover({ index: i, row })}
              tabIndex={0}
              role="listitem"
              aria-label={`${row[xKey]}: ${seriesList.map((s) => `${s.label || s.key} ${valueFormat(toNum(row[s.key]), s.key)}`).join(", ")}`}
            >
              <rect x={margin.left + i * bandW} y={margin.top} width={bandW} height={innerH} fill={hover?.index === i ? "#f8fafc" : "transparent"} />
              {seriesList.map((s, si) => {
                const value = toNum(row[s.key]);
                const color = s.color || colorFor(s.key, si);
                const h = (value / max) * innerH;
                let x;
                let y;
                let w = barW;
                if (stacked) {
                  x = gx;
                  y = margin.top + innerH - ((stackCursor + value) / max) * innerH;
                  stackCursor += value;
                } else {
                  x = gx + si * (groupW / seriesList.length);
                  y = margin.top + innerH - h;
                }
                return (
                  <rect
                    key={s.key}
                    x={x}
                    y={y}
                    width={w}
                    height={Math.max(h, value > 0 ? 2 : 0)}
                    rx={3}
                    fill={color}
                    opacity={hover && hover.index !== i ? 0.5 : 1}
                  />
                );
              })}
              <text x={cx} y={height - 16} textAnchor="middle" fontSize="10" fill="#64748b">
                {truncate(String(row[xKey] ?? ""), Math.max(4, Math.floor(bandW / 6)))}
              </text>
            </g>
          );
        })}
      </svg>

      {hover && (
        <ChartTooltip
          x={margin.left + hover.index * bandW + bandW / 2}
          y={margin.top + 24}
          width={VB_W}
        >
          <div className="font-semibold">{hover.row[xKey]}</div>
          {seriesList.map((s, si) => (
            <div key={s.key} className="mt-0.5 flex items-center gap-1.5 text-slate-600">
              <span className="inline-block h-2 w-2 rounded-full" style={{ background: s.color || colorFor(s.key, si) }} />
              {s.label || s.key}: <span className="font-semibold text-slate-900">{valueFormat(toNum(hover.row[s.key]), s.key)}</span>
            </div>
          ))}
        </ChartTooltip>
      )}

      {seriesList.length > 1 && (
        <div className="mt-2 flex flex-wrap items-center gap-3 px-1">
          {seriesList.map((s, si) => (
            <span key={s.key} className="flex items-center gap-1.5 text-[11px] text-slate-600">
              <span className="inline-block h-2 w-2 rounded-full" style={{ background: s.color || colorFor(s.key, si) }} />
              {s.label || s.key}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}
