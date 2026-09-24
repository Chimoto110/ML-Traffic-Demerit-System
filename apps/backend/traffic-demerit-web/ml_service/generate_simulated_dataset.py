from __future__ import annotations

from pathlib import Path

import numpy as np
import pandas as pd


def main() -> None:
    rng = np.random.default_rng(42)
    n = 2500

    zone_types = np.array(["urban", "highway", "school_zone", "residential"])
    weather_types = np.array(["clear", "rain", "fog", "night"])

    days_since_last_offense = rng.integers(0, 366, size=n)
    speed_recorded = rng.normal(78, 20, size=n).clip(0, 180)
    zone_type = rng.choice(zone_types, size=n, p=[0.45, 0.25, 0.15, 0.15])
    weather_conditions = rng.choice(weather_types, size=n, p=[0.55, 0.2, 0.15, 0.1])
    cumulative_active_demerit_points = rng.integers(0, 31, size=n)
    past_fine_payment_delays_days = rng.gamma(shape=2.0, scale=3.0, size=n).clip(0, 45)

    zone_risk = {
        "urban": 0.08,
        "highway": 0.12,
        "school_zone": 0.18,
        "residential": 0.06,
    }
    weather_risk = {
        "clear": 0.02,
        "rain": 0.08,
        "fog": 0.13,
        "night": 0.10,
    }

    logits = (
        1.3 * (1 - np.minimum(days_since_last_offense, 365) / 365)
        + 1.5 * (cumulative_active_demerit_points / 30)
        + 1.1 * np.minimum(past_fine_payment_delays_days, 45) / 45
        + 0.9 * np.maximum(speed_recorded - 80, 0) / 70
        + np.vectorize(zone_risk.get)(zone_type)
        + np.vectorize(weather_risk.get)(weather_conditions)
        - 2.0
    )

    probs = 1 / (1 + np.exp(-logits))
    reoffended = rng.binomial(1, probs)

    df = pd.DataFrame(
        {
            "days_since_last_offense": days_since_last_offense,
            "speed_recorded": speed_recorded.round(2),
            "zone_type": zone_type,
            "weather_conditions": weather_conditions,
            "cumulative_active_demerit_points": cumulative_active_demerit_points,
            "past_fine_payment_delays_days": past_fine_payment_delays_days.round(2),
            "reoffended_within_180_days": reoffended,
        }
    )

    out_dir = Path(__file__).resolve().parent / "data"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / "simulated_reoffending.csv"
    df.to_csv(out_path, index=False)
    print(f"Saved dataset to {out_path}")
    print(df.head(3).to_string(index=False))


if __name__ == "__main__":
    main()
