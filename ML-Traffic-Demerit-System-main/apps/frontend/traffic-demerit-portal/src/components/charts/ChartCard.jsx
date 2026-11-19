import React from "react";

/**
 * Shared chart shell: white card with title/subtitle plus loading, empty
 * and error states. Every chart in the kit can be wrapped with this.
 *
 * Props:
 *  - title, subtitle
 *  - loading: boolean          -> shows a spinner
 *  - error: string|null        -> shows an inline error banner
 *  - onRetry: () => void       -> retry button in the error banner
 *  - isEmpty: boolean          -> shows the empty state
 *  - emptyText: string         -> defaults to "No data yet"
 *  - actions: ReactNode        -> top-right controls
 *  - children
 */
export default function ChartCard({
  title,
  subtitle,
  loading = false,
  error = null,
  onRetry = null,
  isEmpty = false,
  emptyText = "No data yet",
  actions = null,
  children,
  className = "",
}) {
  return (
    <div className={`rounded-xl border border-slate-200 bg-white p-4 ${className}`}>
      {(title || actions) && (
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div className="min-w-0">
            {title && <div className="text-sm font-semibold text-slate-900">{title}</div>}
            {subtitle && <div className="mt-1 text-xs text-slate-500">{subtitle}</div>}
          </div>
          {actions}
        </div>
      )}

      {loading && (
        <div className="mt-4 flex items-center justify-center gap-3 py-10 text-sm text-slate-500">
          <span className="h-5 w-5 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500" aria-hidden="true" />
          Loading…
        </div>
      )}

      {!loading && error && (
        <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-sm text-red-700">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span>{error}</span>
            {onRetry && (
              <button
                type="button"
                onClick={onRetry}
                className="rounded-md border border-red-300 bg-white px-3 py-1 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100"
              >
                Retry
              </button>
            )}
          </div>
        </div>
      )}

      {!loading && !error && isEmpty && (
        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
          {emptyText}
        </div>
      )}

      {!loading && !error && !isEmpty && children}
    </div>
  );
}

/** Hover tooltip shell used by every chart (positioned over a relative wrapper). */
export function ChartTooltip({ x, y, width, children }) {
  const flip = x > (width || 600) * 0.62;
  return (
    <div
      className="pointer-events-none absolute z-20 max-w-[240px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs shadow-xl"
      style={{
        left: flip ? undefined : x,
        right: flip ? (width || 600) - x : undefined,
        top: y,
        transform: "translate(-50%, calc(-100% - 12px))",
      }}
    >
      {children}
    </div>
  );
}
