import React, { useState, useEffect, useCallback, useMemo } from "react";
import { appClient } from "@/api/appClient";
import AppLayout from "@/components/AppLayout";
import DriverList from "@/components/DriverList";
import SanctionCard from "@/components/SanctionCard";
import LedgerTable from "@/components/LedgerTable";
import RiskBadge from "@/components/RiskBadge";
import LiveDemeritGauge from "@/components/LiveDemeritGauge";
import RiskExplanationPanel from "@/components/RiskExplanationPanel";
import NotificationCenter from "@/components/NotificationCenter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { offenceLabel } from "@/lib/constants";

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
  const [stationForm, setStationForm] = useState({ station_name: "", region: "", physical_address: "", contact_phone: "" });
  const [assignmentForm, setAssignmentForm] = useState({ officer_user_id: "", station_id: "", badge_number: "", shift_type: "day", assignment_start_date: "", assignment_end_date: "" });
  const [vehicleForm, setVehicleForm] = useState({ owner_user_id: "", plate_number: "", make_model: "", vehicle_class: "", colour: "", year_of_manufacture: "", registration_expiry: "" });
  const [editingStationId, setEditingStationId] = useState(null);
  const [editingAssignmentId, setEditingAssignmentId] = useState(null);
  const [editingVehicleId, setEditingVehicleId] = useState(null);
  const [editingRoleId, setEditingRoleId] = useState(null);
  const [editingReviewId, setEditingReviewId] = useState(null);
  const [predictingRisk, setPredictingRisk] = useState(false);
  const [predictionFeedback, setPredictionFeedback] = useState("");
  const [latestPrediction, setLatestPrediction] = useState(null);
  const [predictionDriverId, setPredictionDriverId] = useState("");
  const [lastRefreshedAt, setLastRefreshedAt] = useState(null);
  const [stationEditForm, setStationEditForm] = useState({ station_name: "", region: "", physical_address: "", contact_phone: "" });
  const [assignmentEditForm, setAssignmentEditForm] = useState({ officer_user_id: "", station_id: "", badge_number: "", shift_type: "day", assignment_start_date: "", assignment_end_date: "" });
  const [vehicleEditForm, setVehicleEditForm] = useState({ owner_user_id: "", plate_number: "", make_model: "", vehicle_class: "", colour: "", year_of_manufacture: "", registration_expiry: "" });
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

  const loadAll = useCallback(async () => {
    const [d, v, s, a, n, p, users, roles, stations, assignments, vehicles, predictions, reviews] = await Promise.all([
      appClient.entities.Driver.list("-demerit_balance", 200),
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
    const sc = await appClient.entities.RecidivismScore.list("-timestamp", 100);
    const map = {};
    sc.forEach((r) => { if (!map[r.driver_id]) map[r.driver_id] = r; });
    setScores(map);
    setLastRefreshedAt(new Date());
  }, []);

  useEffect(() => { loadAll(); }, [loadAll]);

  useEffect(() => {
    const timer = window.setInterval(() => {
      loadAll();
    }, 10000);

    return () => window.clearInterval(timer);
  }, [loadAll]);

  useEffect(() => {
    if (!selected?.id) return;

    const fresh = drivers.find((driver) => driver.id === selected.id);
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
    await appClient.entities.Sanction.update(s.id, { status: "lifted", lifted_by: user.email, lifted_at: new Date().toISOString() });
    await appClient.entities.Driver.update(s.driver_id, { status: "active", locked_reason: "", locked_at: null });
    loadAll();
  };

  const reviewAppeal = async (a, decision) => {
    await appClient.entities.Appeal.update(a.id, {
      status: decision, reviewed_by: user.email, reviewed_at: new Date().toISOString(),
      review_notes: decision === "approved" ? "Appeal upheld on review." : "Appeal denied.",
    });
    if (decision === "approved") {
      await appClient.entities.Violation.update(a.violation_id, { status: "disputed" });
    }
    loadAll();
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

  const filteredViolations = useMemo(() => {
    return violations.filter((v) => includesSearch([v.driver_name, offenceLabel(v.offence_type), v.zone_type, v.weather, v.location]));
  }, [violations, searchTerm]);

  const filteredSanctions = useMemo(() => {
    return sanctions.filter((s) => includesSearch([s.driver_name, s.status, s.risk_classification, s.explanation]));
  }, [sanctions, searchTerm]);

  const filteredAppeals = useMemo(() => {
    return appeals.filter((a) => includesSearch([a.driver_name, a.status, a.reason, a.review_notes]));
  }, [appeals, searchTerm]);

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

  const filteredStations = useMemo(() => {
    return ops.stations.filter((s) => includesSearch([s.station_name, s.region, s.physical_address, s.contact_phone]));
  }, [ops.stations, searchTerm]);

  const filteredAssignments = useMemo(() => {
    return ops.assignments.filter((a) => includesSearch([a.officer_name, a.station_name, a.badge_number, a.shift_type]));
  }, [ops.assignments, searchTerm]);

  const filteredVehicles = useMemo(() => {
    return ops.vehicles.filter((v) => includesSearch([v.plate_number, v.make_model, v.vehicle_class, v.owner_name]));
  }, [ops.vehicles, searchTerm]);

  const filteredPredictions = useMemo(() => {
    return ops.predictions.filter((p) => includesSearch([p.driver_name, p.risk_class, p.model_version]));
  }, [ops.predictions, searchTerm]);

  const filteredReviews = useMemo(() => {
    return ops.reviews.filter((r) => includesSearch([r.officer_name, r.station_name, r.decision, r.notes]));
  }, [ops.reviews, searchTerm]);

  const analyticsMonthlyTrend = useMemo(() => {
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
  }, [report, violations]);

  const analyticsRiskDistribution = useMemo(() => {
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
  }, [report, scores]);

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

  const createStation = async () => {
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.Station.create(stationForm);
      setStationForm({ station_name: "", region: "", physical_address: "", contact_phone: "" });
      setOpsMessage("Station created.");
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to create station.");
    } finally {
      setSavingOps(false);
    }
  };

  const createAssignment = async () => {
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.OfficerAssignment.create({
        ...assignmentForm,
        officer_user_id: Number(assignmentForm.officer_user_id),
        station_id: Number(assignmentForm.station_id),
        assignment_end_date: assignmentForm.assignment_end_date || null,
      });
      setAssignmentForm({ officer_user_id: "", station_id: "", badge_number: "", shift_type: "day", assignment_start_date: "", assignment_end_date: "" });
      setOpsMessage("Officer assignment created.");
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to create assignment.");
    } finally {
      setSavingOps(false);
    }
  };

  const createVehicle = async () => {
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.Vehicle.create({
        ...vehicleForm,
        owner_user_id: Number(vehicleForm.owner_user_id),
        year_of_manufacture: vehicleForm.year_of_manufacture ? Number(vehicleForm.year_of_manufacture) : null,
        registration_expiry: vehicleForm.registration_expiry || null,
      });
      setVehicleForm({ owner_user_id: "", plate_number: "", make_model: "", vehicle_class: "", colour: "", year_of_manufacture: "", registration_expiry: "" });
      setOpsMessage("Vehicle created.");
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to create vehicle.");
    } finally {
      setSavingOps(false);
    }
  };

  const startEditStation = (station) => {
    setEditingStationId(station.id);
    setStationEditForm({
      station_name: station.station_name || "",
      region: station.region || "",
      physical_address: station.physical_address || "",
      contact_phone: station.contact_phone || "",
    });
  };

  const saveStationEdit = async () => {
    if (!editingStationId) return;
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.Station.update(editingStationId, stationEditForm);
      setOpsMessage("Station updated.");
      setEditingStationId(null);
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to update station.");
    } finally {
      setSavingOps(false);
    }
  };

  const startEditAssignment = (assignment) => {
    setEditingAssignmentId(assignment.id);
    setAssignmentEditForm({
      officer_user_id: assignment.officer_user_id || "",
      station_id: assignment.station_id || "",
      badge_number: assignment.badge_number || "",
      shift_type: assignment.shift_type || "day",
      assignment_start_date: assignment.assignment_start_date || "",
      assignment_end_date: assignment.assignment_end_date || "",
    });
  };

  const saveAssignmentEdit = async () => {
    if (!editingAssignmentId) return;
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.OfficerAssignment.update(editingAssignmentId, {
        ...assignmentEditForm,
        officer_user_id: Number(assignmentEditForm.officer_user_id),
        station_id: Number(assignmentEditForm.station_id),
        assignment_end_date: assignmentEditForm.assignment_end_date || null,
      });
      setOpsMessage("Officer assignment updated.");
      setEditingAssignmentId(null);
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to update assignment.");
    } finally {
      setSavingOps(false);
    }
  };

  const startEditVehicle = (vehicle) => {
    setEditingVehicleId(vehicle.id);
    setVehicleEditForm({
      owner_user_id: vehicle.owner_user_id || "",
      plate_number: vehicle.plate_number || "",
      make_model: vehicle.make_model || "",
      vehicle_class: vehicle.vehicle_class || "",
      colour: vehicle.colour || "",
      year_of_manufacture: vehicle.year_of_manufacture || "",
      registration_expiry: vehicle.registration_expiry || "",
    });
  };

  const saveVehicleEdit = async () => {
    if (!editingVehicleId) return;
    setSavingOps(true); setOpsMessage("");
    try {
      await appClient.entities.Vehicle.update(editingVehicleId, {
        ...vehicleEditForm,
        owner_user_id: Number(vehicleEditForm.owner_user_id),
        year_of_manufacture: vehicleEditForm.year_of_manufacture ? Number(vehicleEditForm.year_of_manufacture) : null,
        registration_expiry: vehicleEditForm.registration_expiry || null,
      });
      setOpsMessage("Vehicle updated.");
      setEditingVehicleId(null);
      await loadAll();
    } catch (err) {
      setOpsMessage(err.message || "Failed to update vehicle.");
    } finally {
      setSavingOps(false);
    }
  };

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
      </header>

      {active === "drivers" && (
        <div className="space-y-4">
          <div className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <div className="text-sm font-semibold text-slate-900">Live Demerit Watch</div>
                <div className="mt-1 text-xs text-slate-500">
                  Auto-refreshes every 10 seconds with color-coded driver risk gauges.
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

          <div className="grid grid-cols-2 gap-6">
            <DriverList drivers={filteredDrivers} scores={scores} onSelect={selectDriver} selectedId={selected?.id} />
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

      {active === "violations" && (
        <div className="rounded-xl border border-slate-200 bg-white overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
              <tr>
                <th className="text-left px-4 py-2.5">Driver</th><th className="text-left px-4 py-2.5">Offence</th>
                <th className="text-left px-4 py-2.5">Zone</th><th className="text-left px-4 py-2.5">Weather</th>
                <th className="text-right px-4 py-2.5">Points</th><th className="text-left px-4 py-2.5">Date</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredViolations.map((v) => (
                <tr key={v.id} className="hover:bg-slate-50">
                  <td className="px-4 py-2.5">{v.driver_name}</td>
                  <td className="px-4 py-2.5">{offenceLabel(v.offence_type)}</td>
                  <td className="px-4 py-2.5 capitalize">{v.zone_type}</td>
                  <td className="px-4 py-2.5 capitalize">{v.weather}</td>
                  <td className="px-4 py-2.5 text-right font-semibold text-red-600">+{v.demerit_points}</td>
                  <td className="px-4 py-2.5 text-slate-500">{new Date(v.timestamp).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {active === "sanctions" && (
        <div className="space-y-3">
          {filteredSanctions.length === 0 ? <div className="text-sm text-slate-500 py-8 text-center">No sanctions issued.</div> :
            filteredSanctions.map((s) => <SanctionCard key={s.id} sanction={s} canLift onLift={liftSanction} />)}
        </div>
      )}

      {active === "payments" && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Mini label="Total Payments" value={payments.length} />
            <Mini label="Completed" value={payments.filter((p) => p.status === "completed").length} />
            <Mini label="Pending" value={payments.filter((p) => p.status !== "completed").length} />
          </div>
          <div className="rounded-xl border border-slate-200 bg-white overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                  <th className="text-left px-4 py-2.5">Driver</th>
                  <th className="text-left px-4 py-2.5">Violation</th>
                  <th className="text-right px-4 py-2.5">Amount</th>
                  <th className="text-left px-4 py-2.5">Ref</th>
                  <th className="text-left px-4 py-2.5">Status</th>
                  <th className="text-left px-4 py-2.5">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {payments.map((payment) => (
                  <tr key={payment.id} className="hover:bg-slate-50">
                    <td className="px-4 py-2.5">{payment.driver_name || "Unknown driver"}</td>
                    <td className="px-4 py-2.5">{payment.violation_type || "—"}</td>
                    <td className="px-4 py-2.5 text-right font-semibold text-slate-900">KES {Number(payment.amount || 0).toLocaleString()}</td>
                    <td className="px-4 py-2.5">{payment.reference_number || payment.transaction_ref || "—"}</td>
                    <td className="px-4 py-2.5">
                      <span className={`text-xs font-semibold px-2 py-1 rounded-full ${payment.status === "completed" ? "bg-emerald-100 text-emerald-700" : "bg-amber-100 text-amber-700"}`}>
                        {payment.status}
                      </span>
                    </td>
                    <td className="px-4 py-2.5 text-slate-500">{payment.paid_at ? new Date(payment.paid_at).toLocaleString() : "Pending"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
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
        <div className="space-y-3">
          {filteredAppeals.length === 0 ? <div className="text-sm text-slate-500 py-8 text-center">No appeals submitted.</div> :
            filteredAppeals.map((a) => (
              <div key={a.id} className="rounded-xl border border-slate-200 bg-white p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <div className="font-semibold text-slate-900">{a.driver_name} — {offenceLabel(a.offence_type)}</div>
                    <div className="text-xs text-slate-500">Submitted {new Date(a.submitted_at).toLocaleString()}</div>
                    <div className="text-sm text-slate-600 mt-2">{a.reason}</div>
                  </div>
                  <span className={`text-xs font-semibold px-2 py-1 rounded-full ${a.status === "pending" ? "bg-amber-100 text-amber-700" : a.status === "approved" ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"}`}>
                    {a.status.toUpperCase()}
                  </span>
                </div>
                {a.status === "pending" && (
                  <div className="flex gap-2 mt-3">
                    <Button size="sm" onClick={() => reviewAppeal(a, "approved")}>Approve</Button>
                    <Button size="sm" variant="outline" onClick={() => reviewAppeal(a, "rejected")}>Reject</Button>
                  </div>
                )}
                {a.review_notes && <div className="text-xs text-slate-500 mt-2">Review: {a.review_notes}</div>}
              </div>
            ))}
        </div>
      )}

      {active === "operations" && (
        <div className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
              <div className="font-semibold text-slate-900">Add Station</div>
              <Input value={stationForm.station_name} onChange={(e) => setStationForm((v) => ({ ...v, station_name: e.target.value }))} placeholder="Station name" />
              <Input value={stationForm.region} onChange={(e) => setStationForm((v) => ({ ...v, region: e.target.value }))} placeholder="Region" />
              <Input value={stationForm.physical_address} onChange={(e) => setStationForm((v) => ({ ...v, physical_address: e.target.value }))} placeholder="Physical address" />
              <Input value={stationForm.contact_phone} onChange={(e) => setStationForm((v) => ({ ...v, contact_phone: e.target.value }))} placeholder="Contact phone" />
              <Button size="sm" disabled={savingOps} onClick={createStation}>Save Station</Button>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
              <div className="font-semibold text-slate-900">Add Assignment</div>
              <Select value={assignmentForm.officer_user_id} onValueChange={(val) => setAssignmentForm((v) => ({ ...v, officer_user_id: val }))}>
                <SelectTrigger><SelectValue placeholder="Officer user" /></SelectTrigger>
                <SelectContent>{ops.users.filter((u) => u.role === "officer" || u.role === "admin").map((u) => <SelectItem key={u.id} value={u.id}>{u.name} ({u.role})</SelectItem>)}</SelectContent>
              </Select>
              <Select value={assignmentForm.station_id} onValueChange={(val) => setAssignmentForm((v) => ({ ...v, station_id: val }))}>
                <SelectTrigger><SelectValue placeholder="Station" /></SelectTrigger>
                <SelectContent>{ops.stations.map((s) => <SelectItem key={s.id} value={s.id}>{s.station_name}</SelectItem>)}</SelectContent>
              </Select>
              <Input value={assignmentForm.badge_number} onChange={(e) => setAssignmentForm((v) => ({ ...v, badge_number: e.target.value }))} placeholder="Badge number" />
              <Select value={assignmentForm.shift_type} onValueChange={(val) => setAssignmentForm((v) => ({ ...v, shift_type: val }))}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="day">Day</SelectItem>
                  <SelectItem value="night">Night</SelectItem>
                  <SelectItem value="swing">Swing</SelectItem>
                </SelectContent>
              </Select>
              <Input type="date" value={assignmentForm.assignment_start_date} onChange={(e) => setAssignmentForm((v) => ({ ...v, assignment_start_date: e.target.value }))} />
              <Input type="date" value={assignmentForm.assignment_end_date} onChange={(e) => setAssignmentForm((v) => ({ ...v, assignment_end_date: e.target.value }))} />
              <Button size="sm" disabled={savingOps} onClick={createAssignment}>Save Assignment</Button>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2">
              <div className="font-semibold text-slate-900">Add Vehicle</div>
              <Select value={vehicleForm.owner_user_id} onValueChange={(val) => setVehicleForm((v) => ({ ...v, owner_user_id: val }))}>
                <SelectTrigger><SelectValue placeholder="Owner user" /></SelectTrigger>
                <SelectContent>{ops.users.map((u) => <SelectItem key={u.id} value={u.id}>{u.name}</SelectItem>)}</SelectContent>
              </Select>
              <Input value={vehicleForm.plate_number} onChange={(e) => setVehicleForm((v) => ({ ...v, plate_number: e.target.value }))} placeholder="Plate number" />
              <Input value={vehicleForm.make_model} onChange={(e) => setVehicleForm((v) => ({ ...v, make_model: e.target.value }))} placeholder="Make / model" />
              <Input value={vehicleForm.vehicle_class} onChange={(e) => setVehicleForm((v) => ({ ...v, vehicle_class: e.target.value }))} placeholder="Vehicle class" />
              <Input value={vehicleForm.colour} onChange={(e) => setVehicleForm((v) => ({ ...v, colour: e.target.value }))} placeholder="Colour" />
              <Input type="number" value={vehicleForm.year_of_manufacture} onChange={(e) => setVehicleForm((v) => ({ ...v, year_of_manufacture: e.target.value }))} placeholder="Year of manufacture" />
              <Input type="date" value={vehicleForm.registration_expiry} onChange={(e) => setVehicleForm((v) => ({ ...v, registration_expiry: e.target.value }))} />
              <Button size="sm" disabled={savingOps} onClick={createVehicle}>Save Vehicle</Button>
            </div>
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

            <EntityCard title="Stations" emptyText="No stations added.">
              {filteredStations.map((s) => (
                <div key={s.id} className="p-3 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="text-sm font-medium text-slate-900">{s.station_name}</div>
                      <div className="text-xs text-slate-500 mt-0.5">{s.region} · {s.physical_address}</div>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => startEditStation(s)}>Edit</Button>
                  </div>
                  {editingStationId === s.id && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid gap-2">
                      <Input value={stationEditForm.station_name} onChange={(e) => setStationEditForm((v) => ({ ...v, station_name: e.target.value }))} placeholder="Station name" />
                      <Input value={stationEditForm.region} onChange={(e) => setStationEditForm((v) => ({ ...v, region: e.target.value }))} placeholder="Region" />
                      <Input value={stationEditForm.physical_address} onChange={(e) => setStationEditForm((v) => ({ ...v, physical_address: e.target.value }))} placeholder="Physical address" />
                      <Input value={stationEditForm.contact_phone} onChange={(e) => setStationEditForm((v) => ({ ...v, contact_phone: e.target.value }))} placeholder="Contact phone" />
                      <div className="flex gap-2">
                        <Button size="sm" disabled={savingOps} onClick={saveStationEdit}>Save</Button>
                        <Button size="sm" variant="outline" onClick={() => setEditingStationId(null)}>Cancel</Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </EntityCard>

            <EntityCard title="Officer Assignments" emptyText="No officer assignments found.">
              {filteredAssignments.map((a) => (
                <div key={a.id} className="p-3 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="text-sm font-medium text-slate-900">{a.officer_name || "Unknown Officer"} ({a.badge_number})</div>
                      <div className="text-xs text-slate-500 mt-0.5">{a.station_name || "No Station"} · {a.shift_type} · {a.assignment_start_date || ""}</div>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => startEditAssignment(a)}>Edit</Button>
                  </div>
                  {editingAssignmentId === a.id && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid gap-2">
                      <Select value={assignmentEditForm.officer_user_id} onValueChange={(val) => setAssignmentEditForm((v) => ({ ...v, officer_user_id: val }))}>
                        <SelectTrigger><SelectValue placeholder="Officer user" /></SelectTrigger>
                        <SelectContent>{ops.users.filter((u) => u.role === "officer" || u.role === "admin").map((u) => <SelectItem key={u.id} value={u.id}>{u.name} ({u.role})</SelectItem>)}</SelectContent>
                      </Select>
                      <Select value={assignmentEditForm.station_id} onValueChange={(val) => setAssignmentEditForm((v) => ({ ...v, station_id: val }))}>
                        <SelectTrigger><SelectValue placeholder="Station" /></SelectTrigger>
                        <SelectContent>{ops.stations.map((st) => <SelectItem key={st.id} value={st.id}>{st.station_name}</SelectItem>)}</SelectContent>
                      </Select>
                      <Input value={assignmentEditForm.badge_number} onChange={(e) => setAssignmentEditForm((v) => ({ ...v, badge_number: e.target.value }))} placeholder="Badge number" />
                      <Select value={assignmentEditForm.shift_type} onValueChange={(val) => setAssignmentEditForm((v) => ({ ...v, shift_type: val }))}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="day">Day</SelectItem>
                          <SelectItem value="night">Night</SelectItem>
                          <SelectItem value="swing">Swing</SelectItem>
                        </SelectContent>
                      </Select>
                      <Input type="date" value={assignmentEditForm.assignment_start_date} onChange={(e) => setAssignmentEditForm((v) => ({ ...v, assignment_start_date: e.target.value }))} />
                      <Input type="date" value={assignmentEditForm.assignment_end_date} onChange={(e) => setAssignmentEditForm((v) => ({ ...v, assignment_end_date: e.target.value }))} />
                      <div className="flex gap-2">
                        <Button size="sm" disabled={savingOps} onClick={saveAssignmentEdit}>Save</Button>
                        <Button size="sm" variant="outline" onClick={() => setEditingAssignmentId(null)}>Cancel</Button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </EntityCard>

            <EntityCard title="Vehicles" emptyText="No registered vehicles.">
              {filteredVehicles.map((v) => (
                <div key={v.id} className="p-3 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="text-sm font-medium text-slate-900">{v.plate_number} · {v.make_model}</div>
                      <div className="text-xs text-slate-500 mt-0.5">{v.vehicle_class}{v.owner_name ? ` · ${v.owner_name}` : ""}</div>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => startEditVehicle(v)}>Edit</Button>
                  </div>
                  {editingVehicleId === v.id && (
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid gap-2">
                      <Select value={vehicleEditForm.owner_user_id} onValueChange={(val) => setVehicleEditForm((state) => ({ ...state, owner_user_id: val }))}>
                        <SelectTrigger><SelectValue placeholder="Owner user" /></SelectTrigger>
                        <SelectContent>{ops.users.map((u) => <SelectItem key={u.id} value={u.id}>{u.name}</SelectItem>)}</SelectContent>
                      </Select>
                      <Input value={vehicleEditForm.plate_number} onChange={(e) => setVehicleEditForm((state) => ({ ...state, plate_number: e.target.value }))} placeholder="Plate number" />
                      <Input value={vehicleEditForm.make_model} onChange={(e) => setVehicleEditForm((state) => ({ ...state, make_model: e.target.value }))} placeholder="Make / model" />
                      <Input value={vehicleEditForm.vehicle_class} onChange={(e) => setVehicleEditForm((state) => ({ ...state, vehicle_class: e.target.value }))} placeholder="Vehicle class" />
                      <Input value={vehicleEditForm.colour} onChange={(e) => setVehicleEditForm((state) => ({ ...state, colour: e.target.value }))} placeholder="Colour" />
                      <Input type="number" value={vehicleEditForm.year_of_manufacture} onChange={(e) => setVehicleEditForm((state) => ({ ...state, year_of_manufacture: e.target.value }))} placeholder="Year of manufacture" />
                      <Input type="date" value={vehicleEditForm.registration_expiry} onChange={(e) => setVehicleEditForm((state) => ({ ...state, registration_expiry: e.target.value }))} />
                      <div className="flex gap-2">
                        <Button size="sm" disabled={savingOps} onClick={saveVehicleEdit}>Save</Button>
                        <Button size="sm" variant="outline" onClick={() => setEditingVehicleId(null)}>Cancel</Button>
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
  const max = points.reduce((acc, item) => Math.max(acc, Number(item.total) || 0), 0) || 1;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="text-sm font-semibold text-slate-900">Violations Over Time</div>
      <div className="text-xs text-slate-500 mt-1">Trend chart for monthly captured violations.</div>
      {points.length === 0 ? (
        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">
          No trend data available yet.
        </div>
      ) : (
        <div className="mt-4 space-y-2">
          {points.map((point) => {
            const value = Number(point.total) || 0;
            const width = Math.max(4, Math.round((value / max) * 100));
            return (
              <div key={point.label}>
                <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                  <span>{point.label}</span>
                  <span className="font-semibold text-slate-900">{value}</span>
                </div>
                <div className="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                  <div className="h-full rounded-full bg-cyan-500" style={{ width: `${width}%` }} />
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

function RiskHistogramChart({ distribution = [] }) {
  const total = distribution.reduce((sum, item) => sum + (Number(item.total) || 0), 0);
  const max = distribution.reduce((acc, item) => Math.max(acc, Number(item.total) || 0), 0) || 1;
  const tones = {
    low: "bg-emerald-500",
    moderate: "bg-amber-500",
    high: "bg-red-500",
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4">
      <div className="text-sm font-semibold text-slate-900">Risk Distribution Histogram</div>
      <div className="text-xs text-slate-500 mt-1">Driver counts by low, moderate, and high risk classes.</div>
      {distribution.length === 0 ? (
        <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-500">
          No risk distribution data available yet.
        </div>
      ) : (
        <div className="mt-4 grid grid-cols-3 gap-3 items-end">
          {distribution.map((item) => {
            const key = String(item.key || "").toLowerCase();
            const value = Number(item.total) || 0;
            const height = Math.max(16, Math.round((value / max) * 120));
            return (
              <div key={key} className="text-center">
                <div className="text-xs text-slate-500 mb-2">{total > 0 ? `${((value / total) * 100).toFixed(0)}%` : "0%"}</div>
                <div className="mx-auto w-full max-w-[70px] rounded-t-md bg-slate-100 relative" style={{ height: "124px" }}>
                  <div className={`absolute bottom-0 left-0 right-0 rounded-t-md ${tones[key] || "bg-slate-400"}`} style={{ height: `${height}px` }} />
                </div>
                <div className="mt-2 text-xs font-semibold text-slate-700 uppercase">{key}</div>
                <div className="text-sm font-bold text-slate-900">{value}</div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

function auditTypePillClass(type) {
  return type === "automated"
    ? "bg-cyan-100 text-cyan-700"
    : "bg-orange-100 text-orange-700";
}