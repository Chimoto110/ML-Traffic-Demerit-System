import React, { useEffect, useState } from "react";

/**
 * Tiny dependency-free toast system (module level, no provider needed).
 *
 * Usage:
 *   import { toast, ToastStack } from "@/components/ui/toasts";
 *   toast.success("Saved", "Violation recorded.");
 *   toast.error("Failed", err.message);
 *   ...
 *   return (<AppLayout>...<ToastStack /></AppLayout>);
 *
 * The <ToastStack /> host must be rendered once somewhere in the page tree.
 * Toasts auto-dismiss after 4 seconds.
 */

const AUTO_DISMISS_MS = 4000;
const MAX_TOASTS = 5;

let listeners = [];
let memoryState = { toasts: [] };
let seq = 0;

const emit = () => {
  memoryState = { ...memoryState };
  listeners.forEach((fn) => fn(memoryState));
};

const remove = (id) => {
  memoryState = { toasts: memoryState.toasts.filter((t) => t.id !== id) };
  emit();
};

export function toast({ title, description, variant = "default", duration = AUTO_DISMISS_MS }) {
  seq += 1;
  const id = `toast-${seq}`;
  const entry = { id, title, description, variant };

  memoryState = { toasts: [...memoryState.toasts, entry].slice(-MAX_TOASTS) };
  emit();

  window.setTimeout(() => remove(id), duration);
  return { id, dismiss: () => remove(id) };
}

toast.success = (title, description) => toast({ title, description, variant: "success" });
toast.error = (title, description) => toast({ title, description, variant: "error" });
toast.info = (title, description) => toast({ title, description, variant: "info" });

const VARIANT_STYLES = {
  default: "border-slate-200 bg-white text-slate-900",
  success: "border-emerald-300 bg-emerald-50 text-emerald-900",
  error: "border-red-300 bg-red-50 text-red-900",
  info: "border-cyan-300 bg-cyan-50 text-cyan-900",
};

const VARIANT_DOT = {
  default: "bg-slate-400",
  success: "bg-emerald-500",
  error: "bg-red-500",
  info: "bg-cyan-500",
};

export function ToastStack() {
  const [state, setState] = useState(memoryState);

  useEffect(() => {
    listeners.push(setState);
    return () => {
      listeners = listeners.filter((fn) => fn !== setState);
    };
  }, []);

  if (state.toasts.length === 0) return null;

  return (
    <div
      aria-live="polite"
      aria-atomic="true"
      className="fixed bottom-4 right-4 z-[100] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2"
    >
      {state.toasts.map((t) => (
        <div
          key={t.id}
          role="status"
          className={`enter-up flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg ${VARIANT_STYLES[t.variant] || VARIANT_STYLES.default}`}
        >
          <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${VARIANT_DOT[t.variant] || VARIANT_DOT.default}`} />
          <div className="min-w-0 flex-1">
            <div className="text-sm font-semibold">{t.title}</div>
            {t.description && <div className="mt-0.5 text-xs opacity-80">{t.description}</div>}
          </div>
          <button
            type="button"
            aria-label="Dismiss notification"
            onClick={() => remove(t.id)}
            className="shrink-0 rounded p-1 text-current opacity-50 transition-opacity hover:opacity-100"
          >
            <svg viewBox="0 0 12 12" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
              <path d="M2 2l8 8M10 2l-8 8" />
            </svg>
          </button>
        </div>
      ))}
    </div>
  );
}
