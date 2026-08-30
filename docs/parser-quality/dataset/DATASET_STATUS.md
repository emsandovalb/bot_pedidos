# Dataset status — v1.0.0

Current total: 6 cases. There are 0 real cases, 0 synthetic cases, and 6 test cases. Category distribution: 2 `valid_order`, 1 `not_order`, 1 `invalid`, 1 `unknown_product`, 1 `ambiguous`, and 0 `duplicate`.

There is 1 incomplete case and 1 pending-label case: `PQI-TEST-006`, whose quantities are deliberately unresolved. No accuracy, precision, recall, or other parser-quality metric is reported from this corpus because it is small, contains no approved real messages, and includes an unresolved label.

Limitations: this is a structural seed dataset sourced solely from existing test/baseline examples; it does not represent production language, catalog variation, duplicate evidence, or real privacy edge cases. Add authorized, manually reviewed real cases before using it for performance reporting.
