export const DEMERIT_POINTS = {
  speeding: 3,
  red_light: 4,
  reckless_driving: 6,
  dui: 8,
  no_seatbelt: 2,
  phone_use: 3,
  illegal_parking: 1,
};

export const SANCTION_THRESHOLD = 0.7;
export const ZONE_RISK = { urban: 0.6, highway: 0.7, residential: 0.4, school_zone: 0.9, rural: 0.3 };
export const WEATHER_RISK = { clear: 0.2, rain: 0.6, fog: 0.8, storm: 0.9 };

export function speedExcessFactor(speedRecorded, speedLimit) {
  const limit = speedLimit || 80;
  const excess = Math.max(0, (speedRecorded || 0) - limit);
  return Math.min(1, excess / 40);
}

export function computeDemeritPoints(offenceType, speedRecorded, speedLimit) {
  let base = DEMERIT_POINTS[offenceType] ?? 2;
  if (offenceType === 'speeding') {
    const excess = Math.max(0, (speedRecorded || 0) - (speedLimit || 80));
    if (excess >= 30) base = 6;
    else if (excess >= 20) base = 5;
    else if (excess >= 10) base = 4;
  }
  return base;
}

export function predictRecidivism(features) {
  const {
    days_since_last_offence = 365,
    speed_recorded = 0,
    speed_limit = 80,
    zone_type = 'urban',
    weather = 'clear',
    cumulative_demerit_points = 0,
    avg_fine_payment_delay_days = 0,
    total_violations = 0,
  } = features;

  const recency = Math.max(0, 1 - days_since_last_offence / 180);
  const speedF = speedExcessFactor(speed_recorded, speed_limit);
  const zoneF = ZONE_RISK[zone_type] ?? 0.5;
  const weatherF = WEATHER_RISK[weather] ?? 0.3;
  const demeritF = Math.min(1, cumulative_demerit_points / 30);
  const delayF = Math.min(1, avg_fine_payment_delay_days / 30);
  const countF = Math.min(1, total_violations / 10);

  const factors = [
    { feature: 'Days since last offence', value: `${days_since_last_offence}d`, raw: recency, weight: 0.22 },
    { feature: 'Speed excess', value: `${speed_recorded}/${speed_limit} km/h`, raw: speedF, weight: 0.18 },
    { feature: 'Zone type', value: zone_type, raw: zoneF, weight: 0.13 },
    { feature: 'Weather', value: weather, raw: weatherF, weight: 0.12 },
    { feature: 'Cumulative demerit points', value: `${cumulative_demerit_points}`, raw: demeritF, weight: 0.2 },
    { feature: 'Fine payment delay', value: `${avg_fine_payment_delay_days}d`, raw: delayF, weight: 0.08 },
    { feature: 'Total past violations', value: `${total_violations}`, raw: countF, weight: 0.07 },
  ];

  const z = factors.reduce((sum, f) => sum + f.raw * f.weight, 0) - 0.15;
  const score = 1 / (1 + Math.exp(-z * 4));
  const classification = score >= 0.7 ? 'high' : score >= 0.4 ? 'moderate' : 'low';

  return {
    score,
    classification,
    factors: factors.map((f) => ({
      feature: f.feature,
      value: f.value,
      contribution: f.raw * f.weight,
      direction: f.raw >= 0.5 ? 'risk' : 'protective',
    })),
  };
}