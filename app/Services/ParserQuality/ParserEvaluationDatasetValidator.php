<?php

namespace App\Services\ParserQuality;

use JsonException;

class ParserEvaluationDatasetValidator
{
    public const CATEGORIES = ['valid_order', 'not_order', 'ambiguous', 'invalid', 'unknown_product', 'duplicate'];

    public const OPERATIONAL_STATUSES = ['no_order_created', 'pending_review', 'duplicate_ignored'];

    public const SOURCE_TYPES = ['real', 'synthetic', 'test'];

    public const LABEL_STATUSES = ['confirmed', 'pending'];

    public function __construct(private readonly DatasetAnonymizer $anonymizer) {}

    /** @return array{valid: bool, errors: list<string>, statistics: array<string, mixed>} */
    public function validateFile(string $path): array
    {
        try {
            $dataset = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['valid' => false, 'errors' => ['Dataset is not valid JSON.'], 'statistics' => []];
        }

        return $this->validate($dataset);
    }

    /** @param mixed $dataset @return array{valid: bool, errors: list<string>, statistics: array<string, mixed>} */
    public function validate(mixed $dataset): array
    {
        $errors = [];
        if (! is_array($dataset) || ! isset($dataset['dataset_version'], $dataset['cases']) || ! is_array($dataset['cases'])) {
            return ['valid' => false, 'errors' => ['Dataset requires dataset_version and cases.'], 'statistics' => []];
        }
        if (! preg_match('/^\d+\.\d+\.\d+$/', (string) $dataset['dataset_version'])) {
            $errors[] = 'dataset_version must use semantic versioning.';
        }

        $ids = [];
        $statistics = ['total_cases' => 0, 'by_source_type' => array_fill_keys(self::SOURCE_TYPES, 0), 'by_category' => array_fill_keys(self::CATEGORIES, 0), 'incomplete_cases' => 0, 'pending_labeling' => 0];
        foreach ($dataset['cases'] as $index => $case) {
            $prefix = "cases.$index";
            if (! is_array($case)) {
                $errors[] = "$prefix must be an object.";

                continue;
            }
            foreach (['id', 'anonymized_text', 'category', 'expected_products', 'expected_operational_status', 'annotator_notes', 'source', 'incorporated_on', 'dataset_version', 'label_status'] as $field) {
                if (! array_key_exists($field, $case) || $case[$field] === '') {
                    $errors[] = "$prefix.$field is required.";
                }
            }
            $id = $case['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^PQI-[A-Z0-9-]+$/', $id)) {
                $errors[] = "$prefix.id must be an anonymous PQI identifier.";
            } elseif (isset($ids[$id])) {
                $errors[] = "$prefix.id duplicates $id.";
            } else {
                $ids[$id] = true;
            }
            if (! in_array($case['category'] ?? null, self::CATEGORIES, true)) {
                $errors[] = "$prefix.category is not allowed.";
            }
            if (! in_array($case['expected_operational_status'] ?? null, self::OPERATIONAL_STATUSES, true)) {
                $errors[] = "$prefix.expected_operational_status is not allowed.";
            }
            if (! in_array($case['label_status'] ?? null, self::LABEL_STATUSES, true)) {
                $errors[] = "$prefix.label_status is not allowed.";
            }
            if (($case['dataset_version'] ?? null) !== $dataset['dataset_version']) {
                $errors[] = "$prefix.dataset_version must match dataset_version.";
            }
            if (! is_array($case['source'] ?? null) || ! in_array($case['source']['type'] ?? null, self::SOURCE_TYPES, true)) {
                $errors[] = "$prefix.source.type is required and must be real, synthetic, or test.";
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($case['incorporated_on'] ?? ''))) {
                $errors[] = "$prefix.incorporated_on must be YYYY-MM-DD.";
            }
            if (! is_array($case['expected_products'] ?? null)) {
                $errors[] = "$prefix.expected_products must be an array.";
            }
            foreach (($case['expected_products'] ?? []) as $productIndex => $product) {
                if (! is_array($product) || ! is_string($product['name'] ?? null) || trim((string) ($product['name'] ?? '')) === '') {
                    $errors[] = "$prefix.expected_products.$productIndex.name is required.";
                }
                $quantity = $product['quantity'] ?? null;
                if (! is_int($quantity) && ! is_float($quantity)) {
                    $errors[] = "$prefix.expected_products.$productIndex.quantity must be numeric.";
                } elseif ($quantity <= 0) {
                    $errors[] = "$prefix.expected_products.$productIndex.quantity must be greater than zero.";
                }
            }
            $category = $case['category'] ?? null;
            $products = $case['expected_products'] ?? [];
            if (in_array($category, ['valid_order', 'unknown_product'], true) && $products === []) {
                $errors[] = "$prefix.expected_products cannot be empty for $category.";
            }
            if (in_array($category, ['not_order', 'invalid', 'duplicate'], true) && $products !== []) {
                $errors[] = "$prefix.expected_products must be empty for $category.";
            }
            if (($case['label_status'] ?? null) === 'pending') {
                $statistics['pending_labeling']++;
            }
            if ($category === 'ambiguous' || ($case['label_status'] ?? null) === 'pending') {
                $statistics['incomplete_cases']++;
            }
            foreach ($this->anonymizer->obviousSensitiveFindings((string) ($case['anonymized_text'] ?? '')) as $finding) {
                $errors[] = "$prefix.anonymized_text contains apparent $finding data.";
            }
            $statistics['total_cases']++;
            if (isset($statistics['by_category'][$category])) {
                $statistics['by_category'][$category]++;
            }
            $sourceType = $case['source']['type'] ?? null;
            if (isset($statistics['by_source_type'][$sourceType])) {
                $statistics['by_source_type'][$sourceType]++;
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'statistics' => $statistics];
    }
}
