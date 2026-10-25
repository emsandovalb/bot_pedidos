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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Genera clientes y pedidos de demo para el toolkit (escenarios, generación
 * rápida y casos QA) y limpia lo que generó.
 */
class ToolkitDataGenerator
{
    private const TOOLKIT_MARKER = '[developer-toolkit]';

    private const CUSTOMER_PREFIX = 'developer-toolkit:customer:';

    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly MessagingIngestionService $messagingIngestionService,
        private readonly ToolkitChannels $channels,
        private readonly ToolkitScenarioCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateScenario(Organization $organization, string $provider, string $scenarioKey): array
    {
        $definition = $this->catalog->scenarioDefinitions()[$scenarioKey] ?? $this->catalog->scenarioDefinitions()['ferreteria_pequena'];
        $providerMix = $definition['provider_mix'] ?? ['whatsapp' => $definition['orders']];
        $start = now()->subDays((int) ($definition['spread_days'] ?? 5));
        $customers = $this->generateCustomerPool($organization, (int) $definition['customers'], $provider);
        $messages = $this->buildScenarioMessages($definition, $customers, $providerMix, $start);

        return $this->runGeneratedMessages($organization, $messages, $definition, [
            'message' => 'Scenario generated: '.$definition['label'].'.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateQuickOrders(Organization $organization, string $provider, int $count): array
    {
        if ($provider === 'instagram') {
            return [
                'processed_count' => 0,
                'ignored_count' => 0,
                'failed_count' => 1,
                'generated_customers' => 0,
                'generated_orders' => 0,
                'whatsapp_count' => 0,
                'telegram_count' => 0,
                'vip_count' => 0,
                'duplicate_count' => 0,
                'execution_ms' => 0,
                'message' => 'Instagram is coming soon.',
                'order_url' => null,
                'incoming_message_url' => null,
            ];
        }

        $count = max(1, $count);
        $customers = $this->generateCustomerPool($organization, max(10, min(100, $count * 2)), $provider);
        $messages = [];

        for ($i = 0; $i < $count; $i++) {
            $customer = $customers[$i % count($customers)];
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $this->catalog->randomOrderText($provider, false),
                externalMessageId: $this->generatedMessageId($provider, 'quick', $i),
                receivedAt: now()->subMinutes($count - $i),
                variant: 'quick',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Quick generation',
            'customers' => count($customers),
            'orders' => $count,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateCustomersOnly(Organization $organization, int $count): array
    {
        $count = max(1, $count);
        $customers = $this->generateCustomerPool($organization, $count, 'whatsapp', true);
        $branch = $this->channels->branchForProvider($organization, 'whatsapp');

        foreach ($customers as $customerData) {
            $this->upsertDemoCustomer($organization, $branch, $customerData);
        }

        return [
            'processed_count' => 0,
            'ignored_count' => 0,
            'failed_count' => 0,
            'generated_customers' => count($customers),
            'generated_orders' => 0,
            'whatsapp_count' => 0,
            'telegram_count' => 0,
            'vip_count' => 0,
            'duplicate_count' => 0,
            'execution_ms' => 0,
            'message' => 'Generated '.count($customers).' demo customers.',
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateQaCase(Organization $organization, string $provider, string $qaCase): array
    {
        return match ($qaCase) {
            'duplicate' => $this->generateDuplicateScenario($organization, $provider),
            'vip' => $this->generateVipScenario($organization, $provider),
            'parser_failures' => $this->generateParserFailureScenario($organization, $provider),
            'unknown_products' => $this->generateUnknownProductsScenario($organization, $provider),
            'busy_day' => $this->generateBusyDayScenario($organization, $provider),
            'empty_inbox' => $this->resetToolkitData($organization, 'environment'),
            default => $this->generateBusyDayScenario($organization, $provider),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function generateDuplicateScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 4, $provider);
        $customer = $customers[0];
        $messages = [
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 1),
                receivedAt: now()->subMinutes(10),
                variant: 'duplicate_seed',
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin para hoy',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 2),
                receivedAt: now()->subMinutes(8),
                variant: 'duplicate_match',
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin urgente',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 3),
                receivedAt: now()->subMinutes(6),
                variant: 'duplicate_match',
            ),
        ];

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Duplicate scenario',
        ], [
            'message' => 'Created duplicate scenario with one customer and repeated demand.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateVipScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 2, $provider);
        $customer = $customers[0];
        $messages = [];

        for ($i = 0; $i < 22; $i++) {
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $this->catalog->vipOrderText($provider, $i),
                externalMessageId: $this->generatedMessageId($provider, 'vip', $i),
                receivedAt: now()->subHours(24 - $i),
                variant: $i < 20 ? 'vip' : 'vip_tail',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'VIP scenario',
        ], [
            'message' => 'Created a VIP customer with enough history to reach VIP classification.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateParserFailureScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 3, $provider);
        $messages = [
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[0],
                messageText: '',
                externalMessageId: $this->generatedMessageId($provider, 'fail', 1),
                receivedAt: now()->subMinutes(5),
                variant: 'parser_failure',
                forceMalformed: true,
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[1],
                messageText: '???',
                externalMessageId: $this->generatedMessageId($provider, 'fail', 2),
                receivedAt: now()->subMinutes(4),
                variant: 'parser_failure',
            ),
        ];

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Parser failures',
        ], [
            'message' => 'Created malformed and low-confidence messages for parser QA.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateUnknownProductsScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 6, $provider);
        $messages = [];

        foreach (['3 cajas de pernos raros', '2 bultos de insumo x', '1 paquete de inventario zeta'] as $index => $text) {
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[$index % count($customers)],
                messageText: $text,
                externalMessageId: $this->generatedMessageId($provider, 'unknown', $index + 1),
                receivedAt: now()->subMinutes(15 - $index),
                variant: 'unknown_product',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Unknown products',
        ], [
            'message' => 'Created low-match products for alias and parser QA.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBusyDayScenario(Organization $organization, string $provider): array
    {
        $definition = $this->catalog->scenarioDefinitions()['ferreteria_grande'];
        $providerMix = ['whatsapp' => 35, 'telegram' => 15];
        $customers = $this->generateCustomerPool($organization, 18, $provider);
        $messages = $this->buildScenarioMessages($definition, $customers, $providerMix, now()->subDays(1));

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Busy day',
        ], [
            'message' => 'Created a mixed high-volume day with randomized timing and customers.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $resultOverrides
     * @return array<string, mixed>
     */
    private function runGeneratedMessages(Organization $organization, array $messages, array $definition, array $resultOverrides = []): array
    {
        $start = microtime(true);
        $generatedOrders = 0;
        $generatedCustomers = [];
        $whatsappCount = 0;
        $telegramCount = 0;
        $duplicateCount = 0;

        foreach ($messages as $index => $payload) {
            $provider = (string) ($payload['provider'] ?? 'whatsapp');
            $customerData = $payload['customer'];
            $branch = $this->channels->branchForProvider($organization, $provider);
            $customer = $this->upsertDemoCustomer($organization, $branch, $customerData);
            $generatedCustomers[$customer->external_id ?? $customer->id] = true;

            $ingestionResult = match ($provider) {
                'telegram' => $this->ingestTelegramGeneratedPayload($organization, $branch, $payload, $customer),
                default => $this->ingestWhatsAppGeneratedPayload($organization, $branch, $payload, $customer),
            };

            if (($ingestionResult['duplicate'] ?? false) === true) {
                $duplicateCount++;
            }

            if (($ingestionResult['order'] ?? null) instanceof Order) {
                $generatedOrders++;
                $this->tagGeneratedOrder(
                    order: $ingestionResult['order'],
                    customer: $customer,
                    scenario: (string) ($definition['label'] ?? 'Toolkit'),
                    provider: $provider,
                    variant: (string) ($payload['variant'] ?? 'standard'),
                );
            }

            if ($provider === 'whatsapp') {
                $whatsappCount++;
            } else {
                $telegramCount++;
            }
        }

        return [
            'processed_count' => $generatedOrders,
            'ignored_count' => $duplicateCount,
            'failed_count' => 0,
            'generated_customers' => count($generatedCustomers),
            'generated_orders' => $generatedOrders,
            'whatsapp_count' => $whatsappCount,
            'telegram_count' => $telegramCount,
            'vip_count' => $this->vipCustomerCount($organization->id),
            'duplicate_count' => $this->duplicateOrderCount($organization->id),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => $resultOverrides['message'] ?? ($definition['label'] ?? 'Toolkit generation complete.'),
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $customers
     * @param  array<string, int>  $providerMix
     * @return array<int, array<string, mixed>>
     */
    private function buildScenarioMessages(array $definition, array $customers, array $providerMix, Carbon $start): array
    {
        $messages = [];
        $providerSequence = [];

        foreach ($providerMix as $provider => $count) {
            for ($i = 0; $i < $count; $i++) {
                $providerSequence[] = $provider;
            }
        }

        $orders = (int) ($definition['orders'] ?? count($providerSequence));
        $providerSequence = array_slice(array_pad($providerSequence, $orders, 'whatsapp'), 0, $orders);
        shuffle($providerSequence);

        $vipTarget = max(1, (int) ($definition['vip'] ?? 0));
        $duplicateTarget = max(0, (int) ($definition['duplicates'] ?? 0));
        $customerPoolCount = count($customers);

        for ($i = 0; $i < $orders; $i++) {
            $provider = $providerSequence[$i] ?? 'whatsapp';
            $customerIndex = $i % max($customerPoolCount, 1);
            $customer = $customers[$customerIndex];
            $receivedAt = $start->copy()->addMinutes($i * 17 + random_int(0, 11));
            $variant = 'standard';
            $messageText = $this->catalog->randomOrderText($definition['category'] ?? 'ferreteria', false);

            if ($i < $vipTarget) {
                $customer = $customers[0];
                $messageText = $this->catalog->vipOrderText($provider, $i);
                $variant = 'vip';
            }

            if ($i < $duplicateTarget) {
                $customer = $customers[min(1, $customerPoolCount - 1)];
                $baseText = $this->catalog->randomOrderText($definition['category'] ?? 'ferreteria', false);
                $messageText = $i % 2 === 0 ? $baseText : $baseText.' para hoy';
                $variant = 'duplicate';
            }

            if (($definition['category'] ?? '') === 'feria') {
                $messageText = $this->catalog->randomFeriaText($i);
            } elseif (($definition['category'] ?? '') === 'restaurant') {
                $messageText = $this->catalog->randomRestaurantText($i);
            } elseif (($definition['category'] ?? '') === 'supermercado') {
                $messageText = $this->catalog->randomSupermarketText($i);
            }

            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $messageText,
                externalMessageId: $this->generatedMessageId($provider, (string) ($definition['slug'] ?? 'scenario'), $i),
                receivedAt: $receivedAt,
                variant: $variant,
            );
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function makeMessagePayload(
        string $provider,
        array $customer,
        string $messageText,
        string $externalMessageId,
        Carbon $receivedAt,
        string $variant,
        bool $forceMalformed = false,
    ): array {
        return [
            'provider' => $provider,
            'customer' => $customer,
            'message_text' => $messageText,
            'external_message_id' => $externalMessageId,
            'received_at' => $receivedAt,
            'variant' => $variant,
            'force_malformed' => $forceMalformed,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ingestWhatsAppGeneratedPayload(
        Organization $organization,
        Branch $branch,
        array $payload,
        Customer $customer,
    ): array {
        $rawPayload = $this->buildWhatsAppGeneratedPayload($payload, $customer);
        $request = SimulatedWhatsAppWebhook::request($rawPayload);

        $result = $this->providerLifecycleManager->receiveWebhook('whatsapp', $request);
        $messageId = $this->channels->stringValue($payload['external_message_id'] ?? null);

        $order = $messageId !== null
            ? Order::query()
                ->where('organization_id', $organization->id)
                ->where('source_channel', 'whatsapp')
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        $incomingMessage = $messageId !== null
            ? IncomingMessage::query()
                ->where('organization_id', $organization->id)
                ->where('provider', 'whatsapp')
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        return [
            'duplicate' => (bool) ($incomingMessage?->status === IncomingMessage::STATUS_DUPLICATE),
            'order' => $order,
            'incoming_message' => $incomingMessage,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ingestTelegramGeneratedPayload(
        Organization $organization,
        Branch $branch,
        array $payload,
        Customer $customer,
    ): array {
        if (! empty($payload['force_malformed'])) {
            return [
                'duplicate' => false,
                'order' => null,
                'incoming_message' => null,
                'result' => null,
            ];
        }

        $chatId = $this->channels->telegramChatId((string) ($customer->phone ?? '4001'));
        $dto = new IncomingMessageDTO(
            provider: 'telegram',
            external_message_id: (string) ($payload['external_message_id'] ?? $payload['message']['message_id'] ?? now()->timestamp),
            external_chat_id: $chatId,
            received_at: $payload['received_at'] instanceof Carbon ? $payload['received_at'] : now(),
            external_user_id: $chatId,
            provider_username: $this->channels->telegramUsername((string) ($customer->name ?? 'Telegram Customer')),
            customer_name: (string) ($customer->name ?? 'Telegram Customer'),
            customer_phone: $chatId,
            message: (string) ($payload['message_text'] ?? ''),
            raw_payload: [
                'source' => 'developer-toolkit',
                'payload' => $payload,
            ],
            attachments: [],
        );

        $result = $this->messagingIngestionService->ingest($organization, $branch, $dto);

        return [
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'order' => $result['order'] ?? null,
            'incoming_message' => $result['incoming_message'] ?? null,
            'result' => $result,
        ];
    }

    private function buildWhatsAppGeneratedPayload(array $payload, Customer $customer): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-toolkit-'.substr(md5((string) ($payload['external_message_id'] ?? '')), 0, 8),
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => ToolkitChannels::PHONE_NUMBER_ID_TEST,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => $customer->name ?? null,
                                        ],
                                        'wa_id' => $customer->phone ?? null,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $customer->phone ?? null,
                                        'id' => $payload['external_message_id'] ?? null,
                                        'timestamp' => (string) ($payload['received_at'] instanceof Carbon ? $payload['received_at']->timestamp : now()->timestamp),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => $payload['message_text'] ?? '',
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

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function upsertDemoCustomer(Organization $organization, Branch $branch, array $customerData): Customer
    {
        $name = trim((string) ($customerData['name'] ?? 'Cliente Demo'));
        $phone = trim((string) ($customerData['phone'] ?? $this->generatedCustomerPhone()));
        $externalId = (string) ($customerData['external_id'] ?? self::CUSTOMER_PREFIX.md5($organization->id.'|'.$phone));

        $customer = Customer::query()->firstOrNew([
            'organization_id' => $organization->id,
            'phone' => $phone,
        ]);

        $customer->fill([
            'branch_id' => $branch->id,
            'name' => $name,
            'external_id' => $externalId,
        ]);
        $customer->organization_id = $organization->id;
        $customer->phone = $phone;
        $customer->save();

        return $customer->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateCustomerPool(Organization $organization, int $count, string $provider, bool $customersOnly = false): array
    {
        $count = max(1, $count);
        $customers = [];

        for ($i = 0; $i < $count; $i++) {
            $first = $this->catalog->firstNames()[$i % count($this->catalog->firstNames())];
            $last = $this->catalog->lastNames()[(int) floor($i / count($this->catalog->firstNames())) % count($this->catalog->lastNames())];
            $name = $first.' '.$last;
            $phone = $provider === 'telegram' ? $this->channels->telegramChatId('4'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)) : $this->generatedCustomerPhone($i);
            $customers[] = [
                'name' => $name,
                'phone' => $phone,
                'external_id' => self::CUSTOMER_PREFIX.$organization->id.':'.$provider.':'.($i + 1),
                'customers_only' => $customersOnly,
            ];
        }

        return $customers;
    }

    private function generatedCustomerPhone(?int $offset = null): string
    {
        $offset = $offset ?? random_int(1000, 9999);

        return '506'.str_pad((string) ((55510000 + $offset) % 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function generatedMessageId(string $provider, string $scope, int $index): string
    {
        return sprintf(
            '%s-%s-%04d',
            strtolower(trim($provider)),
            $this->slugify($scope),
            $index + 1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function metricsFor(int $organizationId): array
    {
        return [
            'generated_customers' => Customer::query()
                ->where('organization_id', $organizationId)
                ->where('external_id', 'like', self::CUSTOMER_PREFIX.'%')
                ->count(),
            'generated_orders' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
                ->count(),
            'whatsapp' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
                ->where('source_channel', 'whatsapp')
                ->count(),
            'telegram' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
                ->where('source_channel', 'telegram')
                ->count(),
            'vip' => Customer::query()
                ->where('organization_id', $organizationId)
                ->where('external_id', 'like', self::CUSTOMER_PREFIX.'%')
                ->withCount([
                    'orders as toolkit_orders_count' => function ($query): void {
                        $query->where('notes', 'like', self::TOOLKIT_MARKER.'%');
                    },
                ])
                ->get()
                ->filter(static fn (Customer $customer): bool => (int) ($customer->toolkit_orders_count ?? 0) >= 3)
                ->count(),
            'duplicates' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
                ->whereNotNull('possible_duplicate_of_order_id')
                ->count(),
        ];
    }

    private function duplicateOrderCount(int $organizationId): int
    {
        return Order::query()
            ->where('organization_id', $organizationId)
            ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
            ->whereNotNull('possible_duplicate_of_order_id')
            ->count();
    }

    private function vipCustomerCount(int $organizationId): int
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('external_id', 'like', self::CUSTOMER_PREFIX.'%')
            ->withCount([
                'orders as toolkit_orders_count' => function ($query): void {
                    $query->where('notes', 'like', self::TOOLKIT_MARKER.'%');
                },
            ])
            ->get()
            ->filter(static fn (Customer $customer): bool => (int) ($customer->toolkit_orders_count ?? 0) >= 3)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function resetToolkitData(Organization $organization, string $scope): array
    {
        $deletedOrders = 0;
        $deletedCustomers = 0;

        DB::transaction(function () use ($organization, $scope, &$deletedOrders, &$deletedCustomers): void {
            if (in_array($scope, ['orders', 'environment'], true)) {
                $deletedOrders = Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('notes', 'like', self::TOOLKIT_MARKER.'%')
                    ->delete();
            }

            if (in_array($scope, ['customers', 'environment'], true)) {
                $customerIds = Customer::query()
                    ->where('organization_id', $organization->id)
                    ->where('external_id', 'like', self::CUSTOMER_PREFIX.'%')
                    ->pluck('id');

                if ($customerIds->isNotEmpty()) {
                    Order::query()
                        ->whereIn('customer_id', $customerIds)
                        ->delete();
                }

                $deletedCustomers = Customer::query()
                    ->whereIn('id', $customerIds)
                    ->delete();
            }
        });

        return [
            'processed_count' => 0,
            'ignored_count' => 0,
            'failed_count' => 0,
            'generated_customers' => 0,
            'generated_orders' => 0,
            'whatsapp_count' => 0,
            'telegram_count' => 0,
            'vip_count' => 0,
            'duplicate_count' => 0,
            'execution_ms' => 0,
            'message' => sprintf(
                'Reset completed: %d orders and %d customers removed.',
                $deletedOrders,
                $deletedCustomers,
            ),
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    private function tagGeneratedOrder(Order $order, Customer $customer, string $scenario, string $provider, string $variant): void
    {
        $notes = sprintf('%s scenario=%s provider=%s variant=%s', self::TOOLKIT_MARKER, $this->slugify($scenario), $provider, $variant);

        $order->forceFill([
            'notes' => $notes,
        ])->save();

        if ($variant === 'vip' || $variant === 'vip_tail') {
            $order->forceFill([
                'status' => Order::STATUS_PENDING_REVIEW,
            ])->save();
        }

        $customer->forceFill([
            'external_id' => self::CUSTOMER_PREFIX.$customer->organization_id.':'.$provider.':'.$customer->phone,
        ])->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by_user_id' => null,
            'changed_via' => 'developer_toolkit',
            'reason' => 'Generated by developer toolkit.',
            'metadata_json' => [
                'scenario' => $scenario,
                'provider' => $provider,
                'variant' => $variant,
            ],
            'created_at' => now(),
        ]);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
