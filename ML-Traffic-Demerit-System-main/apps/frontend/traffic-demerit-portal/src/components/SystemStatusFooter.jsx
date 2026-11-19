import React, { useCallback, useEffect, useState } from "react";
import { appClient } from "@/api/appClient";

const shortText = (value, max = 48) => {
  const text = String(value ?? "").trim();
  if (!text) return "unknown";
  return text.length > max ? `${text.slice(0, max - 1)}…` : text;
};

const formatCount = (value) => {
  const n = Number(value);
  return Number.isFinite(n) ? n.toLocaleString() : "–";
};

/**
 * Compact admin-only footer widget: DB name, key row counts, ML up/down.
 * Data comes from GET /api/system/status - no mock values.
 */
export default function SystemStatusFooter() {
  const [status, setStatus] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await appClient.system.status();
      setStatus(payload);
    } catch (err) {
      setError(err?.message || "Status unavailable");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return (
      <div className="mx-1 rounded-lg border border-cyan-100/15 bg-slate-950/45 px-3 py-2" aria-hidden="true">
        <div className="h-2.5 w-2/3 animate-pulse rounded bg-slate-700/70" />
        <div className="mt-2 h-2.5 w-1/2 animate-pulse rounded bg-slate-700/70" />
        <div className="mt-2 h-2.5 w-1/3 animate-pulse rounded bg-slate-700/70" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-1 rounded-lg border border-amber-300/25 bg-amber-500/10 px-3 py-2 text-[11px] text-amber-100">
        <div className="flex items-start justify-between gap-2">
          <span className="leading-snug">Status unavailable: {shortText(error, 60)}</span>
          <button
            type="button"
            onClick={load}
            className="shrink-0 rounded border border-amber-200/40 px-1.5 py-0.5 text-[10px] font-semibold text-amber-100 hover:bg-amber-500/20"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  const counts = status?.database?.counts || {};
  const mlUp = status?.ml?.status === "up";

  return (
    <div className="mx-1 rounded-lg border border-cyan-100/15 bg-slate-950/45 px-3 py-2 text-[11px] leading-relaxed text-slate-300 space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <span className="uppercase tracking-wide text-[10px] font-semibold text-slate-400">System status</span>
        <span className="truncate text-slate-100 font-semibold" title={status?.database?.name || ""}>
          {status?.database?.name || "–"}
        </span>
      </div>

      <div className="flex flex-wrap gap-x-2 gap-y-1">
        <span>
          Violations <span className="font-semibold text-slate-100">{formatCount(counts.violations)}</span>
        </span>
        <span className="text-slate-600">·</span>
        <span>
          Drivers <span className="font-semibold text-slate-100">{formatCount(counts.drivers)}</span>
        </span>
        <span className="text-slate-600">·</span>
        <span>
          Payments <span className="font-semibold text-slate-100">{formatCount(counts.payments)}</span>
        </span>
      </div>

      {mlUp ? (
        <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300/25 bg-emerald-500/15 px-2 py-0.5 text-[10px] font-semibold text-emerald-200">
          <span className="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400" />
          ML up
        </span>
      ) : (
        <span
          className="inline-flex items-center gap-1 rounded-full border border-amber-300/25 bg-amber-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-100"
          title={status?.ml?.error || status?.ml?.status || "ML status unknown"}
        >
          <span className="inline-block h-1.5 w-1.5 rounded-full bg-amber-400" />
          ML down: {shortText(status?.ml?.error || status?.ml?.status, 34)}
        </span>
      )}
    </div>
  );
}
