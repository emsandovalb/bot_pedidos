<?php

namespace App\Console\Commands;

use App\Services\ParserQuality\ParserEvaluationDatasetValidator;
use Illuminate\Console\Command;

class ValidateParserEvaluationDatasetCommand extends Command
{
    protected $signature = 'parser:validate-evaluation-dataset {--path=docs/parser-quality/dataset/v1/cases.json} {--json : Emit the validation result as JSON}';

    protected $description = 'Validate the versioned, anonymized parser evaluation dataset.';

    public function handle(ParserEvaluationDatasetValidator $validator): int
    {
        $result = $validator->validateFile(base_path((string) $this->option('path')));
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($result['statistics'] as $key => $value) {
                $this->line($key.': '.json_encode($value));
            }
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
