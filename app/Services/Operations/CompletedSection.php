<?php

namespace App\Services\Operations;

use Illuminate\Support\Collection;

class CompletedSection
{
    /**
     * @param  Collection<int, AgendaCard>  $cards
     */
    public function __construct(private readonly Collection $cards)
    {
    }

    /**
     * @return array{key:string,label:string,empty_message:string,collapsed:bool,cards:array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => 'completed',
            'label' => 'LISTOS',
            'empty_message' => 'Nada despachado hoy.',
            'collapsed' => true,
            'cards' => $this->cards
                ->sortBy(fn (AgendaCard $card): array => $card->sortKey())
                ->values()
                ->map(fn (AgendaCard $card): array => $card->toArray())
                ->all(),
        ];
    }
}
