import React, { useCallback, useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import { BubbleChart, ChartCard, LineChart, ScatterPlot } from "@/components/charts";
import { Button } from "@/components/ui/button";

const TRAIN_HINT = "Run the ML training step (php artisan ml:train-all) to populate this view.";

/**
 * Embeddable ML insights content (elbow/silhouette curves, PCA scatter,
 * segment profiles, association rules). Used by the standalone page and
 * embedded in the admin dashboard analytics tab.
 */
export function MlInsightsContent() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [rulesSort, setRulesSort] = useState({ key: "lift", dir: "desc" });

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const payload = await appClient.charts.mlInsights();
      setData(payload);
    } catch (err) {
      setError(err.message || "Failed to load ML insights.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const rules = useMemo(() => {
    const rows = Array.isArray(data?.association_rules) ? data.association_rules : [];
    const { key, dir } = rulesSort;
    return [...rows].sort((a, b) => {
      const left = Number(a[key]) || 0;
      const right = Number(b[key]) || 0;
      return dir === "asc" ? left - right : right - left;
    });
  }, [data, rulesSort]);

  const toggleRulesSort = (key) => {
    setRulesSort((prev) => (prev.key === key ? { key, dir: prev.dir === "desc" ? "asc" : "desc" } : { key, dir: "desc" }));
  };

  const sortMark = (key) => (rulesSort.key === key ? (rulesSort.dir === "desc" ? "▼" : "▲") : "↕");
  const bestSilhouette = useMemo(() => {
    const rows = Array.isArray(data?.silhouette) ? data.silhouette : [];
    if (rows.length === 0) return null;
    return rows.reduce((best, row) => ((Number(row.silhouette) || 0) > (Number(best.silhouette) || 0) ? row : best), rows[0]);
  }, [data]);

  const elbow = Array.isArray(data?.elbow) ? data.elbow : [];
  const silhouette = Array.isArray(data?.silhouette) ? data.silhouette : [];
  const pca = Array.isArray(data?.pca_scatter) ? data.pca_scatter : [];
  const profiles = Array.isArray(data?.segment_profiles) ? data.segment_profiles : [];
  const bubbles = Array.isArray(data?.rules_bubble) ? data.rules_bubble : [];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-bold text-slate-900">ML Insights</h2>
          <p className="text-xs text-slate-500">
            k-Means segmentation, PCA projection and FP-Growth association rules.
            {data?.generated_at && ` Generated ${new Date(data.generated_at).toLocaleString()}.`}
          </p>
        </div>
        <div className="flex gap-2">
          <Link to="/ml-insights">
            <Button size="sm" variant="outline">Open full page</Button>
          </Link>
          <Button size="sm" variant="outline" onClick={load} disabled={loading}>Refresh</Button>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <ChartCard
          title="Elbow Curve (WCSS by k)"
          subtitle="Within-cluster sum of squares as k grows — the 'elbow' suggests the optimal k."
          loading={loading}
          error={error}
          onRetry={load}
          isEmpty={!loading && !error && elbow.length === 0}
          emptyText={TRAIN_HINT}
        >
          <LineChart
            data={elbow}
            xKey="k"
            series={[{ key: "wcss", label: "WCSS", color: "#06b6d4", area: true }]}
            ariaLabel="Elbow curve: WCSS by k"
          />
        </ChartCard>

        <ChartCard
          title="Silhouette Score by k"
          subtitle={bestSilhouette ? `Best k = ${bestSilhouette.k} (silhouette ${bestSilhouette.silhouette}).` : "Higher is better."}
          loading={loading}
          error={error}
          onRetry={load}
          isEmpty={!loading && !error && silhouette.length === 0}
          emptyText={silhouette.length > 0 && silhouette.length < 2 ? `Only ${silhouette.length} silhouette point available. ${TRAIN_HINT}` : TRAIN_HINT}
        >
          {silhouette.length >= 2 ? (
            <LineChart
              data={silhouette}
              xKey="k"
              series={[{ key: "silhouette", label: "Silhouette", color: "#f97316", area: true }]}
              valueFormat={(v) => Number(v).toFixed(3)}
              ariaLabel="Silhouette score by k"
            />
          ) : (
            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-4 text-sm text-amber-800">
              Need at least 2 silhouette points to draw the curve. {TRAIN_HINT}
            </div>
          )}
        </ChartCard>
      </div>

      <ChartCard
        title="Driver Segments — PCA Projection"
        subtitle="Each dot is a driver, colored by cluster. Hover a dot for the driver name and coordinates."
        loading={loading}
        error={error}
        onRetry={load}
        isEmpty={!loading && !error && pca.length === 0}
        emptyText={`No PCA coordinates stored yet. ${TRAIN_HINT}`}
      >
        <ScatterPlot
          data={pca}
          xKey="pca_x"
          yKey="pca_y"
          groupKey="segment_label"
          labelKey="driver_name"
          metaKeys={["driver_id", "segment_id"]}
          height={320}
          ariaLabel="PCA scatter plot of drivers by segment"
        />
      </ChartCard>

      <ChartCard
        title="Segment Profiles"
        subtitle="Average behaviour of each driver cluster."
        loading={loading}
        error={error}
        onRetry={load}
        isEmpty={!loading && !error && profiles.length === 0}
        emptyText={`No segments computed yet. ${TRAIN_HINT}`}
      >
        <div className="overflow-x-auto">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
              <tr>
                <th className="px-3 py-2.5 text-left">Segment</th>
                <th className="px-3 py-2.5 text-right">Drivers</th>
                <th className="px-3 py-2.5 text-right">Avg Violations</th>
                <th className="px-3 py-2.5 text-right">Avg Demerit Pts</th>
                <th className="px-3 py-2.5 text-right">Avg Speed Over Limit</th>
                <th className="px-3 py-2.5 text-right">Avg Unpaid Ratio</th>
                <th className="px-3 py-2.5 text-right">Avg Fines (KES)</th>
                <th className="px-3 py-2.5 text-right">Avg Days Since Offence</th>
                <th className="px-3 py-2.5 text-right">Avg Night Share</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {profiles.map((p) => (
                <tr key={p.segment_id} className="hover:bg-slate-50">
                  <td className="px-3 py-2.5 font-medium text-slate-900">{p.segment_label || `Segment ${p.segment_id}`}</td>
                  <td className="px-3 py-2.5 text-right">{p.drivers}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_violation_count}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_demerit_points}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_speed_over_limit}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_unpaid_ratio}</td>
                  <td className="px-3 py-2.5 text-right">{Number(p.avg_fines_kes).toLocaleString()}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_days_since_offense}</td>
                  <td className="px-3 py-2.5 text-right">{p.avg_share_at_night}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </ChartCard>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-5">
        <ChartCard
          title="Association Rules"
          subtitle="Click a column header to sort. Bubble size = lift."
          loading={loading}
          error={error}
          onRetry={load}
          isEmpty={!loading && !error && rules.length === 0}
          emptyText={`No association rules mined yet. ${TRAIN_HINT}`}
          className="xl:col-span-3"
        >
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                  <th className="px-3 py-2.5 text-left">Rule</th>
                  {[
                    { key: "support", label: "Support" },
                    { key: "confidence", label: "Confidence" },
                    { key: "lift", label: "Lift" },
                  ].map((col) => (
                    <th key={col.key} className="px-3 py-2.5 text-right">
                      <button
                        type="button"
                        onClick={() => toggleRulesSort(col.key)}
                        className="inline-flex items-center gap-1 font-semibold uppercase transition-colors hover:text-slate-800"
                        aria-label={`Sort by ${col.label}`}
                      >
                        {col.label} {sortMark(col.key)}
                      </button>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {rules.map((rule) => (
                  <tr key={rule.id} className="align-top hover:bg-slate-50">
                    <td className="px-3 py-2.5">
                      <div className="font-medium text-slate-900">
                        {rule.antecedent} <span className="text-cyan-600">⇒</span> {rule.consequent}
                      </div>
                      {rule.plain_english && <div className="mt-0.5 text-xs text-slate-500">{rule.plain_english}</div>}
                      {rule.rule_family && (
                        <span className="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                          {rule.rule_family}
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{Number(rule.support).toFixed(3)}</td>
                    <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{Number(rule.confidence).toFixed(3)}</td>
                    <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{Number(rule.lift).toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </ChartCard>

        <ChartCard
          title="Rules: Support × Confidence"
          subtitle="Bubble size = lift. Hover a bubble for the rule."
          loading={loading}
          error={error}
          onRetry={load}
          isEmpty={!loading && !error && bubbles.length === 0}
          emptyText={TRAIN_HINT}
          className="xl:col-span-2"
        >
          <BubbleChart
            data={bubbles}
            xKey="support"
            yKey="confidence"
            sizeKey="lift"
            labelKey="label"
            height={280}
            ariaLabel="Association rules bubble chart"
          />
        </ChartCard>
      </div>
    </div>
  );
}

/** Standalone /ml-insights page (admin only). */
export default function MlInsightsPage({ user, effectiveRole, onRoleChange }) {
  return (
    <AppLayout
      role={user?.role || "admin"}
      effectiveRole={effectiveRole || "admin"}
      onRoleChange={onRoleChange || (() => {})}
      active="ml-insights"
      onNavigate={() => {
        window.location.href = "/";
      }}
    >
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">ML Insights</h1>
        <p className="text-sm text-slate-500">
          Supervised and unsupervised model outputs: k-Means driver segmentation, PCA projection and FP-Growth association rules.
        </p>
      </header>
      <MlInsightsContent />
    </AppLayout>
  );
}
