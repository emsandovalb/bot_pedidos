<?php

namespace App\Services\Operations;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OperationsAgenda
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

        $critical = $cards->filter(fn (AgendaCard $card): bool => $card->isCritical())->values();
        $dueSoon = $cards->filter(fn (AgendaCard $card): bool => ! $card->isCritical() && $card->isDueSoon())->values();
        $today = $cards->filter(fn (AgendaCard $card): bool => $card->isToday())->values();
        $tomorrow = $cards->filter(fn (AgendaCard $card): bool => $card->isTomorrow())->values();
        $noCommitment = $cards->filter(fn (AgendaCard $card): bool => $card->isNoCommitment())->values();
        $completedToday = $cards->filter(fn (AgendaCard $card): bool => $card->isCompletedToday())->values();

        return [
            'sections' => [
                $this->sectionFromCards(
                    'critical',
                    'Critical',
                    'border-rose-200 bg-rose-50/80 text-rose-800',
                    'No hay pedidos críticos.',
                    $critical,
                    grouped: false,
                ),
                $this->sectionFromCards(
                    'due_soon',
                    'Due Soon',
                    'border-orange-200 bg-orange-50/80 text-orange-800',
                    'No hay pedidos en riesgo alto.',
                    $dueSoon,
                    grouped: false,
                ),
                $this->sectionFromCards(
                    'today',
                    'Today',
                    'border-blue-200 bg-blue-50/80 text-blue-800',
                    'No hay pedidos para hoy.',
                    $today,
                    grouped: true,
                ),
                $this->sectionFromCards(
                    'tomorrow',
                    'Tomorrow',
                    'border-sky-200 bg-sky-50/80 text-sky-800',
                    'No hay pedidos para mañana.',
                    $tomorrow,
                    grouped: true,
                ),
                $this->sectionFromCards(
                    'no_commitment',
                    'No Commitment',
                    'border-slate-200 bg-slate-50 text-slate-700',
                    'No hay pedidos sin compromiso.',
                    $noCommitment,
                    grouped: false,
                ),
                $this->sectionFromCards(
                    'completed',
                    'Completed Today',
                    'border-emerald-200 bg-emerald-50/80 text-emerald-800',
                    'No hay pedidos completados hoy.',
                    $completedToday,
                    grouped: false,
                ),
            ],
            'metrics' => $this->metrics($cards, $referenceTime),
        ];
    }

    /**
     * @param  Collection<int, AgendaCard>  $cards
     * @return array<string, mixed>
     */
    private function sectionFromCards(string $key, string $label, string $tone, string $emptyMessage, Collection $cards, bool $grouped): array
    {
        $section = new AgendaSection($key, $label, $tone, $emptyMessage);
        $sorted = $cards->sortBy(fn (AgendaCard $card): array => $card->sortKey())->values();

        if (! $grouped) {
            $section->addGroup($label, $sorted->map(fn (AgendaCard $card): array => $card->toArray())->all());

            return $section->toArray();
        }

        $groupedCards = $sorted->groupBy(fn (AgendaCard $card): string => $card->toArray()['time_window_label'] ?? 'Anytime');
        $timeWindowOrder = ['Morning', 'Before Noon', 'Afternoon', 'Evening', 'Anytime'];

        foreach ($timeWindowOrder as $groupLabel) {
            $group = $groupedCards->get($groupLabel, collect());

            if ($group->isEmpty()) {
                continue;
            }

            $section->addGroup(
                $groupLabel,
                $group->map(fn (AgendaCard $card): array => $card->toArray())->values()->all(),
            );
        }

        return $section->toArray();
    }

    /**
     * @param  Collection<int, AgendaCard>  $cards
     * @return array<string, mixed>
     */
    private function metrics(Collection $cards, CarbonInterface $referenceTime): array
    {
        $today = $referenceTime->toDateString();

        $ordersToday = $cards->filter(function (AgendaCard $card) use ($today): bool {
            return ($card->toArray()['commitment_date'] ?? null) === $today;
        })->count();
        $deliveries = $cards->filter(fn (AgendaCard $card): bool => ($card->toArray()['delivery_method'] ?? null) === 'delivery')->count();
        $pickups = $cards->filter(fn (AgendaCard $card): bool => ($card->toArray()['delivery_method'] ?? null) === 'pickup')->count();
        $urgent = $cards->filter(fn (AgendaCard $card): bool => in_array($card->toArray()['risk_level'] ?? null, ['critical', 'high'], true))->count();
        $completed = $cards->filter(fn (AgendaCard $card): bool => $card->isCompletedToday())->count();
        $averageRemainingSla = $this->averageRemainingSla($cards);

        return [
            'orders_today' => $ordersToday,
            'deliveries' => $deliveries,
            'pickups' => $pickups,
            'urgent' => $urgent,
            'completed' => $completed,
            'average_sla_remaining' => $averageRemainingSla,
        ];
    }

    /**
     * @param  Collection<int, AgendaCard>  $cards
     */
    private function averageRemainingSla(Collection $cards): ?int
    {
        $values = $cards
            ->map(fn (AgendaCard $card): mixed => $card->toArray()['remaining_sla_minutes'] ?? null)
            ->filter(static fn ($value): bool => is_numeric($value))
            ->map(static fn ($value): int => (int) $value)
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return (int) round($values->avg());
    }
}
