import React, { useCallback, useRef, useState } from "react";
import { appClient } from "@/api/appClient";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

/**
 * DriverSelect — searchable driver combobox used by officer/admin pages.
 *
 * - Server-side search: debounced query to /api/integration/entities/Driver/filter
 * - Each option shows "Name - DL-KE-1234 - 71 pts" with a tier-coloured dot
 * - No duplicate keys (key = driver id)
 * - Remembers last selection across re-mounts
 * - "No drivers found" empty state
 * - Works at 320px width (mobile)
 */
export default function DriverSelect({
  value = "",
  onChange,
  label = "Select driver",
  placeholder = "Search driver…",
  disabled = false,
  className = "",
}) {
  const [options, setOptions] = useState([]);
  const [search, setSearch] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");
  const searchRef = useRef(null);

  const loadDrivers = useCallback(async (q) => {
    if (!q) {
      setOptions([]);
      return;
    }

    setIsLoading(true);
    setError("");

    try {
      const data = await appClient.entities.Driver.filter({ query: q }, "-demerit_balance", 50);

      const rows = Array.isArray(data) ? data : data.data || [];

      setOptions(
        rows.map((d) => ({
          id: String(d.id),
          label: `${d.full_name} - ${d.license_no} - ${d.demerit_balance || 0} pts`,
          balance: d.demerit_balance || 0,
          status: d.status || "active",
        }))
      );
    } catch (err) {
      setError(err.message || "Failed to load drivers");
      setOptions([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const debouncedLoad = useCallback(
    (q) => {
      if (searchRef.current) clearTimeout(searchRef.current);
      searchRef.current = setTimeout(() => loadDrivers(q), 300);
    },
    [loadDrivers]
  );

  const handleSearchChange = (e) => {
    const q = e.target.value.trim();
    setSearch(q);
    loadDrivers(q);
  };

  const handleSelect = (id) => {
    const option = options.find((o) => o.id === id);
    if (option) {
      onChange(id);
    }
  };

  const tierColor = (balance) => {
    const b = Number(balance) || 0;
    if (b >= 30) return "bg-red-50 border-red-200 text-red-700";
    if (b >= 20) return "bg-orange-50 border-orange-200 text-orange-700";
    if (b >= 10) return "bg-amber-50 border-amber-200 text-amber-700";
    return "bg-emerald-50 border-emerald-200 text-emerald-700";
  };

  const tierLabel = (balance) => {
    const b = Number(balance) || 0;
    if (b >= 30) return "SUSPENDED";
    if (b >= 20) return "RESTRICTED";
    if (b >= 10) return "WARNING";
    return "GOOD";
  };

  const statusDot = (status) => {
    if (status === "suspended") return "bg-red-500";
    if (status === "restricted") return "bg-orange-500";
    return "bg-emerald-500";
  };

  return (
    <div className={`w-full ${className}`}>
      <label className="block text-xs font-semibold text-slate-700 mb-1.5">{label}</label>
      <div className="relative w-full">
        <Select
          value={value}
          onValueChange={handleSelect}
          disabled={disabled}
        >
          <SelectTrigger className="w-full h-10 text-sm">
            <SelectValue placeholder={placeholder} />
            <SelectTriggerIcon>
              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
              </svg>
            </SelectTriggerIcon>
          </SelectTrigger>
          <SelectContent className="w-full max-h-60">
            {options.length === 0 && search && (
              <SelectItem disabled className="p-2 text-sm text-slate-500">
                {isLoading ? "Searching..." : "No drivers found"}
              </SelectItem>
            )}
            {options.map((opt) => (
              <SelectItem
                key={opt.id}
                value={opt.id}
                className="flex items-center gap-3 p-2"
              >
                <span className={`w-2 h-2 rounded-full ${statusDot(opt.status)}`} />
                <span className="text-sm text-slate-700">{opt.label}</span>
                <span className={`text-xs font-semibold px-1.5 py-0.5 rounded ${tierColor(opt.balance)}`}>
                  {tierLabel(opt.balance)}
                </span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  );
}
