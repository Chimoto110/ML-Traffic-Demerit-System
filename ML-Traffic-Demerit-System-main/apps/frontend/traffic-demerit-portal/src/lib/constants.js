// Shared labels and enums for the frontend.

export const OFFENCE_TYPES = [
  { value: "speeding", label: "Speeding", basePoints: 3 },
  { value: "red_light", label: "Red Light Violation", basePoints: 4 },
  { value: "reckless_driving", label: "Reckless Driving", basePoints: 6 },
  { value: "dui", label: "Driving Under Influence", basePoints: 8 },
  { value: "no_seatbelt", label: "No Seatbelt", basePoints: 2 },
  { value: "phone_use", label: "Phone Use While Driving", basePoints: 3 },
  { value: "overloading", label: "Overloading", basePoints: 3 },
  { value: "wrong_way", label: "Wrong Way Driving", basePoints: 5 },
  { value: "expired_license", label: "Expired Licence", basePoints: 2 },
  { value: "other", label: "Other Offence", basePoints: 2 },
];

export const ZONE_TYPES = [
  { value: "urban", label: "Urban" },
  { value: "highway", label: "Highway" },
  { value: "residential", label: "Residential" },
  { value: "school_zone", label: "School Zone" },
  { value: "rural", label: "Rural" },
];

export const WEATHER_TYPES = [
  { value: "clear", label: "Clear" },
  { value: "rain", label: "Rain" },
  { value: "fog", label: "Fog" },
  { value: "storm", label: "Storm" },
];

export const offenceLabel = (v) => OFFENCE_TYPES.find((o) => o.value === v)?.label ?? v;
export const zoneLabel = (v) => ZONE_TYPES.find((o) => o.value === v)?.label ?? v;
export const weatherLabel = (v) => WEATHER_TYPES.find((o) => o.value === v)?.label ?? v;

export const RISK_THRESHOLDS = { low: 0.4, high: 0.7 };
export const SANCTION_THRESHOLD = 0.7;

// Graduated sanction tiers based on final risk score
export const RISK_TIERS = {
  good_standing: { min: 0.0, max: 0.4, label: "Good standing", action: "none", color: "emerald" },
  warning: { min: 0.4, max: 0.55, label: "Warning", action: "notify", color: "amber" },
  restricted: { min: 0.55, max: 0.7, label: "Restricted", action: "mandatory_training", color: "orange" },
  suspended: { min: 0.7, max: 1.0, label: "Suspended", action: "profile_suspension", color: "red" },
};

// Points floor anchors (linear interpolation between anchors)
export const POINTS_FLOOR_ANCHORS = {
  warning: { points: 10, score: 0.40 },
  restriction: { points: 20, score: 0.55 },
  suspension: { points: 30, score: 0.70 },
};

export const tierForScore = (score) => {
  if (score >= 0.7) return "suspended";
  if (score >= 0.55) return "restricted";
  if (score >= 0.4) return "warning";
  return "good_standing";
};

export const tierConfig = (tier) => RISK_TIERS[tier] || RISK_TIERS.good_standing;