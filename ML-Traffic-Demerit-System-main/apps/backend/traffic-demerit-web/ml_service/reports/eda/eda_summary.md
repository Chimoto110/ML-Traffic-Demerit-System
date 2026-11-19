# EDA Summary

Total driver records: 403
Synthetic records: 350
Real (original) records: 53

## Class Balance (reoffended_within_180_days)
- Class 1: 225 (55.8%)
- Class 0: 178 (44.2%)

## Numeric Feature Summary
|       |   violation_count |   cumulative_active_demerit_points |   days_since_last_offense |   appeals_filed |
|:------|------------------:|-----------------------------------:|--------------------------:|----------------:|
| count |         403       |                           403      |                   403     |     403         |
| mean  |           3.73945 |                            18.0893 |                   182.127 |       0.0521092 |
| std   |           2.23364 |                            12.4582 |                   160.611 |       0.281722  |
| min   |           0       |                             0      |                     4     |       0         |
| 25%   |           2       |                             9      |                    58     |       0         |
| 50%   |           3       |                            16      |                   128     |       0         |
| 75%   |           5       |                            25      |                   264.5   |       0         |
| max   |          13       |                            71      |                   713     |       3         |

## Data Limitations
- All feature data is derived from synthetic driver profiles (is_synthetic=1 for generated rows).
- Original rows (is_synthetic=0) retain real-world provenance but are few (50 drivers).
- The `reoffended_within_180_days` label is derived from violation history: a driver is labeled
  as reoffended if they have a second violation within 180 days of their first.
- This labeling approach has limitations: drivers with only one violation cannot be labeled as
  reoffended, and the 180-day window may not capture all recidivism patterns.
- Results on this data demonstrate the pipeline and say nothing about real-world accuracy.