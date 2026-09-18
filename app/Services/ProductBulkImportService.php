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

            $product = $this->upsertProduct($organization, $parsed['name'], $parsed['sku'], $parsed['unit_label'], $parsed['price']);
            $summary[$product['created'] ? 'products_created' : 'products_updated']++;

            foreach ($parsed['aliases'] as $aliasText) {
                $aliasResult = $this->upsertAlias($organization, $product['model'], $aliasText);
                $summary[$aliasResult['created'] ? 'aliases_created' : 'aliases_updated']++;
            }
        }

        return $summary;
    }

    /**
     * Formato por línea: `nombre | sku | unidad | alias1, alias2`
     * o, con precio: `nombre | sku | unidad | precio | alias1, alias2`.
     *
     * @return array{name:string,sku:?string,unit_label:?string,price:?float,aliases:array<int, string>,error:?string}
     */
    private function parseLine(string $line): array
    {
        $error = static fn (string $message): array => [
            'name' => '', 'sku' => null, 'unit_label' => null, 'price' => null, 'aliases' => [], 'error' => $message,
        ];

        $parts = array_map(
            fn (string $part): string => $this->normalizeDisplayText($part),
            explode('|', $line, 6)
        );

        $name = $parts[0] ?? '';

        if ($name === '') {
            return $error('Product name is required.');
        }

        if (count($parts) > 5) {
            return $error('Too many fields in line.');
        }

        // Con 5 campos el 4º es el precio y el 5º los alias; con 4 o menos no hay precio.
        $hasPrice = count($parts) === 5;
        $priceRaw = $hasPrice ? ($parts[3] ?? '') : '';
        $aliasesRaw = $hasPrice ? ($parts[4] ?? '') : ($parts[3] ?? '');

        $price = null;
        if ($priceRaw !== '') {
            $normalizedPrice = str_replace(',', '.', $priceRaw);
            if (! is_numeric($normalizedPrice) || (float) $normalizedPrice < 0) {
                return $error('Price must be a non-negative number.');
            }
            $price = round((float) $normalizedPrice, 2);
        }

        $aliases = [];

        if ($aliasesRaw !== '') {
            $aliases = collect(explode(',', $aliasesRaw))
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
            'price' => $price,
            'aliases' => $aliases,
            'error' => null,
        ];
    }

    /**
     * @return array{model: Product, created: bool}
     */
    private function upsertProduct(Organization $organization, string $name, ?string $sku, ?string $unitLabel, ?float $price): array
    {
        $normalizedName = $this->normalizer->normalize($name);

        $product = Product::query()
            ->where('organization_id', $organization->id)
            ->where('normalized_name', $normalizedName)
            ->first();

        $created = $product === null;

        $attributes = [
            'name' => $name,
            'sku' => $sku,
            'unit_label' => $unitLabel,
            'branch_id' => null,
            'is_active' => true,
        ];

        // Solo se pisa el precio si la línea trae uno; así una reimportación sin
        // precio no borra el que ya estaba.
        if ($price !== null) {
            $attributes['price'] = $price;
        }

        if ($created) {
            $product = new Product($attributes + [
                'organization_id' => $organization->id,
                'sort_order' => 0,
            ]);
        } else {
            $product->fill($attributes);
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
