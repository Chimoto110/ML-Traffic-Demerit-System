import React, { useCallback, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import ConfirmDialog from "@/components/ui/ConfirmDialog";
import { toast } from "@/components/ui/toasts";

/**
 * Reusable server-driven CRUD table.
 *
 * Wires any appClient.crud.* resource ({list, get, create, update, remove})
 * to a paginated table with:
 *  - server-side pagination (per-page selector + page buttons)
 *  - debounced search (`q`)
 *  - sortable column headers (`sort=field` / `sort=-field`)
 *  - filter dropdowns (`filter[col]=value`)
 *  - create/edit modal with client-side validation + real API error display
 *  - delete with a confirmation dialog
 *  - success/failure toasts, and a refresh of the current page after writes
 *
 * Props:
 *  - resource:          appClient.crud.* api object
 *  - resourceLabel:     singular label, e.g. "driver" (used in dialogs)
 *  - columns:           [{ key, label, sortable?, align?, render?(row) }]
 *  - fields:            [{ name, label, type?: 'text'|'number'|'select'|'date'|
 *                          'datetime-local'|'textarea'|'password', options?,
 *                          required?, placeholder?, help?, coerce?, min?, max? }]
 *  - filters:           [{ name, label, options: [{ value, label }] }]
 *  - searchable (bool)  search box on/off (default true)
 *  - searchPlaceholder: string
 *  - initialSort        e.g. "-id"
 *  - defaultValues      initial values for the create form
 *  - canCreate/ canEdit(row)/ canDelete(row)
 *  - createTitle/ editTitle
 *  - getRowLabel(row)   human label used in the delete confirmation
 *  - rowActions(row)    [{ label, onClick, variant?, busy? }] extra buttons
 *  - onSaved(mode, row, response) / onDeleted(row)   callbacks
 *  - refreshSignal      bump (any value) to trigger a reload from the parent
 *  - emptyText, errorText
 */
export default function DataTable({
  resource,
  resourceLabel = "record",
  columns = [],
  fields = [],
  filters = [],
  searchable = true,
  searchPlaceholder = "Search…",
  perPageOptions = [10, 25, 50, 100],
  initialSort = "-id",
  initialPerPage = 10,
  defaultValues = {},
  canCreate = true,
  canEdit = () => true,
  canDelete = () => true,
  createTitle = null,
  editTitle = null,
  getRowLabel = (row) => `${resourceLabel} #${row?.id}`,
  rowActions = null,
  onSaved = null,
  onDeleted = null,
  refreshSignal = 0,
  emptyText = null,
  transformRows = null,
  preparePayload = null,
  className = "",
}) {
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState("");

  const [searchInput, setSearchInput] = useState("");
  const [q, setQ] = useState("");
  const [sort, setSort] = useState(initialSort);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(initialPerPage);
  const [filterVals, setFilterVals] = useState({});

  const [modal, setModal] = useState(null); // { mode: 'create'|'edit', id?, values, fieldErrors, formError, busy }
  const [confirmState, setConfirmState] = useState(null); // { row, busy, error }
  const [voidState, setVoidState] = useState(null); // { row, reason, busy, error }

  /* ---------------- data fetching ---------------- */

  const load = useCallback(async () => {
    if (!resource?.list) return;
    setLoading(true);
    setLoadError("");
    try {
      const activeFilters = Object.fromEntries(Object.entries(filterVals).filter(([, v]) => v !== "all" && v !== "" && v != null));
      const payload = await resource.list({
        page,
        per_page: perPage,
        sort,
        ...(q ? { q } : {}),
        ...(Object.keys(activeFilters).length ? { filter: activeFilters } : {}),
      });
      const data = Array.isArray(payload?.data) ? payload.data : [];
      setRows(transformRows ? transformRows(data) : data);
      setMeta(payload?.meta || null);
    } catch (err) {
      setLoadError(err.message || `Failed to load ${resourceLabel}s.`);
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [resource, page, perPage, sort, q, filterVals, transformRows]);

  useEffect(() => {
    load();
  }, [load, refreshSignal]);

  // Debounce the search box (~400ms) and reset to page 1.
  useEffect(() => {
    const term = searchInput.trim();
    const timer = window.setTimeout(() => {
      setQ(term);
      setPage(1);
    }, 400);
    return () => window.clearTimeout(timer);
  }, [searchInput]);

  /* ---------------- sorting ---------------- */

  const toggleSort = (key) => {
    setSort((prev) => {
      if (prev === key) return `-${key}`;
      if (prev === `-${key}`) return key;
      return key;
    });
    setPage(1);
  };

  const sortArrow = (key) => {
    if (sort === key) return <span aria-hidden="true">▲</span>;
    if (sort === `-${key}`) return <span aria-hidden="true">▼</span>;
    return <span aria-hidden="true" className="opacity-30">↕</span>;
  };

  /* ---------------- create / edit ---------------- */

  const openCreate = () => {
    const values = {};
    fields.forEach((f) => {
      values[f.name] = defaultValues[f.name] ?? "";
    });
    setModal({ mode: "create", values, fieldErrors: {}, formError: "", busy: false });
  };

  const openEdit = (row) => {
    const values = {};
    fields.forEach((f) => {
      const raw = row[f.name];
      values[f.name] = raw == null ? "" : String(raw);
    });
    setModal({ mode: "edit", id: row.id, values, fieldErrors: {}, formError: "", busy: false });
  };

  const validate = (values) => {
    const errors = {};
    fields.forEach((f) => {
      const raw = values[f.name];
      const empty = raw === "" || raw == null;
      if (f.required && empty && f.type !== "password") {
        errors[f.name] = `${f.label} is required.`;
        return;
      }
      if (empty) return;
      if (f.type === "number") {
        const n = Number(raw);
        if (!Number.isFinite(n)) errors[f.name] = `${f.label} must be a number.`;
        else if (f.min != null && n < f.min) errors[f.name] = `${f.label} must be at least ${f.min}.`;
        else if (f.max != null && n > f.max) errors[f.name] = `${f.label} must be at most ${f.max}.`;
      }
      if (f.validate) {
        const msg = f.validate(raw, values);
        if (msg) errors[f.name] = msg;
      }
    });
    return errors;
  };

  const submitModal = async () => {
    if (!modal) return;
    const fieldErrors = validate(modal.values);
    if (Object.keys(fieldErrors).length > 0) {
      setModal((m) => ({ ...m, fieldErrors }));
      return;
    }

    const payload = {};
    fields.forEach((f) => {
      const raw = modal.values[f.name];
      if (f.type === "password" && (raw === "" || raw == null)) return; // never send blank passwords
      payload[f.name] = f.coerce ? f.coerce(raw) : raw;
    });
    const finalPayload = preparePayload ? preparePayload(payload, modal.mode) : payload;

    setModal((m) => ({ ...m, busy: true, formError: "", fieldErrors: {} }));
    try {
      let response;
      if (modal.mode === "create") {
        response = await resource.create(finalPayload);
      } else {
        response = await resource.update(modal.id, finalPayload);
      }
      const row = response?.data ?? response;
      toast.success(
        modal.mode === "create" ? `${capitalize(resourceLabel)} created` : `${capitalize(resourceLabel)} updated`,
        `${getRowLabel(row)} was saved successfully.`
      );
      setModal(null);
      onSaved?.(modal.mode, row, response);
      load();
    } catch (err) {
      const mapped = {};
      if (err?.errors && typeof err.errors === "object") {
        Object.entries(err.errors).forEach(([key, messages]) => {
          mapped[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
      }
      setModal((m) => ({
        ...m,
        busy: false,
        fieldErrors: mapped,
        formError: Object.keys(mapped).length === 0 ? err.message : "",
      }));
      toast.error(`Could not save ${resourceLabel}`, err.message);
    }
  };

  /* ---------------- delete / custom confirm ---------------- */

  const requestDelete = (row) => setConfirmState({ row, busy: false, error: "" });

  /** Helpers passed to rowActions(row, helpers) so custom flows can reuse
   *  the built-in confirmation dialog and refresh the table afterwards. */
  const actionHelpers = useMemo(
    () => ({
      confirm: (opts) =>
        setVoidState({
          title: opts.title || "Confirm action",
          message: opts.message,
          confirmLabel: opts.confirmLabel || "Confirm",
          danger: opts.danger !== false,
          reasonVisible: Boolean(opts.reasonVisible),
          reason: "",
          row: opts.row,
          onConfirm: async (row, reason) => {
            await opts.onConfirm(row, reason);
            if (opts.successTitle !== null) {
              toast.success(opts.successTitle || "Done", opts.successMessage || "");
            }
            load();
          },
        }),
    }),
    [load]
  );

  const confirmDelete = async () => {
    if (!confirmState) return;
    const { row } = confirmState;
    setConfirmState((c) => ({ ...c, busy: true, error: "" }));
    try {
      await resource.remove(row.id);
      toast.success(`${capitalize(resourceLabel)} deleted`, `${getRowLabel(row)} was soft-deleted.`);
      setConfirmState(null);
      onDeleted?.(row);
      // If the page just became empty, step back one page.
      if (rows.length === 1 && page > 1) setPage(page - 1);
      else load();
    } catch (err) {
      setConfirmState((c) => ({ ...c, busy: false, error: err.message }));
      toast.error(`Could not delete ${resourceLabel}`, err.message);
    }
  };

  /* ---------------- pagination helpers ---------------- */

  const lastPage = meta?.last_page || 1;
  const total = meta?.total ?? rows.length;
  const pageNumbers = useMemo(() => {
    const nums = new Set([1, lastPage, page, page - 1, page + 1]);
    return [...nums].filter((n) => n >= 1 && n <= lastPage).sort((a, b) => a - b);
  }, [page, lastPage]);

  /* ---------------- render ---------------- */

  const hasActions = columns.some((c) => c.actions) || rowActions || canDelete;

  return (
    <div className={`rounded-xl border border-slate-200 bg-white ${className}`}>
      {/* toolbar */}
      <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3">
        {searchable && (
          <Input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder={searchPlaceholder}
            aria-label={searchPlaceholder}
            className="h-9 max-w-xs flex-1"
          />
        )}
        {filters.map((f) => (
          <Select
            key={f.name}
            value={String(filterVals[f.name] ?? "all")}
            onValueChange={(v) => {
              setFilterVals((prev) => ({ ...prev, [f.name]: v }));
              setPage(1);
            }}
          >
            <SelectTrigger className="h-9 w-44" aria-label={f.label}>
              <SelectValue placeholder={f.label} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All {f.label}</SelectItem>
              {f.options.map((o) => (
                <SelectItem key={String(o.value)} value={String(o.value)}>
                  {o.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ))}
        <div className="ml-auto flex items-center gap-2">
          <Button size="sm" variant="outline" onClick={load} disabled={loading} aria-label="Refresh table">
            Refresh
          </Button>
          {canCreate && fields.length > 0 && (
            <Button size="sm" onClick={openCreate}>
              New {resourceLabel}
            </Button>
          )}
        </div>
      </div>

      {/* inline load error */}
      {loadError && (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
          <span>{loadError}</span>
          <Button size="sm" variant="outline" onClick={load} className="border-red-300 bg-white text-red-700 hover:bg-red-100">
            Retry
          </Button>
        </div>
      )}

      {/* table */}
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
            <tr>
              {columns.map((col) => (
                <th key={col.key} className={`px-4 py-2.5 ${col.align === "right" ? "text-right" : "text-left"} ${col.width || ""}`}>
                  {col.sortable ? (
                    <button
                      type="button"
                      onClick={() => toggleSort(col.key)}
                      className="inline-flex items-center gap-1 font-semibold uppercase transition-colors hover:text-slate-800"
                      aria-label={`Sort by ${col.label}`}
                    >
                      {col.label}
                      {sortArrow(col.key)}
                    </button>
                  ) : (
                    col.label
                  )}
                </th>
              ))}
              {hasActions && <th className="px-4 py-2.5 text-right">Actions</th>}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {loading ? (
              <tr>
                <td colSpan={columns.length + (hasActions ? 1 : 0)} className="px-4 py-10 text-center text-slate-400">
                  <span className="inline-block h-5 w-5 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500 align-middle" /> Loading…
                </td>
              </tr>
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length + (hasActions ? 1 : 0)} className="px-4 py-8 text-center text-sm text-slate-500">
                  {loadError ? "—" : emptyText || `No ${resourceLabel}s found.`}
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={row.id} className="hover:bg-slate-50">
                  {columns.map((col) => (
                    <td key={col.key} className={`px-4 py-2.5 ${col.align === "right" ? "text-right" : "text-left"}`}>
                      {col.render ? col.render(row) : formatCell(row[col.key])}
                    </td>
                  ))}
                  {hasActions && (
                    <td className="px-4 py-2.5">
                      <div className="flex flex-wrap items-center justify-end gap-1.5">
                        {rowActions?.(row, actionHelpers)?.map((action, i) => (
                          <Button
                            key={action.label || i}
                            size="sm"
                            variant={action.variant || "outline"}
                            disabled={action.disabled}
                            onClick={() => action.onClick(row)}
                          >
                            {action.label}
                          </Button>
                        ))}
                        {canEdit(row) && fields.length > 0 && (
                          <Button size="sm" variant="outline" onClick={() => openEdit(row)}>
                            Edit
                          </Button>
                        )}
                        {canDelete(row) && resource?.remove && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50"
                            onClick={() => requestDelete(row)}
                          >
                            Delete
                          </Button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* footer */}
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3">
        <div className="flex items-center gap-2 text-xs text-slate-500">
          <span>
            {total.toLocaleString()} {resourceLabel}{total === 1 ? "" : "s"}
            {meta ? ` · showing ${meta.from ?? 0}–${meta.to ?? rows.length} of ${meta.total.toLocaleString()}` : ""}
          </span>
          <Select value={String(perPage)} onValueChange={(v) => { setPerPage(Number(v)); setPage(1); }}>
            <SelectTrigger className="h-8 w-20" aria-label="Rows per page">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {perPageOptions.map((n) => (
                <SelectItem key={n} value={String(n)}>
                  {n} / page
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-1">
          <Button size="sm" variant="outline" disabled={loading || page <= 1} onClick={() => setPage(page - 1)}>
            Prev
          </Button>
          {pageNumbers.map((n, i) => (
            <React.Fragment key={n}>
              {i > 0 && pageNumbers[i - 1] !== n - 1 && <span className="px-1 text-xs text-slate-400">…</span>}
              <Button
                size="sm"
                variant={n === page ? "default" : "outline"}
                onClick={() => setPage(n)}
                aria-current={n === page ? "page" : undefined}
              >
                {n}
              </Button>
            </React.Fragment>
          ))}
          <Button size="sm" variant="outline" disabled={loading || page >= lastPage} onClick={() => setPage(page + 1)}>
            Next
          </Button>
        </div>
      </div>

      {/* create / edit modal */}
      {modal && (
        <div
          className="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto bg-slate-950/50 p-4 py-10"
          role="dialog"
          aria-modal="true"
          aria-label={modal.mode === "create" ? `New ${resourceLabel}` : `Edit ${resourceLabel}`}
          onClick={(e) => {
            if (e.target === e.currentTarget && !modal.busy) setModal(null);
          }}
        >
          <div className="enter-up w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
            <div className="flex items-center justify-between">
              <div className="text-base font-semibold text-slate-900">
                {modal.mode === "create" ? createTitle || `New ${resourceLabel}` : editTitle || `Edit ${resourceLabel}`}
              </div>
              <button
                type="button"
                aria-label="Close dialog"
                onClick={() => setModal(null)}
                disabled={modal.busy}
                className="rounded p-1 text-slate-400 hover:text-slate-700"
              >
                <svg viewBox="0 0 12 12" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round">
                  <path d="M2 2l8 8M10 2l-8 8" />
                </svg>
              </button>
            </div>

            {modal.formError && (
              <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{modal.formError}</div>
            )}

            <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
              {fields.map((f) => (
                <div key={f.name} className={f.type === "textarea" ? "sm:col-span-2 space-y-1.5" : "space-y-1.5"}>
                  <Label htmlFor={`dt-${f.name}`}>
                    {f.label}
                    {f.required && <span className="ml-0.5 text-red-500">*</span>}
                  </Label>
                  {f.type === "select" ? (
                    <Select value={String(modal.values[f.name] ?? "")} onValueChange={(v) => setModal((m) => ({ ...m, values: { ...m.values, [f.name]: v } }))}>
                      <SelectTrigger id={`dt-${f.name}`} className="h-10">
                        <SelectValue placeholder={f.placeholder || `Select ${f.label.toLowerCase()}`} />
                      </SelectTrigger>
                      <SelectContent>
                        {(f.options || []).map((o) => (
                          <SelectItem key={String(o.value)} value={String(o.value)}>
                            {o.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  ) : f.type === "textarea" ? (
                    <textarea
                      id={`dt-${f.name}`}
                      rows={3}
                      className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                      value={modal.values[f.name] ?? ""}
                      placeholder={f.placeholder}
                      onChange={(e) => setModal((m) => ({ ...m, values: { ...m.values, [f.name]: e.target.value } }))}
                    />
                  ) : (
                    <Input
                      id={`dt-${f.name}`}
                      type={f.type || "text"}
                      min={f.min}
                      max={f.max}
                      step={f.type === "number" ? "any" : undefined}
                      className="h-10"
                      value={modal.values[f.name] ?? ""}
                      placeholder={f.placeholder}
                      onChange={(e) => setModal((m) => ({ ...m, values: { ...m.values, [f.name]: e.target.value } }))}
                    />
                  )}
                  {f.help && !modal.fieldErrors[f.name] && <p className="text-xs text-slate-400">{f.help}</p>}
                  {modal.fieldErrors[f.name] && <p className="text-xs text-red-600">{modal.fieldErrors[f.name]}</p>}
                </div>
              ))}
            </div>

            <div className="mt-5 flex justify-end gap-2">
              <Button variant="outline" size="sm" onClick={() => setModal(null)} disabled={modal.busy}>
                Cancel
              </Button>
              <Button size="sm" onClick={submitModal} disabled={modal.busy}>
                {modal.busy ? "Saving..." : modal.mode === "create" ? "Create" : "Save changes"}
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* delete confirmation */}
      <ConfirmDialog
        open={Boolean(confirmState)}
        title={`Delete ${resourceLabel}`}
        message={
          <>
            This will soft-delete <span className="font-semibold">{confirmState ? getRowLabel(confirmState.row) : ""}</span>. Continue?
          </>
        }
        confirmLabel="Delete"
        danger
        busy={confirmState?.busy}
        error={confirmState?.error}
        onConfirm={confirmDelete}
        onCancel={() => setConfirmState(null)}
      />

      {/* custom confirmation (e.g. void payment with a reason) */}
      <ConfirmDialog
        open={Boolean(voidState)}
        title={voidState?.title || "Confirm action"}
        message={voidState?.message}
        confirmLabel={voidState?.confirmLabel || "Confirm"}
        danger={voidState?.danger !== false}
        busy={voidState?.busy}
        error={voidState?.error}
        reason={
          voidState?.reasonVisible
            ? {
                visible: true,
                required: true,
                label: "Reason",
                placeholder: "Why is this action being taken?",
                value: voidState.reason || "",
                onChange: (v) => setVoidState((s) => ({ ...s, reason: v })),
              }
            : null
        }
        onConfirm={async () => {
          if (!voidState?.onConfirm) return;
          setVoidState((s) => ({ ...s, busy: true, error: "" }));
          try {
            await voidState.onConfirm(voidState.row, voidState.reason);
            setVoidState(null);
          } catch (err) {
            setVoidState((s) => ({ ...s, busy: false, error: err.message }));
          }
        }}
        onCancel={() => setVoidState(null)}
      />
    </div>
  );
}

/* ---------------- helpers ---------------- */

function capitalize(s) {
  return String(s || "").charAt(0).toUpperCase() + String(s || "").slice(1);
}

function formatCell(value) {
  if (value == null || value === "") return "—";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (typeof value === "number") return value.toLocaleString();
  return String(value);
}
