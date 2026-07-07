<?php

namespace App\Services\Developer;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScenarioGeneratorService
{
    private const TOOLKIT_MARKER = '[developer-toolkit]';

    private const DEMO_CUSTOMER_PREFIX = 'developer-toolkit:customer:small-hardware-store:';

    private const DEMO_MESSAGE_PREFIX = 'demo-';

    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
        private readonly MessagingIngestionService $messagingIngestionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generateSmallHardwareStoreScenario(Organization $organization): array
    {
        $start = microtime(true);
        $runToken = now()->format('YmdHisv');

        $this->whatsAppConfigurationService->loadConfiguration($organization->id);

        $customerSpecs = $this->customerSpecs();
        $customers = [];

        foreach ($customerSpecs as $spec) {
            $customers[$spec['key']] = $this->upsertDemoCustomer($organization, $spec);
        }

        $messages = $this->buildScenarioMessages($runToken, $customerSpecs, $customers);

        $generatedOrders = 0;
        $whatsappOrders = 0;
        $telegramOrders = 0;
        $duplicateOrders = 0;
        $latestOrder = null;
        $latestIncomingMessage = null;

        foreach ($messages as $index => $payload) {
            $provider = $payload['provider'];
            $customer = $customers[$payload['customer_key']];
            $receivedAt = $payload['received_at'];
            $messageId = $payload['external_message_id'];
            $messageText = $payload['message_text'];
            $targetStatus = $payload['target_status'];

            $result = $provider === 'telegram'
                ? $this->ingestTelegramGeneratedPayload($organization, $customer, $messageId, $messageText, $receivedAt)
                : $this->ingestWhatsAppGeneratedPayload($organization, $customer, $messageId, $messageText, $receivedAt);

            $order = $result['order'] ?? null;
            $incomingMessage = $result['incoming_message'] ?? null;

            if ($order instanceof Order) {
                $this->tagGeneratedOrder(
                    order: $order,
                    customer: $customer,
                    provider: $provider,
                    messageText: $messageText,
                    variant: $payload['variant'],
                );

                if ($targetStatus !== Order::STATUS_PENDING_REVIEW) {
                    $this->transitionOrder($order, $targetStatus);
                }

                if ($order->possible_duplicate_of_order_id !== null) {
                    $duplicateOrders++;
                }

                $latestOrder = $order->fresh(['customer', 'possibleDuplicateOf']);
                $generatedOrders++;
            }

            if ($incomingMessage instanceof IncomingMessage) {
                $latestIncomingMessage = $incomingMessage->fresh();
            }

            if ($provider === 'whatsapp') {
                $whatsappOrders++;
            } else {
                $telegramOrders++;
            }

            unset($messages[$index]);
        }

        $vipCustomers = EloquentCollection::make($customers)
            ->filter(static fn (Customer $customer): bool => str_starts_with((string) $customer->external_id, self::DEMO_CUSTOMER_PREFIX . 'vip-'))
            ->count();

        return [
            'processed_count' => $generatedOrders,
            'ignored_count' => $duplicateOrders,
            'failed_count' => 0,
            'customers_created' => count($customerSpecs),
            'generated_customers' => count($customerSpecs),
            'orders_created' => $generatedOrders,
            'generated_orders' => $generatedOrders,
            'whatsapp_orders' => $whatsappOrders,
            'whatsapp_count' => $whatsappOrders,
            'telegram_orders' => $telegramOrders,
            'telegram_count' => $telegramOrders,
            'vip_customers' => $vipCustomers,
            'vip_count' => $vipCustomers,
            'duplicate_orders' => $duplicateOrders,
            'duplicate_count' => $duplicateOrders,
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => 'Escenario Ferreteria pequena generado.',
            'order_url' => $latestOrder !== null ? route('orders.show', $latestOrder) : null,
            'incoming_message_url' => $latestIncomingMessage !== null
                ? route('incoming-messages.index') . '#incoming-message-' . $latestIncomingMessage->id
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resetGeneratedDemoData(Organization $organization): array
    {
        $start = microtime(true);
        $deletedOrders = 0;
        $deletedIncomingMessages = 0;
        $deletedCustomerIdentities = 0;
        $deletedCustomers = 0;

        DB::transaction(function () use ($organization, &$deletedOrders, &$deletedIncomingMessages, &$deletedCustomerIdentities, &$deletedCustomers): void {
            $demoCustomerIds = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('external_id', 'like', self::DEMO_CUSTOMER_PREFIX . '%')
                ->pluck('id');

            $deletedOrders = Order::query()
                ->where('organization_id', $organization->id)
                ->where(function ($query): void {
                    $query->where('external_message_id', 'like', self::DEMO_MESSAGE_PREFIX . 'whatsapp-%')
                        ->orWhere('external_message_id', 'like', self::DEMO_MESSAGE_PREFIX . 'telegram-%');
                })
                ->delete();

            $deletedIncomingMessages = IncomingMessage::query()
                ->where('organization_id', $organization->id)
                ->where(function ($query): void {
                    $query->where('external_message_id', 'like', self::DEMO_MESSAGE_PREFIX . 'whatsapp-%')
                        ->orWhere('external_message_id', 'like', self::DEMO_MESSAGE_PREFIX . 'telegram-%');
                })
                ->delete();

            $deletedCustomerIdentities = CustomerIdentity::query()
                ->where('organization_id', $organization->id)
                ->where(function ($query): void {
                    $query->where('metadata_json->demo_generated', true)
                        ->orWhere('metadata_json->source', 'developer-toolkit');
                })
                ->delete();

            $demoCustomerModels = Customer::query()
                ->whereIn('id', $demoCustomerIds)
                ->with(['orders' => function ($query): void {
                    $query->select('id', 'customer_id', 'external_message_id', 'notes', 'organization_id');
                }])
                ->get()
                ->filter(function (Customer $customer): bool {
                    return $customer->orders->every(function (Order $order): bool {
                        $messageId = (string) ($order->external_message_id ?? '');
                        $notes = (string) ($order->notes ?? '');

                        return str_starts_with($messageId, self::DEMO_MESSAGE_PREFIX . 'whatsapp-')
                            || str_starts_with($messageId, self::DEMO_MESSAGE_PREFIX . 'telegram-')
                            || str_starts_with($notes, self::TOOLKIT_MARKER);
                    });
                });

            if ($demoCustomerModels->isNotEmpty()) {
                $deletedCustomers = Customer::query()
                    ->whereIn('id', $demoCustomerModels->pluck('id'))
                    ->delete();
            }
        });

        return [
            'processed_count' => 0,
            'ignored_count' => 0,
            'failed_count' => 0,
            'customers_created' => 0,
            'generated_customers' => 0,
            'orders_created' => 0,
            'generated_orders' => 0,
            'whatsapp_orders' => 0,
            'whatsapp_count' => 0,
            'telegram_orders' => 0,
            'telegram_count' => 0,
            'vip_customers' => 0,
            'vip_count' => 0,
            'duplicate_orders' => 0,
            'duplicate_count' => 0,
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => sprintf(
                'Reset completed: %d orders, %d messages, %d identities and %d customers removed.',
                $deletedOrders,
                $deletedIncomingMessages,
                $deletedCustomerIdentities,
                $deletedCustomers,
            ),
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    /**
     * @return array<int, array{key: string, name: string, phone: string, provider: string, vip: bool}>
     */
    private function customerSpecs(): array
    {
        return [
            ['key' => 'vip-1', 'name' => 'Maria Lopez', 'phone' => '50255530001', 'provider' => 'whatsapp', 'vip' => true],
            ['key' => 'vip-2', 'name' => 'Jose Vargas', 'phone' => '50255530002', 'provider' => 'whatsapp', 'vip' => true],
            ['key' => 'vip-3', 'name' => 'Ana Rojas', 'phone' => '50255530003', 'provider' => 'whatsapp', 'vip' => true],
            ['key' => 'wa-regular-1', 'name' => 'Carla Castro', 'phone' => '50255530004', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-2', 'name' => 'Luis Mora', 'phone' => '50255530005', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-3', 'name' => 'Diana Soto', 'phone' => '50255530006', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-4', 'name' => 'Pablo Chaves', 'phone' => '50255530007', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-5', 'name' => 'Sofia Ramirez', 'phone' => '50255530008', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-6', 'name' => 'Javier Perez', 'phone' => '50255530009', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'wa-regular-7', 'name' => 'Andrea Gonzalez', 'phone' => '50255530010', 'provider' => 'whatsapp', 'vip' => false],
            ['key' => 'tg-regular-1', 'name' => 'Kevin Ortiz', 'phone' => '9001', 'provider' => 'telegram', 'vip' => false],
            ['key' => 'tg-regular-2', 'name' => 'Lucia Calderon', 'phone' => '9002', 'provider' => 'telegram', 'vip' => false],
            ['key' => 'tg-regular-3', 'name' => 'Mateo Navarro', 'phone' => '9003', 'provider' => 'telegram', 'vip' => false],
            ['key' => 'tg-regular-4', 'name' => 'Elena Vargas', 'phone' => '9004', 'provider' => 'telegram', 'vip' => false],
            ['key' => 'tg-regular-5', 'name' => 'Raul Rojas', 'phone' => '9005', 'provider' => 'telegram', 'vip' => false],
        ];
    }

    /**
     * @param  array<int, array{key: string, name: string, phone: string, provider: string, vip: bool}>  $customerSpecs
     * @param  array<string, Customer>  $customers
     * @return array<int, array<string, mixed>>
     */
    private function buildScenarioMessages(string $runToken, array $customerSpecs, array $customers): array
    {
        $plan = [
            ['provider' => 'whatsapp', 'customer_key' => 'vip-1', 'message_text' => '2 bolsas de jardin', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-1', 'message_text' => '5 tubos pvc', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-1', 'message_text' => '10 tornillos', 'target_status' => Order::STATUS_PREPARING, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-2', 'message_text' => '3 sacos de cemento', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-2', 'message_text' => '2 galones de pintura blanca', 'target_status' => Order::STATUS_PREPARING, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-2', 'message_text' => '1 llave de paso', 'target_status' => Order::STATUS_READY_FOR_DISPATCH, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-3', 'message_text' => '8 codos pvc', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-3', 'message_text' => '4 brochas', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'vip-3', 'message_text' => '15 bloques', 'target_status' => Order::STATUS_DISPATCHED, 'variant' => 'vip'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-1', 'message_text' => '2 bolsas de jardin', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'duplicate_seed'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-1', 'message_text' => '2 bolsas de jardin urgente', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'duplicate_match'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-2', 'message_text' => '5 tubos pvc', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'duplicate_seed'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-2', 'message_text' => '5 tubos pvc para hoy', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'duplicate_match'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-3', 'message_text' => '1 manguera', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-4', 'message_text' => '3 cajas de clavos', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-5', 'message_text' => '6 laminas zinc', 'target_status' => Order::STATUS_PREPARING, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-6', 'message_text' => '15 bloques', 'target_status' => Order::STATUS_READY_FOR_DISPATCH, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-7', 'message_text' => '3 sacos de cemento', 'target_status' => Order::STATUS_DISPATCHED, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-3', 'message_text' => '4 brochas para repintar', 'target_status' => Order::STATUS_CANCELLED, 'variant' => 'standard'],
            ['provider' => 'whatsapp', 'customer_key' => 'wa-regular-4', 'message_text' => '8 codos pvc', 'target_status' => Order::STATUS_READY_FOR_DISPATCH, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-1', 'message_text' => '2 bolsas de jardin', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-1', 'message_text' => '5 tubos pvc', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-2', 'message_text' => '10 tornillos', 'target_status' => Order::STATUS_PREPARING, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-2', 'message_text' => '3 sacos de cemento', 'target_status' => Order::STATUS_READY_FOR_DISPATCH, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-3', 'message_text' => '2 galones de pintura blanca', 'target_status' => Order::STATUS_DISPATCHED, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-3', 'message_text' => '1 llave de paso', 'target_status' => Order::STATUS_CANCELLED, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-4', 'message_text' => '8 codos pvc', 'target_status' => Order::STATUS_PENDING_REVIEW, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-4', 'message_text' => '4 brochas', 'target_status' => Order::STATUS_CONFIRMED, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-5', 'message_text' => '15 bloques', 'target_status' => Order::STATUS_PREPARING, 'variant' => 'standard'],
            ['provider' => 'telegram', 'customer_key' => 'tg-regular-5', 'message_text' => '3 cajas de clavos', 'target_status' => Order::STATUS_READY_FOR_DISPATCH, 'variant' => 'standard'],
        ];

        $messages = [];

        foreach ($plan as $index => $entry) {
            $provider = $entry['provider'];
            $customer = $customers[$entry['customer_key']];

            $messages[] = [
                'provider' => $provider,
                'customer_key' => $entry['customer_key'],
                'customer' => $customer,
                'message_text' => $entry['message_text'],
                'target_status' => $entry['target_status'],
                'variant' => $entry['variant'],
                'external_message_id' => sprintf(
                    '%s%s-%s-%02d',
                    self::DEMO_MESSAGE_PREFIX,
                    $provider,
                    self::slugify('small-hardware-store-' . $runToken),
                    $index + 1,
                ),
                'received_at' => now()->subMinutes((count($plan) - $index) * 4),
            ];
        }

        return $messages;
    }

    private function upsertDemoCustomer(Organization $organization, array $spec): Customer
    {
        $externalId = self::DEMO_CUSTOMER_PREFIX . $spec['key'];

        $customer = Customer::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'external_id' => $externalId,
            ],
            [
                'branch_id' => $this->branchForProvider($organization, $spec['provider'])->id,
                'name' => $spec['name'],
                'phone' => $spec['phone'],
            ]
        );

        return $customer->fresh();
    }

    private function branchForProvider(Organization $organization, string $provider): Branch
    {
        $provider = strtolower(trim($provider));
        $channelType = $provider === 'telegram' ? Branch::CHANNEL_TYPE_TELEGRAM : Branch::CHANNEL_TYPE_WHATSAPP;
        $identifier = $provider === 'telegram'
            ? '@developer-toolkit-small-hardware-store-' . $organization->id
            : 'developer-toolkit-small-hardware-store-' . $organization->id;

        return Branch::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel_identifier' => $identifier,
            ],
            [
                'name' => $provider === 'telegram' ? 'Developer Toolkit Telegram' : 'Developer Toolkit WhatsApp',
                'channel_type' => $channelType,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );
    }

    private function ingestWhatsAppGeneratedPayload(
        Organization $organization,
        Customer $customer,
        string $externalMessageId,
        string $messageText,
        Carbon $receivedAt,
    ): array {
        $rawPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-toolkit-' . substr(md5($externalMessageId), 0, 8),
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

        $this->providerLifecycleManager->receiveWebhook('whatsapp', $request);

        $incomingMessage = IncomingMessage::query()
            ->where('organization_id', $organization->id)
            ->where('provider', 'whatsapp')
            ->where('external_message_id', $externalMessageId)
            ->latest('id')
            ->first();

        $order = $incomingMessage?->order ?? Order::query()
            ->where('organization_id', $organization->id)
            ->where('source_channel', 'whatsapp')
            ->where('external_message_id', $externalMessageId)
            ->latest('id')
            ->first();

        $this->markDemoIdentity($organization, 'whatsapp', $customer->phone);

        return [
            'duplicate' => $incomingMessage?->status === IncomingMessage::STATUS_DUPLICATE,
            'incoming_message' => $incomingMessage,
            'order' => $order,
        ];
    }

    private function ingestTelegramGeneratedPayload(
        Organization $organization,
        Customer $customer,
        string $externalMessageId,
        string $messageText,
        Carbon $receivedAt,
    ): array {
        $chatId = $customer->phone;

        $result = $this->messagingIngestionService->ingest(
            $organization,
            $this->branchForProvider($organization, 'telegram'),
            new IncomingMessageDTO(
                provider: 'telegram',
                external_message_id: $externalMessageId,
                external_chat_id: $chatId,
                received_at: $receivedAt,
                external_user_id: $chatId,
                provider_username: $this->telegramUsername($customer->name ?? 'Telegram Customer'),
                customer_name: $customer->name,
                customer_phone: $chatId,
                metadata: [
                    'demo_generated' => true,
                    'scenario' => 'small_hardware_store',
                    'source' => 'developer-toolkit',
                ],
                message: $messageText,
                raw_payload: [
                    'source' => 'developer-toolkit',
                    'demo_generated' => true,
                ],
                attachments: [],
            ),
        );

        $this->markDemoIdentity($organization, 'telegram', $chatId);

        return $result;
    }

    private function markDemoIdentity(Organization $organization, string $provider, string $externalChatId): void
    {
        $identity = CustomerIdentity::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->where('external_chat_id', $externalChatId)
            ->latest('id')
            ->first();

        if ($identity === null) {
            return;
        }

        $metadata = $identity->metadata_json ?? [];
        $metadata['demo_generated'] = true;
        $metadata['source'] = 'developer-toolkit';
        $metadata['scenario'] = 'small_hardware_store';

        $identity->forceFill([
            'metadata_json' => $metadata,
        ])->save();
    }

    private function tagGeneratedOrder(Order $order, Customer $customer, string $provider, string $messageText, string $variant): void
    {
        $order->forceFill([
            'notes' => sprintf(
                '%s scenario=small-hardware-store provider=%s variant=%s message="%s"',
                self::TOOLKIT_MARKER,
                $provider,
                $variant,
                $messageText,
            ),
        ])->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by_user_id' => null,
            'changed_via' => 'developer_toolkit',
            'reason' => 'Generated by developer toolkit.',
            'metadata_json' => [
                'demo_generated' => true,
                'scenario' => 'small_hardware_store',
                'provider' => $provider,
                'variant' => $variant,
            ],
            'created_at' => now(),
        ]);

    }

    private function transitionOrder(Order $order, string $targetStatus): void
    {
        $statusSequence = match ($targetStatus) {
            Order::STATUS_CONFIRMED => [Order::STATUS_CONFIRMED],
            Order::STATUS_PREPARING => [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING],
            Order::STATUS_READY_FOR_DISPATCH => [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH],
            Order::STATUS_DISPATCHED => [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_DISPATCH, Order::STATUS_DISPATCHED],
            Order::STATUS_CANCELLED => [Order::STATUS_CANCELLED],
            default => [],
        };

        $statusHistoryMap = [
            Order::STATUS_CONFIRMED => [
                'confirmed_at' => now(),
                'rejected_at' => null,
                'cancelled_at' => null,
            ],
            Order::STATUS_PREPARING => [
                'confirmed_at' => $order->confirmed_at ?? now(),
                'preparing_at' => now(),
                'ready_for_dispatch_at' => null,
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ],
            Order::STATUS_READY_FOR_DISPATCH => [
                'confirmed_at' => $order->confirmed_at ?? now(),
                'preparing_at' => $order->preparing_at ?? now(),
                'ready_for_dispatch_at' => now(),
                'dispatched_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
            ],
            Order::STATUS_DISPATCHED => [
                'confirmed_at' => $order->confirmed_at ?? now(),
                'preparing_at' => $order->preparing_at ?? now(),
                'ready_for_dispatch_at' => $order->ready_for_dispatch_at ?? now(),
                'dispatched_at' => now(),
                'cancelled_at' => null,
                'rejected_at' => null,
            ],
            Order::STATUS_CANCELLED => [
                'cancelled_at' => now(),
                'rejected_at' => null,
            ],
        ];

        foreach ($statusSequence as $status) {
            $previousStatus = $order->status;
            $updates = $statusHistoryMap[$status] ?? [];

            $order->forceFill(array_merge([
                'status' => $status,
            ], $updates))->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $previousStatus,
                'to_status' => $status,
                'changed_by_user_id' => null,
                'changed_via' => 'developer_toolkit',
                'reason' => 'Generated by developer toolkit.',
                'metadata_json' => [
                    'demo_generated' => true,
                    'scenario' => 'small_hardware_store',
                ],
                'created_at' => now(),
            ]);
        }
    }

    private function telegramUsername(string $name): ?string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? '');

        return $username !== '' ? $username . '_cr' : null;
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
