<?php

namespace App\Services\Messaging;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\OrderIngestionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class MessagingIngestionService
{
    public function __construct(
        private readonly OrderIngestionService $orderIngestionService,
    ) {
    }

    /**
     * @return array{
     *     duplicate: bool,
     *     incoming_message: IncomingMessage,
     *     order: mixed,
     *     customer: Customer,
     *     status: string,
     *     failure_reason?: string
     * }
     */
    public function ingest(Organization $organization, Branch $branch, IncomingMessageDTO $message): array
    {
        return DB::transaction(function () use ($organization, $branch, $message): array {
            $provider = strtolower(trim($message->provider));
            $externalMessageId = trim($message->external_message_id);

            if ($provider === '') {
                throw new InvalidArgumentException('Incoming message provider is required.');
            }

            if ($externalMessageId === '') {
                throw new InvalidArgumentException('Incoming message external_message_id is required.');
            }

            $existingMessage = IncomingMessage::query()
                ->where('organization_id', $organization->id)
                ->where('provider', $provider)
                ->where('external_message_id', $externalMessageId)
                ->first();

            if ($existingMessage !== null) {
                $existingMessage->loadMissing(['customer', 'order']);
                $resolvedCustomer = $existingMessage->customer
                    ?? $existingMessage->order?->customer
                    ?? $this->resolveCustomer(
                        organization: $organization,
                        branch: $branch,
                        phone: $message->customer_phone,
                        name: $message->customer_name,
                    );

                return [
                    'duplicate' => true,
                    'incoming_message' => $existingMessage,
                    'order' => $existingMessage->order,
                    'customer' => $resolvedCustomer,
                    'status' => IncomingMessage::STATUS_DUPLICATE,
                    'existing_status' => $existingMessage->status,
                ];
            }

            $customer = $this->resolveCustomer(
                organization: $organization,
                branch: $branch,
                phone: $message->customer_phone,
                name: $message->customer_name,
            );

            try {
                $incomingMessage = IncomingMessage::create([
                    'organization_id' => $organization->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'provider' => $provider,
                    'channel_type' => $provider,
                    'from_identifier' => $message->external_chat_id,
                    'to_identifier' => $branch->channel_identifier,
                    'raw_text' => $message->message ?? '',
                    'payload_json' => [
                        'provider' => $provider,
                        'raw_payload' => $message->raw_payload,
                        'attachments' => $message->attachments,
                    ],
                    'external_message_id' => $externalMessageId,
                    'status' => IncomingMessage::STATUS_RECEIVED,
                    'received_at' => $message->received_at,
                ]);
            } catch (QueryException $exception) {
                $duplicateMessage = IncomingMessage::query()
                    ->where('organization_id', $organization->id)
                    ->where('provider', $provider)
                    ->where('external_message_id', $externalMessageId)
                    ->firstOrFail();

                $duplicateMessage->loadMissing(['customer', 'order']);

                return [
                    'duplicate' => true,
                    'incoming_message' => $duplicateMessage,
                    'order' => $duplicateMessage->order,
                    'customer' => $duplicateMessage->customer ?? $customer,
                    'status' => IncomingMessage::STATUS_DUPLICATE,
                    'existing_status' => $duplicateMessage->status,
                ];
            }

            $incomingMessage->forceFill([
                'status' => IncomingMessage::STATUS_PROCESSING,
                'status_reason' => 'Inbound message accepted for processing.',
            ])->save();

            try {
                $order = $this->orderIngestionService->ingest(
                    organization: $organization,
                    branch: $branch,
                    customer: $customer,
                    rawMessageText: $message->message ?? '',
                    sourceChannel: $provider,
                    externalMessageId: $externalMessageId,
                    incomingMessage: $incomingMessage,
                );

                $incomingMessage->refresh();
                $incomingMessage->forceFill([
                    'status' => IncomingMessage::STATUS_PROCESSED,
                    'status_reason' => 'Inbound message processed successfully.',
                    'processed_at' => now(),
                ])->save();

                return [
                    'duplicate' => false,
                    'incoming_message' => $incomingMessage->fresh(),
                    'order' => $order,
                    'customer' => $customer->fresh(),
                    'status' => IncomingMessage::STATUS_PROCESSED,
                ];
            } catch (Throwable $exception) {
                $failureReason = $exception->getMessage();

                $incomingMessage->forceFill([
                    'status' => IncomingMessage::STATUS_FAILED,
                    'parser_result_json' => [
                        'failure_reason' => $failureReason,
                        'provider' => $provider,
                        'raw_payload' => $message->raw_payload,
                        'attachments' => $message->attachments,
                    ],
                    'parse_status' => IncomingMessage::STATUS_FAILED,
                    'status_reason' => $failureReason,
                    'processed_at' => now(),
                ])->save();

                return [
                    'duplicate' => false,
                    'incoming_message' => $incomingMessage->fresh(),
                    'order' => null,
                    'customer' => $customer->fresh(),
                    'status' => IncomingMessage::STATUS_FAILED,
                    'failure_reason' => $failureReason,
                ];
            }
        });
    }

    private function resolveCustomer(Organization $organization, Branch $branch, string $phone, ?string $name): Customer
    {
        $customer = Customer::query()
            ->where('organization_id', $organization->id)
            ->where('phone', $phone)
            ->first();

        if ($customer === null) {
            return Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => $name,
                'phone' => $phone,
                'external_id' => null,
            ]);
        }

        $updates = [];

        if ($customer->branch_id !== $branch->id) {
            $updates['branch_id'] = $branch->id;
        }

        if ($name && blank($customer->name)) {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer->fresh();
    }
}
