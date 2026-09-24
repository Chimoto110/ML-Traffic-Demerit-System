import React from "react";
import { offenceLabel } from "@/lib/constants";

export default function LedgerTable({ entries }) {
  if (!entries?.length) return <div className="text-sm text-slate-500 py-8 text-center">No ledger entries yet.</div>;
  return (
    <div className="overflow-x-auto rounded-lg border border-slate-200">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
          <tr>
            <th className="text-left px-4 py-2.5 font-semibold">Date</th>
            <th className="text-left px-4 py-2.5 font-semibold">Offence</th>
            <th className="text-right px-4 py-2.5 font-semibold">Points</th>
            <th className="text-right px-4 py-2.5 font-semibold">Days Since Last</th>
            <th className="text-right px-4 py-2.5 font-semibold">Balance</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {entries.map((e) => (
            <tr key={e.id} className="hover:bg-slate-50">
              <td className="px-4 py-2.5 text-slate-600">{new Date(e.timestamp).toLocaleDateString()}</td>
              <td className="px-4 py-2.5">{offenceLabel(e.offence_type)}</td>
              <td className="px-4 py-2.5 text-right font-semibold text-red-600">+{e.points_applied}</td>
              <td className="px-4 py-2.5 text-right text-slate-500">{e.days_since_last_offence ?? "—"}</td>
              <td className="px-4 py-2.5 text-right font-semibold">{e.cumulative_balance}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}