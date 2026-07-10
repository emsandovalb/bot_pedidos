<?php

namespace App\Services\Developer;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderNotificationLog;
use App\Models\Organization;
use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessScenarioService
{
    public function __construct(
        private readonly ScenarioFactory $scenarioFactory,
        private readonly RandomMessageFactory $randomMessageFactory,
        private readonly SimulationRunner $simulationRunner,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scenarios(): array
    {
        return $this->scenarioFactory->scenarioCards();
    }

    /**
     * @return array<int, int>
     */
    public function simulationSpeeds(): array
    {
        return [1, 5, 10];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateScenario(Organization $organization, string $scenarioKey): array
    {
        $start = microtime(true);
        $scenario = $this->scenarioFactory->definition($scenarioKey);
        $messages = $this->scenarioFactory->buildScenarioMessages(
            organization: $organization,
            scenarioKey: $scenario['slug'],
            randomMessageFactory: $this->randomMessageFactory,
            startAt: now()->subHours(6),
        );

        $result = $this->simulationRunner->run($organization, $messages, [
            'scenario' => $scenario['slug'],
            'message' => 'Generated scenario: ' . $scenario['label'] . '.',
        ]);

        return array_merge($result, [
            'metrics' => $this->metrics($organization),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => 'Generated scenario: ' . $scenario['label'] . '.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateCustomMessage(Organization $organization, array $input): array
    {
        $start = microtime(true);
        $provider = strtolower(trim((string) ($input['provider'] ?? 'whatsapp')));
        $customerMode = strtolower(trim((string) ($input['customer_mode'] ?? 'new')));
        $customerName = trim((string) ($input['customer_name'] ?? ''));
        $customerPhone = trim((string) ($input['customer_phone'] ?? ''));
        $messageText = trim((string) ($input['message'] ?? ''));

        if ($customerName === '') {
            $customerName = 'New customer';
        }

        if ($messageText === '') {
            $messageText = $this->randomMessageFactory->buildMessage([
                'family' => 'hardware',
                'item_count' => 1,
                'date_phrase' => 'today',
                'urgency' => 'normal',
            ]);
        }

        if ($customerPhone === '') {
            $customerPhone = $provider === 'telegram'
                ? $this->randomMessageFactory->customerPhone('telegram', random_int(100, 999))
                : $this->randomMessageFactory->customerPhone('whatsapp', random_int(100, 999));
        }

        if ($customerMode === 'existing') {
            $existing = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('phone', $customerPhone)
                ->first();

            if ($existing !== null) {
                $customerName = $existing->name ?: $customerName;
            }
        }

        $messages = [[
            'provider' => $provider,
            'customer' => [
                'name' => $customerName,
                'phone' => $customerPhone,
                'external_id' => SimulationRunner::CUSTOMER_PREFIX . $organization->id . ':custom:' . md5($provider . '|' . $customerName . '|' . $customerPhone),
            ],
            'message_text' => $messageText,
            'external_message_id' => 'bizsim-custom-' . now()->format('YmdHisv'),
            'received_at' => now(),
            'variant' => $customerMode === 'existing' ? 'existing_customer' : 'new_customer',
            'scenario' => 'custom_message',
        ]];

        $result = $this->simulationRunner->run($organization, $messages, [
            'scenario' => 'custom_message',
            'message' => 'Custom message injected through the production pipeline.',
        ]);

        return array_merge($result, [
            'metrics' => $this->metrics($organization),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => 'Custom message injected through the production pipeline.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateRandomMessages(Organization $organization, int $count): array
    {
        $start = microtime(true);
        $count = max(1, $count);
        $messages = [];
        $providerCycle = ['whatsapp', 'whatsapp', 'telegram'];
        $randomMessages = $this->randomMessageFactory->randomMessages($count);

        foreach ($randomMessages as $index => $messageText) {
            $provider = $providerCycle[$index % count($providerCycle)];
            $messages[] = [
                'provider' => $provider,
                'customer' => [
                    'name' => $this->randomMessageFactory->customerName($index),
                    'phone' => $this->randomMessageFactory->customerPhone($provider, $index + 1),
                    'external_id' => SimulationRunner::CUSTOMER_PREFIX . $organization->id . ':random:' . ($index + 1),
                ],
                'message_text' => $messageText,
                'external_message_id' => sprintf('bizsim-random-%s-%04d', $provider, $index + 1),
                'received_at' => now()->subMinutes($count - $index),
                'variant' => 'random',
                'scenario' => 'random_messages',
            ];
        }

        $result = $this->simulationRunner->run($organization, $messages, [
            'scenario' => 'random_messages',
            'message' => 'Generated ' . $count . ' random messages.',
        ]);

        return array_merge($result, [
            'metrics' => $this->metrics($organization),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => 'Generated ' . $count . ' random messages.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function simulateBusinessDay(Organization $organization, int $speedMultiplier = 1): array
    {
        $start = microtime(true);
        $speedMultiplier = max(1, $speedMultiplier);
        $messages = $this->scenarioFactory->buildBusinessDayMessages(
            organization: $organization,
            randomMessageFactory: $this->randomMessageFactory,
            startAt: now()->startOfDay()->addHours(8),
            speedMultiplier: $speedMultiplier,
        );

        $result = $this->simulationRunner->run($organization, $messages, [
            'scenario' => 'business_day',
            'message' => 'Simulated business day at ' . $speedMultiplier . 'x speed.',
        ]);

        return array_merge($result, [
            'metrics' => $this->metrics($organization),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'speed' => $speedMultiplier,
            'message' => 'Simulated business day at ' . $speedMultiplier . 'x speed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function reset(Organization $organization, string $scope): array
    {
        $start = microtime(true);
        $scope = strtolower(trim($scope));
        $deletedOrders = 0;
        $deletedIncomingMessages = 0;
        $deletedWebhookEvents = 0;
        $deletedCustomers = 0;
        $deletedCustomerIdentities = 0;

        DB::transaction(function () use ($organization, $scope, &$deletedOrders, &$deletedIncomingMessages, &$deletedWebhookEvents, &$deletedCustomers, &$deletedCustomerIdentities): void {
            $generatedCustomerIds = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('external_id', 'like', SimulationRunner::CUSTOMER_PREFIX . '%')
                ->pluck('id');

            if (in_array($scope, ['today', 'demo_orders', 'messages', 'notifications', 'webhook_logs', 'environment'], true)) {
                $deletedOrders = Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('notes', 'like', SimulationRunner::MARKER . '%')
                    ->delete();
            }

            if (in_array($scope, ['today', 'demo_orders', 'messages', 'webhook_logs', 'environment'], true)) {
                $deletedIncomingMessages = IncomingMessage::query()
                    ->where('organization_id', $organization->id)
                    ->where(function ($query): void {
                        $query->where('external_message_id', 'like', 'bizsim-%')
                            ->orWhere('payload_json->raw_payload->source', 'business_scenario')
                            ->orWhere('payload_json->source', 'business_scenario');
                    })
                    ->delete();
            }

            if (in_array($scope, ['today', 'demo_customers', 'environment'], true) && $generatedCustomerIds->isNotEmpty()) {
                $deletedCustomerIdentities = CustomerIdentity::query()
                    ->where('organization_id', $organization->id)
                    ->whereIn('customer_id', $generatedCustomerIds)
                    ->delete();

                $deletedCustomers = Customer::query()
                    ->whereIn('id', $generatedCustomerIds)
                    ->delete();
            }

            if (in_array($scope, ['today', 'webhook_logs', 'environment'], true)) {
                $deletedWebhookEvents = WebhookEvent::query()
                    ->where('organization_id', $organization->id)
                    ->where(function ($query): void {
                        $query->where('payload_json->entry.0.changes.0.value.messages.0.id', 'like', 'bizsim-%')
                            ->orWhere('payload_json->message_id', 'like', 'bizsim-%')
                            ->orWhere('payload_json->scenario', 'business_scenario')
                            ->orWhere('payload_json->source', 'business_scenario');
                    })
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
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => sprintf(
                'Cleanup completed: %d orders, %d messages, %d webhook logs, %d identities and %d customers removed.',
                $deletedOrders,
                $deletedIncomingMessages,
                $deletedWebhookEvents,
                $deletedCustomerIdentities,
                $deletedCustomers,
            ),
            'order_url' => null,
            'incoming_message_url' => null,
            'metrics' => $this->metrics($organization),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(Organization $organization): array
    {
        $orders = Order::query()
            ->where('organization_id', $organization->id)
            ->where('notes', 'like', SimulationRunner::MARKER . '%')
            ->with('fulfillmentPlan')
            ->get();

        $customers = Customer::query()
            ->where('organization_id', $organization->id)
            ->where('external_id', 'like', SimulationRunner::CUSTOMER_PREFIX . '%')
            ->withCount(['orders as business_scenario_orders_count' => function ($query): void {
                $query->where('notes', 'like', SimulationRunner::MARKER . '%');
            }])
            ->get();

        $today = now()->toDateString();
        $tomorrow = now()->copy()->addDay()->toDateString();

        $deliveryCount = $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->delivery_method ?? null) === 'delivery')->count();
        $pickupCount = $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->delivery_method ?? null) === 'pickup')->count();
        $todayCount = $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->requested_date?->toDateString() ?? null) === $today)->count();
        $tomorrowCount = $orders->filter(fn (Order $order): bool => ($order->fulfillmentPlan?->requested_date?->toDateString() ?? null) === $tomorrow)->count();
        $urgentCount = $orders->filter(function (Order $order): bool {
            return in_array($order->fulfillmentPlan?->priority_level, ['urgent', 'high'], true) || (int) ($order->fulfillmentPlan?->priority_score ?? 0) >= 85;
        })->count();
        $duplicateCount = $orders->whereNotNull('possible_duplicate_of_order_id')->count();

        return [
            'customers' => $customers->count(),
            'orders' => $orders->count(),
            'delivery' => $deliveryCount,
            'pickup' => $pickupCount,
            'today' => $todayCount,
            'tomorrow' => $tomorrowCount,
            'urgent' => $urgentCount,
            'vip' => $customers->filter(static fn (Customer $customer): bool => (int) ($customer->business_scenario_orders_count ?? 0) >= 3)->count(),
            'duplicates' => $duplicateCount,
            'average_parser_confidence' => $this->averageParserConfidence($orders),
            'average_priority_score' => $this->averagePriorityScore($orders),
            'average_sla' => $this->averageSla($orders),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>|array<int, Order>  $orders
     */
    private function averageParserConfidence(iterable $orders): ?float
    {
        $values = [];

        foreach ($orders as $order) {
            if ($order->parser_confidence === null) {
                continue;
            }

            $values[] = (float) $order->parser_confidence;
        }

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>|array<int, Order>  $orders
     */
    private function averagePriorityScore(iterable $orders): ?float
    {
        $values = [];

        foreach ($orders as $order) {
            if ($order->fulfillmentPlan?->priority_score === null) {
                continue;
            }

            $values[] = (float) $order->fulfillmentPlan->priority_score;
        }

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>|array<int, Order>  $orders
     */
    private function averageSla(iterable $orders): ?float
    {
        $values = [];

        foreach ($orders as $order) {
            if ($order->fulfillmentPlan?->remaining_sla_minutes === null) {
                continue;
            }

            $values[] = (float) $order->fulfillmentPlan->remaining_sla_minutes;
        }

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}
