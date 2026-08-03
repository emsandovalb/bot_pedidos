<?php

namespace App\Services\Operations;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class NextActionQueue
{
    /**
     * @param  Collection<int, array<string, mixed>>  $orders
     * @return array{sections:array<int, array<string, mixed>>, metrics:array<string, mixed>}
     */
    public function build(Collection $orders, ?CarbonInterface $referenceTime = null): array
    {
        $referenceTime ??= now();

        $cards = $orders
            ->map(fn (array $order): AgendaCard => new AgendaCard($order, $referenceTime))
            ->values();

        $activeCards = $cards
            ->reject(fn (AgendaCard $card): bool => $card->isCompletedToday())
            ->values();

        $doNowCards = $activeCards
            ->sortBy(fn (AgendaCard $card): array => $card->sortKey())
            ->values()
            ->take(5);

        $doNowIds = $doNowCards->map(fn (AgendaCard $card): int => (int) ($card->toArray()['id'] ?? 0))->all();

        $upcomingCards = $activeCards
            ->reject(function (AgendaCard $card) use ($doNowIds): bool {
                return in_array((int) ($card->toArray()['id'] ?? 0), $doNowIds, true);
            })
            ->values();

        $completedCards = $cards->filter(fn (AgendaCard $card): bool => $card->isCompletedToday())->values();

        return [
            'sections' => [
                (new DoNowSection($activeCards, 5, $referenceTime))->toArray(),
                (new UpcomingSection($upcomingCards, $referenceTime))->toArray(),
                (new CompletedSection($completedCards))->toArray(),
            ],
            'metrics' => $this->metrics($cards, $referenceTime),
        ];
    }

    /**
     * @param  Collection<int, AgendaCard>  $cards
     * @return array<string, mixed>
     */
    private function metrics(Collection $cards, CarbonInterface $referenceTime): array
    {
        $today = $referenceTime->toDateString();

        $asArray = $cards->map(fn (AgendaCard $card): array => $card->toArray());

        return [
            'urgent_orders' => $asArray->filter(function (array $card): bool {
                $risk = (string) ($card['risk_level'] ?? '');
                $priority = (string) ($card['priority_level'] ?? '');
                $remaining = $card['remaining_sla_minutes'] ?? null;

                return $risk === 'critical'
                    || $risk === 'high'
                    || $priority === 'urgent'
                    || (is_numeric($remaining) && (int) $remaining < 0);
            })->count(),
            'orders_today' => $asArray->filter(fn (array $card): bool => ($card['commitment_date'] ?? null) === $today)->count(),
            'deliveries' => $asArray->filter(fn (array $card): bool => ($card['delivery_method'] ?? null) === 'delivery')->count(),
            'pickups' => $asArray->filter(fn (array $card): bool => ($card['delivery_method'] ?? null) === 'pickup')->count(),
            'overdue' => $asArray->filter(function (array $card) use ($today): bool {
                $remaining = $card['remaining_sla_minutes'] ?? null;

                return ($card['commitment_date'] ?? null) === $today
                    && is_numeric($remaining)
                    && (int) $remaining < 0;
            })->count(),
            'completed_today' => $cards->filter(fn (AgendaCard $card): bool => $card->isCompletedToday())->count(),
        ];
    }
}
