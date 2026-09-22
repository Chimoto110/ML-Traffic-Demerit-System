# Traffic Demerit System

Traffic demerit and recidivism management frontend.

## Run locally

1. Install dependencies:

```bash
npm install
```

2. Start dev server:

```bash
npm run dev
```

3. Build production bundle:

```bash
npm run build
```

## App behavior

- Uses a local browser-backed client in `src/api/appClient.js`.
- Stores sample entities in `localStorage` for offline/demo usage.
- Supports officer logging, ledger updates, risk scoring, sanctions, and appeals.

## Notes

- Authentication pages run in local demo mode.
- Data resets if local storage is cleared.
