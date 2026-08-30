<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Services\Fulfillment\FulfillmentPlannerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderIngestionService
{
    public function __construct(
        private readonly OrderParserService $orderParserService,
        private readonly ProductMatchingService $productMatchingService,
        private readonly OrderDuplicateDetectionService $orderDuplicateDetectionService,
        private readonly OrderNotificationDispatcher $orderNotificationDispatcher,
        private readonly FulfillmentPlannerService $fulfillmentPlannerService,
    ) {}

    public function ingest(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $rawMessageText,
        string $sourceChannel = 'telegram',
        ?string $externalMessageId = null,
        ?IncomingMessage $incomingMessage = null,
    ): ?Order {
        $parserResult = $this->orderParserService->parse($rawMessageText);
        $orderNotes = $parserResult['notes_text'] ?? null;

        if (in_array($parserResult['status'], [OrderParserService::STATUS_INVALID, OrderParserService::STATUS_NOT_ORDER], true)) {
            if ($incomingMessage !== null) {
                $incomingMessage->forceFill([
                    'parser_result_json' => $parserResult,
                    'parser_confidence' => $parserResult['final_confidence'],
                    'status' => IncomingMessage::STATUS_PROCESSED,
                    'parse_status' => $parserResult['status'],
                    'status_reason' => $parserResult['review_reason'],
                    'processed_at' => now(),
                ])->save();
            }

            return null;
        }

        $order = DB::transaction(function () use ($organization, $branch, $customer, $rawMessageText, $sourceChannel, $externalMessageId, $incomingMessage, $parserResult, $orderNotes): Order {
            $resolvedItems = [];

            foreach ($parserResult['items'] as $item) {
                $match = $this->productMatchingService->match(
                    organization: $organization,
                    productName: $item['product_name'],
                    rawText: $item['raw_text'],
                );

                $resolvedItems[] = [
                    'item' => $item,
                    'match' => $match,
                ];
            }

            $hasUnmatchedProduct = collect($resolvedItems)->contains(static fn (array $resolved): bool => $resolved['match']['product'] === null);

            if ($hasUnmatchedProduct) {
                $parserResult['status'] = OrderParserService::STATUS_UNMATCHED;
                $parserResult['needs_review'] = true;
                $parserResult['review_reason'] = 'One or more parsed products could not be resolved in the active catalog.';
                $parserResult['warnings'][] = 'No unresolved product can be treated as a valid catalog product.';
            }

            $finalConfidenceValues = [];
            foreach ($resolvedItems as $index => $resolved) {
                $item = $resolved['item'];
                $match = $resolved['match'];
                $productResolved = $match['product'] !== null;
                $finalItemConfidence = $productResolved
                    ? min((float) $item['confidence_score'], (float) $match['confidence_score'])
                    : (float) $match['confidence_score'];

                $finalConfidenceValues[] = $finalItemConfidence;
                $parserResult['items'][$index]['product_resolution'] = [
                    'resolved' => $productResolved,
                    'confidence' => (float) $match['confidence_score'],
                    'matched_text' => $match['matched_text'],
                ];
                $parserResult['items'][$index]['final_confidence_score'] = round($finalItemConfidence, 2);
            }

            $finalConfidence = round(array_sum($finalConfidenceValues) / max(count($finalConfidenceValues), 1), 2);
            if ($parserResult['needs_review']) {
                $finalConfidence = min($finalConfidence, 0.84);
            }
            $parserResult['final_confidence'] = $finalConfidence;
            $parserResult['confidence'] = $finalConfidence;

            $order = Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => $incomingMessage?->id,
                'source_channel' => $sourceChannel,
                'external_message_id' => $externalMessageId,
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => $parserResult['final_confidence'],
                'raw_message_text' => $rawMessageText,
                'parsed_payload_json' => $parserResult,
                'notes' => $orderNotes !== '' ? $orderNotes : null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'preparing_at' => null,
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ]);

            foreach ($resolvedItems as $index => $resolved) {
                $item = $resolved['item'];
                $match = $resolved['match'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $match['product']?->id,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'raw_text' => $item['raw_text'],
                    'matched_text' => $match['matched_text'] ?? $item['matched_text'],
                    'confidence_score' => $parserResult['items'][$index]['final_confidence_score'],
                    'notes' => null,
                    'sort_order' => $index,
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING_REVIEW,
                'changed_by_user_id' => null,
                'changed_via' => 'system',
                'reason' => $parserResult['review_reason'] ?? ($parserResult['needs_review'] ? 'Parsed order needs manual review.' : 'Parsed order ready for review.'),
                'metadata_json' => [
                    'parser_result' => $parserResult,
                    'source_channel' => $sourceChannel,
                ],
                'created_at' => now(),
            ]);

            if ($incomingMessage !== null) {
                $incomingMessage->forceFill([
                    'order_id' => $order->id,
                    'parser_result_json' => $parserResult,
                    'parser_confidence' => $parserResult['final_confidence'],
                    'status' => IncomingMessage::STATUS_PROCESSED,
                    'parse_status' => $parserResult['status'],
                    'status_reason' => $parserResult['review_reason'] ?? 'Order parsed successfully.',
                    'processed_at' => now(),
                ])->save();
            }

            $this->orderNotificationDispatcher->dispatch($order, 'order_created');

            return $order->load('orderItems.product');
        });

        try {
            $this->fulfillmentPlannerService->parseIntentFromMessage($order, $rawMessageText);
        } catch (\Throwable $exception) {
            Log::warning('Fulfillment intent enrichment failed.', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        try {
            $duplicateResult = $this->orderDuplicateDetectionService->detect($order);
            $duplicateUpdates = [
                'duplicate_checked_at' => now(),
                'order_fingerprint' => $duplicateResult['fingerprint'],
            ];

            if (($duplicateResult['matched_order'] ?? null) instanceof Order && (float) ($duplicateResult['score'] ?? 0) >= 75) {
                $duplicateUpdates['possible_duplicate_of_order_id'] = $duplicateResult['matched_order']->id;
                $duplicateUpdates['duplicate_score'] = $duplicateResult['score'];
                $duplicateUpdates['duplicate_reason'] = $duplicateResult['reason'];
            }

            $order->forceFill($duplicateUpdates)->save();
        } catch (\Throwable $exception) {
            Log::warning('Order duplicate detection failed.', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $order->fresh(['customer', 'incomingMessage', 'orderItems.product', 'possibleDuplicateOf', 'fulfillmentPlan']);
    }
}
