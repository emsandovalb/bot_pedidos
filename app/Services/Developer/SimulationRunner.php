<?php

namespace App\Services\Developer;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

class SimulationRunner
{
    public const MARKER = '[business-scenario]';

    public const CUSTOMER_PREFIX = 'business-scenario:customer:';

    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly MessagingIngestionService $messagingIngestionService,
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function run(Organization $organization, array $messages, array $metadata = []): array
    {
        $start = microtime(true);
        $this->whatsAppConfigurationService->loadConfiguration($organization->id);

        $generatedCustomers = [];
        $generatedOrders = 0;
        $whatsappCount = 0;
        $telegramCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;
        $latestOrder = null;
        $latestIncomingMessage = null;
        $timeline = [];

        foreach ($messages as $index => $message) {
            $provider = strtolower(trim((string) ($message['provider'] ?? 'whatsapp')));
            $customerData = (array) ($message['customer'] ?? []);
            $receivedAt = $this->normalizeCarbon($message['received_at'] ?? now());
            $scenario = (string) ($message['scenario'] ?? ($metadata['scenario'] ?? 'business_scenario'));
            $variant = (string) ($message['variant'] ?? 'standard');

            $customer = $this->upsertGeneratedCustomer($organization, $provider, $customerData, $scenario);
            $generatedCustomers[(string) $customer->external_id] = true;

            $result = match ($provider) {
                'telegram' => $this->runTelegramMessage($organization, $customer, $message, $receivedAt, $scenario),
                'instagram' => [
                    'duplicate' => false,
                    'order' => null,
                    'incoming_message' => null,
                    'status' => 'failed',
                    'message' => 'Instagram is a placeholder only.',
                ],
                default => $this->runWhatsAppMessage($organization, $customer, $message, $receivedAt, $scenario),
            };

            if (($result['duplicate'] ?? false) === true) {
                $duplicateCount++;
            }

            if (($result['order'] ?? null) instanceof Order) {
                $order = $result['order'];
                $this->stampGeneratedOrder($order, $receivedAt, $scenario, $provider, $variant, (string) ($message['message_text'] ?? ''));
                $latestOrder = $order->fresh(['customer', 'possibleDuplicateOf', 'fulfillmentPlan']);
                $generatedOrders++;
            }

            if (($result['incoming_message'] ?? null) instanceof IncomingMessage) {
                $incomingMessage = $result['incoming_message'];
                $this->stampIncomingMessage($incomingMessage, $receivedAt);
                $latestIncomingMessage = $incomingMessage->fresh();
            }

            if ($provider === 'telegram') {
                $telegramCount++;
            } else {
                $whatsappCount++;
            }

            $timeline[] = [
                'time' => $receivedAt->toIso8601String(),
                'provider' => $provider,
                'customer' => $customer->name,
                'variant' => $variant,
            ];

            unset($messages[$index]);
        }

        return [
            'processed_count' => $generatedOrders,
            'ignored_count' => $duplicateCount,
            'failed_count' => $failedCount,
            'generated_customers' => count($generatedCustomers),
            'generated_orders' => $generatedOrders,
            'whatsapp_count' => $whatsappCount,
            'telegram_count' => $telegramCount,
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => (string) ($metadata['message'] ?? 'Business scenario generated.'),
            'order_url' => $latestOrder !== null ? route('orders.show', $latestOrder) : null,
            'incoming_message_url' => $latestIncomingMessage !== null ? route('incoming-messages.index') . '#incoming-message-' . $latestIncomingMessage->id : null,
            'timeline' => $timeline,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function runWhatsAppMessage(Organization $organization, Customer $customer, array $message, Carbon $receivedAt, string $scenario): array
    {
        $rawPayload = $this->buildWhatsAppPayload($customer, (string) ($message['external_message_id'] ?? ''), (string) ($message['message_text'] ?? ''), $receivedAt, $scenario);
        $request = Request::create(
            uri: '/webhooks/whatsapp',
            method: 'POST',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $result = $this->providerLifecycleManager->receiveWebhook('whatsapp', $request);

        $incomingMessage = IncomingMessage::query()
            ->where('organization_id', $organization->id)
            ->where('provider', 'whatsapp')
            ->where('external_message_id', (string) ($message['external_message_id'] ?? ''))
            ->latest('id')
            ->first();

        $order = Order::query()
            ->where('organization_id', $organization->id)
            ->where('source_channel', 'whatsapp')
            ->where('external_message_id', (string) ($message['external_message_id'] ?? ''))
            ->latest('id')
            ->first();

        return [
            'duplicate' => (bool) ($incomingMessage?->status === IncomingMessage::STATUS_DUPLICATE),
            'order' => $order,
            'incoming_message' => $incomingMessage,
            'status' => $result->status ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function runTelegramMessage(Organization $organization, Customer $customer, array $message, Carbon $receivedAt, string $scenario): array
    {
        $branch = $this->branchForProvider($organization, 'telegram');
        $chatId = (string) ($customer->phone ?? '');
        $messageId = (string) ($message['external_message_id'] ?? '');

        $result = $this->messagingIngestionService->ingest(
            organization: $organization,
            branch: $branch,
            message: new IncomingMessageDTO(
                provider: 'telegram',
                external_message_id: $messageId,
                external_chat_id: $chatId,
                received_at: $receivedAt,
                external_user_id: $chatId,
                provider_username: $this->telegramUsername((string) ($customer->name ?? 'Telegram Customer')),
                customer_name: (string) ($customer->name ?? 'Telegram Customer'),
                customer_phone: $chatId,
                metadata: [
                    'source' => 'business_scenario',
                    'scenario' => $scenario,
                ],
                message: (string) ($message['message_text'] ?? ''),
                raw_payload: [
                    'source' => 'business_scenario',
                    'scenario' => $scenario,
                    'message_id' => $messageId,
                ],
                attachments: [],
            ),
        );

        return [
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'order' => $result['order'] ?? null,
            'incoming_message' => $result['incoming_message'] ?? null,
            'status' => $result['status'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    private function upsertGeneratedCustomer(Organization $organization, string $provider, array $customerData, string $scenario): Customer
    {
        $name = trim((string) ($customerData['name'] ?? 'Demo Customer'));
        $phone = trim((string) ($customerData['phone'] ?? ''));
        $externalId = (string) ($customerData['external_id'] ?? self::CUSTOMER_PREFIX . $organization->id . ':' . $scenario . ':' . md5($provider . '|' . $name . '|' . $phone));
        $branch = $this->branchForProvider($organization, $provider);

        $customer = Customer::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'external_id' => $externalId,
            ],
            [
                'branch_id' => $branch->id,
                'name' => $name,
                'phone' => $phone !== '' ? $phone : ($provider === 'telegram' ? '9000' : '50255510000'),
            ]
        );

        return $customer->fresh();
    }

    private function branchForProvider(Organization $organization, string $provider): Branch
    {
        $provider = strtolower(trim($provider));
        $channelType = $provider === 'telegram' ? Branch::CHANNEL_TYPE_TELEGRAM : Branch::CHANNEL_TYPE_WHATSAPP;
        $identifier = $provider === 'telegram'
            ? '@business-scenario-' . $organization->id
            : 'business-scenario-whatsapp-' . $organization->id;

        return Branch::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel_identifier' => $identifier,
            ],
            [
                'name' => $provider === 'telegram' ? 'Business Scenario Telegram' : 'Business Scenario WhatsApp',
                'channel_type' => $channelType,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );
    }

    private function telegramUsername(string $name): ?string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? '');

        return $username !== '' ? $username . '_cr' : null;
    }

    private function buildWhatsAppPayload(Customer $customer, string $externalMessageId, string $messageText, Carbon $receivedAt, string $scenario): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-bizsim-' . substr(md5($externalMessageId), 0, 8),
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => 'PHONE_NUMBER_ID_TEST',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => $customer->name,
                                        ],
                                        'wa_id' => $customer->phone,
                                    ],
                                ],
                                'bizsim_metadata' => [
                                    'source' => 'business_scenario',
                                    'scenario' => $scenario,
                                ],
                                'messages' => [
                                    [
                                        'from' => $customer->phone,
                                        'id' => $externalMessageId,
                                        'timestamp' => (string) $receivedAt->timestamp,
                                        'type' => 'text',
                                        'text' => [
                                            'body' => $messageText,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function stampGeneratedOrder(Order $order, Carbon $receivedAt, string $scenario, string $provider, string $variant, string $messageText): void
    {
        DB::transaction(function () use ($order, $receivedAt, $scenario, $provider, $variant, $messageText): void {
            $notes = sprintf(
                '%s scenario=%s provider=%s variant=%s message="%s"',
                self::MARKER,
                $scenario,
                $provider,
                $variant,
                Str::limit($messageText, 80, ''),
            );

            $order->forceFill([
                'created_at' => $receivedAt,
                'updated_at' => $receivedAt,
                'notes' => $notes,
            ])->save();

            $history = OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->first();

            if ($history !== null) {
                $history->forceFill([
                    'created_at' => $receivedAt,
                ])->save();
            }

            if (str_starts_with($variant, 'duplicate') && $order->possible_duplicate_of_order_id === null) {
                $candidate = Order::query()
                    ->where('organization_id', $order->organization_id)
                    ->where('customer_id', $order->customer_id)
                    ->where('id', '<', $order->id)
                    ->where('raw_message_text', $order->raw_message_text)
                    ->where('notes', 'like', self::MARKER . '%')
                    ->latest('id')
                    ->first();

                if ($candidate !== null) {
                    $order->forceFill([
                        'possible_duplicate_of_order_id' => $candidate->id,
                        'duplicate_score' => 95,
                        'duplicate_reason' => 'Same customer and identical normalized item set within the last 30 minutes.',
                        'duplicate_checked_at' => $receivedAt,
                    ])->save();
                }
            }
        });
    }

    private function stampIncomingMessage(IncomingMessage $incomingMessage, Carbon $receivedAt): void
    {
        $incomingMessage->forceFill([
            'created_at' => $receivedAt,
            'updated_at' => $receivedAt,
            'received_at' => $receivedAt,
        ])->save();
    }

    private function normalizeCarbon(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse((string) $value);
    }
}
