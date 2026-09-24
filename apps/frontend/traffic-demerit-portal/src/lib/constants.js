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