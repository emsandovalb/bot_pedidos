<?php

namespace App\Services\Developer;

use App\Models\Organization;
use Illuminate\Support\Carbon;

class ScenarioFactory
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'small_hardware_store' => [
                'slug' => 'small_hardware_store',
                'label' => 'Small Hardware Store',
                'description' => '30 realistic orders from a mixed hardware store day.',
                'customers' => 15,
                'orders' => 30,
                'provider_mix' => ['whatsapp' => 20, 'telegram' => 10],
                'themes' => ['hardware', 'construction'],
                'vip_target' => 3,
                'duplicate_target' => 2,
                'timing' => 'random',
                'delivery_bias' => ['delivery', 'pickup', 'delivery', 'delivery'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe', 'cash'],
                'date_bias' => ['today', 'tomorrow', 'none', 'today', 'none'],
                'urgency_bias' => ['urgent', 'normal', 'normal'],
                'size_bias' => ['medium', 'small', 'large'],
            ],
            'morning_rush' => [
                'slug' => 'morning_rush',
                'label' => 'Morning Rush',
                'description' => '15 delivery-heavy orders before noon, mostly urgent and for today.',
                'customers' => 8,
                'orders' => 15,
                'provider_mix' => ['whatsapp' => 10, 'telegram' => 5],
                'themes' => ['restaurant', 'hardware'],
                'vip_target' => 1,
                'duplicate_target' => 1,
                'timing' => 'morning',
                'delivery_bias' => ['delivery', 'delivery', 'delivery', 'pickup'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe'],
                'date_bias' => ['today', 'today', 'today', 'tomorrow'],
                'urgency_bias' => ['urgent', 'urgent', 'normal'],
                'size_bias' => ['small', 'medium'],
            ],
            'rainy_day' => [
                'slug' => 'rainy_day',
                'label' => 'Rainy Day',
                'description' => '20 orders pushed to tomorrow with bulky materials and delivery.',
                'customers' => 10,
                'orders' => 20,
                'provider_mix' => ['whatsapp' => 12, 'telegram' => 8],
                'themes' => ['construction', 'hardware'],
                'vip_target' => 2,
                'duplicate_target' => 1,
                'timing' => 'random',
                'delivery_bias' => ['delivery', 'delivery', 'delivery', 'pickup'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe'],
                'date_bias' => ['tomorrow', 'tomorrow', 'today', 'tomorrow'],
                'urgency_bias' => ['urgent', 'normal', 'normal'],
                'size_bias' => ['large', 'large', 'medium'],
            ],
            'construction_company' => [
                'slug' => 'construction_company',
                'label' => 'Construction Company',
                'description' => '10 customers placing high-priority delivery orders for heavy materials.',
                'customers' => 10,
                'orders' => 12,
                'provider_mix' => ['whatsapp' => 8, 'telegram' => 4],
                'themes' => ['construction'],
                'vip_target' => 2,
                'duplicate_target' => 1,
                'timing' => 'morning',
                'delivery_bias' => ['delivery', 'delivery', 'delivery', 'delivery', 'pickup'],
                'payment_bias' => ['sinpe', 'cash', 'sinpe'],
                'date_bias' => ['today', 'today', 'tomorrow'],
                'urgency_bias' => ['urgent', 'high', 'normal'],
                'size_bias' => ['large', 'large', 'large'],
            ],
            'farmers_market' => [
                'slug' => 'farmers_market',
                'label' => 'Farmers Market',
                'description' => '20 pickup orders for today with cash and SINPE payments.',
                'customers' => 12,
                'orders' => 20,
                'provider_mix' => ['whatsapp' => 14, 'telegram' => 6],
                'themes' => ['market'],
                'vip_target' => 1,
                'duplicate_target' => 2,
                'timing' => 'morning',
                'delivery_bias' => ['pickup'],
                'payment_bias' => ['cash', 'sinpe', 'cash', 'sinpe'],
                'date_bias' => ['today', 'today', 'today', 'none'],
                'urgency_bias' => ['normal', 'urgent'],
                'size_bias' => ['small', 'small', 'medium'],
            ],
        ];
    }

    public function definition(string $key): array
    {
        return $this->definitions()[$key] ?? $this->definitions()['small_hardware_store'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function scenarioCards(): array
    {
        return array_values(array_map(static fn (array $definition): array => [
            'key' => $definition['slug'],
            'label' => $definition['label'],
            'description' => $definition['description'],
            'customers' => $definition['customers'],
            'orders' => $definition['orders'],
            'provider_mix' => $definition['provider_mix'],
        ], $this->definitions()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildScenarioMessages(Organization $organization, string $scenarioKey, RandomMessageFactory $randomMessageFactory, Carbon $startAt): array
    {
        $definition = $this->definition($scenarioKey);
        $customers = $this->buildCustomerPool($organization, $definition, $randomMessageFactory);
        $providerSequence = $this->buildProviderSequence($definition);
        $messages = [];
        $duplicateMessage = null;

        for ($index = 0; $index < (int) $definition['orders']; $index++) {
            $provider = $providerSequence[$index] ?? 'whatsapp';
            $customer = $customers[$index % count($customers)];
            $variant = 'standard';
            $message = $this->messageForScenario($definition, $randomMessageFactory, $index, $provider, $customer, $variant);
            $receivedAt = $this->receivedAtForScenario($definition, $startAt, $index);

            if ($index < (int) ($definition['vip_target'] ?? 0)) {
                $customer = $customers[0];
                $variant = 'vip';
                $message = $this->messageForScenario($definition, $randomMessageFactory, $index, $provider, $customer, $variant, true);
            }

            if ($index < (int) ($definition['duplicate_target'] ?? 0)) {
                $customer = $customers[min(1, count($customers) - 1)];
                $variant = $index % 2 === 0 ? 'duplicate_seed' : 'duplicate_match';
                if ($duplicateMessage === null) {
                    $duplicateMessage = $this->duplicateMessageForScenario($definition, $randomMessageFactory, $index, $provider, $customer, $variant);
                }

                $message = $duplicateMessage;
            }

            $messages[] = [
                'provider' => $provider,
                'customer' => $customer,
                'message_text' => $message,
                'external_message_id' => $this->generatedMessageId($definition['slug'], $provider, $index),
                'received_at' => $receivedAt,
                'variant' => $variant,
                'scenario' => $definition['slug'],
            ];
        }

        return $messages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildBusinessDayMessages(Organization $organization, RandomMessageFactory $randomMessageFactory, Carbon $startAt, int $speedMultiplier): array
    {
        $definition = $this->definition('small_hardware_store');
        $customers = $this->buildCustomerPool($organization, [
            'slug' => 'business_day',
            'customers' => 12,
            'provider_mix' => ['whatsapp' => 12, 'telegram' => 4],
        ], $randomMessageFactory);
        $timeline = [
            ['time' => '08:00', 'count' => 2, 'date' => 'today', 'delivery' => 'delivery'],
            ['time' => '08:15', 'count' => 1, 'date' => 'today', 'delivery' => 'delivery'],
            ['time' => '08:30', 'count' => 3, 'date' => 'today', 'delivery' => 'delivery'],
            ['time' => '09:20', 'count' => 1, 'date' => 'today', 'urgency' => 'urgent', 'vip' => true],
            ['time' => '10:10', 'count' => 1, 'date' => 'today', 'delivery' => 'delivery', 'express' => true],
            ['time' => '11:00', 'count' => 1, 'date' => 'today', 'delivery' => 'pickup'],
            ['time' => '11:30', 'count' => 2, 'date' => 'tomorrow', 'delivery' => 'delivery'],
            ['time' => '12:15', 'count' => 2, 'date' => 'none', 'payment' => 'cash'],
            ['time' => '13:05', 'count' => 2, 'date' => 'today', 'delivery' => 'delivery', 'payment' => 'sinpe'],
            ['time' => '14:40', 'count' => 2, 'date' => 'tomorrow', 'delivery' => 'delivery', 'large' => true],
        ];

        $messages = [];
        $providerSequence = $this->buildProviderSequence([
            'orders' => array_sum(array_column($timeline, 'count')),
            'provider_mix' => ['whatsapp' => 12, 'telegram' => 4],
        ]);
        $providerIndex = 0;
        $messageIndex = 0;

        foreach ($timeline as $slot) {
            $slotAt = $this->resolveTimelineAt($startAt, (string) $slot['time'], $speedMultiplier);

            for ($i = 0; $i < (int) $slot['count']; $i++) {
                $provider = $providerSequence[$providerIndex % count($providerSequence)] ?? 'whatsapp';
                $customer = $customers[$messageIndex % count($customers)];

                if (($slot['vip'] ?? false) === true) {
                    $customer = $customers[0];
                }

                if (($slot['express'] ?? false) === true) {
                    $customer = $customers[min(1, count($customers) - 1)];
                }

                $messages[] = [
                    'provider' => $provider,
                    'customer' => $customer,
                    'message_text' => $randomMessageFactory->buildMessage([
                        'family' => $this->pickTheme($definition['themes'] ?? ['hardware'], $randomMessageFactory),
                        'item_count' => random_int(1, 3),
                        'large' => (bool) ($slot['large'] ?? false),
                        'delivery_method' => (string) ($slot['delivery'] ?? $randomMessageFactory->pick($definition['delivery_bias'] ?? ['delivery'])),
                        'payment_method' => (string) ($slot['payment'] ?? $randomMessageFactory->pick($definition['payment_bias'] ?? ['sinpe'])),
                        'date_phrase' => (string) ($slot['date'] ?? $randomMessageFactory->pick($definition['date_bias'] ?? ['today'])),
                        'urgency' => (string) ($slot['urgency'] ?? $randomMessageFactory->pick($definition['urgency_bias'] ?? ['normal'])),
                        'before_noon' => str_starts_with((string) $slot['time'], '08:') || str_starts_with((string) $slot['time'], '09:') || str_starts_with((string) $slot['time'], '10:') || str_starts_with((string) $slot['time'], '11:'),
                    ]),
                    'external_message_id' => $this->generatedMessageId('business_day', $provider, $messageIndex),
                    'received_at' => $slotAt->copy()->addMinutes($i * max(1, (int) round(12 / max(1, $speedMultiplier)))),
                    'variant' => (($slot['vip'] ?? false) === true ? 'vip' : 'standard'),
                    'scenario' => 'business_day',
                ];

                $providerIndex++;
                $messageIndex++;
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, array<string, mixed>>
     */
    private function buildCustomerPool(Organization $organization, array $definition, RandomMessageFactory $randomMessageFactory): array
    {
        $count = max(1, (int) ($definition['customers'] ?? 1));
        $providerMix = (array) ($definition['provider_mix'] ?? ['whatsapp' => $count]);
        $providers = [];

        foreach ($providerMix as $provider => $providerCount) {
            for ($i = 0; $i < max(1, (int) $providerCount); $i++) {
                $providers[] = (string) $provider;
            }
        }

        if ($providers === []) {
            $providers = array_fill(0, $count, 'whatsapp');
        }

        $customers = [];

        for ($index = 0; $index < $count; $index++) {
            $provider = $providers[$index % count($providers)];
            $customers[] = [
                'key' => $definition['slug'] . '-customer-' . ($index + 1),
                'name' => $randomMessageFactory->customerName($index),
                'phone' => $randomMessageFactory->customerPhone($provider, $index + 1),
                'provider' => $provider,
                'external_id' => 'business-scenario:customer:' . $organization->id . ':' . $definition['slug'] . ':' . ($index + 1),
            ];
        }

        return $customers;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function buildProviderSequence(array $definition): array
    {
        $providerMix = (array) ($definition['provider_mix'] ?? ['whatsapp' => (int) ($definition['orders'] ?? 1)]);
        $providers = [];

        foreach ($providerMix as $provider => $count) {
            for ($i = 0; $i < max(1, (int) $count); $i++) {
                $providers[] = (string) $provider;
            }
        }

        if ($providers === []) {
            $providers = array_fill(0, max(1, (int) ($definition['orders'] ?? 1)), 'whatsapp');
        }

        shuffle($providers);

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function messageForScenario(array $definition, RandomMessageFactory $randomMessageFactory, int $index, string $provider, array $customer, string $variant, bool $vip = false): string
    {
        $themes = (array) ($definition['themes'] ?? ['hardware']);
        $theme = $this->pickTheme($themes, $randomMessageFactory);
        $deliveryMethod = $this->chooseBias($definition['delivery_bias'] ?? ['delivery'], $randomMessageFactory);
        $paymentMethod = $this->chooseBias($definition['payment_bias'] ?? ['sinpe'], $randomMessageFactory);
        $datePhrase = $this->chooseBias($definition['date_bias'] ?? ['today'], $randomMessageFactory);
        $urgency = $vip
            ? 'urgent'
            : $this->chooseBias($definition['urgency_bias'] ?? ['normal'], $randomMessageFactory);

        if ($definition['slug'] === 'construction_company') {
            $deliveryMethod = $index < 10 ? 'delivery' : 'pickup';
            $datePhrase = $index < 10 ? 'today' : 'tomorrow';
            $paymentMethod = $index < 9 ? 'sinpe' : 'cash';
        } elseif ($definition['slug'] === 'morning_rush') {
            $deliveryMethod = $index < 12 ? 'delivery' : 'pickup';
            $datePhrase = 'today';
            $urgency = $index < 12 ? 'urgent' : $urgency;
        } elseif ($definition['slug'] === 'farmers_market') {
            $deliveryMethod = 'pickup';
            $datePhrase = 'today';
            $paymentMethod = $index % 2 === 0 ? 'cash' : 'sinpe';
            $urgency = 'normal';
        } elseif ($definition['slug'] === 'rainy_day') {
            $datePhrase = $index < 16 ? 'tomorrow' : 'today';
            $deliveryMethod = 'delivery';
        }

        return $randomMessageFactory->buildMessage([
            'family' => $theme,
            'item_count' => $vip ? random_int(2, 3) : random_int(1, 3),
            'large' => in_array($definition['slug'], ['rainy_day', 'construction_company'], true),
            'delivery_method' => $deliveryMethod,
            'payment_method' => $paymentMethod,
            'date_phrase' => $datePhrase,
            'urgency' => $urgency,
            'before_noon' => in_array($definition['slug'], ['morning_rush', 'construction_company'], true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function duplicateMessageForScenario(array $definition, RandomMessageFactory $randomMessageFactory, int $index, string $provider, array $customer, string $variant): string
    {
        $themes = (array) ($definition['themes'] ?? ['hardware']);
        $theme = $this->pickTheme($themes, $randomMessageFactory);
        $base = $randomMessageFactory->buildMessage([
            'family' => $theme,
            'item_count' => 1,
            'large' => false,
            'delivery_method' => $this->chooseBias($definition['delivery_bias'] ?? ['delivery'], $randomMessageFactory),
            'payment_method' => $this->chooseBias($definition['payment_bias'] ?? ['sinpe'], $randomMessageFactory),
            'date_phrase' => $this->chooseBias($definition['date_bias'] ?? ['today'], $randomMessageFactory),
            'urgency' => 'normal',
            'before_noon' => in_array($definition['slug'], ['morning_rush', 'construction_company'], true),
        ]);

        return $base;
    }

    private function chooseBias(array $values, RandomMessageFactory $randomMessageFactory): string
    {
        return $randomMessageFactory->pick($values);
    }

    private function pickTheme(array $themes, RandomMessageFactory $randomMessageFactory): string
    {
        $themes = array_values(array_filter($themes, static fn ($value): bool => is_string($value) && $value !== ''));

        if ($themes === []) {
            return 'hardware';
        }

        return $randomMessageFactory->pick($themes);
    }

    private function generatedMessageId(string $scenarioSlug, string $provider, int $index): string
    {
        return sprintf('bizsim-%s-%s-%04d', $scenarioSlug, strtolower(trim($provider)), $index + 1);
    }

    private function receivedAtForScenario(array $definition, Carbon $startAt, int $index): Carbon
    {
        if (($definition['timing'] ?? 'random') === 'morning') {
            return $startAt->copy()->startOfDay()->addHours(8)->addMinutes($index * 14 + random_int(0, 4));
        }

        return $startAt->copy()->startOfDay()->addMinutes($index * 17 + random_int(0, 11));
    }

    private function resolveTimelineAt(Carbon $startAt, string $time, int $speedMultiplier): Carbon
    {
        $start = $startAt->copy()->startOfDay();
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        $target = $start->copy()->addHours($hours)->addMinutes($minutes);
        $offset = $start->diffInMinutes($target, false);

        return $start->copy()->addMinutes((int) round($offset / max(1, $speedMultiplier)));
    }
}
