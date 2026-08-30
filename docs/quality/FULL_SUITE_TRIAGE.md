# Full suite triage

Date: 2026-08-19

The default application configuration sets `features.legacy_lottery_routes_enabled` to `false`. Routes for the prior lottery workflow are consequently absent by design; they must not be restored to make the test suite pass.

| Failing group | Classification | Evidence | Decision |
|---|---|---|---|
| `SimulatorIntakeTest` | A | Every case calls `simulator.store`, registered only inside the `legacy_lottery_routes_enabled` conditional in `routes/web.php`. | Removed the obsolete lottery suite. |
| `NumberBoardTest` | A | Every case calls `numbers.index` or `numbers.store`, also conditional legacy routes. | Removed the obsolete lottery suite. |
| `NumberLimitManagementTest` | A | Every case calls `limits.*`, also conditional legacy routes. | Removed the obsolete lottery suite. |
| `IntakeRequestManagementTest` | A | Every case calls `intake-requests.*`, also conditional legacy routes. | Removed the obsolete lottery suite. |
| `PilotPagesTest` | A | The pilot checklist, script, and guide routes are inside the same legacy-lottery conditional. | Removed the obsolete lottery suite. |
| `ScopeVisibilityTest` | A (partial) | Its branches and incoming-message assertions use active routes. Its final simulator assertion references the retired lottery simulator. | Retained the active scope coverage and removed only the simulator assertion. |
| `BranchDailyClosureTest::test_dashboard_reporting_visibility_matches_scope` | A | The test creates lottery `IntakeRequest` records and asserts dashboard branch rendering from the retired workflow. The active dashboard is order-centric and no longer renders that branch report. | Removed only the obsolete method; the closure authorization and export coverage remains. |
| `ExampleTest::test_dashboard_uses_official_order_workflow_links` | A (partial) | It correctly asserts the active order link, but it generated a removed `intake-requests.index` route merely to assert it was absent. | Kept the assertion and checked the removed `/requests` path directly. |
| `PrimaryNavigationCleanupTest` | A (partial) | The legacy-link cleanup assertions pass. The sole failure is an outdated unaccented copy expectation; rendered active UI says `Catálogo de productos`. | Corrected the stale copy expectation. |
| `TelegramIntakeTest::test_telegram_text_update_uses_order_ingestion_when_enabled` | B | The test provides no active catalog products. `STATE_AND_REVIEW_POLICY.md` requires `unmatched` when a parsed line has no active catalog product, with a tentative review order. | Changed the expected `parse_status` from `pending_review` to `unmatched`. |
| `OrderIngestionTest::test_order_ingestion_enriches_fulfillment_plan` | C (test regression) | `para mañana` is relative. The parser resolves it to the next calendar day; the hard-coded `2026-08-11` was stale on the current test date. | Made the expected date relative to `today()`; no application behavior changed. |
| `BusinessScenarioSimulatorTest::test_construction_scenario_creates_large_delivery_orders` | C | The construction definition promises ten delivery orders. Its duplicate seed bypassed the construction-specific delivery rule and randomly produced a pickup, yielding 9 delivery orders. | Restored the scenario invariant in the duplicate-message builder: construction duplicates are delivery orders for today. |

No active route was missing and no production lottery functionality was restored. The `C` item is a deterministic-test regression, not a production parser regression.
