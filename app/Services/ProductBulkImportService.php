<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use Illuminate\Validation\ValidationException;

class ProductBulkImportService
{
    public function __construct(
        private readonly ProductTextNormalizer $normalizer,
    ) {
    }

    /**
     * @return array{
     *     products_created:int,
     *     products_updated:int,
     *     aliases_created:int,
     *     aliases_updated:int,
     *     skipped_lines:int,
     *     errors: array<int, array{line:int, content:string, error:string}>
     * }
     */
    public function import(Organization $organization, string $content): array
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $nonEmptyLines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));

        if (count($nonEmptyLines) > 500) {
            throw ValidationException::withMessages([
                'content' => 'You may import up to 500 non-empty lines at a time.',
            ]);
        }

        $summary = [
            'products_created' => 0,
            'products_updated' => 0,
            'aliases_created' => 0,
            'aliases_updated' => 0,
            'skipped_lines' => 0,
            'errors' => [],
        ];

        foreach ($lines as $lineNumber => $rawLine) {
            $line = $this->normalizeDisplayText($rawLine);

            if ($line === '') {
                continue;
            }

            $parsed = $this->parseLine($line);

            if ($parsed['error'] !== null) {
                $summary['skipped_lines']++;
                $summary['errors'][] = [
                    'line' => $lineNumber + 1,
                    'content' => $line,
                    'error' => $parsed['error'],
                ];

                continue;
            }

            $product = $this->upsertProduct($organization, $parsed['name'], $parsed['sku'], $parsed['unit_label']);
            $summary[$product['created'] ? 'products_created' : 'products_updated']++;

            foreach ($parsed['aliases'] as $aliasText) {
                $aliasResult = $this->upsertAlias($organization, $product['model'], $aliasText);
                $summary[$aliasResult['created'] ? 'aliases_created' : 'aliases_updated']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{name:string,sku:?string,unit_label:?string,aliases:array<int, string>,error:?string}
     */
    private function parseLine(string $line): array
    {
        $parts = array_map(
            fn (string $part): string => $this->normalizeDisplayText($part),
            explode('|', $line, 4)
        );

        $name = $parts[0] ?? '';

        if ($name === '') {
            return [
                'name' => '',
                'sku' => null,
                'unit_label' => null,
                'aliases' => [],
                'error' => 'Product name is required.',
            ];
        }

        if (count($parts) > 4) {
            return [
                'name' => '',
                'sku' => null,
                'unit_label' => null,
                'aliases' => [],
                'error' => 'Too many fields in line.',
            ];
        }

        $aliases = [];

        if (isset($parts[3]) && $parts[3] !== '') {
            $aliases = collect(explode(',', $parts[3]))
                ->map(fn (string $alias): string => $this->normalizeDisplayText($alias))
                ->filter()
                ->unique(fn (string $alias): string => $this->normalizer->normalize($alias))
                ->values()
                ->all();
        }

        return [
            'name' => $name,
            'sku' => isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null,
            'unit_label' => isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null,
            'aliases' => $aliases,
            'error' => null,
        ];
    }

    /**
     * @return array{model: Product, created: bool}
     */
    private function upsertProduct(Organization $organization, string $name, ?string $sku, ?string $unitLabel): array
    {
        $normalizedName = $this->normalizer->normalize($name);

        $product = Product::query()
            ->where('organization_id', $organization->id)
            ->where('normalized_name', $normalizedName)
            ->first();

        $created = $product === null;

        if ($created) {
            $product = new Product([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'name' => $name,
                'sku' => $sku,
                'unit_label' => $unitLabel,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        } else {
            $product->fill([
                'name' => $name,
                'sku' => $sku,
                'unit_label' => $unitLabel,
                'branch_id' => null,
                'is_active' => true,
            ]);
        }

        $product->save();

        return [
            'model' => $product,
            'created' => $created,
        ];
    }

    /**
     * @return array{created: bool}
     */
    private function upsertAlias(Organization $organization, Product $product, string $aliasText): array
    {
        $normalizedAlias = $this->normalizer->normalize($aliasText);

        if ($normalizedAlias === '') {
            return [
                'created' => false,
            ];
        }

        $alias = ProductAlias::query()
            ->where('organization_id', $organization->id)
            ->where('normalized_alias', $normalizedAlias)
            ->first();

        $created = $alias === null;

        if ($created) {
            $alias = new ProductAlias([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'alias' => $aliasText,
                'match_weight' => 100,
                'is_active' => true,
            ]);
        } else {
            $alias->fill([
                'product_id' => $product->id,
                'alias' => $aliasText,
                'match_weight' => 100,
                'is_active' => true,
            ]);
        }

        $alias->save();

        return [
            'created' => $created,
        ];
    }

    private function normalizeDisplayText(?string $value): string
    {
        $value = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
