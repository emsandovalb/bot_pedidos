# Parser output contract

`OrderParserService::parse` preserves `raw_text` and returns `normalized_text`, `items`, recognized notes, explicit outcome metadata, and confidence components. The original inbound text is also stored on `IncomingMessage.raw_text`; when an order is created it is copied to `Order.raw_message_text`.

## Outcome states

| State | When used | System action | Creates order | Human review | Preserved information / response |
|---|---|---|---:|---:|---|
| `parsed` | A structured candidate was extracted. Catalog resolution may still change it. | Continue to catalog resolution and persist as `pending_review` workflow state. | Yes, only if all later safeguards allow it. | No parser-level trigger. | Full parser payload; normal acknowledgement. |
| `pending_review` | The syntax is incomplete, uses fallback, or lacks enough structure to safely interpret automatically. | Create a tentative `pending_review` order; do not represent it as confirmed. | Yes. | Yes. | Candidate text, reason, warnings, and source text; advise that an operator will review it. |
| `unmatched` | Syntax produced items but one or more active catalog products did not resolve. This is assigned by `OrderIngestionService`, not by text parsing alone. | Create a tentative `pending_review` order with no product ID for unresolved lines. | Yes. | Yes. | Per-item resolution result, warning, reason, aliases tried indirectly through input, and source text; advise that catalog review is needed. |
| `not_order` | A known existing greeting or command is received. | Persist the inbound message as processed without an order or order items. | No. | No. | Source text, status, reason and warning; reply with ordering guidance where the channel supports replies. |
| `invalid` | The normalized message is empty (including whitespace or punctuation-only input). | Persist the inbound message as processed without an order or order items. | No. | No. | Exact source text, status, reason and warning; request a usable order message where replies are supported. |

`duplicate` is intentionally not a parser state: duplicate detection already belongs to `MessagingIngestionService` (provider/external-message identity) and `OrderDuplicateDetectionService` (possible duplicate order metadata).

## Confidence semantics

| Field | Meaning |
|---|---|
| `text_confidence` | Confidence that text patterns were recognized. |
| `structure_confidence` | Confidence that quantities, units and product phrases form an order structure. |
| `product_resolution.confidence` | Catalog matcher confidence; `0.15` when no active alias resolves. |
| `final_confidence` / legacy `confidence` | Operational confidence persisted to the order. It is the mean final item confidence and is capped below `0.85` whenever review is required. |

`items[].confidence_score` remains the syntax confidence for compatibility. After catalog resolution, `items[].final_confidence_score` and `OrderItem.confidence_score` are operational confidence. An unresolved item can therefore never retain high operational confidence merely because its syntax was strong.

## Compatibility

`needs_review` is retained for existing consumers. `Order.status` remains the pre-existing workflow state `pending_review`; parser `status` is classification metadata and must not be confused with that workflow state.
