import React, { useEffect, useRef, useState } from "react";

const COLOR_STYLES = {
  low: {
    track: "bg-emerald-100",
    fill: "bg-emerald-500",
    text: "text-emerald-700",
    badge: "bg-emerald-50 border-emerald-200 text-emerald-700",
    label: "LOW RISK",
    gaugeColor: "#10b981",
  },
  moderate: {
    track: "bg-amber-100",
    fill: "bg-amber-500",
    text: "text-amber-700",
    badge: "bg-amber-50 border-amber-200 text-amber-700",
    label: "MODERATE",
    gaugeColor: "#f59e0b",
  },
  high: {
    track: "bg-red-100",
    fill: "bg-red-500",
    text: "text-red-700",
    badge: "bg-red-50 border-red-200 text-red-700",
    label: "HIGH RISK",
    gaugeColor: "#ef4444",
  },
};

const ZONES = [
  { start: 0, end: 40, color: "#10b981", label: "Low" },
  { start: 40, end: 55, color: "#f59e0b", label: "Warning" },
  { start: 55, end: 70, color: "#f97316", label: "Restricted" },
  { start: 70, end: 100, color: "#ef4444", label: "Suspended" },
];

const SIZE_CONFIG = {
  md: { width: 260, height: 160, fontSize: "text-2xl", labelSize: "text-xs" },
  lg: { width: 360, height: 200, fontSize: "text-3xl", labelSize: "text-sm" },
  xl: { width: 480, height: 260, fontSize: "text-4xl", labelSize: "text-base" },
};

function resolveRiskClass(classification, score) {
  if (classification === "high" || classification === "moderate" || classification === "low") {
    return classification;
  }
  if (typeof score === "number") {
    if (score >= 0.7) return "high";
    if (score >= 0.4) return "moderate";
    return "low";
  }
  return "low";
}

function getScoreClass(score) {
  if (score >= 0.7) return "high";
  if (score >= 0.4) return "moderate";
  return "low";
}

export default function RiskGauge({
  score = null,
  status = "idle",
  size = "lg",
  classification,
  tier,
  modelVersion,
  floorApplied,
  floorScore,
  showLegend = true,
  onReplay,
  animateOnMount = true,
}) {
  const [animatedScore, setAnimatedScore] = useState(0);
  const [isAnimating, setIsAnimating] = useState(false);
  const [previousScore, setPreviousScore] = useState(null);
  const animationRef = useRef(null);

  const riskClass = resolveRiskClass(classification, score);
  const colors = COLOR_STYLES[riskClass];
  const sizeConfig = SIZE_CONFIG[size];
  const percent = score != null ? Math.round(score * 100) : 0;
  const tierColor = tier === "suspended" ? "bg-red-50 border-red-200 text-red-700" :
    tier === "restricted" ? "bg-amber-50 border-amber-200 text-amber-700" :
    tier === "warning" ? "bg-emerald-50 border-emerald-200 text-emerald-700" :
    "bg-slate-50 border-slate-200 text-slate-700";

  // Animate score on mount or score change
  useEffect(() => {
    if (!animateOnMount || score == null) return;

    if (previousScore !== null && previousScore !== score) {
      // Re-animation: animate from previous to current
      setAnimatedScore(previousScore);
      setIsAnimating(true);
      animateScore(previousScore, score);
    } else if (previousScore === null) {
      // Initial animation from 0
      setIsAnimating(true);
      animateScore(0, score);
    }
  }, [score, animateOnMount, previousScore]);

  // Track previous score for next animation
  useEffect(() => {
    setPreviousScore(score);
  }, [score]);

  const animateScore = (from, to) => {
    const duration = 1600; // ms
    const startTime = performance.now();

    const step = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease-out cubic
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = from + (to - from) * ease;
      setAnimatedScore(current);

      if (progress < 1) {
        animationRef.current = requestAnimationFrame(step);
      } else {
        setIsAnimating(false);
        // Pulse if high risk
        if (to >= 0.7) {
          // Pulse effect handled by CSS
        }
      }
    };

    animationRef.current = requestAnimationFrame(step);
  };

  // Cleanup animation frame on unmount
  useEffect(() => {
    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
      }
    };
  }, []);

  const gaugeValue = animatedScore;
  const percentValue = Math.round(gaugeValue * 100);
  const arcSweep = (gaugeValue * 180); // 180 degrees max
  const arcSweepClamped = Math.min(180, Math.max(0, arcSweep));

  // Needle rotation: 180 degrees at score 1.0, 0 degrees at score 0
  const needleRotation = arcSweepClamped;

  const formatRiskScore = (s) => (s != null ? (s * 100).toFixed(1) : "--");

  return (
    <div className="space-y-3" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percentValue} aria-valuetext={score != null ? `Risk score ${percentValue}% risk, ${riskClass}, ${tierColor}` : "Risk score not available"}>
      <div className="flex items-center justify-between gap-3">
        <div className={`text-xs font-semibold uppercase tracking-wide ${colors.text}`}>
          {colors.label}
        </div>
        <span className={`text-[11px] font-semibold px-2 py-1 rounded-full border ${colors.badge}`}>
          {percentValue}% pts
        </span>
      </div>

      {/* SVG Gauge */}
      <div className="relative w-full aspect-[16:10]">
        <svg
          width="100%"
          height="100%"
          viewBox="0 0 200 120"
          className="w-full h-auto"
        >
          {/* Background arc */}
          <path
            d="M 30,100 A 70,70 0 0,1 170,100"
            fill="none"
            stroke={COLOR_STYLES.low.track.replace("bg-", "").replace("-100", "-200")}
            strokeWidth="14"
            strokeLinecap="round"
          />
          {/* Zone arcs */}
          {ZONES.map((zone) => {
            const startAngle = 180 - (zone.start / 100) * 180;
            const endAngle = 180 - (zone.end / 100) * 180;
            const startX = 30 + 70 * Math.cos((startAngle * Math.PI) / 180);
            const startY = 100 - 70 * Math.sin((startAngle * Math.PI) / 180);
            const endX = 30 + 70 * Math.cos((endAngle * Math.PI) / 180);
            const endY = 100 - 70 * Math.sin((endAngle * Math.PI) / 180);
            const largeArcFlag = zone.end - zone.start > 50 ? 1 : 0;

            return (
              <path
                key={zone.start}
                d={`M ${startX},${startY} A 70,70 0 ${largeArcFlag} 0 ${endX},${endY}`}
                fill="none"
                stroke={zone.color}
                strokeWidth="14"
                strokeLinecap="round"
              />
            );
          })}
          {/* Value arc */}
          <path
            d={`M 30,100 A 70,70 0 ${arcSweepClamped > 180 ? 1 : 0} 0 ${30 + 70 * Math.cos(((180 - arcSweepClamped) * Math.PI) / 180)},${100 - 70 * Math.sin(((180 - arcSweepClamped) * Math.PI) / 180)}`}
            fill="none"
            stroke={colors.gaugeColor}
            strokeWidth="14"
            strokeLinecap="round"
            strokeDasharray="440"
            strokeDashoffset={440 - (arcSweepClamped / 180) * 440}
            className="transition-all duration-1000 ease-out"
          />
          {/* Center hub */}
          <circle cx="100" cy="100" r="8" fill={colors.gaugeColor} />
        </svg>
      </div>

      {/* Readout */}
      <div className="flex items-center justify-between gap-3">
        <div className={`text-[11px] font-semibold uppercase tracking-wide ${colors.text}`}>
          {colors.label}
        </div>
        <span className={`text-[11px] font-semibold px-2 py-1 rounded-full border ${colors.badge}`}>
          {percentValue}%
        </span>
      </div>

      <div className="text-3xl font-bold text-slate-900">
        {score != null ? `${(score * 100).toFixed(1)}%` : "—"}
      </div>

      {showLegend && (
        <div className="flex items-center gap-2 text-[10px] text-slate-500">
          <span className="inline-flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-emerald-500" />
            <span>Low</span>
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-amber-500" />
            <span>Warning</span>
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-orange-500" />
            <span>Restricted</span>
          </span>
          <span className="inline-flex items-center gap-1">
            <span className="w-2 h-2 rounded-full bg-red-500" />
            <span>Suspended</span>
          </span>
        </div>
      )}

      {modelVersion && (
        <div className="text-[10px] text-slate-400 mt-1">
          Model: {modelVersion}
          {floorApplied && ` · Floor: ${(floorScore * 100).toFixed(0)}%`}
        </div>
      )}

      {onReplay && (
        <button
          type="button"
          onClick={onReplay}
          className="text-xs font-semibold px-2 py-1 rounded-full border border-cyan-200 bg-slate-50 text-cyan-700 hover:bg-cyan-100 transition-colors"
          disabled={isAnimating}
        >
          {isAnimating ? "Calculating..." : "Replay"}
        </button>
      )}
    </div>
  );
}

RiskGauge.displayName = "RiskGauge";

export { RiskGauge, COLOR_STYLES, SIZE_CONFIG, resolveRiskClass, getScoreClass };
