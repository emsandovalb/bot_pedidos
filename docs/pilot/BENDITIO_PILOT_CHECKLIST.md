# Benditio Pilot Checklist

## Before pilot

- [x] `npm run pilot` reports `READY`.
- [x] Latest report path exists: `artifacts/pilot/latest/report.html`.
- [x] Latest JSON summary exists: `artifacts/pilot/latest/e2e-summary.json`.
- [ ] Pilot facilitator briefed on the current scope and limitations.

## Environment

- [x] Desktop viewport confirmed for screenshots.
- [x] Current local app is reachable.
- [x] Pilot database path is isolated from normal development work.
- [x] No secrets or tokens exposed in screenshots or docs.

## Automated readiness

- [x] `npm run pilot` reports `READY`.
- [x] Console errors = 0.
- [x] HTTP 500 = 0.
- [x] Browser E2E passed.
- [x] Build passed.

## Scenario preparation

- [x] Deterministic scenario available.
- [x] WhatsApp path exercised.
- [x] Telegram path exercised.
- [x] Cross-channel possible duplicate scenario available.

## During pilot

- [ ] Login captured.
- [ ] Developer Toolkit captured.
- [ ] Operations home captured.
- [ ] Do Now card verified.
- [ ] Drawer items verified.
- [ ] Status workflow verified.
- [ ] Completed after refresh verified.

## Operator observations

- [ ] The operator understood what `DO NOW` means.
- [ ] The operator understood what `NEXT` means.
- [ ] The operator understood what `COMPLETED` means.
- [ ] The operator understood the primary action for the current status.

## Blocking issues

- [ ] No blocking issues observed.
- [ ] Any blocking issue was recorded with order id, channel, time and screenshot.

## Non-blocking observations

- [ ] Duplicate warning was understandable.
- [ ] Fulfillment interpretation was understandable.
- [ ] Live status was understandable.

## After pilot

- [x] Screenshots captured.
- [x] User feedback recorded.
- [x] Completed orders remain visible after refresh.
- [x] Latest readiness report reviewed.

## Evidence and artifacts

- [x] `artifacts/pilot/latest/report.html`
- [x] `artifacts/pilot/latest/report.json`
- [x] `artifacts/pilot/latest/run.json`
- [x] `artifacts/pilot/latest/e2e-summary.json`

## Follow-up backlog

- [ ] Any gaps in duplicate handling.
- [ ] Any ambiguity in fulfillment interpretation.
- [ ] Any missing operator guidance.
- [ ] Any screenshot or docs regression.

