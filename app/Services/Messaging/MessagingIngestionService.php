<?php

namespace App\Services\Messaging;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\IncomingMessage;
use App\Models\Organization;
use App\Services\CustomerIdentityResolver;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\OrderIngestionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class MessagingIngestionService
{
    public function __construct(
        private readonly OrderIngestionService $orderIngestionService,
        private readonly CustomerIdentityResolver $customerIdentityResolver,
    ) {}

    /**
     * Recibe un mensaje entrante de forma completa y síncrona:
     * lo persiste (acceptInbound) y lo procesa hasta pedido (processInbound).
     *
     * Lo usan el poller de Telegram y el developer toolkit, que necesitan el
     * resultado al momento. El webhook HTTP usa acceptInbound + un job.
     *
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
        $accepted = $this->acceptInbound($organization, $branch, $message);

        if ($accepted['duplicate']) {
            return $accepted;
        }

        return $this->processInbound($accepted['incoming_message']);
    }

    /**
     * Parte síncrona y barata: deduplicación, resolución del cliente, ventana de
     * servicio y creación de la fila `incoming_messages` en estado `received`.
     * No ejecuta el parser ni crea el pedido.
     *
     * @return array{
     *     duplicate: bool,
     *     incoming_message: IncomingMessage,
     *     customer: Customer,
     *     status: string,
     *     existing_status?: string
     * }
     */
    public function acceptInbound(Organization $organization, Branch $branch, IncomingMessageDTO $message): array
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
                    ?? $this->resolveCustomer($organization, $branch, $message)['customer'];

                return [
                    'duplicate' => true,
                    'incoming_message' => $existingMessage,
                    'order' => $existingMessage->order,
                    'customer' => $resolvedCustomer,
                    'status' => IncomingMessage::STATUS_DUPLICATE,
                    'existing_status' => $existingMessage->status,
                ];
            }

            $customerContext = $this->resolveCustomer($organization, $branch, $message);
            $customer = $customerContext['customer'];
            $customerIdentity = $customerContext['customer_identity'];

            if ($provider === 'whatsapp' && $customerIdentity !== null) {
                $receivedAt = Carbon::instance($message->received_at);

                $customerIdentity->forceFill([
                    'last_customer_message_at' => $receivedAt,
                    'service_window_expires_at' => $receivedAt->copy()->addHours(24),
                ])->save();
            }

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
                'status_reason' => 'Inbound message accepted for processing.',
            ])->save();

            return [
                'duplicate' => false,
                'incoming_message' => $incomingMessage,
                'customer' => $customer->fresh(),
                'status' => IncomingMessage::STATUS_RECEIVED,
            ];
        });
    }

    /**
     * Parte cara: corre el parser y crea/actualiza el pedido a partir de una fila
     * `incoming_messages` ya persistida. Idempotente: si el mensaje ya está
     * procesado, no vuelve a hacer nada.
     *
     * @return array{
     *     duplicate: bool,
     *     incoming_message: IncomingMessage,
     *     order: mixed,
     *     customer: Customer,
     *     status: string,
     *     failure_reason?: string
     * }
     */
    public function processInbound(IncomingMessage $incomingMessage): array
    {
        $incomingMessage->loadMissing(['organization', 'branch', 'customer']);

        if (in_array($incomingMessage->status, [IncomingMessage::STATUS_PROCESSED, IncomingMessage::STATUS_DUPLICATE], true)) {
            return [
                'duplicate' => $incomingMessage->status === IncomingMessage::STATUS_DUPLICATE,
                'incoming_message' => $incomingMessage,
                'order' => $incomingMessage->order,
                'customer' => $incomingMessage->customer,
                'status' => $incomingMessage->status,
            ];
        }

        $customer = $incomingMessage->customer;
        $provider = (string) $incomingMessage->provider;

        $incomingMessage->forceFill([
            'status' => IncomingMessage::STATUS_PROCESSING,
        ])->save();

        try {
            $order = $this->orderIngestionService->ingest(
                organization: $incomingMessage->organization,
                branch: $incomingMessage->branch,
                customer: $customer,
                rawMessageText: (string) $incomingMessage->raw_text,
                sourceChannel: $provider,
                externalMessageId: (string) $incomingMessage->external_message_id,
                incomingMessage: $incomingMessage,
            );

            $incomingMessage->refresh();
            $incomingMessage->forceFill([
                'status' => IncomingMessage::STATUS_PROCESSED,
                'status_reason' => $incomingMessage->status_reason ?: 'Inbound message processed successfully.',
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
    }

    /**
     * @return array{customer: Customer, customer_identity: CustomerIdentity}
     */
    private function resolveCustomer(Organization $organization, Branch $branch, IncomingMessageDTO $message): array
    {
        $resolution = $this->customerIdentityResolver->resolve(
            organizationId: $organization->id,
            provider: $message->provider,
            externalUserId: $message->external_user_id,
            externalChatId: $message->external_chat_id,
            providerUsername: $message->provider_username,
            phone: $message->customer_phone,
            displayName: $message->customer_name,
            email: $message->email,
            metadata: $message->metadata,
        );

        /** @var Customer $customer */
        $customer = $resolution['customer'];
        $customerIdentity = $resolution['customer_identity'];

        $updates = [];

        if ($customer->branch_id !== $branch->id) {
            $updates['branch_id'] = $branch->id;
        }

        if (filled($message->customer_name) && blank($customer->name)) {
            $updates['name'] = $message->customer_name;
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return [
            'customer' => $customer->fresh(),
            'customer_identity' => $customerIdentity->fresh(['customer']),
        ];
    }
}
