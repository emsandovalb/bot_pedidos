<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Services\OrderDuplicateDetectionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderIngestionService
{
    public function __construct(
        private readonly OrderParserService $orderParserService,
        private readonly ProductMatchingService $productMatchingService,
        private readonly OrderDuplicateDetectionService $orderDuplicateDetectionService,
    ) {
    }

    public function ingest(
        Organization $organization,
        Branch $branch,
        Customer $customer,
        string $rawMessageText,
        string $sourceChannel = 'telegram',
        ?string $externalMessageId = null,
        ?IncomingMessage $incomingMessage = null,
    ): Order {
        $parserResult = $this->orderParserService->parse($rawMessageText);
        $orderNotes = $parserResult['notes_text'] ?? null;

        $order = DB::transaction(function () use ($organization, $branch, $customer, $rawMessageText, $sourceChannel, $externalMessageId, $incomingMessage, $parserResult, $orderNotes): Order {
            $order = Order::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => $incomingMessage?->id,
                'source_channel' => $sourceChannel,
                'external_message_id' => $externalMessageId,
                'status' => Order::STATUS_PENDING_REVIEW,
                'parser_confidence' => $parserResult['confidence'],
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

            foreach ($parserResult['items'] as $index => $item) {
                $match = $this->productMatchingService->match(
                    organization: $organization,
                    productName: $item['product_name'],
                    rawText: $item['raw_text'],
                );

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $match['product']?->id,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'raw_text' => $item['raw_text'],
                    'matched_text' => $match['matched_text'] ?? $item['matched_text'],
                    'confidence_score' => $match['product'] !== null ? $match['confidence_score'] : $item['confidence_score'],
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
                'reason' => $parserResult['needs_review'] ? 'Parsed order needs manual review.' : 'Parsed order ready for review.',
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
                    'parser_confidence' => $parserResult['confidence'],
                    'status' => IncomingMessage::STATUS_PROCESSED,
                    'parse_status' => Order::STATUS_PENDING_REVIEW,
                    'status_reason' => $parserResult['needs_review']
                        ? 'Order parsed and queued for manual review.'
                        : 'Order parsed successfully.',
                    'processed_at' => now(),
                ])->save();
            }

            return $order->load('orderItems.product');
        });

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

        return $order->fresh(['customer', 'incomingMessage', 'orderItems.product', 'possibleDuplicateOf']);
    }
}
