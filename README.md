# Traffic Demerit System

Traffic Demerit System is a traffic-enforcement platform with a React portal, a Laravel API, and a separate Python service for recidivism risk scoring.

## Repository layout

```text
.
|-- .editorconfig                 Shared editor formatting rules
|-- .gitattributes                Git text and line-ending rules
|-- .gitignore                    Generated files and local secrets excluded from Git
|-- .vscode/settings.json         Explorer and search exclusions
|-- README.md                     This project guide
`-- apps/
    |-- frontend/traffic-demerit-portal/
    |   |-- src/                    React application source
    |   |   |-- api/                 Browser API/client adapters
    |   |   |-- assets/              Images and static frontend assets
    |   |   |-- components/          Reusable UI components
    |   |   |-- hooks/               Reusable React hooks
    |   |   |-- lib/                 Shared frontend utilities
    |   |   |-- pages/               Route-level screens
    |   |   |-- App.jsx              Application routes and composition
    |   |   |-- index.css             Global styles
    |   |   `-- main.jsx              React entry point
    |   |-- index.html               Vite HTML entry point
    |   |-- package.json              Frontend scripts and dependencies
    |   |-- vite.config.js             Vite configuration
    |   |-- tailwind.config.js         Tailwind configuration
    |   |-- postcss.config.js          PostCSS configuration
    |   |-- eslint.config.js           ESLint configuration
    |   |-- jsconfig.json              JavaScript path/type-checking configuration
    |   |-- components.json            UI component generator configuration
    |   `-- .env.local                 Local frontend environment values
    `-- backend/traffic-demerit-web/
        |-- app/                      Laravel application code
        |   |-- Http/                 Controllers, requests, middleware, resources
        |   |-- Mail/                 Mail classes
        |   |-- Models/                Eloquent models
        |   |-- Providers/             Service providers
        |   `-- Services/              Domain services, including risk and sanctions
        |-- routes/                    API, web, and console routes
        |-- database/                  Migrations, factories, and seeders
        |-- config/                    Laravel and risk configuration
        |-- resources/                 Backend views, CSS, and JavaScript resources
        |-- public/                    Public entry point and published assets
        |-- tests/                     Feature and unit tests
        |-- bootstrap/                 Laravel bootstrap and cached framework files
        |-- storage/                   Runtime files, logs, cache, and generated data
        |-- artisan                    Laravel command-line entry point
        |-- composer.json              PHP dependencies and scripts
        |-- phpunit.xml                PHPUnit configuration
        |-- .env.example               Backend environment template
        |-- README.md                 Backend and API documentation
        `-- ml_service/               Python model training and inference service
            |-- app.py                FastAPI health and prediction endpoints
            |-- train_model.py        Preprocessing, training, evaluation, and export
            |-- generate_simulated_dataset.py  Reproducible demo data generator
            |-- requirements.txt       Python ML/API dependencies
            |-- data/                  Generated CSV input data
            `-- model/                Generated Joblib model artifacts
```

Generated folders such as `node_modules`, `vendor`, `dist`, Python virtual environments, and model artifacts are hidden from the VS Code Explorer and excluded from the workspace Git repository. Each application keeps its own dependencies and environment configuration.

## Machine learning workflow

### Dataset provenance

The current training dataset is **synthetic**, not downloaded from an external website or copied from a public dataset. It is generated locally by `ml_service/generate_simulated_dataset.py` using NumPy and Pandas.

The generator creates 2,500 rows with a fixed random seed of `42`. It samples:

- `days_since_last_offense`: integer from 0 to 365
- `speed_recorded`: normally distributed speed, clipped to 0-180
- `zone_type`: urban, highway, school_zone, or residential
- `weather_conditions`: clear, rain, fog, or night
- `cumulative_active_demerit_points`: integer from 0 to 30
- `past_fine_payment_delays_days`: gamma-distributed delay, clipped to 0-45

The target, `reoffended_within_180_days`, is sampled from a probability created by a documented logistic formula. Higher active points, recent offences, payment delays, and excessive speed increase the probability. Zone and weather also add small configured risk terms. This makes the dataset useful for local development and demonstrations, but it is not evidence of real-world model accuracy.

Generate it with:

```powershell
cd apps/backend/traffic-demerit-web/ml_service
python generate_simulated_dataset.py
```

The output is `data/simulated_reoffending.csv`.

### Training

The training script requires the CSV columns listed above plus the binary target `reoffended_within_180_days`. It performs the following steps:

1. Reads and validates the required columns.
2. Splits the data into 80% training and 20% test sets using stratification.
3. Median-imputes numeric values.
4. Most-frequent-imputes categorical values.
5. One-hot encodes `zone_type` and `weather_conditions`.
6. Trains a balanced `RandomForestClassifier` with 300 trees, maximum depth 10, minimum leaf size 3, and random seed 42.
7. Evaluates the test set with ROC-AUC, F1-score, and a classification report.
8. Saves the complete preprocessing-plus-model pipeline and metadata.

Train the model with:

```powershell
python train_model.py --input data/simulated_reoffending.csv
```

Artifacts are written to `ml_service/model/`:

- `random_forest_pipeline.joblib`: preprocessing and fitted classifier
- `metadata.joblib`: model version, feature list, target, and algorithm name

To use another simulated or anonymized CSV, pass its path to `--input`. The script does not silently accept missing columns.

### Inference service

Install the Python dependencies and start FastAPI:

```powershell
pip install -r requirements.txt
uvicorn app:app --host 127.0.0.1 --port 8001 --reload
```

Endpoints:

- `GET /health`: confirms that the service is available.
- `POST /predict`: returns risk probability, risk class, confidence, latency, and the top feature-importance-based factors.

Risk classes use these thresholds:

- `low`: score below `RISK_LOW_CUTOFF` (default `0.40`)
- `moderate`: score from `0.40` through below `RISK_HIGH_CUTOFF`
- `high`: score at or above `RISK_HIGH_CUTOFF` (default `0.70`)

Only the six approved non-demographic features are accepted by the API. Gender, region, occupation, and demographic proxies are intentionally excluded.

## Application flow

1. An officer submits a violation through the frontend.
2. Laravel persists the violation and demerit ledger entry.
3. `RiskInferenceService` sends the approved feature vector to `/predict`.
4. Laravel stores the prediction and applies the configured sanction threshold.
5. `SanctionService` can create a profile suspension and factor-based reasoning.
6. `risk:batch-infer` periodically re-scores driver profiles.

## Running the applications

### Frontend

```powershell
cd apps/frontend/traffic-demerit-portal
npm install
npm run dev
```

Useful checks are `npm run build`, `npm run lint`, and `npm run typecheck`.

### Laravel backend

```powershell
cd apps/backend/traffic-demerit-web
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

The backend uses the values in `.env`. Configure `ML_SERVICE_URL` to point to the Python service, normally `http://127.0.0.1:8001`.

The hourly batch command can be run locally with:

```powershell
php artisan schedule:work
```

## Important configuration

- `ML_SERVICE_URL`: Python inference service URL.
- `RISK_SANCTION_THRESHOLD`: score at which sanctions are triggered.
- `RISK_MODEL_VERSION`: label stored with predictions, normally `rf_v1`.
- `RISK_LOW_CUTOFF` and `RISK_HIGH_CUTOFF`: ML service class boundaries.
- `PEXELS_API_KEY`: optional backend integration for driver profile pictures.

## Limitations

The synthetic dataset is for development and demonstration only. Before using the system for operational decisions, replace it with a suitable, documented, lawfully obtained dataset; validate it independently; monitor calibration and fairness; and obtain the required governance and legal approvals.
