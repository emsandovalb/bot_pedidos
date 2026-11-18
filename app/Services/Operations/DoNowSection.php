<?php

namespace App\Services\Operations;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DoNowSection
{
    /**
     * @param  Collection<int, AgendaCard>  $cards
     */
    public function __construct(
        private readonly Collection $cards,
        private readonly int $limit = 5,
        private readonly ?CarbonInterface $referenceTime = null,
    ) {}

    /**
     * @return array{key:string,label:string,empty_message:string,limit:int,cards:array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        $sorted = $this->cards
            ->sortBy(fn (AgendaCard $card): array => $card->sortKey())
            ->values()
            ->take($this->limit)
            ->map(fn (AgendaCard $card): array => $card->toArray())
            ->all();

        return [
            'key' => 'do_now',
            'label' => 'AHORA',
            'empty_message' => 'Nada urgente. ¡Vas al día!',
            'limit' => $this->limit,
            'cards' => $sorted,
        ];
    }
}
