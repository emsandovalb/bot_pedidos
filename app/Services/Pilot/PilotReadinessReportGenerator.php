<?php

namespace App\Services\Pilot;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PilotReadinessReportGenerator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function build(array $payload): array
    {
        $phases = array_values(array_map(
            fn (array $phase): array => $this->normalizePhase($phase),
            Arr::get($payload, 'phases', []),
        ));

        $warnings = array_values(array_filter(array_merge(
            Arr::get($payload, 'warnings', []),
            $this->phaseWarnings($phases),
        )));
        $failures = array_values(array_filter(array_merge(
            Arr::get($payload, 'failures', []),
            $this->phaseFailures($phases),
        )));

        $status = $this->statusFor($phases, $warnings, $failures, $payload);
        $report = [
            'status' => $status,
            'started_at' => Arr::get($payload, 'started_at'),
            'finished_at' => Arr::get($payload, 'finished_at'),
            'duration_seconds' => (float) Arr::get($payload, 'duration_seconds', 0),
            'phases' => $phases,
            'metrics' => Arr::get($payload, 'metrics', []),
            'warnings' => $warnings,
            'failures' => $failures,
            'artifacts' => Arr::get($payload, 'artifacts', []),
            'environment' => Arr::get($payload, 'environment', []),
            'recommendation' => $this->recommendationFor($status),
        ];

        $report['sections'] = $this->sections($report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{json_path:string, html_path:string, report:array<string, mixed>}
     */
    public function write(array $payload, string $outputDir): array
    {
        $report = $this->build($payload);
        File::ensureDirectoryExists($outputDir);

        $jsonPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report.json';
        $htmlPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'report.html';

        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        File::put($htmlPath, $this->renderHtml($report));

        return [
            'json_path' => $jsonPath,
            'html_path' => $htmlPath,
            'report' => $report,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function renderHtml(array $report): string
    {
        $title = 'BENDITIO PILOT READINESS';
        $statusClass = match ($report['status'] ?? 'not_ready') {
            'ready' => 'ready',
            'warning' => 'warning',
            default => 'not-ready',
        };

        $sections = collect($report['sections'] ?? [])->map(fn (array $section): string => $this->renderSection($section))->implode('');
        $artifactLinks = $this->renderArtifacts($report['artifacts'] ?? []);
        $generatedAt = e(Carbon::now()->toIso8601String());

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f7fb;
            --card: #ffffff;
            --text: #122033;
            --muted: #5a6b82;
            --border: #d9e1ec;
            --green: #0f7a4c;
            --amber: #9a6700;
            --red: #b42318;
        }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%); color: var(--text); }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 32px 20px 56px; }
        .hero { background: rgba(255,255,255,0.9); border: 1px solid var(--border); border-radius: 24px; padding: 28px; box-shadow: 0 18px 50px rgba(18,32,51,.08); }
        .kicker { letter-spacing: .18em; text-transform: uppercase; font-size: 12px; font-weight: 700; color: var(--muted); }
        h1 { margin: 10px 0 6px; font-size: 36px; }
        .status { display: inline-flex; align-items: center; gap: 10px; margin-top: 12px; padding: 8px 14px; border-radius: 999px; font-weight: 700; }
        .ready { background: #ecfdf3; color: var(--green); }
        .warning { background: #fff7e6; color: var(--amber); }
        .not-ready { background: #fff1f0; color: var(--red); }
        .meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 20px; }
        .meta div { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 14px; }
        .meta span { display: block; color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        .meta strong { display: block; margin-top: 6px; font-size: 15px; }
        .grid { display: grid; gap: 16px; margin-top: 18px; }
        .card { background: rgba(255,255,255,0.95); border: 1px solid var(--border); border-radius: 20px; padding: 20px; box-shadow: 0 14px 30px rgba(18,32,51,.05); }
        .card h2 { margin: 0 0 6px; font-size: 22px; }
        .section-status { margin: 0 0 14px; color: var(--muted); font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-top: 1px solid #edf1f6; padding: 10px 0; text-align: left; vertical-align: top; }
        th { width: 36%; font-size: 14px; }
        td { color: var(--muted); }
        ul { margin: 0; padding-left: 20px; color: var(--muted); }
        .footer { margin-top: 18px; color: var(--muted); font-size: 13px; }
        a { color: #0f62fe; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div class="kicker">Benditio Pilot Readiness</div>
        <h1>BENDITIO PILOT READINESS</h1>
        <div class="status {$statusClass}">Overall status: <strong>{$this->labelForStatus($report['status'] ?? 'not_ready')}</strong></div>
        <div class="meta">
            <div><span>Started</span><strong>{$this->displayValue($report['started_at'] ?? null)}</strong></div>
            <div><span>Finished</span><strong>{$this->displayValue($report['finished_at'] ?? null)}</strong></div>
            <div><span>Duration</span><strong>{$this->displayValue(number_format((float) ($report['duration_seconds'] ?? 0), 2))} s</strong></div>
            <div><span>Warnings</span><strong>{$this->displayValue(count($report['warnings'] ?? []))}</strong></div>
        </div>
        <div class="footer">Generated at {$generatedAt}</div>
    </header>

    <div class="grid">
        {$sections}
        <section class="card">
            <h2>Artifacts</h2>
            <ul>{$artifactLinks}</ul>
        </section>
    </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function sections(array $report): array
    {
        $environment = (array) ($report['environment'] ?? []);
        $metrics = (array) ($report['metrics'] ?? []);
        $phases = (array) ($report['phases'] ?? []);
        $warnings = (array) ($report['warnings'] ?? []);
        $failures = (array) ($report['failures'] ?? []);
        $artifacts = (array) ($report['artifacts'] ?? []);

        return [
            [
                'title' => 'Environment',
                'status' => 'Context and runtime',
                'items' => [
                    ['label' => 'PHP version', 'value' => $environment['php_version'] ?? 'n/a'],
                    ['label' => 'Laravel version', 'value' => $environment['laravel_version'] ?? 'n/a'],
                    ['label' => 'Node version', 'value' => $environment['node_version'] ?? 'n/a'],
                    ['label' => 'Database driver', 'value' => $environment['database_driver'] ?? 'n/a'],
                    ['label' => 'Pilot database path', 'value' => $environment['pilot_database_path'] ?? 'n/a'],
                    ['label' => 'Application URL', 'value' => $environment['application_url'] ?? 'n/a'],
                    ['label' => 'Timestamp', 'value' => $environment['timestamp'] ?? 'n/a'],
                ],
            ],
            [
                'title' => 'Backend',
                'status' => $this->phaseStatus($phases, 'backend'),
                'items' => [
                    ['label' => 'Tests passed', 'value' => $this->phasePassLabel($phases, 'backend')],
                    ['label' => 'Assertions', 'value' => $metrics['backend_assertions'] ?? 'n/a'],
                    ['label' => 'Duration', 'value' => $this->phaseDuration($phases, 'backend')],
                    ['label' => 'Failures', 'value' => $metrics['backend_failures'] ?? 0],
                ],
            ],
            [
                'title' => 'Build',
                'status' => $this->phaseStatus($phases, 'build'),
                'items' => [
                    ['label' => 'Vite build status', 'value' => $this->phasePassLabel($phases, 'build')],
                    ['label' => 'Duration', 'value' => $this->phaseDuration($phases, 'build')],
                    ['label' => 'Warnings', 'value' => $metrics['build_warnings'] ?? []],
                    ['label' => 'Asset manifest exists', 'value' => ! empty($metrics['vite_manifest_exists']) ? 'Yes' : 'No'],
                ],
            ],
            [
                'title' => 'Browser E2E',
                'status' => $this->phaseStatus($phases, 'e2e'),
                'items' => [
                    ['label' => 'Tests passed', 'value' => $this->phasePassLabel($phases, 'e2e')],
                    ['label' => 'Browser used', 'value' => $metrics['browser'] ?? 'n/a'],
                    ['label' => 'Duration', 'value' => $this->phaseDuration($phases, 'e2e')],
                    ['label' => 'Console errors', 'value' => $metrics['console_error_count'] ?? 0],
                    ['label' => 'HTTP 500 responses', 'value' => $metrics['http_500_count'] ?? 0],
                    ['label' => 'Failed requests', 'value' => $metrics['failed_request_count'] ?? 0],
                    ['label' => 'Screenshots', 'value' => array_values((array) ($artifacts['screenshots'] ?? []))],
                ],
            ],
            [
                'title' => 'Business Flow',
                'status' => $this->metricStatus($metrics),
                'items' => [
                    ['label' => 'WhatsApp tested', 'value' => ! empty($metrics['whatsapp_tested']) ? 'Yes' : 'No'],
                    ['label' => 'Telegram tested', 'value' => ! empty($metrics['telegram_tested']) ? 'Yes' : 'No'],
                    ['label' => 'Incoming messages created', 'value' => $metrics['incoming_messages_created'] ?? 0],
                    ['label' => 'Customers created', 'value' => $metrics['customers_created'] ?? 0],
                    ['label' => 'Identities created', 'value' => $metrics['identities_created'] ?? 0],
                    ['label' => 'Orders created', 'value' => $metrics['orders_created'] ?? 0],
                    ['label' => 'Fulfillment plans created', 'value' => $metrics['fulfillment_plans_created'] ?? 0],
                    ['label' => 'Workflow completed', 'value' => ! empty($metrics['workflow_completed']) ? 'Yes' : 'No'],
                    ['label' => 'Dispatched orders verified', 'value' => ! empty($metrics['dispatched_orders_verified']) ? 'Yes' : 'No'],
                ],
            ],
            [
                'title' => 'Operations UX',
                'status' => $this->operationsWarningStatus($metrics, $warnings),
                'items' => [
                    ['label' => 'Do Now visible', 'value' => ! empty($metrics['operations_do_now_visible']) ? 'Yes' : 'No'],
                    ['label' => 'Next visible', 'value' => ! empty($metrics['operations_next_visible']) ? 'Yes' : 'No'],
                    ['label' => 'Drawer opens', 'value' => ! empty($metrics['operations_drawer_opened']) ? 'Yes' : 'No'],
                    ['label' => 'Items visible', 'value' => ! empty($metrics['operations_items_visible']) ? 'Yes' : 'No'],
                    ['label' => 'Valid action shown', 'value' => ! empty($metrics['operations_valid_action_visible']) ? 'Yes' : 'No'],
                    ['label' => 'Status transition succeeds', 'value' => ! empty($metrics['operations_transition_succeeded']) ? 'Yes' : 'No'],
                    ['label' => 'Live indicator visible', 'value' => ! empty($metrics['operations_live_visible']) ? 'Yes' : 'No'],
                    ['label' => 'Search/filter stable', 'value' => ! empty($metrics['operations_search_stable']) ? 'Yes' : 'No'],
                ],
            ],
            [
                'title' => 'Known Warnings',
                'status' => $warnings !== [] ? 'Review' : 'None',
                'list' => $warnings !== [] ? $warnings : ['No known warnings.'],
            ],
            [
                'title' => 'Final Recommendation',
                'status' => $this->labelForStatus($report['status'] ?? 'not_ready'),
                'items' => [
                    ['label' => 'Recommendation', 'value' => $report['recommendation'] ?? 'Blocked'],
                    ['label' => 'Failures', 'value' => $failures !== [] ? implode('; ', $failures) : 'None'],
                ],
            ],
        ];
    }

    private function renderSection(array $section): string
    {
        $rows = collect($section['items'] ?? [])->map(function (array $item): string {
            $value = $item['value'] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            return '<tr><th>' . e((string) ($item['label'] ?? '')) . '</th><td>' . e((string) $value) . '</td></tr>';
        })->implode('');

        $list = collect($section['list'] ?? [])->map(fn ($item): string => '<li>' . e((string) $item) . '</li>')->implode('');

        return sprintf(
            '<section class="card"><h2>%s</h2><p class="section-status">%s</p>%s%s</section>',
            e((string) ($section['title'] ?? 'Section')),
            e((string) ($section['status'] ?? '')),
            $rows !== '' ? '<table>' . $rows . '</table>' : '',
            $list !== '' ? '<ul>' . $list . '</ul>' : '',
        );
    }

    /**
     * @param  array<string, mixed>  $artifacts
     */
    private function renderArtifacts(array $artifacts): string
    {
        return collect($artifacts)->flatMap(function ($value, string $key): array {
            if (is_array($value)) {
                return collect($value)->map(fn ($entry): string => '<li><a href="' . e((string) $entry) . '">' . e($key) . '</a></li>')->all();
            }

            return ['<li><a href="' . e((string) $value) . '">' . e($key) . '</a></li>'];
        })->implode('');
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array<int, string>
     */
    private function phaseWarnings(array $phases): array
    {
        return collect($phases)->flatMap(fn (array $phase): array => (array) ($phase['warnings'] ?? []))->filter()->values()->all();
    }

    private function normalizePhase(array $phase): array
    {
        return [
            'name' => (string) ($phase['name'] ?? 'unknown'),
            'status' => (string) ($phase['status'] ?? 'skipped'),
            'duration_seconds' => (float) ($phase['duration_seconds'] ?? 0),
            'command' => (string) ($phase['command'] ?? ''),
            'exit_code' => $phase['exit_code'] ?? null,
            'stdout' => (string) ($phase['stdout'] ?? ''),
            'stderr' => (string) ($phase['stderr'] ?? ''),
            'warnings' => array_values(array_filter((array) ($phase['warnings'] ?? []))),
            'failures' => array_values(array_filter((array) ($phase['failures'] ?? []))),
            'artifacts' => (array) ($phase['artifacts'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     * @return array<int, string>
     */
    private function phaseFailures(array $phases): array
    {
        return collect($phases)->flatMap(fn (array $phase): array => (array) ($phase['failures'] ?? []))->filter()->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function statusFor(array $phases, array $warnings, array $failures, array $payload): string
    {
        $criticalFailure = Collection::make($phases)->contains(fn (array $phase): bool => in_array($phase['status'] ?? null, ['failed', 'skipped'], true));

        if ($criticalFailure || $failures !== []) {
            return 'not_ready';
        }

        if (($payload['metrics']['backend_status_dispatched'] ?? false) === false && ! empty($payload['metrics']['workflow_completed'])) {
            return 'warning';
        }

        if ($warnings !== []) {
            return 'warning';
        }

        return 'ready';
    }

    private function recommendationFor(string $status): string
    {
        return match ($status) {
            'ready' => 'Ready for pilot',
            'warning' => 'Ready with warnings',
            default => 'Blocked',
        };
    }

    private function labelForStatus(string $status): string
    {
        return match ($status) {
            'ready' => 'READY',
            'warning' => 'WARNING',
            default => 'NOT READY',
        };
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function phaseStatus(array $phases, string $phaseName): string
    {
        $phase = collect($phases)->firstWhere('name', $phaseName);

        return (string) ($phase['status'] ?? 'missing');
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function phaseDuration(array $phases, string $phaseName): string
    {
        $phase = collect($phases)->firstWhere('name', $phaseName);

        return number_format((float) ($phase['duration_seconds'] ?? 0), 2) . ' s';
    }

    /**
     * @param  array<int, array<string, mixed>>  $phases
     */
    private function phasePassLabel(array $phases, string $phaseName): string
    {
        return $this->phaseStatus($phases, $phaseName) === 'passed' ? 'PASS' : 'FAIL';
    }

    private function metricStatus(array $metrics): string
    {
        return empty($metrics['workflow_completed']) || empty($metrics['dispatched_orders_verified'])
            ? 'Review'
            : 'Healthy';
    }

    private function operationsWarningStatus(array $metrics, array $warnings): string
    {
        if (($metrics['backend_status_dispatched'] ?? false) === false) {
            return 'Blocked';
        }

        return $warnings !== [] ? 'Review' : 'Healthy';
    }
}
