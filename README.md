Traffic Demerit System

A traffic-enforcement platform made of three parts: a React portal, a Laravel API, and a Python service that scores how likely a driver is to reoffend.

Repo layout
text
apps/
|-- frontend/traffic-demerit-portal/   React + Vite + Tailwind (src/ has api, components, hooks, lib, pages)
`-- backend/traffic-demerit-web/       Laravel API (app/, routes/, database/, config/, tests/)
    `-- ml_service/                    Python training and inference (FastAPI)
        |-- app.py                     /health and /predict endpoints
        |-- train_model.py             Preprocessing, training, evaluation, export
        |-- generate_simulated_dataset.py
        |-- data/                      Generated CSV
        `-- model/                     Generated Joblib artifacts

Generated folders (node_modules, vendor, dist, virtual environments, model artifacts) are hidden in VS Code and ignored by Git. Each app manages its own dependencies and environment.

Machine learning

The data is synthetic. generate_simulated_dataset.py builds 2,500 rows locally (seed 42) from six features: days_since_last_offense, speed_recorded, zone_type, weather_conditions, cumulative_active_demerit_points, and past_fine_payment_delays_days. The target, reoffended_within_180_days, comes from a logistic formula, so it's fine for demos but says nothing about real-world accuracy.

powershell
cd apps/backend/traffic-demerit-web/ml_service
python generate_simulated_dataset.py          # writes data/simulated_reoffending.csv
python train_model.py --input data/simulated_reoffending.csv

Training validates the columns, does an 80/20 stratified split, imputes missing values, one-hot encodes zone and weather, and fits a balanced RandomForestClassifier (300 trees, depth 10, leaf size 3). It reports ROC-AUC and F1, then saves random_forest_pipeline.joblib and metadata.joblib to model/. Pass any other CSV with the same columns via --input.

Inference service:

powershell
pip install -r requirements.txt
uvicorn app:app --host 127.0.0.1 --port 8001 --reload
GET /health checks the service is up.
POST /predict returns a risk probability, class, confidence, latency, and top contributing factors.

Classes: low below RISK_LOW_CUTOFF (0.40), high at or above RISK_HIGH_CUTOFF (0.70), moderate in between. Only the six non-demographic features are accepted. Gender, region, occupation, and similar proxies are excluded on purpose.

How it flows
An officer submits a violation in the frontend.
Laravel saves it and adds a demerit ledger entry.
RiskInferenceService sends the features to /predict.
Laravel stores the prediction and checks the sanction threshold.
SanctionService can suspend the profile and record the reasoning.
risk:batch-infer re-scores profiles on a schedule.
Running it
powershell
# Frontend
cd apps/frontend/traffic-demerit-portal
npm install && npm run dev        # also: build, lint, typecheck

# Backend
cd apps/backend/traffic-demerit-web
composer install
php artisan migrate && php artisan db:seed
php artisan serve
php artisan schedule:work         # optional: run the hourly batch locally
Key settings (.env)
ML_SERVICE_URL: Python service address, normally http://127.0.0.1:8001
RISK_SANCTION_THRESHOLD: score that triggers sanctions
RISK_MODEL_VERSION: label saved with predictions, normally rf_v1
RISK_LOW_CUTOFF / RISK_HIGH_CUTOFF: class boundaries
PEXELS_API_KEY: optional, for driver profile pictures