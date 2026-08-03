<?php

namespace App\Console\Commands;

use App\Services\Pilot\PilotReadinessReportGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class PilotReportCommand extends Command
{
    protected $signature = 'pilot:report {--input= : Path to the raw pilot run JSON} {--output-dir= : Directory to write report files}';

    protected $description = 'Generate the Benditio pilot readiness report.';

    public function handle(PilotReadinessReportGenerator $generator): int
    {
        $inputPath = $this->option('input') ?: config('pilot.artifact_paths.latest') . DIRECTORY_SEPARATOR . 'run.json';
        $outputDir = $this->option('output-dir') ?: config('pilot.artifact_paths.latest');

        if (! File::exists($inputPath)) {
            $this->error('Pilot run JSON not found at ' . $inputPath);

            return self::FAILURE;
        }

        $payload = json_decode((string) File::get($inputPath), true);
        if (! is_array($payload)) {
            $this->error('Pilot run JSON is invalid.');

            return self::FAILURE;
        }

        $payload['generated_at'] = Carbon::now()->toIso8601String();

        $result = $generator->write($payload, $outputDir);

        $this->info('Pilot report written to ' . $result['html_path']);
        $this->line('Pilot JSON report written to ' . $result['json_path']);

        return self::SUCCESS;
    }
}
