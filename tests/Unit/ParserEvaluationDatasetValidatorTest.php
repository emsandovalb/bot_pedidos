<?php

namespace Tests\Unit;

use App\Services\ParserQuality\DatasetAnonymizer;
use App\Services\ParserQuality\ParserEvaluationDatasetValidator;
use Tests\TestCase;

class ParserEvaluationDatasetValidatorTest extends TestCase
{
    public function test_committed_dataset_is_valid_and_reports_its_current_composition(): void
    {
        $result = app(ParserEvaluationDatasetValidator::class)->validateFile(base_path('docs/parser-quality/dataset/v1/cases.json'));

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertSame(6, $result['statistics']['total_cases']);
        $this->assertSame(6, $result['statistics']['by_source_type']['test']);
        $this->assertSame(1, $result['statistics']['pending_labeling']);
        $this->assertSame(1, $result['statistics']['incomplete_cases']);
    }

    public function test_validator_rejects_sensitive_text_duplicate_ids_and_invalid_quantities(): void
    {
        $case = [
            'id' => 'PQI-TEST-001',
            'anonymized_text' => 'Llame al +502 5555 1234 por 0 cajas',
            'category' => 'valid_order',
            'expected_products' => [['name' => 'cajas', 'quantity' => 0]],
            'expected_operational_status' => 'pending_review',
            'annotator_notes' => 'fixture',
            'source' => ['type' => 'test', 'reference' => 'test'],
            'incorporated_on' => '2026-08-12',
            'dataset_version' => '1.0.0',
            'label_status' => 'confirmed',
        ];
        $result = app(ParserEvaluationDatasetValidator::class)->validate(['dataset_version' => '1.0.0', 'cases' => [$case, $case]]);

        $this->assertFalse($result['valid']);
        $this->assertContains('cases.0.expected_products.0.quantity must be greater than zero.', $result['errors']);
        $this->assertContains('cases.1.id duplicates PQI-TEST-001.', $result['errors']);
        $this->assertContains('cases.0.anonymized_text contains apparent phone data.', $result['errors']);
    }

    public function test_anonymizer_replaces_direct_identifiers_without_removing_order_quantities(): void
    {
        $text = 'Soy Ana Perez, mi correo es ana@example.com. Pedido #4821: 2 cajas. Telegram user: 12345; @ana_perez; Calle 5 zona 10; +502 5555 1234';
        $result = app(DatasetAnonymizer::class)->anonymize($text);

        $this->assertStringContainsString('[PERSON]', $result);
        $this->assertStringContainsString('[EMAIL]', $result);
        $this->assertStringContainsString('[ORDER_NUMBER]', $result);
        $this->assertStringContainsString('[TELEGRAM_ID]', $result);
        $this->assertStringContainsString('[TELEGRAM_USERNAME]', $result);
        $this->assertStringContainsString('[ADDRESS]', $result);
        $this->assertStringContainsString('[PHONE]', $result);
        $this->assertStringContainsString('2 cajas', $result);
        $this->assertSame([], app(DatasetAnonymizer::class)->obviousSensitiveFindings($result));
    }
}
