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
        $result = app(OrderParserService::class)->parse('2 bolsas de jardin');

        $this->assertSame('2 bolsas de jardin', $result['normalized_text']);
        $this->assertSame('2 bolsas de jardin', $result['raw_text']);
        $this->assertFalse($result['needs_review']);
        $this->assertGreaterThan(0.9, $result['confidence']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
        $this->assertSame('bolsas de jardin', $result['items'][0]['matched_text']);
    }

    public function test_parser_handles_comma_and_y_separated_items(): void
    {
        $result = app(OrderParserService::class)->parse('2 bolsas de jardin, 1 caja de vasos y 3 paquetes de servilletas');

        $this->assertCount(3, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
        $this->assertSame(1, $result['items'][1]['quantity']);
        $this->assertSame('caja', $result['items'][1]['unit']);
        $this->assertSame('vasos', $result['items'][1]['product_name']);
        $this->assertSame(3, $result['items'][2]['quantity']);
        $this->assertSame('paquete', $result['items'][2]['unit']);
        $this->assertSame('servilletas', $result['items'][2]['product_name']);
    }

    public function test_parser_handles_line_break_separated_items(): void
    {
        $result = app(OrderParserService::class)->parse("2 bolsas de jardin\n1 caja de vasos\n3 servilletas");

        $this->assertCount(3, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame(1, $result['items'][1]['quantity']);
        $this->assertSame(3, $result['items'][2]['quantity']);
    }

    public function test_parser_handles_semicolon_separated_items(): void
    {
        $result = app(OrderParserService::class)->parse('5 tomates; 2 kilos de papa; 1 caja de leche');

        $this->assertCount(3, $result['items']);
        $this->assertSame('tomates', $result['items'][0]['product_name']);
        $this->assertSame('kilo', $result['items'][1]['unit']);
        $this->assertSame('papa', $result['items'][1]['product_name']);
        $this->assertSame('caja', $result['items'][2]['unit']);
        $this->assertSame('leche', $result['items'][2]['product_name']);
    }

    public function test_parser_parses_quantity_after_product(): void
    {
        $result = app(OrderParserService::class)->parse('bolsas de jardin 2');

        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
        $this->assertSame('bolsas de jardin', $result['items'][0]['matched_text']);
    }

    public function test_parser_parses_quantity_prefix_with_x_marker(): void
    {
        $result = app(OrderParserService::class)->parse('x2 bolsas de jardin');

        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
    }

    public function test_parser_parses_quantity_suffix_with_x_marker(): void
    {
        $result = app(OrderParserService::class)->parse('bolsas de jardin x2');

        $this->assertCount(1, $result['items']);
        $this->assertSame(2, $result['items'][0]['quantity']);
        $this->assertSame('bolsa', $result['items'][0]['unit']);
        $this->assertSame('jardin', $result['items'][0]['product_name']);
    }

    public function test_parser_parses_intent_words(): void
    {
        $result = app(OrderParserService::class)->parse('MÃ¡ndeme 3 kilos de tomate');

        $this->assertCount(1, $result['items']);
        $this->assertSame(3, $result['items'][0]['quantity']);
        $this->assertSame('kilo', $result['items'][0]['unit']);
        $this->assertSame('tomate', $result['items'][0]['product_name']);
    }

    public function test_parser_defaults_quantity_to_one_for_multi_item_message_without_quantity(): void
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

    public function test_parser_extracts_common_units(): void
    {
        $result = app(OrderParserService::class)->parse('3 unidades de vasos, 2 docenas de huevos, 4 rollos de papel, 1 litro de aceite');

        $this->assertCount(4, $result['items']);
        $this->assertSame('unidad', $result['items'][0]['unit']);
        $this->assertSame('docena', $result['items'][1]['unit']);
        $this->assertSame('rollo', $result['items'][2]['unit']);
        $this->assertSame('litro', $result['items'][3]['unit']);
    }

    public function test_parser_preserves_delivery_and_extra_notes_in_result(): void
    {
        $message = '2 bolsas de jardin para mañana, lo paso recogiendo y urgente';
        $result = app(OrderParserService::class)->parse($message);

        $this->assertSame($message, $result['raw_text']);
        $this->assertStringContainsString('para mañana', $result['raw_text']);
        $this->assertStringContainsString('para manana', $result['normalized_text']);
        $this->assertSame(['para manana', 'lo paso recogiendo', 'urgente'], $result['notes']);
        $this->assertSame('para manana, lo paso recogiendo, urgente', $result['notes_text']);
        $this->assertNotEmpty($result['items']);
    }

    public function test_parser_extracts_para_manana_into_notes(): void
    {
        $result = app(OrderParserService::class)->parse('Ocupo 2 kilos de tomate para mañana');

        $this->assertSame(['para manana'], $result['notes']);
        $this->assertSame('para manana', $result['notes_text']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('tomate', $result['items'][0]['product_name']);
        $this->assertStringNotContainsString('para manana', $result['items'][0]['product_name']);
        $this->assertStringNotContainsString('para manana', $result['items'][0]['raw_text']);
    }

    public function test_parser_extracts_urgente_into_notes(): void
    {
        $result = app(OrderParserService::class)->parse('1 caja de vasos urgente');

        $this->assertSame(['urgente'], $result['notes']);
        $this->assertSame('urgente', $result['notes_text']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('vasos', $result['items'][0]['product_name']);
    }

    public function test_parser_does_not_include_para_manana_inside_product_name(): void
    {
        $result = app(OrderParserService::class)->parse('Me aparta 3 kilos de carne y 2 paquetes de tortillas para mañana');

        $this->assertSame(['para manana'], $result['notes']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('carne', $result['items'][0]['product_name']);
        $this->assertSame('tortillas', $result['items'][1]['product_name']);
        $this->assertStringNotContainsString('para manana', $result['items'][1]['product_name']);
        $this->assertStringNotContainsString('para manana', $result['items'][1]['raw_text']);
    }

    public function test_parser_returns_at_least_one_item_for_unclear_message(): void
    {
        $result = app(OrderParserService::class)->parse('???');

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['items'][0]['quantity']);
        $this->assertLessThan(0.5, $result['items'][0]['confidence_score']);
    }

    public function test_parser_remains_domain_agnostic_and_does_not_depend_on_product_vocab(): void
    {
        $result = app(OrderParserService::class)->parse('2 cajas de pernos y 1 bolsa de semillas');

        $this->assertCount(2, $result['items']);
        $this->assertSame('pernos', $result['items'][0]['product_name']);
        $this->assertSame('semillas', $result['items'][1]['product_name']);
    }

    public function test_existing_parser_behavior_for_no_quantity_multi_item_messages_is_preserved(): void
    {
        $result = app(OrderParserService::class)->parse('Ocupo bolsas negras grandes, servilletas y vasos');

        $this->assertTrue($result['needs_review']);
        $this->assertGreaterThan(0.6, $result['confidence']);
    }
}
