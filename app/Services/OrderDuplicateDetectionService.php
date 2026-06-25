<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class OrderDuplicateDetectionService
{
    public function detect(Order $order, int $windowMinutes = 30): array
    {
        $order->loadMissing(['customer', 'orderItems.product']);

        $fingerprint = $this->fingerprint($order);
        $candidates = $this->candidateOrders($order, $windowMinutes);
        $bestMatch = null;

        foreach ($candidates as $candidate) {
            $evaluation = $this->evaluateCandidate($order, $candidate, $windowMinutes);

            if ($bestMatch === null || $evaluation['score'] > $bestMatch['score']) {
                $bestMatch = array_merge($evaluation, ['matched_order' => $candidate]);
            }
        }

        return [
            'matched_order' => $bestMatch['matched_order'] ?? null,
            'score' => $bestMatch['score'] ?? 0,
            'reason' => $bestMatch['reason'] ?? null,
            'fingerprint' => $fingerprint,
        ];
    }

    private function candidateOrders(Order $order, int $windowMinutes): Collection
    {
        $start = $order->created_at?->copy()->subMinutes($windowMinutes) ?? now()->subMinutes($windowMinutes);

        return Order::query()
            ->with(['customer', 'orderItems.product'])
            ->where('organization_id', $order->organization_id)
            ->whereKeyNot($order->id)
            ->whereNotIn('status', [
                Order::STATUS_CANCELLED,
                Order::STATUS_REJECTED,
            ])
            ->where('created_at', '>=', $start)
            ->orderByDesc('created_at')
            ->get();
    }

    private function evaluateCandidate(Order $order, Order $candidate, int $windowMinutes): array
    {
        $sameCustomer = $this->hasStrongCustomerMatch($order, $candidate);
        $currentStrict = $this->normalizedStrictItemSet($order);
        $candidateStrict = $this->normalizedStrictItemSet($candidate);
        $currentLoose = $this->normalizedLooseItemSet($order);
        $candidateLoose = $this->normalizedLooseItemSet($candidate);
        $messageSimilarity = $this->similarity(
            $this->normalizedMessage($order->raw_message_text ?? ''),
            $this->normalizedMessage($candidate->raw_message_text ?? ''),
        );

        if ($sameCustomer && $currentStrict === $candidateStrict && $currentStrict !== []) {
            return [
                'score' => 95,
                'reason' => sprintf(
                    'Same customer and identical normalized item set within the last %d minutes.',
                    $windowMinutes,
                ),
            ];
        }

        if ($sameCustomer) {
            $itemSimilarity = $this->similarity(implode('|', $currentLoose), implode('|', $candidateLoose));

            if ($itemSimilarity >= 85.0 || $messageSimilarity >= 85.0) {
                return [
                    'score' => 75,
                    'reason' => sprintf(
                        'Same customer and highly similar item set within the last %d minutes.',
                        $windowMinutes,
                    ),
                ];
            }
        }

        if (! $sameCustomer && $messageSimilarity >= 90.0) {
            return [
                'score' => 60,
                'reason' => sprintf(
                    'Similar message text from an unknown customer within the last %d minutes.',
                    $windowMinutes,
                ),
            ];
        }

        return [
            'score' => 0,
            'reason' => null,
        ];
    }

    private function hasStrongCustomerMatch(Order $order, Order $candidate): bool
    {
        if ($order->customer_id !== null && $candidate->customer_id !== null && $order->customer_id === $candidate->customer_id) {
            return true;
        }

        $currentPhone = $this->normalizeContact($order->customer?->phone ?? null);
        $candidatePhone = $this->normalizeContact($candidate->customer?->phone ?? null);

        return $currentPhone !== null && $currentPhone === $candidatePhone;
    }

    /**
     * @return array<int, string>
     */
    private function normalizedStrictItemSet(Order $order): array
    {
        return $order->orderItems
            ->map(fn ($item): string => implode('|', [
                $item->product_id !== null
                    ? 'product:' . $item->product_id
                    : 'text:' . $this->normalizedText($item->matched_text ?? $item->raw_text ?? ''),
                'qty:' . $this->normalizedQuantity($item->quantity),
                'unit:' . $this->normalizedText($item->unit ?? ''),
            ]))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normalizedLooseItemSet(Order $order): array
    {
        return $order->orderItems
            ->map(fn ($item): string => implode('|', [
                $item->product_id !== null
                    ? 'product:' . $item->product_id
                    : 'text:' . $this->normalizedText($item->matched_text ?? $item->raw_text ?? ''),
                'unit:' . $this->normalizedText($item->unit ?? ''),
            ]))
            ->sort()
            ->values()
            ->all();
    }

    private function fingerprint(Order $order): string
    {
        $customerKey = $this->customerKey($order);
        $itemParts = $this->normalizedStrictItemSet($order);

        return sha1($customerKey . '|' . implode(';', $itemParts));
    }

    private function customerKey(Order $order): string
    {
        $normalizedPhone = $this->normalizeContact($order->customer?->phone ?? null);

        if ($normalizedPhone !== null) {
            return 'phone:' . $normalizedPhone;
        }

        if ($order->customer_id !== null) {
            return 'customer:' . $order->customer_id;
        }

        return 'unknown';
    }

    private function normalizedMessage(string $value): string
    {
        return $this->normalizedText($value);
    }

    private function normalizedText(string $value): string
    {
        $normalized = Str::ascii(mb_strtolower(trim($value)));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeContact(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/u', '', $value) ?? '';

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedQuantity(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }

    private function similarity(string $first, string $second): float
    {
        if ($first === '' || $second === '') {
            return 0.0;
        }

        similar_text($first, $second, $percent);

        return (float) $percent;
    }
}
