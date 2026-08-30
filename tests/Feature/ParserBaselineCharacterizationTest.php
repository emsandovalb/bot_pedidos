<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\OrderParserService;
use App\Services\ProductMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParserBaselineCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('baselineCases')]
    public function test_current_parser_behavior_matches_the_versioned_baseline(array $case): void
    {
        $result = app(OrderParserService::class)->parse($case['input']);
        $expected = $case['expected'];

        $this->assertSame($case['input'], $result['raw_text'], $case['id'] . ': raw message must be retained.');
        $this->assertSame($expected['normalized_text'], $result['normalized_text'], $case['id']);
        $this->assertSame($expected['confidence'], $result['confidence'], $case['id']);
        $this->assertSame($expected['needs_review'], $result['needs_review'], $case['id']);
        $this->assertSame($expected['notes'], $result['notes'], $case['id']);
        $this->assertSame(count($expected['items']), count($result['items']), $case['id']);

        foreach ($expected['items'] as $index => $expectedItem) {
            foreach ($expectedItem as $field => $value) {
                $this->assertSame($value, $result['items'][$index][$field], $case['id'] . ': ' . $field);
            }
        }
    }

    #[DataProvider('catalogCases')]
    public function test_catalog_alias_resolution_is_characterized_separately_from_syntax_parsing(array $case): void
    {
        $organization = Organization::create(['name' => 'Parser baseline', 'status' => Organization::STATUS_ACTIVE]);
        $catalog = $case['catalog'];
        $product = Product::create([
            'organization_id' => $organization->id,
            'name' => $catalog['product_name'],
            'sku' => 'BASELINE-' . $case['id'],
            'unit_label' => 'unidad',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        foreach ($catalog['aliases'] as $alias) {
            ProductAlias::create([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'alias' => $alias,
                'match_weight' => 100,
                'is_active' => true,
            ]);
        }

        $parsed = app(OrderParserService::class)->parse($case['input']);
        $item = $parsed['items'][0];
        $match = app(ProductMatchingService::class)->match($organization, $item['product_name'], $item['raw_text']);

        $this->assertSame($product->id, $match['product']?->id, $case['id']);
        $this->assertSame($catalog['expected_alias'], $match['matched_text'], $case['id']);
        $this->assertGreaterThanOrEqual(0.9, $match['confidence_score'], $case['id']);
    }

    public static function baselineCases(): array
    {
        return array_reduce(self::cases(), static function (array $provided, array $case): array {
            $provided[$case['id']] = [$case];

            return $provided;
        }, []);
    }

    public static function catalogCases(): array
    {
        return array_reduce(
            array_filter(self::cases(), static fn (array $case): bool => isset($case['catalog'])),
            static function (array $provided, array $case): array {
                $provided[$case['id']] = [$case];

                return $provided;
            },
            [],
        );
    }

    private static function cases(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/parser-quality/baseline-cases.json');

        return json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
