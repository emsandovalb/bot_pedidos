<?php

namespace Tests\Feature\Pilot;

use App\Services\Pilot\PilotReadinessReportGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PilotReportTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = storage_path('framework/testing/pilot-report');
        File::deleteDirectory($this->outputDir);
        File::ensureDirectoryExists($this->outputDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputDir);

        parent::tearDown();
    }

    public function test_ready_result_generates_html_and_json(): void
    {
        $report = $this->generator()->write($this->readyPayload(), $this->outputDir);

        $this->assertSame('ready', $report['report']['status']);
        $this->assertFileExists($report['json_path']);
        $this->assertFileExists($report['html_path']);
        $this->assertStringContainsString('BENDITIO PILOT READINESS', File::get($report['html_path']));
        $this->assertSame('Ready for pilot', $report['report']['recommendation']);
    }

    public function test_warning_result_is_reported_without_failures(): void
    {
        $payload = $this->readyPayload();
        $payload['warnings'] = ['Backend order dispatched, but Completed UI did not reflect it.'];
        $payload['metrics']['backend_status_dispatched'] = true;
        $payload['metrics']['workflow_completed'] = true;
        $payload['metrics']['completed_ui_inconsistent'] = true;

        $report = $this->generator()->build($payload);

        $this->assertSame('warning', $report['status']);
        $this->assertNotEmpty($report['warnings']);
        $this->assertSame('Ready with warnings', $report['recommendation']);
    }

    public function test_failed_result_marks_not_ready(): void
    {
        $payload = $this->readyPayload();
        $payload['phases'][2]['status'] = 'failed';
        $payload['failures'] = ['Browser E2E failed.'];

        $report = $this->generator()->build($payload);

        $this->assertSame('not_ready', $report['status']);
        $this->assertSame('Blocked', $report['recommendation']);
    }

    public function test_json_report_structure_contains_expected_fields(): void
    {
        $report = $this->generator()->build($this->readyPayload());

        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('started_at', $report);
        $this->assertArrayHasKey('finished_at', $report);
        $this->assertArrayHasKey('duration_seconds', $report);
        $this->assertArrayHasKey('phases', $report);
        $this->assertArrayHasKey('metrics', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('failures', $report);
        $this->assertArrayHasKey('artifacts', $report);
    }

    private function generator(): PilotReadinessReportGenerator
    {
        return app(PilotReadinessReportGenerator::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function readyPayload(): array
    {
        return [
            'status' => 'ready',
            'started_at' => '2026-08-03T10:00:00+00:00',
            'finished_at' => '2026-08-03T10:05:00+00:00',
            'duration_seconds' => 300,
            'environment' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => 'Laravel Framework 13.x',
                'node_version' => 'v22.0.0',
                'database_driver' => 'sqlite',
                'pilot_database_path' => database_path('pilot.sqlite'),
                'application_url' => 'http://127.0.0.1:8010',
                'timestamp' => '2026-08-03T10:05:00+00:00',
            ],
            'phases' => [
                ['name' => 'prepare', 'status' => 'passed', 'duration_seconds' => 1.2, 'command' => 'php artisan pilot:prepare'],
                ['name' => 'backend', 'status' => 'passed', 'duration_seconds' => 9.4, 'command' => 'php artisan test --filter=HardwareStorePilotTest'],
                ['name' => 'build', 'status' => 'passed', 'duration_seconds' => 6.2, 'command' => 'npm run build'],
                ['name' => 'e2e', 'status' => 'passed', 'duration_seconds' => 24.3, 'command' => 'playwright test tests/e2e/operations-pilot.spec.js'],
            ],
            'metrics' => [
                'backend_assertions' => 42,
                'backend_failures' => 0,
                'vite_manifest_exists' => true,
                'browser' => 'chromium',
                'console_error_count' => 0,
                'http_500_count' => 0,
                'failed_request_count' => 0,
                'whatsapp_tested' => true,
                'telegram_tested' => true,
                'incoming_messages_created' => 30,
                'customers_created' => 15,
                'identities_created' => 15,
                'orders_created' => 30,
                'fulfillment_plans_created' => 30,
                'workflow_completed' => true,
                'dispatched_orders_verified' => true,
                'operations_do_now_visible' => true,
                'operations_next_visible' => true,
                'operations_drawer_opened' => true,
                'operations_items_visible' => true,
                'operations_valid_action_visible' => true,
                'operations_transition_succeeded' => true,
                'operations_live_visible' => true,
                'operations_search_stable' => true,
                'backend_status_dispatched' => true,
                'completed_ui_inconsistent' => false,
            ],
            'warnings' => [],
            'failures' => [],
            'artifacts' => [
                'screenshots' => [
                    '01-toolkit-generated.png',
                    '02-operations-home.png',
                    '03-order-drawer.png',
                    '04-after-action.png',
                    '05-final-state.png',
                ],
                'playwright_report' => 'playwright-report/index.html',
            ],
        ];
    }
}
