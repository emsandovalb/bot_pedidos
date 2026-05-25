<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Support\Collection;

class ProductMatchingService
{
    public function __construct(
        private readonly ProductTextNormalizer $normalizer,
    ) {
    }

    /**
     * @return array{product:?Product, matched_text:?string, confidence_score:float}
     */
    public function match(Organization $organization, string $productName, ?string $rawText = null): array
    {
        $candidates = collect(array_filter([
            $this->normalizer->normalize($productName),
            $this->normalizer->normalize($rawText),
        ], static fn (string $value): bool => $value !== ''))->unique()->values();

        if ($candidates->isEmpty()) {
            return $this->noMatch();
        }

        $aliases = ProductAlias::query()
            ->with('product')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereHas('product', static function ($query): void {
                $query->where('is_active', true);
            })
            ->get()
            ->filter(static fn (ProductAlias $alias): bool => trim($alias->normalized_alias) !== '');

        if ($aliases->isEmpty()) {
            return $this->noMatch();
        }

        $exactMatch = $this->findExactMatch($aliases, $candidates);

        if ($exactMatch !== null) {
            return $exactMatch;
        }

        $containsMatch = $this->findContainsMatch($aliases, $candidates);

        if ($containsMatch !== null) {
            return $containsMatch;
        }

        return $this->noMatch();
    }

    /**
     * @param  Collection<int, ProductAlias>  $aliases
     * @param  Collection<int, string>  $candidates
     * @return array{product:?Product, matched_text:?string, confidence_score:float}|null
     */
    private function findExactMatch(Collection $aliases, Collection $candidates): ?array
    {
        $matches = $aliases->filter(function (ProductAlias $alias) use ($candidates): bool {
            return $candidates->contains($alias->normalized_alias);
        });

        if ($matches->isEmpty()) {
            return null;
        }

        $alias = $matches
            ->sort(function (ProductAlias $a, ProductAlias $b): int {
                $lengthComparison = mb_strlen($b->normalized_alias) <=> mb_strlen($a->normalized_alias);

                if ($lengthComparison !== 0) {
                    return $lengthComparison;
                }

                return $b->match_weight <=> $a->match_weight;
            })
            ->first();

        return [
            'product' => $alias?->product,
            'matched_text' => $alias?->alias,
            'confidence_score' => $this->exactMatchConfidence($alias?->match_weight ?? 100),
        ];
    }

    /**
     * @param  Collection<int, ProductAlias>  $aliases
     * @param  Collection<int, string>  $candidates
     * @return array{product:?Product, matched_text:?string, confidence_score:float}|null
     */
    private function findContainsMatch(Collection $aliases, Collection $candidates): ?array
    {
        $matches = collect();

        foreach ($aliases as $alias) {
            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                if (str_contains($candidate, $alias->normalized_alias) || str_contains($alias->normalized_alias, $candidate)) {
                    $matches->push([
                        'alias' => $alias,
                        'candidate' => $candidate,
                    ]);

                    break;
                }
            }
        }

        if ($matches->isEmpty()) {
            return null;
        }

        $best = $matches
            ->sort(function (array $a, array $b): int {
                $lengthComparison = mb_strlen($b['alias']->normalized_alias) <=> mb_strlen($a['alias']->normalized_alias);

                if ($lengthComparison !== 0) {
                    return $lengthComparison;
                }

                return $b['alias']->match_weight <=> $a['alias']->match_weight;
            })
            ->first();

        /** @var ProductAlias $alias */
        $alias = $best['alias'];
        $candidate = $best['candidate'];

        return [
            'product' => $alias->product,
            'matched_text' => $alias->alias,
            'confidence_score' => $this->containsMatchConfidence($alias->match_weight, mb_strlen($alias->normalized_alias), mb_strlen($candidate)),
        ];
    }

    private function exactMatchConfidence(int $matchWeight): float
    {
        return round(min(0.99, 0.95 + min($matchWeight, 1000) / 10000), 2);
    }

    private function containsMatchConfidence(int $matchWeight, int $aliasLength, int $candidateLength): float
    {
        $lengthBonus = $candidateLength > 0 ? min(0.12, $aliasLength / max($candidateLength, 1) * 0.12) : 0.05;
        $weightBonus = min(0.05, min($matchWeight, 1000) / 20000);

        return round(min(0.92, 0.78 + $lengthBonus + $weightBonus), 2);
    }

    /**
     * @return array{product:?Product, matched_text:?string, confidence_score:float}
     */
    private function noMatch(): array
    {
        return [
            'product' => null,
            'matched_text' => null,
            'confidence_score' => 0.15,
        ];
    }
}
