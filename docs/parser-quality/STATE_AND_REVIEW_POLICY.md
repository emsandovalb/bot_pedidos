# Parser state and review policy

This policy prevents uncertain text from looking like a valid operational order. It applies only to the existing order-ingestion path.

| State | Trigger | System action | Can create an order? | Human review? | Retained information and response |
|---|---|---|---:|---:|---|
| `parsed` | Structured syntax extracted. | Resolve products before operational use. | Yes, as existing `pending_review` workflow. | No parser trigger. | Source, parser payload; normal receipt acknowledgement. |
| `pending_review` | Incomplete, ambiguous, or fallback structure. | Create only a tentative review order. | Yes. | Yes. | Source, candidates, warning and reason; say review is needed. |
| `unmatched` | A parsed line has no active catalog product. | Persist tentative review order, leaving product ID null. | Yes. | Yes. | Source, per-item resolution result, warning and reason; say catalog review is needed. |
| `not_order` | Existing known greeting or command. | Persist inbound message with no order/items. | No. | No. | Exact source, reason/warning; offer ordering guidance where replies exist. |
| `invalid` | Empty after normalization, including whitespace/punctuation only. | Persist inbound message with no order/items. | No. | No. | Exact source, reason/warning; request usable order text where replies exist. |

`duplicate` deliberately remains outside parser classification. Existing message-identity and possible-order duplicate mechanisms already own that behavior.

## Confidence guardrails

`text_confidence` is pattern recognition confidence; `structure_confidence` is order-shape confidence; `product_resolution.confidence` is active-catalog resolution confidence; `final_confidence` is the operational value. High final confidence is prohibited for invalid input, non-orders, unresolved products, and review-required ambiguity. The current parser has no independent contradiction detector, so any future quantity/product contradiction policy requires labeled examples and is deferred.
