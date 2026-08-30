# Parser evaluation dataset schema

The versioned evaluation dataset lives in `docs/parser-quality/dataset/v1/cases.json`. It contains only anonymized text and no production identifiers. A major schema change creates a new versioned directory; additive fields are allowed in the same major version after the validator is updated.

```json
{
  "dataset_version": "1.0.0",
  "cases": [{
    "id": "PQI-REAL-000001",
    "anonymized_text": "2 cajas de producto",
    "category": "valid_order",
    "expected_products": [{"name": "producto", "quantity": 2, "unit": "caja"}],
    "expected_operational_status": "pending_review",
    "annotator_notes": "Reason for the label or uncertainty.",
    "source": {"type": "real", "reference": "approved-local-export"},
    "incorporated_on": "YYYY-MM-DD",
    "dataset_version": "1.0.0",
    "label_status": "confirmed"
  }]
}
```

`id` is a newly assigned anonymous identifier, never a database, customer, message, Telegram, or order ID. `source.reference` is a general provenance label or repository path, never an external identifier. `source.type` is `real`, `synthetic`, or `test`. The current corpus has no `real` records.

Allowed categories are `valid_order`, `not_order`, `ambiguous`, `invalid`, `unknown_product`, and `duplicate`. Allowed operational expectations are `pending_review`, `no_order_created`, and `duplicate_ignored`. `expected_products` uses human-readable catalog-neutral names and positive numeric quantities; it is required for `valid_order` and `unknown_product`, empty for `not_order`, `invalid`, and `duplicate`, and may be empty for an unresolved `ambiguous` case. `label_status` is `confirmed` or `pending`; uncertain labels must be `pending` and are excluded from future accuracy reporting.
