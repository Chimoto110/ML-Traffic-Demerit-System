"""
ETL: Extract -> Transform -> Load for ML feature store.

Extracts from MySQL (traffic_demerit database), transforms into per-driver
feature rows and per-violation transaction rows, then loads them idempotently
into ml_driver_features and ml_transactions tables.

Safe to re-run: uses upsert (INSERT ... ON DUPLICATE KEY UPDATE) so no
existing data is destroyed.
"""

from __future__ import annotations

import os
import sys
from datetime import datetime, timedelta

import pymysql
import pandas as pd
from dotenv import load_dotenv

# Load environment variables from .env
load_dotenv()

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USERNAME", "root"),
    "password": os.getenv("DB_PASSWORD", ""),
    "database": os.getenv("DB_DATABASE", "traffic_demerit"),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
}

OFFENSE_POINTS = {
    "speeding": 4,
    "red_light": 5,
    "dui": 10,
    "no_seatbelt": 2,
    "phone_use": 3,
    "reckless_driving": 8,
    "illegal_parking": 2,
    "driving_without_license": 8,
    "overloading": 4,
    "no_insurance": 6,
}

OFFENSE_FINES = {
    "speeding": 5000,
    "red_light": 3000,
    "dui": 15000,
    "no_seatbelt": 1000,
    "phone_use": 2000,
    "reckless_driving": 7000,
    "illegal_parking": 500,
    "driving_without_license": 12000,
    "overloading": 4000,
    "no_insurance": 8000,
}


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def extract(conn):
    """Extract all needed tables from MySQL."""
    queries = {
        "violations": """
            SELECT v.*, dp.user_id as driver_user_id
            FROM violations v
            JOIN driver_profiles dp ON v.driver_id = dp.id
        """,
        "ledger": """
            SELECT * FROM demerit_ledger_entries
        """,
        "payments": """
            SELECT * FROM payments
        """,
        "drivers": """
            SELECT dp.*, u.email as user_email
            FROM driver_profiles dp
            LEFT JOIN users u ON dp.user_id = u.id
        """,
        "sanctions": """
            SELECT * FROM sanction_actions
        """,
        "appeals": """
            SELECT * FROM appeals
        """,
    }

    frames = {}
    with conn.cursor() as cur:
        for name, q in queries.items():
            cur.execute(q)
            rows = cur.fetchall()
            frames[name] = pd.DataFrame(rows)
            print(f"  Extracted {len(rows)} rows from {name}")

    return frames


def transform(frames):
    """Transform raw tables into feature and transaction tables."""
    violations = frames["violations"]
    ledger = frames["ledger"]
    payments = frames["payments"]
    drivers = frames["drivers"]
    sanctions = frames["sanctions"]
    appeals = frames["appeals"]

    now = pd.Timestamp.now()

    # ------------------------------------------------------------------
    # 1. Per-driver features
    # ------------------------------------------------------------------
    driver_features = []

    for _, driver in drivers.iterrows():
        did = driver["id"]
        d_violations = violations[violations["driver_id"] == did]
        d_ledger = ledger[ledger["driver_id"] == did]
        d_payments = payments[payments["driver_id"] == did]
        d_sanctions = sanctions[sanctions["driver_id"] == did]
        d_appeals = appeals[appeals["driver_id"] == did]

        violation_count = len(d_violations)

        # Cumulative active demerit points from ledger running_balance
        if len(d_ledger) > 0:
            latest = d_ledger.sort_values("id").iloc[-1]
            cumulative_points = int(latest["running_balance"])
        else:
            cumulative_points = 0

        # Days since last offense
        if violation_count > 0:
            last_offense = pd.to_datetime(d_violations["occurred_at"]).max()
            days_since_last = (now - last_offense).days
        else:
            days_since_last = 365

        # New driver flag: fewer than 2 violations
        new_driver = violation_count < 2

        # Avg speed over limit (only for speeding violations with speed data)
        speeding = d_violations[
            (d_violations["offense_type"] == "speeding")
            & d_violations["speed_recorded"].notna()
            & d_violations["posted_speed_limit"].notna()
        ]
        if len(speeding) > 0:
            over_limit = (
                speeding["speed_recorded"].astype(float)
                - speeding["posted_speed_limit"].astype(float)
            ).clip(lower=0)
            avg_speed_over_limit = float(over_limit.mean())
        else:
            avg_speed_over_limit = 0.0

        # Share in school zone
        if violation_count > 0:
            share_school = float((d_violations["zone_type"] == "school_zone").mean())
            share_night = float((d_violations["weather_conditions"] == "night").mean())
        else:
            share_school = 0.0
            share_night = 0.0

        # Total fines KES
        completed_payments = d_payments[d_payments["status"] == "completed"]
        total_fines = float(completed_payments["amount"].sum()) if len(completed_payments) > 0 else 0.0

        # Past fine payment delays (average days from violation to payment)
        paid = d_payments[
            d_payments["status"] == "completed"
        ].merge(
            d_violations[["id", "occurred_at"]].rename(columns={"id": "violation_id_2", "occurred_at": "viol_occurred_at"}),
            left_on="violation_id",
            right_on="violation_id_2",
        )
        if len(paid) > 0:
            paid["delay"] = (
                pd.to_datetime(paid["paid_at"]) - pd.to_datetime(paid["viol_occurred_at"])
            ).dt.days
            avg_delay = float(paid["delay"].mean())
        else:
            avg_delay = 0.0

        # Unpaid fine ratio
        total_violation_fines = sum(
            OFFENSE_FINES.get(ot, 1000) for ot in d_violations["offense_type"]
        )
        if total_violation_fines > 0:
            unpaid_ratio = float(
                1 - (completed_payments["amount"].sum() / total_violation_fines)
            )
            unpaid_ratio = max(0.0, min(1.0, unpaid_ratio))
        else:
            unpaid_ratio = 0.0

        # Appeals filed
        appeals_filed = len(d_appeals)

        # Reoffended within 180 days: does this driver have a violation within
        # 180 days after their first violation?
        reoffended = 0
        if violation_count >= 2:
            sorted_viols = d_violations.sort_values("occurred_at")
            first = pd.to_datetime(sorted_viols.iloc[0]["occurred_at"])
            for _, v in sorted_viols.iloc[1:].iterrows():
                if (pd.to_datetime(v["occurred_at"]) - first).days <= 180:
                    reoffended = 1
                    break

        is_synthetic = bool(driver.get("is_synthetic", 0)) if "is_synthetic" in driver else False

        driver_features.append({
            "driver_profile_id": did,
            "violation_count": violation_count,
            "cumulative_active_demerit_points": cumulative_points,
            "days_since_last_offense": days_since_last,
            "avg_speed_over_limit": round(avg_speed_over_limit, 2),
            "share_in_school_zone": round(share_school, 4),
            "share_at_night": round(share_night, 4),
            "total_fines_kes": round(total_fines, 2),
            "past_fine_payment_delays_days": round(avg_delay, 2),
            "unpaid_fine_ratio": round(unpaid_ratio, 4),
            "appeals_filed": appeals_filed,
            "reoffended_within_180_days": reoffended,
            "new_driver": int(new_driver),
            "is_synthetic": int(is_synthetic),
        })

    driver_features_df = pd.DataFrame(driver_features)

    # ------------------------------------------------------------------
    # 2. Per-violation transactions for association rule mining
    # ------------------------------------------------------------------
    transactions = []

    for _, v in violations.iterrows():
        vid = v["id"]
        did = v["driver_id"]
        offense = v["offense_type"]
        zone = v["zone_type"]
        weather = v["weather_conditions"]
        occurred_at = pd.to_datetime(v["occurred_at"])

        # Time of day
        hour = occurred_at.hour
        is_night = hour >= 18 or hour <= 6

        # Payment status
        v_payments = payments[payments["violation_id"] == vid]
        payment_late = False
        payment_completed = False
        payment_pending = False

        if len(v_payments) > 0:
            latest_pay = v_payments.sort_values("id").iloc[-1]
            p_status = latest_pay["status"]
            if p_status == "completed":
                payment_completed = True
                paid_at = pd.to_datetime(latest_pay["paid_at"])
                delay_days = (paid_at - occurred_at).days
                if delay_days > 30:
                    payment_late = True
            elif p_status == "pending":
                payment_pending = True

        # Appeal filed
        v_appeals = appeals[appeals["violation_id"] == vid]
        appeal_filed = len(v_appeals) > 0

        # Reoffended within 180 days after this violation
        d_future = violations[
            (violations["driver_id"] == did)
            & (pd.to_datetime(violations["occurred_at"]) > occurred_at)
        ]
        reoffended_180d = (
            len(d_future) > 0
            and (pd.to_datetime(d_future["occurred_at"].min()) - occurred_at).days
            <= 180
        )

        is_synthetic = bool(v.get("is_synthetic", 0)) if "is_synthetic" in v else False

        transactions.append({
            "violation_id": vid,
            "driver_profile_id": did,
            "type_speeding": offense == "speeding",
            "type_red_light": offense == "red_light",
            "type_dui": offense == "dui",
            "type_no_seatbelt": offense == "no_seatbelt",
            "type_phone_use": offense == "phone_use",
            "type_reckless_driving": offense == "reckless_driving",
            "type_illegal_parking": offense == "illegal_parking",
            "type_driving_without_license": offense == "driving_without_license",
            "type_overloading": offense == "overloading",
            "type_no_insurance": offense == "no_insurance",
            "zone_urban": zone == "urban",
            "zone_highway": zone == "highway",
            "zone_school_zone": zone == "school_zone",
            "zone_residential": zone == "residential",
            "weather_clear": weather == "clear",
            "weather_rain": weather == "rain",
            "weather_fog": weather == "fog",
            "weather_night": weather == "night",
            "time_night": bool(is_night),
            "time_day": not bool(is_night),
            "payment_late": payment_late,
            "payment_completed": payment_completed,
            "payment_pending": payment_pending,
            "appeal_filed": appeal_filed,
            "outcome_reoffended_180d": int(reoffended_180d),
            "is_synthetic": int(is_synthetic),
        })

    transactions_df = pd.DataFrame(transactions)

    return driver_features_df, transactions_df


def load(driver_features_df, transactions_df):
    """Load feature and transaction data into MySQL (upsert)."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            # --- ml_driver_features ---
            for _, row in driver_features_df.iterrows():
                cur.execute(
                    """
                    INSERT INTO ml_driver_features
                    (driver_profile_id, violation_count, cumulative_active_demerit_points,
                     days_since_last_offense, avg_speed_over_limit, share_in_school_zone,
                     share_at_night, total_fines_kes, past_fine_payment_delays_days,
                     unpaid_fine_ratio, appeals_filed, reoffended_within_180_days,
                     new_driver, is_synthetic, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
                    ON DUPLICATE KEY UPDATE
                        violation_count = VALUES(violation_count),
                        cumulative_active_demerit_points = VALUES(cumulative_active_demerit_points),
                        days_since_last_offense = VALUES(days_since_last_offense),
                        avg_speed_over_limit = VALUES(avg_speed_over_limit),
                        share_in_school_zone = VALUES(share_in_school_zone),
                        share_at_night = VALUES(share_at_night),
                        total_fines_kes = VALUES(total_fines_kes),
                        past_fine_payment_delays_days = VALUES(past_fine_payment_delays_days),
                        unpaid_fine_ratio = VALUES(unpaid_fine_ratio),
                        appeals_filed = VALUES(appeals_filed),
                        reoffended_within_180_days = VALUES(reoffended_within_180_days),
                        new_driver = VALUES(new_driver),
                        is_synthetic = VALUES(is_synthetic),
                        updated_at = NOW()
                """,
                    (
                        int(row["driver_profile_id"]),
                        int(row["violation_count"]),
                        int(row["cumulative_active_demerit_points"]),
                        int(row["days_since_last_offense"]),
                        float(row["avg_speed_over_limit"]),
                        float(row["share_in_school_zone"]),
                        float(row["share_at_night"]),
                        float(row["total_fines_kes"]),
                        float(row["past_fine_payment_delays_days"]),
                        float(row["unpaid_fine_ratio"]),
                        int(row["appeals_filed"]),
                        int(row["reoffended_within_180_days"]),
                        int(row["new_driver"]),
                        int(row["is_synthetic"]),
                    ),
                )

            # --- ml_transactions ---
            for _, row in transactions_df.iterrows():
                cur.execute(
                    """
                    INSERT INTO ml_transactions
                    (violation_id, driver_profile_id,
                     type_speeding, type_red_light, type_dui, type_no_seatbelt,
                     type_phone_use, type_reckless_driving, type_illegal_parking,
                     type_driving_without_license, type_overloading, type_no_insurance,
                     zone_urban, zone_highway, zone_school_zone, zone_residential,
                     weather_clear, weather_rain, weather_fog, weather_night,
                     time_night, time_day,
                     payment_late, payment_completed, payment_pending,
                     appeal_filed, outcome_reoffended_180d,
                     is_synthetic, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                            %s, %s, %s, %s, %s, %s, %s, %s,
                            %s, %s, %s, %s, %s, %s, %s,
                            %s, NOW())
                    ON DUPLICATE KEY UPDATE
                        type_speeding = VALUES(type_speeding),
                        type_red_light = VALUES(type_red_light),
                        type_dui = VALUES(type_dui),
                        type_no_seatbelt = VALUES(type_no_seatbelt),
                        type_phone_use = VALUES(type_phone_use),
                        type_reckless_driving = VALUES(type_reckless_driving),
                        type_illegal_parking = VALUES(type_illegal_parking),
                        type_driving_without_license = VALUES(type_driving_without_license),
                        type_overloading = VALUES(type_overloading),
                        type_no_insurance = VALUES(type_no_insurance),
                        zone_urban = VALUES(zone_urban),
                        zone_highway = VALUES(zone_highway),
                        zone_school_zone = VALUES(zone_school_zone),
                        zone_residential = VALUES(zone_residential),
                        weather_clear = VALUES(weather_clear),
                        weather_rain = VALUES(weather_rain),
                        weather_fog = VALUES(weather_fog),
                        weather_night = VALUES(weather_night),
                        time_night = VALUES(time_night),
                        time_day = VALUES(time_day),
                        payment_late = VALUES(payment_late),
                        payment_completed = VALUES(payment_completed),
                        payment_pending = VALUES(payment_pending),
                        appeal_filed = VALUES(appeal_filed),
                        outcome_reoffended_180d = VALUES(outcome_reoffended_180d),
                        is_synthetic = VALUES(is_synthetic),
                        updated_at = NOW()
                """,
                    (
                        int(row["violation_id"]),
                        int(row["driver_profile_id"]),
                        bool(row["type_speeding"]),
                        bool(row["type_red_light"]),
                        bool(row["type_dui"]),
                        bool(row["type_no_seatbelt"]),
                        bool(row["type_phone_use"]),
                        bool(row["type_reckless_driving"]),
                        bool(row["type_illegal_parking"]),
                        bool(row["type_driving_without_license"]),
                        bool(row["type_overloading"]),
                        bool(row["type_no_insurance"]),
                        bool(row["zone_urban"]),
                        bool(row["zone_highway"]),
                        bool(row["zone_school_zone"]),
                        bool(row["zone_residential"]),
                        bool(row["weather_clear"]),
                        bool(row["weather_rain"]),
                        bool(row["weather_fog"]),
                        bool(row["weather_night"]),
                        bool(row["time_night"]),
                        bool(row["time_day"]),
                        bool(row["payment_late"]),
                        bool(row["payment_completed"]),
                        bool(row["payment_pending"]),
                        bool(row["appeal_filed"]),
                        int(row["outcome_reoffended_180d"]),
                        int(row["is_synthetic"]),
                    ),
                )

        conn.commit()
        print(f"  Loaded {len(driver_features_df)} driver features")
        print(f"  Loaded {len(transactions_df)} transactions")
    finally:
        conn.close()


def main():
    print("=" * 60)
    print("ETL: Build ML Features")
    print("=" * 60)

    print("\n[1/3] Extracting from MySQL...")
    conn = get_connection()
    try:
        frames = extract(conn)
    finally:
        conn.close()

    print("\n[2/3] Transforming...")
    driver_features_df, transactions_df = transform(frames)
    print(f"  Driver features: {len(driver_features_df)} rows")
    print(f"  Transactions: {len(transactions_df)} rows")

    print("\n[3/3] Loading into MySQL...")
    load(driver_features_df, transactions_df)

    print("\nETL complete!")


if __name__ == "__main__":
    main()
