<?php

namespace Tests\Feature\Pilot;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PilotPrepareCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $pilotDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pilotDatabasePath = storage_path('framework/testing/pilot/pilot.sqlite');
        File::ensureDirectoryExists(dirname($this->pilotDatabasePath));
        File::delete($this->pilotDatabasePath);

        config([
            'pilot.enabled' => true,
            'pilot.database_path' => $this->pilotDatabasePath,
            'pilot.application_port' => 8010,
            'pilot.organization_name' => 'Benditio Pilot Hardware Store',
            'pilot.owner_email' => 'owner@local.test',
            'pilot.owner_name' => 'Pilot Owner',
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->pilotDatabasePath);

        parent::tearDown();
    }

    public function test_refuses_production_environment(): void
    {
        config(['app.env' => 'production']);

        $exitCode = Artisan::call('pilot:prepare');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('local or testing environments', Artisan::output());
    }

    public function test_uses_isolated_database_and_seeds_deterministic_owner(): void
    {
        User::factory()->create([
            'email' => 'normal-local@example.test',
        ]);

        $localUserCount = User::query()->count();

        $exitCode = Artisan::call('pilot:prepare');

        $this->assertSame(0, $exitCode);
        $this->assertSame($localUserCount, User::query()->count());
        $this->assertFileExists($this->pilotDatabasePath);

        $pilotConnection = DB::connection('pilot');
        $this->assertSame(1, $pilotConnection->table('users')->where('email', 'owner@local.test')->count());
        $this->assertSame(1, $pilotConnection->table('organizations')->where('name', 'Benditio Pilot Hardware Store')->count());
        $this->assertSame(1, $pilotConnection->table('branches')->where('channel_type', 'whatsapp')->count());
        $this->assertSame(1, $pilotConnection->table('branches')->where('channel_type', 'telegram')->count());
        $this->assertGreaterThanOrEqual(5, $pilotConnection->table('products')->count());
    }

    public function test_does_not_alter_normal_local_database(): void
    {
        User::factory()->create([
            'email' => 'local-db-check@example.test',
        ]);

        $before = DB::table('users')->count();

        Artisan::call('pilot:prepare');

        $this->assertSame($before, DB::table('users')->count());
    }
}
