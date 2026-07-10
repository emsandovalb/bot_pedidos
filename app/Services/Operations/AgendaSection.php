<?php

namespace App\Services\Operations;

class AgendaSection
{
    /**
     * @param  array<int, array{label:string,cards:array<int, array<string, mixed>>}>  $groups
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $tone,
        public readonly string $emptyMessage,
        private array $groups = [],
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    public function addGroup(string $label, array $cards): void
    {
        if ($cards === []) {
            return;
        }

        $this->groups[] = [
            'label' => $label,
            'cards' => $cards,
        ];
    }

    /**
     * @return array{key:string,label:string,tone:string,empty_message:string,groups:array<int, array{label:string,cards:array<int, array<string, mixed>>}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'tone' => $this->tone,
            'empty_message' => $this->emptyMessage,
            'groups' => $this->groups,
        ];
    }
}
