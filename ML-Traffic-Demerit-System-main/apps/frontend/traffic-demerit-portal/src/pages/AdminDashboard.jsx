import React, { useState, useEffect, useCallback, useMemo } from "react";
import { Link } from "react-router-dom";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import SanctionCard from "@/components/SanctionCard";
import LedgerTable from "@/components/LedgerTable";
import RiskBadge from "@/components/RiskBadge";
import LiveDemeritGauge from "@/components/LiveDemeritGauge";
import RiskExplanationPanel from "@/components/RiskExplanationPanel";
import NotificationCenter from "@/components/NotificationCenter";
import DataTable from "@/components/crud/DataTable";
import { BarChart, ChartCard, DonutChart, formatCompact } from "@/components/charts";
import { toast, ToastStack } from "@/components/ui/toasts";
import { MlInsightsContent } from "@/pages/MlInsightsPage";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel, OFFENCE_TYPES } from "@/lib/constants";

/* Static option lists for the CRUD forms (backend enums). */
const OFFENCE_OPTIONS = OFFENCE_TYPES.map((o) => ({ value: o.value, label: o.label }));
const ZONE_OPTIONS = [
  { value: "urban", label: "Urban" },
  { value: "highway", label: "Highway" },
  { value: "school_zone", label: "School Zone" },
  { value: "residential", label: "Residential" },
];
const WEATHER_OPTIONS = [
  { value: "clear", label: "Clear" },
  { value: "rain", label: "Rain" },
  { value: "fog", label: "Fog" },
  { value: "night", label: "Night" },
];
const VIOLATION_STATUS_OPTIONS = [
  { value: "confirmed", label: "Confirmed" },
  { value: "disputed", label: "Disputed" },
];
const PAYMENT_METHOD_OPTIONS = [
  { value: "mpesa", label: "M-Pesa" },
  { value: "card", label: "Card" },
  { value: "cash", label: "Cash" },
];
const APPEAL_STATUS_OPTIONS = [
  { value: "pending", label: "Pending" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
];
const STATUS_OPTIONS = [
  { value: "active", label: "Active" },
  { value: "suspended", label: "Suspended" },
];
const SHIFT_OPTIONS = [
  { value: "day", label: "Day" },
  { value: "night", label: "Night" },
  { value: "swing", label: "Swing" },
];
const ROLE_OPTIONS = [
  { value: "admin", label: "Admin" },
  { value: "officer", label: "Officer" },
  { value: "motorist", label: "Motorist" },
];
const ACTIVE_OPTIONS = [
  { value: "1", label: "Active" },
  { value: "0", label: "Inactive" },
];

export default function AdminDashboard({ user, effectiveRole, onRoleChange }) {
  const [active, setActive] = useState("drivers");
  const [drivers, setDrivers] = useState([]);
  const [scores, setScores] = useState({});
  const [violations, setViolations] = useState([]);
  const [sanctions, setSanctions] = useState([]);
  const [appeals, setAppeals] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [payments, setPayments] = useState([]);
  const [selected, setSelected] = useState(null);
  const [ledger, setLedger] = useState([]);
  const [report, setReport] = useState(null);
  const [ops, setOps] = useState({
    users: [],
    roles: [],
    stations: [],
    assignments: [],
    vehicles: [],
    predictions: [],
    reviews: [],
  });
  const [savingOps, setSavingOps] = useState(false);
  const [opsMessage, setOpsMessage] = useState("");
  const [editingRoleId, setEditingRoleId] = useState(null);
  const [editingReviewId, setEditingReviewId] = useState(null);
  const [predictingRisk, setPredictingRisk] = useState(false);
  const [predictionFeedback, setPredictionFeedback] = useState("");
  const [latestPrediction, setLatestPrediction] = useState(null);
  const [predictionDriverId, setPredictionDriverId] = useState("");
  const [lastRefreshedAt, setLastRefreshedAt] = useState(null);
  const [roleEditForm, setRoleEditForm] = useState({ role_name: "", permission_level: "", role_description: "" });
  const [reviewEditForm, setReviewEditForm] = useState({ officer_assignment_id: "", decision: "", notes: "", reviewed_at: "" });
  const [searchInput, setSearchInput] = useState("");
  const [searchTerm, setSearchTerm] = useState("");
  const [leaderboardSearchInput, setLeaderboardSearchInput] = useState("");
  const [leaderboardSearchTerm, setLeaderboardSearchTerm] = useState("");
  const [leaderboardRiskClass, setLeaderboardRiskClass] = useState("all");
  const [leaderboardMinScore, setLeaderboardMinScore] = useState("");
  const [auditSearchInput, setAuditSearchInput] = useState("");
  const [auditSearchTerm, setAuditSearchTerm] = useState("");
  const [auditTypeFilter, setAuditTypeFilter] = useState("all");
  const [auditEntityFilter, setAuditEntityFilter] = useState("all");

  // MySQL-backed analytics (loaded once on mount, manual retry on error).
  const [adminCharts, setAdminCharts] = useState(null);
  const [chartsLoading, setChartsLoading] = useState(true);
  const [chartsError, setChartsError] = useState("");
  // System status strip (DB name, row counts, ML up/down).
  const [systemStatus, setSystemStatus] = useState(null);
  const [statusError, setStatusError] = useState("");
  // Bump to force every CRUD DataTable to reload the current page.
  const [tableRefresh, setTableRefresh] = useState(0);

  const loadAdminCharts = useCallback(async () => {
    setChartsLoading(true);
    setChartsError("");
    try {
      setAdminCharts(await appClient.charts.admin());
    } catch (err) {
      setChartsError(err.message || "Failed to load analytics.");
    } finally {
      setChartsLoading(false);
    }
  }, []);

  const loadSystemStatus = useCallback(async () => {
    setStatusError("");
    try {
      setSystemStatus(await appClient.system.status());
    } catch (err) {
      setStatusError(err.message || "Status unavailable.");
    }
  }, []);

  useEffect(() => { loadAdminCharts(); loadSystemStatus(); }, [loadAdminCharts, loadSystemStatus]);

  const refreshTables = useCallback(() => setTableRefresh((t) => t + 1), []);

  const loadAll = useCallback(async () => {
    const [d, v, s, a, n, p, users, roles, stations, assignments, vehicles, predictions, reviews] = await Promise.all([
      appClient.entities.Driver.list("-demerit_balance", 1000),
      appClient.entities.Violation.list("-timestamp", 100),
      appClient.entities.Sanction.list("-triggered_at", 50),
      appClient.entities.Appeal.list("-submitted_at", 50),
      appClient.entities.Notification.list("-created_at", 200),
      appClient.entities.Payment.list("-created_at", 200),
      appClient.entities.User.list("name", 300),
      appClient.entities.Role.list("permission_level", 20),
      appClient.entities.Station.list("region", 100),
      appClient.entities.OfficerAssignment.list("-assignment_start_date", 100),
      appClient.entities.Vehicle.list("plate_number", 100),
      appClient.entities.RiskPrediction.list("-predicted_at", 100),
      appClient.entities.ReviewLog.list("-reviewed_at", 100),
    ]);
    setDrivers(d); setViolations(v); setSanctions(s); setAppeals(a); setNotifications(n); setPayments(p);
    if (!predictionDriverId && d.length > 0) {
      setPredictionDriverId(String(d[0].id));
    }
    setOps({ users, roles, stations, assignments, vehicles, predictions, reviews });
    // All predictions (no cap): the integration list has no limit ceiling, and
    // the scores map needs the LATEST row for EVERY driver — with 420+ rows, a
    // limit of 100 left most watch cards stuck on "SCORE PENDING".
    const sc = await appClient.entities.RecidivismScore.list("-timestamp", 1000);
    const map = {};
    sc.forEach((r) => { if (!map[r.driver_id]) map[r.driver_id] = r; });
    setScores(map);
    setLastRefreshedAt(new Date());
  }, []);

  useEffect(() => { loadAll(); }, [loadAll]);

  // Live Demerit Watch auto-refresh: only the state-backed views (drivers, risk
  // scores, sanctions, notifications) — NOT the full 15-list loadAll, which
  // saturated the API with heavy queries every 10 seconds.
  const refreshWatch = useCallback(async () => {
    try {
      const [d, sc, s, n] = await Promise.all([
        appClient.entities.Driver.list("-demerit_balance", 1000),
        appClient.entities.RecidivismScore.list("-timestamp", 1000),
        appClient.entities.Sanction.list("-triggered_at", 50),
        appClient.entities.Notification.list("-created_at", 200),
      ]);
      setDrivers(d);
      const map = {};
      sc.forEach((r) => { if (!map[r.driver_id]) map[r.driver_id] = r; });
      setScores(map);
      setSanctions(s);
      setNotifications(n);
      setLastRefreshedAt(new Date());
    } catch {
      // Transient refresh failure: keep the last snapshot on screen.
    }
  }, []);

  useEffect(() => {
    const timer = window.setInterval(() => {
      refreshWatch();
    }, 30000);

    return () => window.clearInterval(timer);
  }, [refreshWatch]);

  useEffect(() => {
    if (!selected?.id) return;

    const fresh = drivers.find((driver) => String(driver.id) === String(selected.id));
    if (fresh) {
      setSelected(fresh);
    }
  }, [drivers, selected?.id]);

  const selectDriver = async (d) => {
    setSelected(d);
    setPredictionDriverId(String(d.id));
    setPredictionFeedback("");
    setLatestPrediction(null);
    const led = await appClient.entities.DemeritLedger.filter({ driver_id: d.id }, "-timestamp", 50);
    setLedger(led);
  };

  const predictAccidentRisk = async () => {
    const driverId = predictionDriverId || selected?.id;
    if (!driverId) {
      setPredictionFeedback("Select a driver first.");
      return;
    }

    setPredictingRisk(true);
    setPredictionFeedback("");

    try {
      const response = await appClient.functions.invoke("predictAccidentRisk", {
        driver_id: Number(driverId),
      });

      const result = response?.data;
      if (!result) {
        throw new Error("Prediction response was empty.");
      }

      setLatestPrediction(result);
      setPredictionFeedback(`Predicted risk: ${(result.risk.score * 100).toFixed(1)}% (${String(result.risk.classification).toUpperCase()}).`);
      setScores((prev) => ({
        ...prev,
        [String(result.driver_id)]: {
          id: result.prediction?.id || `assessment-${result.driver_id}-${Date.now()}`,
          driver_id: String(result.driver_id),
          driver_name: result.driver_name,
          risk_score: result.risk.score,
          risk_classification: result.risk.classification,
          factor_contributions: result.risk.factor_contributions || [],
          timestamp: result.predicted_at,
        },
      }));
      await loadAll();
    } catch (err) {
      setPredictionFeedback(err.message || "Failed to generate prediction.");
    } finally {
      setPredictingRisk(false);
    }
  };

  const liftSanction = async (s) => {
    try {
      await appClient.entities.Sanction.update(s.id, { status: "lifted", lifted_by: user.email, lifted_at: new Date().toISOString() });
      await appClient.entities.Driver.update(s.driver_id, { status: "active", locked_reason: "", locked_at: null });
      toast.success("Sanction lifted", `${s.driver_name || "Driver"} is active again.`);
      refreshTables();
      loadAll();
    } catch (err) {
      toast.error("Could not lift sanction", err.message);
    }
  };

  const generateReport = async () => {
    const res = await appClient.functions.invoke("generateComplianceReport", {});
    setReport(res.data);
  };

  const applySearch = () => {
    setSearchTerm(searchInput.trim().toLowerCase());
  };

  const clearSearch = () => {
    setSearchInput("");
    setSearchTerm("");
  };

  const includesSearch = (values) => {
    if (!searchTerm) return true;
    const haystack = values.filter(Boolean).join(" ").toLowerCase();
    return haystack.includes(searchTerm);
  };

  const filteredDrivers = useMemo(() => {
    return drivers.filter((d) => includesSearch([d.full_name, d.license_number, d.email, d.status]));
  }, [drivers, searchTerm]);

  const filteredSanctions = useMemo(() => {
    return sanctions.filter((s) => includesSearch([s.driver_name, s.status, s.risk_classification, s.explanation]));
  }, [sanctions, searchTerm]);

  const filteredNotifications = useMemo(() => {
    return notifications.filter((item) => includesSearch([item.title, item.message, item.status, item.channel, item.recipient_email, item.recipient_role]));
  }, [notifications, searchTerm]);

  const unreadNotificationCount = useMemo(() => {
    return notifications.filter((item) => item.status === "unread").length;
  }, [notifications]);

  const toggleNotificationRead = async (notification, status) => {
    await appClient.entities.Notification.update(notification.id, { status });
    await loadAll();
  };

  const filteredRoles = useMemo(() => {
    return ops.roles.filter((r) => includesSearch([r.role_name, String(r.permission_level), r.role_description]));
  }, [ops.roles, searchTerm]);

  const filteredPredictions = useMemo(() => {
    return ops.predictions.filter((p) => includesSearch([p.driver_name, p.risk_class, p.model_version]));
  }, [ops.predictions, searchTerm]);

  const filteredReviews = useMemo(() => {
    return ops.reviews.filter((r) => includesSearch([r.officer_name, r.station_name, r.decision, r.notes]));
  }, [ops.reviews, searchTerm]);

  const analyticsMonthlyTrend = useMemo(() => {
    if (Array.isArray(adminCharts?.monthly_violations_fines) && adminCharts.monthly_violations_fines.length > 0) {
      return adminCharts.monthly_violations_fines.map((entry) => ({
        label: String(entry.month || "Unknown"),
        total: Number(entry.violations) || 0,
      }));
    }

    if (Array.isArray(report?.monthly_violations) && report.monthly_violations.length > 0) {
      return report.monthly_violations.map((entry) => ({
        label: String(entry.month || "Unknown"),
        total: Number(entry.total) || 0,
      }));
    }

    const grouped = {};
    (violations || []).forEach((violation) => {
      const raw = violation.timestamp || violation.violation_date_time;
      if (!raw) return;
      const date = new Date(raw);
      if (Number.isNaN(date.getTime())) return;
      const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
      grouped[key] = (grouped[key] || 0) + 1;
    });

    return Object.entries(grouped)
      .sort((a, b) => a[0].localeCompare(b[0]))
      .slice(-6)
      .map(([month, total]) => ({ label: month, total }));
  }, [adminCharts, report, violations]);

  const analyticsRiskDistribution = useMemo(() => {
    if (Array.isArray(adminCharts?.risk_distribution) && adminCharts.risk_distribution.length > 0) {
      return adminCharts.risk_distribution.map((row) => ({
        key: String(row.risk_class || "unknown"),
        total: Number(row.drivers) || 0,
      }));
    }

    if (Array.isArray(report?.risk_distribution) && report.risk_distribution.length > 0) {
      return ["low", "moderate", "high"].map((key) => {
        const row = report.risk_distribution.find((entry) => String(entry.risk_class || "").toLowerCase() === key);
        return { key, total: Number(row?.total) || 0 };
      });
    }

    const totals = { low: 0, moderate: 0, high: 0 };
    Object.values(scores || {}).forEach((score) => {
      const riskClass = String(score?.risk_classification || "").toLowerCase();
      if (riskClass === "high" || riskClass === "moderate" || riskClass === "low") {
        totals[riskClass] += 1;
      }
    });

    return [
      { key: "low", total: totals.low },
      { key: "moderate", total: totals.moderate },
      { key: "high", total: totals.high },
    ];
  }, [adminCharts, report, scores]);

  const leaderboardRows = useMemo(() => {
    const mapped = (drivers || []).map((driver) => {
      const scoreRecord = scores[driver.id] || scores[String(driver.id)] || null;
      const riskScore = Number(scoreRecord?.risk_score);
      const classification = String(scoreRecord?.risk_classification || "unscored").toLowerCase();
      return {
        id: driver.id,
        full_name: driver.full_name,
        license_number: driver.license_number,
        demerit_balance: Number(driver.demerit_balance) || 0,
        status: driver.status,
        risk_score: Number.isFinite(riskScore) ? riskScore : null,
        risk_classification: classification,
      };
    });

    return mapped
      .filter((row) => {
        if (!leaderboardSearchTerm) return true;
        const text = [row.full_name, row.license_number, row.status].filter(Boolean).join(" ").toLowerCase();
        return text.includes(leaderboardSearchTerm);
      })
      .filter((row) => leaderboardRiskClass === "all" ? true : row.risk_classification === leaderboardRiskClass)
      .filter((row) => {
        if (leaderboardMinScore === "") return true;
        const threshold = Number(leaderboardMinScore);
        if (Number.isNaN(threshold)) return true;
        return (row.risk_score || 0) * 100 >= threshold;
      })
      .sort((a, b) => {
        const left = a.risk_score ?? -1;
        const right = b.risk_score ?? -1;
        if (right !== left) return right - left;
        return b.demerit_balance - a.demerit_balance;
      });
  }, [drivers, scores, leaderboardSearchTerm, leaderboardRiskClass, leaderboardMinScore]);

  const auditEntries = useMemo(() => {
    const automatedPredictions = (ops.predictions || []).map((prediction) => ({
      id: `risk-${prediction.id}`,
      timestamp: prediction.predicted_at,
      type: "automated",
      entity: "risk_prediction",
      title: "Risk prediction generated",
      actor: prediction.model_version || "Random Forest model",
      summary: `${prediction.driver_name || "Unknown driver"} scored ${Math.round((Number(prediction.risk_score) || 0) * 100)}% (${String(prediction.risk_class || "unknown").toUpperCase()})`,
    }));

    const automatedSanctions = (sanctions || []).map((sanction) => ({
      id: `sanction-trigger-${sanction.id}`,
      timestamp: sanction.triggered_at,
      type: "automated",
      entity: "sanction",
      title: "Sanction triggered",
      actor: "Automated sanctions engine",
      summary: `${sanction.driver_name || "Unknown driver"} moved to ${String(sanction.status || "active").toUpperCase()} at ${(Number(sanction.risk_score) * 100).toFixed(1)}% risk`,
    }));

    const humanSanctionUpdates = (sanctions || [])
      .filter((sanction) => sanction.lifted_by || sanction.lifted_at)
      .map((sanction) => ({
        id: `sanction-lift-${sanction.id}`,
        timestamp: sanction.lifted_at,
        type: "human",
        entity: "sanction",
        title: "Sanction updated",
        actor: sanction.lifted_by || "Administrator",
        summary: `Sanction for ${sanction.driver_name || "Unknown driver"} was lifted`,
      }));

    const humanReviewLogs = (ops.reviews || []).map((review) => ({
      id: `review-log-${review.id}`,
      timestamp: review.reviewed_at,
      type: "human",
      entity: "review_log",
      title: "Human review logged",
      actor: review.reviewer_name || review.officer_name || "Reviewer",
      summary: `${String(review.decision || "pending").toUpperCase()} decision for sanction #${review.sanction_action_id || "--"}`,
    }));

    const appealSubmissions = (appeals || []).map((appeal) => ({
      id: `appeal-submitted-${appeal.id}`,
      timestamp: appeal.submitted_at,
      type: "human",
      entity: "appeal",
      title: "Appeal submitted",
      actor: appeal.driver_name || "Driver",
      summary: `Appeal submitted for violation #${appeal.violation_id || "--"}`,
    }));

    const appealReviews = (appeals || [])
      .filter((appeal) => appeal.reviewed_at || appeal.resolved_at)
      .map((appeal) => ({
        id: `appeal-reviewed-${appeal.id}`,
        timestamp: appeal.resolved_at || appeal.reviewed_at,
        type: "human",
        entity: "appeal",
        title: "Appeal resolved",
        actor: appeal.reviewed_by || "Administrator",
        summary: `Appeal marked ${String(appeal.status || appeal.outcome || "resolved").toUpperCase()}`,
      }));

    const automatedNotifications = (notifications || []).map((notification) => ({
      id: `notification-${notification.id}`,
      timestamp: notification.created_at || notification.sent_at,
      type: "automated",
      entity: "notification",
      title: "Notification dispatched",
      actor: `${String(notification.channel || "in-app").toUpperCase()} channel`,
      summary: `${notification.title || "Untitled"} to ${notification.recipient_name || notification.recipient_email || notification.recipient_role || "recipient"}`,
    }));

    return [
      ...automatedPredictions,
      ...automatedSanctions,
      ...humanSanctionUpdates,
      ...humanReviewLogs,
      ...appealSubmissions,
      ...appealReviews,
      ...automatedNotifications,
    ]
      .filter((entry) => entry.timestamp)
      .map((entry) => ({
        ...entry,
        sortTs: new Date(entry.timestamp).getTime(),
      }))
      .sort((a, b) => b.sortTs - a.sortTs);
  }, [ops.predictions, ops.reviews, sanctions, appeals, notifications]);

  const filteredAuditEntries = useMemo(() => {
    return auditEntries
      .filter((entry) => {
        if (auditTypeFilter === "all") return true;
        return entry.type === auditTypeFilter;
      })
      .filter((entry) => {
        if (auditEntityFilter === "all") return true;
        return entry.entity === auditEntityFilter;
      })
      .filter((entry) => {
        if (!auditSearchTerm) return true;
        const text = [entry.title, entry.actor, entry.summary, entry.entity].join(" ").toLowerCase();
        return text.includes(auditSearchTerm);
      });
  }, [auditEntries, auditTypeFilter, auditEntityFilter, auditSearchTerm]);

  const auditSummary = useMemo(() => {
    return {
      total: filteredAuditEntries.length,
      automated: filteredAuditEntries.filter((entry) => entry.type === "automated").length,
      human: filteredAuditEntries.filter((entry) => entry.type === "human").length,
    };
  }, [filteredAuditEntries]);

  const startEditRole = (role) => {
    setEditingRoleId(role.id);
    setRoleEditForm({
      role_name: role.role_name || "",
      permission_level: role.permission_level ?? "",
      role_description: role.role_description || "",
    });
  };

  const saveRoleEdit = async () => {
    if (!editingRoleId) return;
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.Role.update(editingRoleId, {
        ...roleEditForm,
        permission_level: Number(roleEditForm.permission_level),
      });
      setOpsMessage("Role updated.");
      setEditingRoleId(null);
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to update role.");
    } finally {
      setSavingOps(false);
    }
  };

  const startEditReview = (review) => {
    setEditingReviewId(review.id);
    setReviewEditForm({
      officer_assignment_id: review.officer_assignment_id || "__none__",
      decision: review.decision || "__pending__",
      notes: review.notes || "",
      reviewed_at: review.reviewed_at ? new Date(review.reviewed_at).toISOString().slice(0, 10) : "",
    });
  };

  const saveReviewEdit = async () => {
    if (!editingReviewId) return;
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.ReviewLog.update(editingReviewId, {
        officer_assignment_id: reviewEditForm.officer_assignment_id === "__none__" ? null : Number(reviewEditForm.officer_assignment_id),
        decision: reviewEditForm.decision === "__pending__" ? null : reviewEditForm.decision,
        notes: reviewEditForm.notes || null,
        reviewed_at: reviewEditForm.reviewed_at || null,
      });
      setOpsMessage("Review log updated.");
      setEditingReviewId(null);
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to update review log.");
    } finally {
      setSavingOps(false);
    }
  };

  const applyLeaderboardSearch = () => {
    setLeaderboardSearchTerm(leaderboardSearchInput.trim().toLowerCase());
  };

  const clearLeaderboardFilters = () => {
    setLeaderboardSearchInput("");
    setLeaderboardSearchTerm("");
    setLeaderboardRiskClass("all");
    setLeaderboardMinScore("");
  };

  const applyAuditSearch = () => {
    setAuditSearchTerm(auditSearchInput.trim().toLowerCase());
  };

  const clearAuditFilters = () => {
    setAuditSearchInput("");
    setAuditSearchTerm("");
    setAuditTypeFilter("all");
    setAuditEntityFilter("all");
  };

  /* ------------------------------------------------------------------ */
  /* CRUD table configurations (server-side pagination/search/sort)      */
  /* ------------------------------------------------------------------ */

  const userSelectOptions = useMemo(
    () => (ops.users || []).map((u) => ({ value: u.id, label: `${u.name} (${u.role})` })),
    [ops.users]
  );
  const officerSelectOptions = useMemo(
    () => (ops.users || []).filter((u) => u.role === "officer" || u.role === "admin").map((u) => ({ value: u.id, label: `${u.name} (${u.role})` })),
    [ops.users]
  );
  const stationSelectOptions = useMemo(
    () => (ops.stations || []).map((s) => ({ value: s.id, label: s.station_name })),
    [ops.stations]
  );
  const driverSelectOptions = useMemo(
    () => (drivers || []).map((d) => ({ value: d.id, label: `${d.full_name} · ${d.license_number || d.license_no || ""}` })),
    [drivers]
  );
  const vehicleSelectOptions = useMemo(
    () => (ops.vehicles || []).map((v) => ({ value: v.id, label: `${v.plate_number} · ${v.make_model}` })),
    [ops.vehicles]
  );

  const statusPill = (status) => {
    const s = String(status || "").toLowerCase();
    let cls = "bg-slate-100 text-slate-600";
    if (["completed", "confirmed", "approved", "active", "lifted"].includes(s)) cls = "bg-emerald-100 text-emerald-700";
    else if (["pending", "disputed", "under_review"].includes(s)) cls = "bg-amber-100 text-amber-700";
    else if (["rejected", "failed", "locked", "suspended", "voided"].includes(s)) cls = "bg-red-100 text-red-700";
    return <span className={`inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${cls}`}>{status || "—"}</span>;
  };

  const driverFields = useMemo(
    () => [
      { name: "user_id", label: "User account", type: "select", options: userSelectOptions, required: true, help: "The login this profile belongs to." },
      { name: "full_name", label: "Full name", required: true },
      { name: "license_no", label: "Licence number", required: true },
      { name: "license_class", label: "Licence class" },
      { name: "license_issue_date", label: "Issue date", type: "date" },
      { name: "license_expiry_date", label: "Expiry date", type: "date" },
      { name: "status", label: "Status", type: "select", options: STATUS_OPTIONS },
    ],
    [userSelectOptions]
  );

  const violationFields = useMemo(
    () => [
      { name: "driver_id", label: "Driver", type: "select", options: driverSelectOptions, required: true },
      { name: "vehicle_id", label: "Vehicle", type: "select", options: vehicleSelectOptions, required: true, help: "Must belong to the selected driver." },
      { name: "offense_type", label: "Offence type", type: "select", options: OFFENCE_OPTIONS, required: true },
      { name: "speed_recorded", label: "Speed recorded (km/h)", type: "number", required: true, min: 0, coerce: (v) => Number(v) },
      { name: "speed_limit", label: "Posted speed limit (km/h)", type: "number", required: true, min: 0, coerce: (v) => Number(v) },
      { name: "zone_type", label: "Zone type", type: "select", options: ZONE_OPTIONS, required: true },
      { name: "weather", label: "Weather", type: "select", options: WEATHER_OPTIONS, required: true },
      { name: "location", label: "Location", required: true },
      { name: "evidence_reference", label: "Evidence reference", required: true },
      { name: "violation_date_time", label: "Violation date & time", type: "datetime-local", required: true },
      { name: "status", label: "Status", type: "select", options: VIOLATION_STATUS_OPTIONS, help: "Only used when editing." },
    ],
    [driverSelectOptions, vehicleSelectOptions]
  );

  // The store endpoint expects `offence_type` (create) while update expects
  // `offense_type` - translate in create mode.
  const violationPayload = useCallback((payload, mode) => {
    if (mode !== "create") return payload;
    const { offense_type, ...rest } = payload;
    return { ...rest, offence_type: offense_type };
  }, []);

  const paymentFields = useMemo(
    () => [
      { name: "violation_id", label: "Violation ID", type: "number", required: true, coerce: (v) => Number(v) },
      { name: "amount", label: "Amount (KES)", type: "number", required: true, min: 1, coerce: (v) => Number(v) },
      { name: "method", label: "Method", type: "select", options: PAYMENT_METHOD_OPTIONS, required: true },
      { name: "reference_number", label: "Reference number" },
      { name: "paid_at", label: "Paid at", type: "datetime-local" },
    ],
    []
  );

  const appealFields = useMemo(
    () => [
      { name: "driver_id", label: "Driver", type: "select", options: driverSelectOptions, required: true },
      { name: "violation_id", label: "Violation ID (optional)", type: "number", coerce: (v) => (v === "" ? null : Number(v)) },
      { name: "reason", label: "Reason", type: "textarea", required: true, placeholder: "Why should this be reviewed?" },
    ],
    [driverSelectOptions]
  );

  const appealReviewFields = useMemo(
    () => [
      { name: "status", label: "Status", type: "select", options: APPEAL_STATUS_OPTIONS, required: true },
      { name: "review_notes", label: "Review notes", type: "textarea", placeholder: "Shown to the driver in their appeal tracker." },
    ],
    []
  );

  const stationFields = useMemo(
    () => [
      { name: "station_name", label: "Station name", required: true },
      { name: "region", label: "Region", required: true },
      { name: "physical_address", label: "Physical address", required: true },
      { name: "contact_phone", label: "Contact phone" },
    ],
    []
  );

  const assignmentFields = useMemo(
    () => [
      { name: "officer_user_id", label: "Officer", type: "select", options: officerSelectOptions, required: true },
      { name: "station_id", label: "Station", type: "select", options: stationSelectOptions, required: true },
      { name: "badge_number", label: "Badge number", required: true },
      { name: "shift_type", label: "Shift", type: "select", options: SHIFT_OPTIONS, required: true },
      { name: "assignment_start_date", label: "Start date", type: "date", required: true },
      { name: "assignment_end_date", label: "End date", type: "date" },
    ],
    [officerSelectOptions, stationSelectOptions]
  );

  const vehicleFields = useMemo(
    () => [
      { name: "owner_user_id", label: "Owner", type: "select", options: userSelectOptions, required: true },
      { name: "plate_number", label: "Plate number", required: true },
      { name: "make_model", label: "Make / model", required: true },
      { name: "vehicle_class", label: "Vehicle class", required: true },
      { name: "colour", label: "Colour" },
      { name: "year_of_manufacture", label: "Year", type: "number", min: 1950, max: 2100, coerce: (v) => (v === "" ? null : Number(v)) },
      { name: "registration_expiry", label: "Registration expiry", type: "date" },
    ],
    [userSelectOptions]
  );

  const userFields = useMemo(
    () => [
      { name: "name", label: "Name", required: true },
      { name: "email", label: "Email", type: "text", required: true, validate: (v) => (/\S+@\S+\.\S+/.test(String(v)) ? "" : "Enter a valid email address.") },
      { name: "password", label: "Password", type: "password", help: "Minimum 8 characters. Leave blank when editing to keep the current password." },
      { name: "role", label: "Role", type: "select", options: ROLE_OPTIONS, required: true },
      { name: "is_active", label: "Active", type: "select", options: ACTIVE_OPTIONS },
    ],
    []
  );

  const onOpsMutation = useCallback(() => {
    refreshTables();
    loadAll();
  }, [refreshTables, loadAll]);

  return (
    <AppLayout role={user.role} effectiveRole={effectiveRole} onRoleChange={onRoleChange} active={active} onNavigate={setActive}>
      <header className="mb-6">
        <h1 className="text-2xl font-bold text-slate-900">NTSA Administration Console</h1>
        <p className="text-sm text-slate-500">Manage driver profiles, review automated sanctions, and generate compliance reports.</p>
        <div className="mt-3 flex flex-wrap gap-2">
          <Input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                applySearch();
              }
            }}
            placeholder="Search drivers, violations, sanctions, operations..."
            className="max-w-md"
          />
          <Button size="sm" onClick={applySearch}>Search</Button>
          <Button size="sm" variant="outline" onClick={clearSearch}>Clear</Button>
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <Button size="sm" variant={active === "analytics" ? "default" : "outline"} onClick={() => setActive("analytics")}>
            Charts &amp; Analytics
          </Button>
          <Link to="/ml-insights">
            <Button size="sm" variant="outline">ML Insights →</Button>
          </Link>
        </div>
      </header>

      {active === "drivers" && (
        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <div className="text-sm font-semibold text-slate-900">Live Demerit Watch</div>
                <div className="mt-1 text-xs text-slate-500">
                  Auto-refreshes every 30 seconds with color-coded driver risk gauges.
                </div>
              </div>
              <div className="text-xs text-slate-500">
                Last sync: {lastRefreshedAt ? lastRefreshedAt.toLocaleTimeString() : "Loading..."}
              </div>
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
              {filteredDrivers.slice(0, 6).map((driver) => {
                const score = scores[driver.id];

                return (
                  <div key={`watch-${driver.id}`} className="rounded-lg border border-slate-200 p-3">
                    <div className="flex items-center justify-between gap-3">
                      <div className="min-w-0">
                        <div className="truncate text-sm font-semibold text-slate-900">{driver.full_name}</div>
                        <div className="truncate text-xs text-slate-500">{driver.license_number}</div>
                      </div>
                      {score ? (
                        <RiskBadge score={score.risk_score} classification={score.risk_classification} />
                      ) : (
                        <span className="text-[11px] font-semibold rounded-full bg-slate-100 px-2 py-1 text-slate-600">
                          SCORE PENDING
                        </span>
                      )}
                    </div>

                    <div className="mt-3">
                      <LiveDemeritGauge
                        compact
                        balance={driver.demerit_balance || 0}
                        score={score?.risk_score}
                        classification={score?.risk_classification}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="text-sm font-semibold text-slate-900">Predict Accident Risk</div>
            <div className="mt-1 text-xs text-slate-500">Run machine learning prediction from a driver's past records.</div>
            <div className="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
              <Select value={predictionDriverId} onValueChange={setPredictionDriverId}>
                <SelectTrigger><SelectValue placeholder="Select driver" /></SelectTrigger>
                <SelectContent>
                  {drivers.map((driver) => (
                    <SelectItem key={driver.id} value={String(driver.id)}>
                      {driver.full_name} ({driver.license_number})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button onClick={predictAccidentRisk} disabled={predictingRisk}>
                {predictingRisk ? "Calculating..." : "Predict"}
              </Button>
            </div>
            {predictionFeedback && (
              <div className="mt-2 text-sm text-slate-700">{predictionFeedback}</div>
            )}
            {latestPrediction && (
              <div className="mt-2 text-xs text-slate-500">
                Model {latestPrediction.model_version} · Predicted {new Date(latestPrediction.predicted_at).toLocaleString()}
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <DataTable
              resource={appClient.crud.drivers}
              resourceLabel="driver"
              refreshSignal={tableRefresh}
              searchPlaceholder="Search name or licence…"
              initialSort="-current_demerit_points"
              columns={[
                {
                  key: "full_name",
                  label: "Driver",
                  sortable: true,
                  render: (row) => (
                    <button type="button" className="font-medium text-cyan-700 hover:underline" onClick={() => selectDriver(row)}>
                      {row.full_name}
                    </button>
                  ),
                },
                { key: "license_no", label: "Licence", sortable: true },
                { key: "email", label: "Email" },
                {
                  key: "current_demerit_points",
                  label: "Points",
                  sortable: true,
                  align: "right",
                  render: (row) => <span className="font-semibold text-red-600">{row.current_demerit_points ?? 0}</span>,
                },
                {
                  key: "status",
                  label: "Status",
                  sortable: true,
                  render: (row) => statusPill(row.account_status || row.status),
                },
              ]}
              filters={[
                { name: "status", label: "status", options: STATUS_OPTIONS },
                { name: "account_status", label: "account", options: [{ value: "active", label: "Active" }, { value: "locked", label: "Locked" }] },
              ]}
              fields={driverFields}
              getRowLabel={(row) => `${row.full_name} (${row.license_no || "no licence"})`}
              onSaved={onOpsMutation}
              onDeleted={onOpsMutation}
            />
          {selected && (
            <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  {selected.profile_photo_url ? (
                    <img
                      src={selected.profile_photo_url}
                      alt={`${selected.full_name} profile`}
                      className="w-14 h-14 rounded-full object-cover border border-slate-200"
                    />
                  ) : (
                    <div className="w-14 h-14 rounded-full bg-slate-200" />
                  )}
                  <div>
                    <div className="font-semibold text-slate-900">{selected.full_name}</div>
                    <div className="text-xs text-slate-500">{selected.license_number} · {selected.phone}</div>
                  </div>
                </div>
                {scores[selected.id] && <RiskBadge score={scores[selected.id].risk_score} classification={scores[selected.id].risk_classification} />}
              </div>
              <div className="grid grid-cols-3 gap-2 text-center">
                <Mini label="Balance" value={selected.demerit_balance || 0} />
                <Mini label="Violations" value={selected.total_violations || 0} />
                <Mini label="Status" value={selected.status === "locked" ? "LOCKED" : "ACTIVE"} />
              </div>
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div className="text-sm font-semibold text-slate-900 mb-2">Live Demerit Gauge</div>
                <LiveDemeritGauge
                  balance={selected.demerit_balance || 0}
                  score={scores[selected.id]?.risk_score}
                  classification={scores[selected.id]?.risk_classification}
                />
              </div>
              <RiskExplanationPanel riskRecord={scores[selected.id]} />
              <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <div className="text-sm font-semibold text-slate-900">Accident Risk Prediction</div>
                    <div className="text-xs text-slate-500">Run ML prediction from this driver's past offences and demerit history.</div>
                  </div>
                  <Button size="sm" onClick={predictAccidentRisk} disabled={predictingRisk}>
                    {predictingRisk ? "Calculating..." : "Predict Accident Risk"}
                  </Button>
                </div>
                {predictionFeedback && (
                  <div className="mt-2 text-sm text-slate-700">{predictionFeedback}</div>
                )}
                {latestPrediction && (
                  <div className="mt-2 text-xs text-slate-500">
                    Model {latestPrediction.model_version} · Predicted {new Date(latestPrediction.predicted_at).toLocaleString()}
                  </div>
                )}
              </div>
              <div>
                <div className="text-sm font-semibold text-slate-900 mb-2">Ledger</div>
                <LedgerTable entries={ledger} />
              </div>
            </div>
          )}
          </div>
        </div>
      )}

      {active === "analytics" && (
        <div className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="text-xs text-slate-500">
              {adminCharts?.generated_at
                ? `Generated ${new Date(adminCharts.generated_at).toLocaleString()} · straight from MySQL — still available while the ML service is down.`
                : "MySQL-backed aggregates — still available while the ML service is down."}
            </div>
            <Button size="sm" variant="outline" onClick={loadAdminCharts} disabled={chartsLoading}>Refresh</Button>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ChartCard
              title="Driver Risk Distribution"
              subtitle="Latest risk prediction per driver."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.risk_distribution || []).length === 0}
            >
              <DonutChart
                data={(adminCharts?.risk_distribution || []).map((r) => ({ label: r.risk_class, value: r.drivers }))}
                centerLabel="drivers"
                centerValue={(adminCharts?.risk_distribution || []).reduce((sum, r) => sum + (Number(r.drivers) || 0), 0)}
                ariaLabel="Driver risk distribution donut"
              />
            </ChartCard>

            <ChartCard
              title="Monthly Violations & Fines"
              subtitle="Violations captured vs fines collected (KES) per month."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.monthly_violations_fines || []).length === 0}
            >
              <BarChart
                data={(adminCharts?.monthly_violations_fines || []).map((m) => ({ month: m.month, violations: m.violations, fines: m.fines }))}
                xKey="month"
                series={[
                  { key: "violations", label: "Violations", color: "#06b6d4" },
                  { key: "fines", label: "Fines (KES)", color: "#f97316" },
                ]}
                valueFormat={(v, key) => (key === "fines" ? `KES ${formatCompact(v)}` : formatCompact(v))}
                ariaLabel="Monthly violations and fines bar chart"
              />
            </ChartCard>

            <ChartCard
              title="Violations by Type"
              subtitle="Most frequently issued offence types."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.violations_by_type || []).length === 0}
            >
              <BarChart
                horizontal
                data={(adminCharts?.violations_by_type || []).map((t) => ({ type: offenceLabel(t.type), total: t.total, points: t.points }))}
                xKey="type"
                series={[{ key: "total", label: "Violations" }]}
                ariaLabel="Violations by type horizontal bar chart"
              />
            </ChartCard>

            <ChartCard
              title="Violations by Station"
              subtitle="Enforcement volume per station (unassigned included)."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.violations_by_station || []).length === 0}
            >
              <BarChart
                data={adminCharts?.violations_by_station || []}
                xKey="station"
                series={[{ key: "total", label: "Violations", color: "#06b6d4" }]}
                ariaLabel="Violations by station bar chart"
              />
            </ChartCard>
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <div className="text-sm font-semibold text-slate-900">Payments Summary</div>
                <div className="mt-1 text-xs text-slate-500">Collected vs invoiced fines, by status and method.</div>
              </div>
            </div>
            <div className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
              <Mini label="Payments" value={adminCharts?.payments_summary?.totals?.payments ?? "—"} />
              <Mini label="Collected (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.collected ?? 0)} />
              <Mini label="Invoiced (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.invoiced ?? 0)} />
              <Mini label="Outstanding (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.outstanding ?? 0)} />
            </div>
            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div>
                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">By status</div>
                {(adminCharts?.payments_summary?.by_status || []).length === 0 ? (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No payments recorded.</div>
                ) : (
                  <DonutChart
                    data={adminCharts.payments_summary.by_status.map((r) => ({ label: r.status, value: r.total }))}
                    ariaLabel="Payments by status donut"
                  />
                )}
              </div>
              <div>
                <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Collected by method (KES)</div>
                {(adminCharts?.payments_summary?.by_method || []).length === 0 ? (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">No payment methods recorded.</div>
                ) : (
                  <BarChart
                    horizontal
                    data={adminCharts.payments_summary.by_method.map((r) => ({ method: r.method, paid: r.paid, total: r.total }))}
                    xKey="method"
                    series={[{ key: "paid", label: "Collected (KES)" }]}
                    valueFormat={(v) => `KES ${formatCompact(v)}`}
                    ariaLabel="Collected fines by payment method"
                  />
                )}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ChartCard
              title="Sanctions by Status"
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.sanctions_by_status || []).length === 0}
              emptyText="No sanctions issued yet."
            >
              <DonutChart
                data={(adminCharts?.sanctions_by_status || []).map((r) => ({ label: r.status, value: r.total }))}
                ariaLabel="Sanctions by status donut"
              />
            </ChartCard>

            <ChartCard
              title="Appeals by Status"
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.appeals_by_status || []).length === 0}
              emptyText="No appeals submitted yet."
            >
              <DonutChart
                data={(adminCharts?.appeals_by_status || []).map((r) => ({ label: r.status, value: r.total }))}
                ariaLabel="Appeals by status donut"
              />
            </ChartCard>
          </div>

          <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <ChartCard
              title="Driver Watch List"
              subtitle="Top 10 drivers by demerit balance with live risk scores."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.watch_list || []).length === 0}
            >
              <div className="overflow-x-auto">
                <table className="w-full min-w-[480px] text-sm">
                  <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                      <th className="px-3 py-2.5 text-left">Driver</th>
                      <th className="px-3 py-2.5 text-right">Balance</th>
                      <th className="px-3 py-2.5 text-left">Status</th>
                      <th className="px-3 py-2.5 text-left">Risk</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {(adminCharts?.watch_list || []).map((w) => (
                      <tr key={w.driver_id} className="hover:bg-slate-50">
                        <td className="px-3 py-2.5">
                          <div className="font-medium text-slate-900">{w.driver_name}</div>
                          <div className="text-xs text-slate-500">{w.email}</div>
                        </td>
                        <td className="px-3 py-2.5 text-right font-semibold text-red-600">{w.demerit_balance}</td>
                        <td className="px-3 py-2.5">{statusPill(w.status)}</td>
                        <td className="px-3 py-2.5">
                          {w.risk_score == null ? (
                            <span className="text-xs font-semibold rounded-full bg-slate-100 px-2 py-1 text-slate-600">UNSCORED</span>
                          ) : (
                            <RiskBadge score={w.risk_score} classification={w.risk_class} />
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ChartCard>

            <ChartCard
              title="Officer Leaderboard"
              subtitle="Violations issued per officer."
              loading={chartsLoading}
              error={chartsError}
              onRetry={loadAdminCharts}
              isEmpty={!chartsLoading && !chartsError && (adminCharts?.leaderboard || []).length === 0}
            >
              <div className="overflow-x-auto">
                <table className="w-full min-w-[480px] text-sm">
                  <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                      <th className="px-3 py-2.5 text-left">Officer</th>
                      <th className="px-3 py-2.5 text-right">Violations</th>
                      <th className="px-3 py-2.5 text-right">Points Issued</th>
                      <th className="px-3 py-2.5 text-right">Drivers</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {(adminCharts?.leaderboard || []).map((o) => (
                      <tr key={o.officer_id} className="hover:bg-slate-50">
                        <td className="px-3 py-2.5 font-medium text-slate-900">{o.officer_name}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{o.violations}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-orange-600">{o.points_issued}</td>
                        <td className="px-3 py-2.5 text-right text-slate-600">{o.drivers}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ChartCard>
          </div>

          <MlInsightsContent />
        </div>
      )}

      {active === "violations" && (
        <DataTable
          resource={appClient.crud.violations}
          resourceLabel="violation"
          refreshSignal={tableRefresh}
          searchPlaceholder="Search location, evidence, status…"
          initialSort="-created_at"
          columns={[
            { key: "driver_name", label: "Driver", render: (row) => row.driver_name || `Driver #${row.driver_id}` },
            { key: "offense_type", label: "Offence", sortable: true, render: (row) => offenceLabel(row.offense_type || row.offence_type) },
            { key: "zone_type", label: "Zone", render: (row) => <span className="capitalize">{row.zone_type || "—"}</span> },
            { key: "weather_conditions", label: "Weather", render: (row) => <span className="capitalize">{row.weather_conditions || row.weather || "—"}</span> },
            {
              key: "points_assigned",
              label: "Points",
              sortable: true,
              align: "right",
              render: (row) => <span className="font-semibold text-red-600">+{row.points_assigned ?? 0}</span>,
            },
            {
              key: "created_at",
              label: "Date",
              sortable: true,
              render: (row) => <span className="text-slate-500">{row.timestamp ? new Date(row.timestamp).toLocaleString() : row.created_at ? new Date(row.created_at).toLocaleDateString() : "—"}</span>,
            },
            { key: "status", label: "Status", sortable: true, render: (row) => statusPill(row.status) },
          ]}
          filters={[
            { name: "status", label: "status", options: VIOLATION_STATUS_OPTIONS },
            { name: "offense_type", label: "offence", options: OFFENCE_OPTIONS },
            { name: "zone_type", label: "zone", options: ZONE_OPTIONS },
          ]}
          fields={violationFields}
          preparePayload={violationPayload}
          defaultValues={{ offense_type: "speeding", zone_type: "urban", weather: "clear", status: "confirmed" }}
          getRowLabel={(row) => `violation #${row.id} (${row.driver_name || `driver #${row.driver_id}`})`}
          onSaved={onOpsMutation}
          onDeleted={onOpsMutation}
        />
      )}

      {active === "sanctions" && (
        <div className="space-y-3">
          {filteredSanctions.length === 0 ? <div className="text-sm text-slate-500 py-8 text-center">No sanctions issued.</div> :
            filteredSanctions.map((s) => <SanctionCard key={s.id} sanction={s} canLift onLift={liftSanction} />)}
        </div>
      )}

      {active === "payments" && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Mini label="Total Payments" value={adminCharts?.payments_summary?.totals?.payments ?? payments.length} />
            <Mini label="Collected (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.collected ?? 0)} />
            <Mini label="Invoiced (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.invoiced ?? 0)} />
            <Mini label="Outstanding (KES)" value={formatCompact(adminCharts?.payments_summary?.totals?.outstanding ?? 0)} />
          </div>
          <DataTable
            resource={appClient.crud.payments}
            resourceLabel="payment"
            refreshSignal={tableRefresh}
            searchPlaceholder="Search reference, status, method…"
            initialSort="-created_at"
            columns={[
              { key: "driver_name", label: "Driver", render: (row) => row.driver_name || (row.driver_id ? `Driver #${row.driver_id}` : "—") },
              { key: "offence_type", label: "Violation", render: (row) => offenceLabel(row.offence_type) },
              { key: "method", label: "Method", render: (row) => <span className="capitalize">{row.method || row.payment_method || "—"}</span> },
              { key: "amount", label: "Amount", sortable: true, align: "right", render: (row) => <span className="font-semibold text-slate-900">KES {Number(row.amount || 0).toLocaleString()}</span> },
              { key: "reference_number", label: "Reference", render: (row) => row.reference_number || row.transaction_ref || "—" },
              { key: "status", label: "Status", sortable: true, render: (row) => statusPill(row.status) },
              {
                key: "paid_at",
                label: "Paid",
                sortable: true,
                render: (row) => <span className="text-slate-500">{row.paid_at ? new Date(row.paid_at).toLocaleString() : "Pending"}</span>,
              },
            ]}
            filters={[
              {
                name: "status",
                label: "status",
                options: [
                  { value: "completed", label: "Completed" },
                  { value: "pending", label: "Pending" },
                  { value: "failed", label: "Failed" },
                  { value: "voided", label: "Voided" },
                ],
              },
              { name: "method", label: "method", options: PAYMENT_METHOD_OPTIONS },
            ]}
            fields={paymentFields}
            canEdit={() => false}
            canDelete={() => false}
            getRowLabel={(row) => `payment #${row.id} (ref ${row.reference_number || row.transaction_ref || "—"})`}
            rowActions={(row, helpers) => [
              ...(String(row.status).toLowerCase() === "pending"
                ? [
                    {
                      label: "Verify",
                      variant: "outline",
                      onClick: async (r) => {
                        try {
                          await appClient.payments.verify(r.id, { status: "completed", amount: Number(r.amount || 0), currency: r.currency || "KES" });
                          toast.success("Payment verified", `Payment #${r.id} marked completed.`);
                          refreshTables();
                          loadAll();
                        } catch (err) {
                          toast.error("Verification failed", err.message);
                        }
                      },
                    },
                  ]
                : []),
              ...(String(row.status).toLowerCase() !== "voided"
                ? [
                    {
                      label: "Void",
                      variant: "outline",
                      onClick: (r) =>
                        helpers.confirm({
                          row: r,
                          title: "Void payment",
                          message: <>Void payment #{r.id} (KES {Number(r.amount || 0).toLocaleString()})? A reason is required.</>,
                          confirmLabel: "Void payment",
                          reasonVisible: true,
                          successTitle: "Payment voided",
                          successMessage: `Payment #${r.id} was voided.`,
                          onConfirm: (payment, reason) => appClient.crud.payments.void(payment.id, reason),
                        }),
                    },
                  ]
                : []),
            ]}
            onSaved={onOpsMutation}
          />
        </div>
      )}

      {active === "notifications" && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Mini label="Total Notifications" value={notifications.length} />
            <Mini label="Unread" value={unreadNotificationCount} />
            <Mini label="Read" value={notifications.length - unreadNotificationCount} />
          </div>
          <NotificationCenter notifications={filteredNotifications} onToggleRead={toggleNotificationRead} />
        </div>
      )}

      {active === "appeals" && (
        <DataTable
          resource={appClient.crud.appeals}
          resourceLabel="appeal"
          refreshSignal={tableRefresh}
          searchPlaceholder="Search reason, status…"
          initialSort="-submitted_at"
          columns={[
            { key: "driver_name", label: "Driver", render: (row) => row.driver_name || `Driver #${row.driver_id}` },
            { key: "offence_type", label: "Offence", render: (row) => offenceLabel(row.offence_type) },
            { key: "reason", label: "Reason", render: (row) => <span className="line-clamp-2">{row.reason}</span> },
            {
              key: "submitted_at",
              label: "Submitted",
              sortable: true,
              render: (row) => <span className="text-slate-500">{row.submitted_at ? new Date(row.submitted_at).toLocaleString() : "—"}</span>,
            },
            { key: "status", label: "Status", sortable: true, render: (row) => statusPill(row.status) },
          ]}
          filters={[{ name: "status", label: "status", options: APPEAL_STATUS_OPTIONS }]}
          fields={appealFields}
          rowActions={(row) =>
            String(row.status).toLowerCase() === "pending"
              ? [
                  {
                    label: "Approve",
                    onClick: async (r) => {
                      try {
                        await appClient.crud.appeals.update(r.id, { status: "approved", review_notes: "Appeal upheld on review." });
                        toast.success("Appeal approved", `Appeal #${r.id} was approved.`);
                        refreshTables();
                        loadAll();
                      } catch (err) {
                        toast.error("Could not approve appeal", err.message);
                      }
                    },
                  },
                  {
                    label: "Reject",
                    variant: "outline",
                    onClick: async (r) => {
                      try {
                        await appClient.crud.appeals.update(r.id, { status: "rejected", review_notes: "Appeal denied." });
                        toast.success("Appeal rejected", `Appeal #${r.id} was rejected.`);
                        refreshTables();
                        loadAll();
                      } catch (err) {
                        toast.error("Could not reject appeal", err.message);
                      }
                    },
                  },
                ]
              : []
          }
          getRowLabel={(row) => `appeal #${row.id} (${row.driver_name || `driver #${row.driver_id}`})`}
          onSaved={onOpsMutation}
          onDeleted={onOpsMutation}
        />
      )}

      {active === "operations" && (
        <div className="space-y-6">
          <div>
            <div className="mb-2 text-sm font-semibold text-slate-900">User Accounts</div>
            <DataTable
              resource={appClient.crud.users}
              resourceLabel="user"
              refreshSignal={tableRefresh}
              searchPlaceholder="Search name, email, role…"
              initialSort="name"
              columns={[
                { key: "name", label: "Name", sortable: true },
                { key: "email", label: "Email", sortable: true },
                { key: "role", label: "Role", sortable: true, render: (row) => <span className="capitalize">{row.role}</span> },
                { key: "is_active", label: "Status", render: (row) => statusPill(row.is_active ? "active" : "inactive") },
                {
                  key: "created_at",
                  label: "Registered",
                  sortable: true,
                  render: (row) => <span className="text-slate-500">{row.registered_on ? new Date(row.registered_on).toLocaleDateString() : row.created_at ? new Date(row.created_at).toLocaleDateString() : "—"}</span>,
                },
              ]}
              filters={[{ name: "role", label: "role", options: ROLE_OPTIONS }]}
              fields={userFields}
              getRowLabel={(row) => `${row.name} (${row.email})`}
              onSaved={onOpsMutation}
              onDeleted={onOpsMutation}
            />
          </div>

          <div>
            <div className="mb-2 text-sm font-semibold text-slate-900">Stations</div>
            <DataTable
              resource={appClient.crud.stations}
              resourceLabel="station"
              refreshSignal={tableRefresh}
              searchPlaceholder="Search station, region…"
              initialSort="station_name"
              columns={[
                { key: "station_name", label: "Station", sortable: true },
                { key: "region", label: "Region", sortable: true },
                { key: "physical_address", label: "Address" },
                { key: "contact_phone", label: "Phone" },
              ]}
              filters={[]}
              fields={stationFields}
              getRowLabel={(row) => `${row.station_name} (${row.region})`}
              onSaved={onOpsMutation}
              onDeleted={onOpsMutation}
            />
          </div>

          <div>
            <div className="mb-2 text-sm font-semibold text-slate-900">Officer Assignments</div>
            <DataTable
              resource={appClient.crud.assignments}
              resourceLabel="assignment"
              refreshSignal={tableRefresh}
              searchPlaceholder="Search badge number, shift…"
              initialSort="-assignment_start_date"
              columns={[
                { key: "officer", label: "Officer", sortable: false, render: (row) => row.officer_name || (row.officer_user_id ? `User #${row.officer_user_id}` : "Unknown Officer") },
                { key: "badge_number", label: "Badge", sortable: true },
                { key: "station", label: "Station", render: (row) => row.station_name || (row.station_id ? `Station #${row.station_id}` : "No Station") },
                { key: "shift_type", label: "Shift", sortable: true, render: (row) => <span className="capitalize">{row.shift_type}</span> },
                { key: "assignment_start_date", label: "Start", sortable: true },
                { key: "assignment_end_date", label: "End" },
              ]}
              filters={[{ name: "shift_type", label: "shift", options: SHIFT_OPTIONS }]}
              fields={assignmentFields}
              getRowLabel={(row) => `assignment #${row.id} (${row.badge_number})`}
              onSaved={onOpsMutation}
              onDeleted={onOpsMutation}
            />
          </div>

          <div>
            <div className="mb-2 text-sm font-semibold text-slate-900">Vehicles</div>
            <DataTable
              resource={appClient.crud.vehicles}
              resourceLabel="vehicle"
              refreshSignal={tableRefresh}
              searchPlaceholder="Search plate, make, class…"
              initialSort="plate_number"
              columns={[
                { key: "plate_number", label: "Plate", sortable: true },
                { key: "make_model", label: "Make / Model", sortable: true },
                { key: "vehicle_class", label: "Class", sortable: true },
                { key: "colour", label: "Colour" },
                { key: "owner", label: "Owner", render: (row) => row.owner?.name || (row.owner_user_id ? `User #${row.owner_user_id}` : "—") },
                { key: "year_of_manufacture", label: "Year", sortable: true, align: "right" },
              ]}
              fields={vehicleFields}
              getRowLabel={(row) => `${row.plate_number} (${row.make_model})`}
              onSaved={onOpsMutation}
              onDeleted={onOpsMutation}
            />
          </div>

          {opsMessage && <div className="text-sm rounded-lg px-3 py-2 bg-slate-100 text-slate-700">{opsMessage}</div>}

          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <Mini label="Roles" value={ops.roles.length} />
            <Mini label="Stations" value={ops.stations.length} />
            <Mini label="Assignments" value={ops.assignments.length} />
            <Mini label="Vehicles" value={ops.vehicles.length} />
            <Mini label="Risk Predictions" value={ops.predictions.length} />
            <Mini label="Review Logs" value={ops.reviews.length} />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <EntityCard title="Roles" emptyText="No roles configured.">
              {filteredRoles.map((r) => (
                <div key={r.id} className="p-3 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="text-sm font-medium text-slate-900">{r.role_name}</div>
                      <div className="text-xs text-slate-500 mt-0.5">Level {r.permission_level}{r.role_description ? ` · ${r.role_description}` : ""}</div>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => startEditRole(r)}>Edit</Button>
                  </div>
                  {editingRoleId === r.id && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid gap-2">
                      <Input value={roleEditForm.role_name} onChange={(e) => setRoleEditForm((v) => ({ ...v, role_name: e.target.value }))} placeholder="Role name" />
                      <Input type="number" value={roleEditForm.permission_level} onChange={(e) => setRoleEditForm((v) => ({ ...v, permission_level: e.target.value }))} placeholder="Permission level" />
                      <Input value={roleEditForm.role_description} onChange={(e) => setRoleEditForm((v) => ({ ...v, role_description: e.target.value }))} placeholder="Description" />
                      <div className="flex gap-2">
                        <Button size="sm" disabled={savingOps} onClick={saveRoleEdit}>Save</Button>
                        <Button size="sm" variant="outline" onClick={() => setEditingRoleId(null)}>Cancel</Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </EntityCard>

            <EntityCard title="Recent Risk Predictions" emptyText="No risk predictions available.">
              {filteredPredictions.slice(0, 12).map((p) => (
                <EntityRow
                  key={p.id}
                  title={`${p.driver_name || "Unknown Driver"} · ${(p.risk_score * 100).toFixed(0)}% ${String(p.risk_class || "").toUpperCase()}`}
                  subtitle={`${p.model_version} · confidence ${(p.confidence_level * 100).toFixed(0)}%`}
                />
              ))}
            </EntityCard>

            <EntityCard title="Sanction Review Logs" emptyText="No sanction review logs yet.">
              {filteredReviews.map((r) => (
                <div key={r.id} className="p-3 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="text-sm font-medium text-slate-900">{(r.decision || "pending").toUpperCase()} · {r.officer_name || "Unassigned reviewer"}</div>
                      <div className="text-xs text-slate-500 mt-0.5">{r.station_name || "No station"}{r.notes ? ` · ${r.notes}` : ""}</div>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => startEditReview(r)}>Edit</Button>
                  </div>
                  {editingReviewId === r.id && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid gap-2">
                      <Select value={reviewEditForm.officer_assignment_id} onValueChange={(val) => setReviewEditForm((v) => ({ ...v, officer_assignment_id: val }))}>
                        <SelectTrigger><SelectValue placeholder="Officer assignment" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__none__">Unassigned</SelectItem>
                          {ops.assignments.map((a) => (
                            <SelectItem key={a.id} value={a.id}>{(a.officer_name || "Unknown")} · {(a.station_name || "No Station")}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <Select value={reviewEditForm.decision} onValueChange={(val) => setReviewEditForm((v) => ({ ...v, decision: val }))}>
                        <SelectTrigger><SelectValue placeholder="Decision" /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="__pending__">Pending</SelectItem>
                          <SelectItem value="upheld">Upheld</SelectItem>
                          <SelectItem value="amended">Amended</SelectItem>
                          <SelectItem value="reversed">Reversed</SelectItem>
                        </SelectContent>
                      </Select>
                      <Input value={reviewEditForm.notes} onChange={(e) => setReviewEditForm((v) => ({ ...v, notes: e.target.value }))} placeholder="Review notes" />
                      <Input type="date" value={reviewEditForm.reviewed_at} onChange={(e) => setReviewEditForm((v) => ({ ...v, reviewed_at: e.target.value }))} />
                      <div className="flex gap-2">
                        <Button size="sm" disabled={savingOps} onClick={saveReviewEdit}>Save</Button>
                        <Button size="sm" variant="outline" onClick={() => setEditingReviewId(null)}>Cancel</Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </EntityCard>
          </div>
        </div>
      )}

      {active === "reports" && (
        <div className="space-y-4">
          <Button onClick={generateReport}>Generate Compliance Report</Button>
          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ViolationsTrendChart points={analyticsMonthlyTrend} />
            <RiskHistogramChart distribution={analyticsRiskDistribution} />
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <div className="text-sm font-semibold text-slate-900">Driver Risk Leaderboard</div>
                <div className="text-xs text-slate-500 mt-1">Search and filter drivers by risk class and minimum score threshold.</div>
              </div>
              <div className="text-xs text-slate-500">{leaderboardRows.length} drivers matched</div>
            </div>

            <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-[1fr_170px_150px_auto_auto]">
              <Input
                value={leaderboardSearchInput}
                onChange={(e) => setLeaderboardSearchInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    applyLeaderboardSearch();
                  }
                }}
                placeholder="Search by name, license, or status"
              />
              <Select value={leaderboardRiskClass} onValueChange={setLeaderboardRiskClass}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All classes</SelectItem>
                  <SelectItem value="high">High</SelectItem>
                  <SelectItem value="moderate">Moderate</SelectItem>
                  <SelectItem value="low">Low</SelectItem>
                  <SelectItem value="unscored">Unscored</SelectItem>
                </SelectContent>
              </Select>
              <Input
                type="number"
                inputMode="numeric"
                min="0"
                max="100"
                value={leaderboardMinScore}
                onChange={(e) => setLeaderboardMinScore(e.target.value)}
                placeholder="Min risk %"
              />
              <Button size="sm" onClick={applyLeaderboardSearch}>Search</Button>
              <Button size="sm" variant="outline" onClick={clearLeaderboardFilters}>Clear</Button>
            </div>

            <div className="mt-4 overflow-x-auto rounded-lg border border-slate-200">
              <table className="w-full min-w-[720px] text-sm">
                <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                  <tr>
                    <th className="px-3 py-2.5 text-left">Rank</th>
                    <th className="px-3 py-2.5 text-left">Driver</th>
                    <th className="px-3 py-2.5 text-left">License</th>
                    <th className="px-3 py-2.5 text-right">Risk Score</th>
                    <th className="px-3 py-2.5 text-left">Risk Class</th>
                    <th className="px-3 py-2.5 text-right">Demerit Points</th>
                    <th className="px-3 py-2.5 text-left">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {leaderboardRows.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-3 py-6 text-center text-slate-500">No drivers matched the leaderboard filters.</td>
                    </tr>
                  ) : (
                    leaderboardRows.map((row, index) => (
                      <tr key={row.id} className="hover:bg-slate-50">
                        <td className="px-3 py-2.5 font-semibold text-slate-700">#{index + 1}</td>
                        <td className="px-3 py-2.5 text-slate-900">{row.full_name}</td>
                        <td className="px-3 py-2.5 text-slate-600">{row.license_number}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900">{row.risk_score === null ? "--" : `${(row.risk_score * 100).toFixed(1)}%`}</td>
                        <td className="px-3 py-2.5">
                          <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${riskPillClass(row.risk_classification)}`}>
                            {String(row.risk_classification || "unscored").toUpperCase()}
                          </span>
                        </td>
                        <td className="px-3 py-2.5 text-right font-semibold text-red-600">{row.demerit_balance}</td>
                        <td className="px-3 py-2.5 text-slate-700 uppercase text-xs">{row.status || "unknown"}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {report && (
            <>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Mini label="Total Drivers" value={report.total_drivers} />
                <Mini label="Total Violations" value={report.total_violations} />
                <Mini label="Active Sanctions" value={report.active_sanctions} />
                <Mini label="High-Risk Drivers" value={report.high_risk_drivers} />
                <Mini label="Pending Appeals" value={report.pending_appeals} />
                <Mini label="Avg Demerit Balance" value={report.avg_demerit_balance?.toFixed(1)} />
                <Mini label="Recidivism Rate" value={`${(report.recidivism_rate * 100).toFixed(0)}%`} />
                <Mini label="Compliance Rate" value={`${(report.compliance_rate * 100).toFixed(0)}%`} />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Mini label="Predictions Logged" value={report.prediction_quality?.total_predictions ?? 0} />
                <Mini label="Avg Model Confidence" value={`${((report.prediction_quality?.avg_confidence ?? 0) * 100).toFixed(1)}%`} />
                <Mini label="Avg Inference Latency" value={`${(report.prediction_quality?.avg_inference_latency_ms ?? 0).toFixed(1)}ms`} />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <EntityCard title="Violations by Offence" emptyText="No violation data.">
                  {(report.violations_by_offence || []).map((row) => (
                    <EntityRow
                      key={row.offence_type}
                      title={`${offenceLabel(row.offence_type)}: ${row.total}`}
                      subtitle="Recorded offences"
                    />
                  ))}
                </EntityCard>

                <EntityCard title="Risk Distribution" emptyText="No risk predictions.">
                  {(report.risk_distribution || []).map((row) => (
                    <EntityRow
                      key={row.risk_class}
                      title={`${String(row.risk_class || "unknown").toUpperCase()}: ${row.total}`}
                      subtitle="Prediction count"
                    />
                  ))}
                </EntityCard>

                <EntityCard title="Appeals by Status" emptyText="No appeals yet.">
                  {(report.appeals_by_status || []).map((row) => (
                    <EntityRow
                      key={row.status}
                      title={`${String(row.status || "unknown").toUpperCase()}: ${row.total}`}
                      subtitle="Appeal volume"
                    />
                  ))}
                </EntityCard>

                <EntityCard title="Sanctions by Status" emptyText="No sanctions yet.">
                  {(report.sanctions_by_status || []).map((row) => (
                    <EntityRow
                      key={row.status}
                      title={`${String(row.status || "unknown").toUpperCase()}: ${row.total}`}
                      subtitle="Sanction volume"
                    />
                  ))}
                </EntityCard>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Mini label="Avg Appeal Resolution" value={`${(report.appeal_resolution?.avg_resolution_hours ?? 0).toFixed(1)}h`} />
                <Mini label="Max Appeal Resolution" value={`${report.appeal_resolution?.max_resolution_hours ?? 0}h`} />
                <Mini label="Unread Notifications" value={report.notifications?.unread ?? 0} />
              </div>

              <EntityCard title="Monthly Violations (Last 6 Months)" emptyText="No monthly trend data.">
                {(report.monthly_violations || []).map((row) => (
                  <EntityRow
                    key={row.month}
                    title={`${row.month}: ${row.total}`}
                    subtitle="Violations captured"
                  />
                ))}
              </EntityCard>
            </>
          )}
        </div>
      )}

      {active === "audit" && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Mini label="Visible Events" value={auditSummary.total} />
            <Mini label="Automated Decisions" value={auditSummary.automated} />
            <Mini label="Human Review Actions" value={auditSummary.human} />
          </div>

          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <div className="text-sm font-semibold text-slate-900">Audit Trail Viewer</div>
                <div className="mt-1 text-xs text-slate-500">Searchable timeline of automated decisions and human review actions.</div>
              </div>
            </div>

            <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-[1fr_180px_180px_auto_auto]">
              <Input
                value={auditSearchInput}
                onChange={(e) => setAuditSearchInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    applyAuditSearch();
                  }
                }}
                placeholder="Search event, actor, or summary"
              />
              <Select value={auditTypeFilter} onValueChange={setAuditTypeFilter}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All event types</SelectItem>
                  <SelectItem value="automated">Automated decisions</SelectItem>
                  <SelectItem value="human">Human review actions</SelectItem>
                </SelectContent>
              </Select>
              <Select value={auditEntityFilter} onValueChange={setAuditEntityFilter}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All entities</SelectItem>
                  <SelectItem value="risk_prediction">Risk Predictions</SelectItem>
                  <SelectItem value="sanction">Sanctions</SelectItem>
                  <SelectItem value="review_log">Review Logs</SelectItem>
                  <SelectItem value="appeal">Appeals</SelectItem>
                  <SelectItem value="notification">Notifications</SelectItem>
                </SelectContent>
              </Select>
              <Button size="sm" onClick={applyAuditSearch}>Search</Button>
              <Button size="sm" variant="outline" onClick={clearAuditFilters}>Clear</Button>
            </div>

            <div className="mt-4 space-y-2 max-h-[34rem] overflow-y-auto pr-1">
              {filteredAuditEntries.length === 0 ? (
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                  No audit events matched your filters.
                </div>
              ) : (
                filteredAuditEntries.map((entry) => (
                  <div key={entry.id} className="rounded-lg border border-slate-200 bg-white p-3">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <div className="text-sm font-semibold text-slate-900">{entry.title}</div>
                        <div className="mt-1 text-xs text-slate-500">{new Date(entry.timestamp).toLocaleString()}</div>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${auditTypePillClass(entry.type)}`}>
                          {entry.type === "automated" ? "AUTOMATED" : "HUMAN"}
                        </span>
                        <span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 uppercase">
                          {entry.entity.replace("_", " ")}
                        </span>
                      </div>
                    </div>
                    <div className="mt-2 text-xs text-slate-600">Actor: <span className="font-semibold text-slate-800">{entry.actor}</span></div>
                    <div className="mt-1 text-sm text-slate-700">{entry.summary}</div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}

      {/* Live system status strip: DB, row counts, ML service probe. */}
      <div className="mt-6 rounded-xl border border-slate-200 bg-white px-4 py-3">
        {statusError ? (
          <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
            <span className="text-red-600">System status unavailable: {statusError}</span>
            <Button size="sm" variant="outline" onClick={loadSystemStatus}>Retry</Button>
          </div>
        ) : !systemStatus ? (
          <div className="flex items-center gap-2 text-xs text-slate-500">
            <span className="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-cyan-500" />
            Loading system status…
          </div>
        ) : (
          <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600">
            <span>
              Database: <span className="font-semibold text-slate-900">{systemStatus.database?.name || "unknown"}</span>
              <span className={`ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold ${systemStatus.database?.status === "connected" ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-600"}`}>
                {(systemStatus.database?.status || "unknown").toUpperCase()}
              </span>
            </span>
            <span className="hidden sm:inline text-slate-300">|</span>
            <span>
              ML service:{" "}
              <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${systemStatus.ml?.status === "up" ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-600"}`}>
                {(systemStatus.ml?.status || "down").toUpperCase()}
              </span>
              {systemStatus.ml?.status === "up" && systemStatus.ml?.latency_ms != null && (
                <span className="ml-1 text-slate-500">({systemStatus.ml.latency_ms}ms)</span>
              )}
              {systemStatus.ml?.status !== "up" && <span className="ml-1 text-slate-500">— dashboards fall back to MySQL data</span>}
            </span>
            <span className="hidden md:inline text-slate-300">|</span>
            {["users", "drivers", "vehicles", "violations", "payments", "appeals"].map((key) => (
              <span key={key}>
                {key.replace("_", " ")}: <span className="font-semibold text-slate-900">{systemStatus.database?.counts?.[key] ?? "—"}</span>
              </span>
            ))}
            <button type="button" onClick={loadSystemStatus} className="ml-auto text-[11px] font-medium text-cyan-600 hover:underline" aria-label="Refresh system status">
              Refresh
            </button>
          </div>
        )}
      </div>

      <ToastStack />
    </AppLayout>
  );
}

function Mini({ label, value }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 text-center">
      <div className="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">{label}</div>
      <div className="text-xl font-bold text-slate-900 mt-1">{value}</div>
    </div>
  );
}

function EntityCard({ title, emptyText, children }) {
  const items = React.Children.toArray(children).filter(Boolean);

  return (
    <div className="rounded-xl border border-slate-200 bg-white">
      <div className="px-4 py-3 border-b border-slate-100 font-semibold text-slate-900">{title}</div>
      <div className="max-h-72 overflow-y-auto divide-y divide-slate-100">
        {items.length === 0 ? (
          <div className="p-4 text-sm text-slate-500">{emptyText}</div>
        ) : (
          items
        )}
      </div>
    </div>
  );
}

function EntityRow({ title, subtitle }) {
  return (
    <div className="p-3">
      <div className="text-sm font-medium text-slate-900">{title}</div>
      <div className="text-xs text-slate-500 mt-0.5">{subtitle}</div>
    </div>
  );
}

function riskPillClass(riskClass) {
  if (riskClass === "high") return "bg-red-100 text-red-700";
  if (riskClass === "moderate") return "bg-amber-100 text-amber-700";
  if (riskClass === "low") return "bg-emerald-100 text-emerald-700";
  return "bg-slate-100 text-slate-600";
}

function ViolationsTrendChart({ points = [] }) {
  return (
    <ChartCard
      title="Violations Over Time"
      subtitle="Trend chart for monthly captured violations."
      isEmpty={points.length === 0}
    >
      <BarChart
        data={points.map((p) => ({ month: p.label, total: Number(p.total) || 0 }))}
        xKey="month"
        series={[{ key: "total", label: "Violations", color: "#06b6d4" }]}
        height={200}
        ariaLabel="Monthly captured violations bar chart"
      />
    </ChartCard>
  );
}

function RiskHistogramChart({ distribution = [] }) {
  const tones = { low: "#10b981", moderate: "#f59e0b", high: "#ef4444" };

  return (
    <ChartCard
      title="Risk Distribution"
      subtitle="Driver counts by low, moderate, and high risk classes."
      isEmpty={distribution.length === 0}
    >
      <BarChart
        horizontal
        data={distribution.map((item) => ({
          risk: String(item.key || "unknown").toUpperCase(),
          total: Number(item.total) || 0,
          color: tones[String(item.key || "").toLowerCase()] || "#94a3b8",
        }))}
        xKey="risk"
        series={[{ key: "total", label: "Drivers" }]}
        ariaLabel="Driver risk classification distribution bar chart"
      />
    </ChartCard>
  );
}

function auditTypePillClass(type) {
  return type === "automated"
    ? "bg-cyan-100 text-cyan-700"
    : "bg-orange-100 text-orange-700";
}
