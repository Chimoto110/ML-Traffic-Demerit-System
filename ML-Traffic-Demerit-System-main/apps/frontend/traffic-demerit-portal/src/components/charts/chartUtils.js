/** Shared helpers for the pure-SVG chart kit. No dependencies. */

/** Palette aligned with the project's chart colors (cyan/orange/slate/emerald/amber/red/violet). */
export const PALETTE = ["#06b6d4", "#f97316", "#64748b", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#14b8a6"];

/** Risk/status aware color pickers. */
export const RISK_COLORS = { low: "#10b981", moderate: "#f59e0b", high: "#ef4444", unscored: "#94a3b8" };
export const STATUS_COLORS = {
  completed: "#10b981",
  paid: "#10b981",
  pending: "#f59e0b",
  failed: "#ef4444",
  voided: "#94a3b8",
  disputed: "#f59e0b",
  confirmed: "#10b981",
  active: "#ef4444",
  lifted: "#10b981",
  lifted_early: "#10b981",
  approved: "#10b981",
  rejected: "#ef4444",
  submitted: "#f59e0b",
};

export const colorFor = (key, index = 0) =>
  STATUS_COLORS[String(key || "").toLowerCase()] || RISK_COLORS[String(key || "").toLowerCase()] || PALETTE[index % PALETTE.length];

export const toNum = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
};

export const niceMax = (value) => {
  const max = toNum(value);
  if (max <= 0) return 1;
  const magnitude = 10 ** Math.floor(Math.log10(max));
  const scaled = max / magnitude;
  const nice = scaled <= 1 ? 1 : scaled <= 2 ? 2 : scaled <= 5 ? 5 : 10;
  return nice * magnitude;
};

export const formatCompact = (value) => {
  const n = toNum(value);
  if (Math.abs(n) >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
  if (Math.abs(n) >= 1_000) return `${(n / 1_000).toFixed(1)}k`;
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
};

export const formatKES = (value) => `KES ${toNum(value).toLocaleString()}`;

/** Simple axis tick generator: min 0, max "nice", n ticks. */
export const yTicks = (max, count = 4) => {
  const step = max / count;
  return Array.from({ length: count + 1 }, (_, i) => i * step);
};

/** Truncate long labels for axis ticks / legends. */
export const truncate = (text, max = 18) => {
  const s = String(text ?? "");
  return s.length > max ? `${s.slice(0, max - 1)}…` : s;
};
