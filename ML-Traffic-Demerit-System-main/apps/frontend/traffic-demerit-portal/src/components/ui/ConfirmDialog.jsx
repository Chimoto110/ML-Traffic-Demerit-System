import React from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";

/**
 * Simple accessible confirmation modal.
 *
 * Props:
 *  - open: boolean
 *  - title: string
 *  - message: ReactNode
 *  - confirmLabel / cancelLabel
 *  - danger: boolean (red confirm button)
 *  - busy: boolean (disables buttons while an action runs)
 *  - error: string shown inside the dialog when the action failed
 *  - reason: { visible, value, onChange, placeholder, label } optional
 *            reason input (used e.g. when voiding a payment)
 *  - onConfirm: async () => {}
 *  - onCancel: () => {}
 */
export default function ConfirmDialog({
  open,
  title = "Are you sure?",
  message = null,
  confirmLabel = "Confirm",
  cancelLabel = "Cancel",
  danger = true,
  busy = false,
  error = "",
  reason = null,
  onConfirm,
  onCancel,
}) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-label={typeof title === "string" ? title : "Confirm action"}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onCancel?.();
      }}
    >
      <div className="enter-up w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div className="text-base font-semibold text-slate-900">{title}</div>
        {message && <div className="mt-2 text-sm text-slate-600">{message}</div>}

        {reason?.visible && (
          <div className="mt-4">
            <label className="text-sm font-medium text-slate-700">{reason.label || "Reason"}</label>
            <Textarea
              className="mt-1"
              rows={3}
              value={reason.value || ""}
              onChange={(e) => reason.onChange?.(e.target.value)}
              placeholder={reason.placeholder || "Enter a reason..."}
            />
          </div>
        )}

        {error && (
          <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>
        )}

        <div className="mt-5 flex justify-end gap-2">
          <Button variant="outline" size="sm" onClick={onCancel} disabled={busy}>
            {cancelLabel}
          </Button>
          <Button
            size="sm"
            disabled={busy || Boolean(reason?.visible && reason?.required && !String(reason.value || "").trim())}
            className={danger ? "bg-red-600 hover:bg-red-500 text-white" : undefined}
            onClick={onConfirm}
          >
            {busy ? "Working..." : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}
