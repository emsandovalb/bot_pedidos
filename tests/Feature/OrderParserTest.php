<?php

namespace Tests\Feature;

use App\Services\OrderParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_handles_simple_quantity_phrase(): void
    {
        $result = app(OrderParserService::class)->parse('2 bolsas de jardín');

        $this->assertSame('2 bolsas de jardin', $result['normalized_text']);
        $this->assertSame('2 bolsas de jardín', $result['raw_text']);
        $this->assertFalse($result['needs_review']);
        $this->assertGreaterThan(0.9, $result['confidence']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
        $this->assertNotNull($result['items'][0]['matched_text']);
    }

    public function test_parser_handles_multiple_items_phrase(): void
    {
        $result = app(OrderParserService::class)->parse('5 bolsas de apretados y 1 caja de vasos');

        $this->assertFalse($result['needs_review']);
        $this->assertCount(2, $result['items']);
        $this->assertSame(5, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('apretados', $result['items'][0]['product_name']);
        $this->assertSame(1, $result['items'][1]['quantity']);
        $this->assertSame('caja', $result['items'][1]['unit']);
        $this->assertSame('vasos', $result['items'][1]['product_name']);
    }

    public function test_parser_defaults_quantity_to_one_when_not_provided(): void
    {
        $result = app(OrderParserService::class)->parse('Ocupo bolsas negras grandes, servilletas y vasos');

        $this->assertTrue($result['needs_review']);
        $this->assertCount(3, $result['items']);
        $this->assertSame(1, $result['items'][0]['quantity']);
        $this->assertSame(1, $result['items'][1]['quantity']);
        $this->assertSame(1, $result['items'][2]['quantity']);
        $this->assertSame('bolsas negras grandes', $result['items'][0]['product_name']);
        $this->assertSame('servilletas', $result['items'][1]['product_name']);
        $this->assertSame('vasos', $result['items'][2]['product_name']);
    }
}
