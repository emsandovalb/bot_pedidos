# Parser evaluation labeling rules

Label the sender's intended request, not the parser's current output. Never add source identifiers, customer context, addresses, or unredacted transcripts to a case.

| Situation | Category | Expected operational status | Example |
|---|---|---|---|
| Clear request with product and quantity | `valid_order` | `pending_review` | `2 cajas de vasos` |
| Greeting, question, thanks, or conversation with no purchase request | `not_order` | `no_order_created` | `hola, tienen vasos?` |
| No usable content or malformed text | `invalid` | `no_order_created` | `???` or empty text |
| Product phrase is clear but absent from the catalog used for evaluation | `unknown_product` | `pending_review` | `2 bolsas de arena` |
| Product, quantity, or intent cannot be determined safely | `ambiguous` | `pending_review` | `ocupo servilletas y vasos` |
| Same source message was already processed, or a confirmed duplicate is supplied with safe evidence | `duplicate` | `duplicate_ignored` | Do not include the original external ID. |

For a valid multi-product message, add one object per item in source order: `2 cajas de vasos y 3 bolsas de jardin` has two product objects. Do not infer a missing quantity. If `quiero vasos` could mean one unit but that is not an agreed policy, use `ambiguous`, leave products empty when the product itself is uncertain, explain why, and set `label_status` to `pending`.

An incomplete message such as `2 bolsas` is `ambiguous` unless the business has a documented, catalog-specific interpretation. Quantity ranges, alternatives, corrections, and contradictions (`2 o 3 cajas`, `una caja... mejor dos`) are also `ambiguous` until a reviewer records a trustworthy intended quantity. Do not mark an uncertain label confirmed merely to balance categories.

`unknown_product` means the text identifies a requested product but catalog matching is expected to fail. It is not a synonym for a typo, an address, or a vague request. Record the requested text as the expected product and preserve the positive quantity.

Each reviewer must run `php artisan parser:validate-evaluation-dataset` before submitting a change. A second reviewer should confirm every real case before changing `label_status` from `pending` to `confirmed`.
