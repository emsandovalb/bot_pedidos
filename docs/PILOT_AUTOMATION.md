# Benditio Pilot Automation

## Prerequisites

- Local or testing environment only.
- A writable isolated SQLite file for the pilot database.
- Node.js and PHP available on the PATH.

## Command

Run the full readiness flow with:

```bash
npm run pilot
```

That executes:

1. `pilot:clean`
2. `pilot:prepare`
3. `pilot:backend`
4. `pilot:build`
5. `pilot:e2e`
6. `pilot:report`

## Isolated Database

The pilot flow uses a dedicated SQLite database, defaulting to:

`database/pilot.sqlite`

It does not use `database/database.sqlite` and does not reset the normal local database.

## Artifacts

Latest run:

- `artifacts/pilot/latest/report.html`
- `artifacts/pilot/latest/report.json`
- `artifacts/pilot/latest/run.json`
- `artifacts/pilot/latest/e2e-summary.json`
- `artifacts/pilot/latest/screenshots/*.png`

Historical runs:

- `artifacts/pilot/runs/YYYY-MM-DD_HH-mm-ss/`

## Status Meanings

- `READY`: all critical phases passed and no blocking warnings were found.
- `READY WITH WARNINGS`: the pilot completed, but a non-blocking issue exists, such as backend dispatch succeeding while the Completed UI is inconsistent.
- `NOT READY`: one or more critical phases failed.

## Playwright Evidence

The browser test records:

- console errors
- page errors
- HTTP 500 responses
- failed snapshot requests
- Alpine expression errors
- unhandled JavaScript errors

Screenshots are saved even when the test passes:

- `01-toolkit-generated.png`
- `02-operations-home.png`
- `03-order-drawer.png`
- `04-after-action.png`
- `05-final-state.png`

## Playwright Report

Open the generated Playwright report at:

`playwright-report/index.html`

## Common Failures

- Missing pilot database path.
- Pilot owner not seeded.
- Missing WhatsApp or Telegram branch.
- Missing demo products.
- Build manifest missing after Vite build.
- Browser console errors or 500 responses.

## Re-running One Phase

You can run a single phase directly:

```bash
npm run pilot:clean
npm run pilot:prepare
npm run pilot:backend
npm run pilot:build
npm run pilot:e2e
npm run pilot:report
```

## Report Interpretation

The readiness report includes:

- environment
- backend test results
- build results
- browser test results
- business flow metrics
- operations UX checks
- known warnings
- final recommendation

