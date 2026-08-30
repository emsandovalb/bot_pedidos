<?php

namespace App\Console\Commands;

use App\Services\OrderParserService;
use Illuminate\Console\Command;
use JsonException;

class ParserBaselineReportCommand extends Command
{
    protected $signature = 'parser:baseline-report {--json : Emit the report as JSON}';

    protected $description = 'Evaluate the versioned parser characterization dataset and report preliminary baseline metrics.';

    public function handle(OrderParserService $parser): int
    {
        try {
            $cases = json_decode(
                (string) file_get_contents(base_path('docs/parser-quality/baseline-cases.json')),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            $this->error('Could not read parser baseline dataset: '.$exception->getMessage());

            return self::FAILURE;
        }

        $metrics = [
            'total_cases' => count($cases),
            'business_correctly_interpreted_cases' => 'not_measured_without_labeled_ground_truth',
            'business_incorrect_cases' => 'not_measured_without_labeled_ground_truth',
            'characterization_matches' => 0,
            'characterization_mismatches' => 0,
            'ambiguous_cases' => 0,
            'manual_review_cases' => 0,
            'not_interpretable_cases' => 0,
            'false_positive_risk_cases' => 0,
            'false_negative_measurable_cases' => 0,
        ];

        foreach ($cases as $case) {
            $result = $parser->parse($case['input']);
            $matches = $this->matchesExpectedResult($result, $case['expected']);
            $metrics[$matches ? 'characterization_matches' : 'characterization_mismatches']++;
            $metrics['ambiguous_cases'] += (int) $case['metrics']['ambiguous'];
            $metrics['manual_review_cases'] += (int) $case['metrics']['manual_review'];
            $metrics['not_interpretable_cases'] += (int) ! $case['metrics']['interpretable'];
            $metrics['false_positive_risk_cases'] += (int) $case['metrics']['false_positive_risk'];
            $metrics['false_negative_measurable_cases'] += (int) $case['metrics']['false_negative_measurable'];
        }

        $report = [
            'dataset' => 'docs/parser-quality/baseline-cases.json',
            'preliminary' => true,
            'scope' => 'Current OrderParserService syntax behavior only; catalog and end-to-end order correctness require a labeled production dataset.',
            'metrics' => $metrics,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->warn('Preliminary baseline: this is a characterization corpus, not a statistically representative accuracy estimate.');
        $this->table(['Metric', 'Value'], collect($metrics)->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])->all());

        return self::SUCCESS;
    }

    private function matchesExpectedResult(array $result, array $expected): bool
    {
        foreach (['normalized_text', 'confidence', 'needs_review', 'notes'] as $field) {
            if (($result[$field] ?? null) !== ($expected[$field] ?? null)) {
                return false;
            }
        }

        if (count($result['items'] ?? []) !== count($expected['items'] ?? [])) {
            return false;
        }

        foreach ($expected['items'] as $index => $expectedItem) {
            foreach ($expectedItem as $field => $value) {
                if (($result['items'][$index][$field] ?? null) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }
}
