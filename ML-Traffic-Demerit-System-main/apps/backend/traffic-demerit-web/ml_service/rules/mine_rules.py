"""
Association Rule Mining (unsupervised).

Uses mlxtend (Apriori and FP-Growth). Mines rules across ALL transactions
(does NOT pre-cluster). Two rule families:
  (a) risk rules: consequent is outcome=reoffended_180d or payment=late
  (b) context rules: among violation circumstances

Computes all four measures: Support, Confidence, Coverage, Lift.
Ranks by transparent composite (never lift alone).

Persists to association_rules table.
"""

from __future__ import annotations

import json
import os
from itertools import combinations
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import pymysql
from dotenv import load_dotenv
from mlxtend.frequent_patterns import apriori, fpgrowth
from mlxtend.frequent_patterns import association_rules as mlxtend_rules

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

MODEL_DIR = Path(__file__).resolve().parent.parent / "model"
MODEL_DIR.mkdir(parents=True, exist_ok=True)

# Item columns in ml_transactions (the one-hot encoded boolean basket)
ITEM_COLUMNS = [
    "type_speeding", "type_red_light", "type_dui", "type_no_seatbelt",
    "type_phone_use", "type_reckless_driving", "type_illegal_parking",
    "type_driving_without_license", "type_overloading", "type_no_insurance",
    "zone_urban", "zone_highway", "zone_school_zone", "zone_residential",
    "weather_clear", "weather_rain", "weather_fog", "weather_night",
    "time_night", "time_day",
    "payment_late", "payment_completed", "payment_pending",
    "appeal_filed", "outcome_reoffended_180d",
]

RISK_CONSEQUENTS = {"outcome_reoffended_180d", "payment_late"}

MODEL_VERSION = "rules_v1"


def get_connection():
    return pymysql.connect(**DB_CONFIG)


def load_transactions():
    """Load one-hot encoded transactions from ml_transactions."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM ml_transactions")
            rows = cur.fetchall()
    finally:
        conn.close()
    return pd.DataFrame(rows)


def item_name(col: str) -> str:
    """Convert column name to readable item string."""
    mapping = {
        "type_speeding": "type=speeding",
        "type_red_light": "type=red_light",
        "type_dui": "type=dui",
        "type_no_seatbelt": "type=no_seatbelt",
        "type_phone_use": "type=phone_use",
        "type_reckless_driving": "type=reckless_driving",
        "type_illegal_parking": "type=illegal_parking",
        "type_driving_without_license": "type=driving_without_license",
        "type_overloading": "type=overloading",
        "type_no_insurance": "type=no_insurance",
        "zone_urban": "zone=urban",
        "zone_highway": "zone=highway",
        "zone_school_zone": "zone=school_zone",
        "zone_residential": "zone=residential",
        "weather_clear": "weather=clear",
        "weather_rain": "weather=rain",
        "weather_fog": "weather=fog",
        "weather_night": "weather=night",
        "time_night": "time=night",
        "time_day": "time=day",
        "payment_late": "payment=late",
        "payment_completed": "payment=completed",
        "payment_pending": "payment=pending",
        "appeal_filed": "appeal=filed",
        "outcome_reoffended_180d": "outcome=reoffended_180d",
    }
    return mapping.get(col, col)


def compute_measures(basket: pd.DataFrame, antecedent: frozenset, consequent: frozenset, n: int):
    """Compute Support, Confidence, Coverage, Lift for a rule A -> B."""
    a_mask = basket[list(antecedent)].all(axis=1)
    b_mask = basket[list(consequent)].all(axis=1)
    ab_mask = a_mask & b_mask

    support_ab = ab_mask.sum() / n
    support_a = a_mask.sum() / n
    support_b = b_mask.sum() / n

    confidence = support_ab / support_a if support_a > 0 else 0
    coverage = support_a
    lift = support_ab / (support_a * support_b) if (support_a * support_b) > 0 else 0

    return {
        "support": round(float(support_ab), 6),
        "confidence": round(float(confidence), 6),
        "coverage": round(float(coverage), 6),
        "lift": round(float(lift), 6),
    }


def composite_score(support, confidence, coverage, lift):
    """Transparent composite: geometric mean of normalized measures."""
    # Normalize each to [0, 1] roughly
    s = min(support * 10, 1.0)  # support * 10 caps at 1.0 for 10% support
    c = confidence
    cov = min(coverage * 5, 1.0)  # coverage * 5 caps at 1.0 for 20%
    l = min(lift / 3.0, 1.0)  # lift / 3 caps at 1.0 for 3x lift
    return (s * c * cov * l) ** 0.25


def plain_english(antecedent_items, consequent_items, support, confidence, coverage, lift):
    """Translate rule to plain English."""
    a_str = ", ".join(antecedent_items)
    b_str = ", ".join(consequent_items)
    return (
        f"When {a_str} occurs, {b_str} also occurs in "
        f"{confidence*100:.1f}% of cases, "
        f"{lift:.2f}x more often than chance "
        f"(covers {coverage*100:.1f}% of all violations)."
    )


def test_lecture_example():
    """Unit test against the lecture's worked example.

    14 transactions: Cooking oil -> Beef gives
    Support 14.3%, Confidence 66.7%, Coverage 21.4%, Lift 1.556.
    """
    # 14 transactions matching the lecture example:
    # Cooking oil -> Beef gives Support 14.3%, Confidence 66.7%, Coverage 21.4%, Lift 1.556
    # Verification: Support(oil)=3/14, Support(beef)=6/14, Support(both)=2/14
    #   Confidence = (2/14)/(3/14) = 66.7%, Coverage = 3/14 = 21.4%
    #   Lift = (2/14)/((3/14)*(6/14)) = 1.556
    data = [
        # T1: Cooking oil, Beef
        {"Cooking oil": 1, "Beef": 1, "Milk": 0},
        # T2: Cooking oil, Beef
        {"Cooking oil": 1, "Beef": 1, "Milk": 0},
        # T3: Cooking oil only
        {"Cooking oil": 1, "Beef": 0, "Milk": 0},
        # T4-T7: Beef only (4 transactions)
        {"Cooking oil": 0, "Beef": 1, "Milk": 0},
        {"Cooking oil": 0, "Beef": 1, "Milk": 0},
        {"Cooking oil": 0, "Beef": 1, "Milk": 0},
        {"Cooking oil": 0, "Beef": 1, "Milk": 0},
        # T8-T14: neither (7 transactions)
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
        {"Cooking oil": 0, "Beef": 0, "Milk": 0},
    ]
    basket = pd.DataFrame(data)
    n = len(basket)

    A = frozenset(["Cooking oil"])
    B = frozenset(["Beef"])
    m = compute_measures(basket, A, B, n)

    print("\n=== Lecture Example Unit Test ===")
    print(f"  Support:  {m['support']*100:.1f}%  (expected 14.3%)")
    print(f"  Confidence: {m['confidence']*100:.1f}%  (expected 66.7%)")
    print(f"  Coverage:  {m['coverage']*100:.1f}%  (expected 21.4%)")
    print(f"  Lift:      {m['lift']:.3f}  (expected 1.556)")

    assert abs(m["support"] - 0.143) < 0.001, f"Support {m['support']} != 0.143"
    assert abs(m["confidence"] - 0.667) < 0.001, f"Confidence {m['confidence']} != 0.667"
    assert abs(m["coverage"] - 0.214) < 0.001, f"Coverage {m['coverage']} != 0.214"
    assert abs(m["lift"] - 1.556) < 0.001, f"Lift {m['lift']} != 1.556"
    print("  PASSED!")


def mine_rules():
    print("=" * 60)
    print("Association Rule Mining")
    print("=" * 60)

    # Run lecture example test first
    test_lecture_example()

    df = load_transactions()
    print(f"\nLoaded {len(df)} transactions")

    # Build basket matrix
    basket = df[ITEM_COLUMNS].copy()
    # Convert booleans to int (mlxtend expects 0/1)
    for col in ITEM_COLUMNS:
        basket[col] = basket[col].astype(int)

    n = len(basket)
    print(f"  Basket shape: {basket.shape}")
    print(f"  Items: {len(ITEM_COLUMNS)}")

    # ---------------------------------------------------------------
    # Sweep min_support and report counts
    # ---------------------------------------------------------------
    print("\nSweeping min_support 0.03-0.10 (FP-Growth)...")
    support_levels = [0.03, 0.04, 0.05, 0.06, 0.07, 0.08, 0.09, 0.10]
    support_results = []

    for ms in support_levels:
        try:
            itemsets = fpgrowth(basket, min_support=ms, use_colnames=True, max_len=None)
            if len(itemsets) > 0:
                rules = mlxtend_rules(itemsets, metric="confidence", min_threshold=0.60)
                # Filter max antecedent length 3
                rules = rules[rules["antecedents"].apply(len) <= 3]
                n_rules = len(rules)
            else:
                n_rules = 0
        except Exception as e:
            print(f"  min_support={ms}: error - {e}")
            n_rules = 0
        support_results.append((ms, len(itemsets) if 'itemsets' in dir() else 0, n_rules))
        print(f"  min_support={ms}: {n_rules} itemsets, {n_rules} rules (conf>=0.60)")

    # ---------------------------------------------------------------
    # Mine final rules at min_support=0.05 (reasonable default)
    # ---------------------------------------------------------------
    min_support = 0.05
    min_confidence = 0.60

    print(f"\nMining rules: min_support={min_support}, min_confidence={min_confidence}")
    itemsets = fpgrowth(basket, min_support=min_support, use_colnames=True)
    print(f"  Found {len(itemsets)} frequent itemsets")

    rules = mlxtend_rules(itemsets, metric="confidence", min_threshold=min_confidence)
    print(f"  Found {len(rules)} rules before antecedent filter")

    # Filter max antecedent length 3
    rules = rules[rules["antecedents"].apply(len) <= 3]
    print(f"  {len(rules)} rules after antecedent length <= 3 filter")

    if len(rules) == 0:
        print("  No rules found! Try lowering min_support.")
        return

    # Compute all four measures for each rule
    rule_records = []
    for _, rule in rules.iterrows():
        antecedent = frozenset(rule["antecedents"])
        consequent = frozenset(rule["consequents"])

        m = compute_measures(basket, antecedent, consequent, n)

        antecedent_items = sorted([item_name(c) for c in antecedent])
        consequent_items = sorted([item_name(c) for c in consequent])

        is_risk_rule = any(c in RISK_CONSEQUENTS for c in consequent_items)
        rule_family = "risk" if is_risk_rule else "context"

        # Lift interpretation
        if m["lift"] > 1.05:
            association = "positive"
        elif m["lift"] < 0.95:
            association = "negative"
        else:
            association = "independence"

        comp = composite_score(m["support"], m["confidence"], m["coverage"], m["lift"])

        rule_records.append({
            "antecedent": antecedent_items,
            "consequent": consequent_items,
            "support": m["support"],
            "confidence": m["confidence"],
            "coverage": m["coverage"],
            "lift": m["lift"],
            "rule_family": rule_family,
            "association": association,
            "composite_score": round(float(comp), 6),
            "plain_english": plain_english(
                antecedent_items, consequent_items,
                m["support"], m["confidence"], m["coverage"], m["lift"]
            ),
            "min_support": min_support,
            "min_confidence": min_confidence,
        })

    # Sort by composite score descending
    rule_records.sort(key=lambda r: r["composite_score"], reverse=True)

    print(f"\nTop 10 rules by composite score:")
    for i, r in enumerate(rule_records[:10]):
        print(f"  {i+1}. {r['plain_english'][:100]}... (composite={r['composite_score']:.3f})")

    # ---------------------------------------------------------------
    # Persist to association_rules table
    # ---------------------------------------------------------------
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            # Clear old rules for this model version
            cur.execute(
                "DELETE FROM association_rules WHERE model_version = %s",
                (MODEL_VERSION,),
            )

            for r in rule_records:
                cur.execute(
                    """
                    INSERT INTO association_rules
                    (antecedent, consequent, support, confidence, coverage, lift,
                     rule_family, association_type, composite_score, plain_english,
                     min_support, min_confidence, model_version, mined_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                """,
                    (
                        json.dumps(r["antecedent"]),
                        json.dumps(r["consequent"]),
                        r["support"],
                        r["confidence"],
                        r["coverage"],
                        r["lift"],
                        r["rule_family"],
                        r["association"],
                        r["composite_score"],
                        r["plain_english"],
                        r["min_support"],
                        r["min_confidence"],
                        MODEL_VERSION,
                    ),
                )

        conn.commit()
        print(f"\nPersisted {len(rule_records)} association rules")
    finally:
        conn.close()

    # Save model artifact
    joblib.dump({
        "rules": rule_records,
        "item_columns": ITEM_COLUMNS,
        "n_transactions": n,
    }, MODEL_DIR / "association_rules.joblib")
    print(f"Rules artifact saved to {MODEL_DIR}/association_rules.joblib")

    print("\nAssociation rule mining complete!")


if __name__ == "__main__":
    mine_rules()
