<?php

namespace App\Services\Operations;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class UpcomingSection
{
    /**
     * @param  Collection<int, AgendaCard>  $cards
     */
    public function __construct(
        private readonly Collection $cards,
        private readonly ?CarbonInterface $referenceTime = null,
    ) {
    }

    /**
     * @return array{key:string,label:string,empty_message:string,groups:array<int, array{key:string,label:string,cards:array<int, array<string, mixed>>}>}
     */
    public function toArray(): array
    {
        $referenceTime = $this->referenceTime ?? now();
        $today = $referenceTime->toDateString();
        $tomorrow = $referenceTime->copy()->addDay()->toDateString();
        $buckets = [
            'Morning' => [],
            'Afternoon' => [],
            'Evening' => [],
            'Tomorrow' => [],
        ];

        foreach ($this->cards->sortBy(fn (AgendaCard $card): array => $card->sortKey())->values() as $card) {
            $cardArray = $card->toArray();
            $commitmentDate = $cardArray['commitment_date'] ?? null;
            $label = $this->labelForCard($cardArray, $today, $tomorrow);

            if (! array_key_exists($label, $buckets)) {
                $label = 'Tomorrow';
            }

            $buckets[$label][] = $cardArray;
        }

        $groups = [];

        foreach (['Morning', 'Afternoon', 'Evening', 'Tomorrow'] as $label) {
            if ($buckets[$label] === []) {
                continue;
            }

            $groups[] = [
                'key' => strtolower(str_replace(' ', '_', $label)),
                'label' => $label,
                'cards' => $buckets[$label],
            ];
        }

        return [
            'key' => 'next',
            'label' => 'NEXT',
            'empty_message' => 'No upcoming work.',
            'groups' => $groups,
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function labelForCard(array $card, string $today, string $tomorrow): string
    {
        $commitmentDate = (string) ($card['commitment_date'] ?? '');

        if ($commitmentDate === $tomorrow) {
            return 'Tomorrow';
        }

        if ($commitmentDate !== '' && $commitmentDate !== $today) {
            return 'Tomorrow';
        }

        return match ((string) ($card['time_window_label'] ?? 'Anytime')) {
            'Morning' => 'Morning',
            'Before Noon', 'Afternoon' => 'Afternoon',
            'Evening' => 'Evening',
            default => 'Tomorrow',
        };
    }
}
