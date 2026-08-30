# Parser Quality Initiative — updated baseline report

Date: 2026-08-12  
Scope: parser-safety policy only. No channels, AI, UI, fulfillment rules, or new business functionality were added.

## Result

The versioned corpus still has 11 characterization cases; it is a regression corpus, not a labeled accuracy dataset. The safety changes close the two confirmed critical risks:

- An unresolved catalog item is `unmatched`, always requires review, keeps `product_id=null`, and has low operational confidence.
- Empty, whitespace-only, punctuation-only, and known greeting messages are retained as inbound messages but create neither orders nor order items.

The actual path remains `TelegramPollCommand` → `MessagingIngestionService` → `OrderIngestionService` → `OrderParserService` → `ProductMatchingService`. The original text is retained on `IncomingMessage.raw_text` and, where an order is appropriate, on `Order.raw_message_text`.

## Confidence change

The parser’s existing item confidence remains syntax confidence for compatibility. The output now also carries text, structure, product-resolution and final operational confidence. `OrderItem.confidence_score` and `Order.parser_confidence` use final operational confidence after catalog matching.

For example, `2 bolsas de arena` still has strong syntax, but its unresolved catalog line has final confidence `0.15`, status `unmatched`, and `needs_review=true`. A partial phrase such as `2 bolsas de jardinero` no longer resolves the alias `bolsas de jardin`.

## Regression coverage

The suite now explicitly covers:

- Empty and whitespace-only messages.
- A known non-order greeting.
- Unknown product and nonexistent alias behavior.
- Partial-but-invalid alias similarity.
- Valid known product, quantity, and multiple-product behavior through existing parser/ingestion cases.
- Ambiguous fallback behavior requiring review.
- Existing provider/external-message duplicate behavior.

## Pre-existing fulfillment test failure

`OrderIngestionTest::test_order_ingestion_enriches_fulfillment_plan` expects `2026-08-11`, but the current system date is 2026-08-12. `FulfillmentIntentParser::detectDateIntent` deliberately resolves `manana` with `CarbonImmutable::now(config('fulfillment.timezone'))->addDay()`, producing `2026-08-13`. It does not apply a business-day adjustment. The expectation was changed before this phase and was already failing in the baseline run; parser-safety changes do not affect fulfillment parsing. This remains a separate deterministic-test/time-freezing task and its date assertion was not changed here.

## Pending decisions

- Obtain a consented, redacted, human-labeled corpus before measuring accuracy, precision, recall, or false-negative rate.
- Define operator workflow and customer-facing wording for `unmatched` and `pending_review` in the currently enabled channels.
- Decide the approved meaning of incomplete product phrases such as `2 bolsas`.
- Decide whether future fuzzy catalog matching is acceptable and, if so, define reviewable thresholds.
