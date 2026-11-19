# Traffic Demerit ML Service

This directory contains the platform's Random Forest training and inference service.

## Files

- `generate_simulated_dataset.py`: creates the reproducible local training CSV.
- `train_model.py`: validates data, preprocesses features, trains the model, evaluates it, and exports artifacts.
- `app.py`: FastAPI health and prediction endpoints.
- `requirements.txt`: pinned Python dependencies.
- `data/`: generated CSV input data.
- `model/`: generated Joblib artifacts.

## Dataset provenance

The current dataset is synthetic. It is not downloaded from Kaggle, a government portal, or another external source. `generate_simulated_dataset.py` creates 2,500 records with NumPy and Pandas using random seed `42`.

It generates these features:

- `days_since_last_offense`: integer from 0 to 365
- `speed_recorded`: normal distribution centered at 78, clipped to 0-180
- `zone_type`: urban, highway, school_zone, or residential
- `weather_conditions`: clear, rain, fog, or night
- `cumulative_active_demerit_points`: integer from 0 to 30
- `past_fine_payment_delays_days`: gamma distribution clipped to 0-45

The target, `reoffended_within_180_days`, is sampled from a documented logistic probability. Recent offences, active points, payment delays, excessive speed, zone risk, and weather risk affect that probability. This dataset is for development and demonstration; it does not establish real-world model accuracy.

Generate it with:

```powershell
python generate_simulated_dataset.py
```

Output: `data/simulated_reoffending.csv`.

## Training

Install dependencies and train:

```powershell
pip install -r requirements.txt
python train_model.py --input data/simulated_reoffending.csv
```

The training script:

1. Validates all required feature and target columns.
2. Splits data into 80% training and 20% test sets with stratification.
3. Median-imputes numeric values.
4. Most-frequent-imputes categorical values.
5. One-hot encodes `zone_type` and `weather_conditions`.
6. Trains a balanced `RandomForestClassifier` with 300 trees, maximum depth 10, minimum leaf size 3, and seed 42.
7. Reports ROC-AUC, F1-score, precision, recall, and support.
8. Saves the preprocessing and classifier as one pipeline.

Artifacts:

- `model/random_forest_pipeline.joblib`
- `model/metadata.joblib`

Only these six non-demographic features are accepted. Gender, region, occupation, and demographic proxies are excluded from training and inference.

## Run the API

```powershell
uvicorn app:app --host 127.0.0.1 --port 8001 --reload
```

Endpoints:

- `GET /health`
- `POST /predict`

`/predict` returns a risk probability, class, confidence, latency, model version, and up to three feature-importance-based factors. Risk cutoffs default to `0.40` for moderate and `0.70` for high, configurable with `RISK_LOW_CUTOFF` and `RISK_HIGH_CUTOFF`.
