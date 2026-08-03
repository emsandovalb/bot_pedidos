<?php

namespace App\Console\Commands;

use Database\Seeders\PilotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PilotPrepareCommand extends Command
{
    protected $signature = 'pilot:prepare';

    protected $description = 'Prepare the isolated Benditio pilot database and deterministic demo data.';

    public function handle(): int
    {
        $originalDefaultConnection = (string) config('database.default');

        if (! config('pilot.enabled')) {
            $this->error('Pilot automation is disabled. Set PILOT_ENABLED=true to enable it.');

            return self::FAILURE;
        }

        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            $this->error('Pilot preparation only runs in local or testing environments.');

            return self::FAILURE;
        }

        $pilotDatabasePath = $this->resolvePilotDatabasePath((string) config('pilot.database_path'));
        $defaultDatabasePath = (string) config('database.connections.sqlite.database');

        if ($this->samePath($pilotDatabasePath, $defaultDatabasePath)) {
            $this->error('Pilot database path must be isolated from the normal local database.');

            return self::FAILURE;
        }

        $port = (int) config('pilot.application_port', 8010);
        if ($port <= 0 || $port > 65535) {
            $this->error('PILOT_APP_PORT must be a valid TCP port.');

            return self::FAILURE;
        }

        try {
            $this->clearCaches();
            $this->ensurePilotDatabaseFile($pilotDatabasePath);
            $this->configurePilotConnection($pilotDatabasePath);

            Artisan::call('migrate', [
                '--database' => 'pilot',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--database' => 'pilot',
                '--class' => PilotSeeder::class,
                '--force' => true,
            ]);
            $this->verifyPilotDataset();
        } catch (\Throwable $exception) {
            $this->error('Pilot preparation failed: ' . $exception->getMessage());

            $this->restoreDefaultConnection($originalDefaultConnection);

            return self::FAILURE;
        }

        $this->restoreDefaultConnection($originalDefaultConnection);

        $this->info(sprintf(
            'Pilot database ready at %s on port %d.',
            $pilotDatabasePath,
            $port,
        ));

        return self::SUCCESS;
    }

    private function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
    }

    private function ensurePilotDatabaseFile(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }
    }

    private function configurePilotConnection(string $pilotDatabasePath): void
    {
        config([
            'database.connections.pilot' => array_merge(config('database.connections.sqlite', []), [
                'database' => $pilotDatabasePath,
            ]),
            'database.default' => 'pilot',
        ]);

        DB::purge('pilot');
        DB::reconnect('pilot');
    }

    private function restoreDefaultConnection(string $originalDefaultConnection): void
    {
        config(['database.default' => $originalDefaultConnection]);
        DB::setDefaultConnection($originalDefaultConnection);
        DB::purge('pilot');
    }

    private function verifyPilotDataset(): void
    {
        $connection = DB::connection('pilot');
        $organizationName = (string) config('pilot.organization_name');
        $ownerEmail = (string) config('pilot.owner_email');
        $requiredBranchTypes = ['whatsapp', 'telegram'];
        $requiredProducts = collect(config('pilot.demo_products', []))
            ->map(fn (array $product): string => (string) ($product['sku'] ?? ''))
            ->filter()
            ->values();

        if (! $connection->table('organizations')->where('name', $organizationName)->exists()) {
            throw new \RuntimeException('Pilot organization was not seeded.');
        }

        if (! $connection->table('users')->where('email', $ownerEmail)->exists()) {
            throw new \RuntimeException('Pilot owner was not seeded.');
        }

        foreach ($requiredBranchTypes as $branchType) {
            $exists = $connection->table('branches')
                ->where('channel_type', $branchType)
                ->exists();

            if (! $exists) {
                throw new \RuntimeException(sprintf('Pilot %s branch was not seeded.', $branchType));
            }
        }

        foreach ($requiredProducts as $sku) {
            if (! $connection->table('products')->where('sku', $sku)->exists()) {
                throw new \RuntimeException(sprintf('Pilot product [%s] was not seeded.', $sku));
            }
        }
    }

    private function resolvePilotDatabasePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return database_path('pilot.sqlite');
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function samePath(string $first, string $second): bool
    {
        $normalizedFirst = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, strtolower($first));
        $normalizedSecond = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, strtolower($second));

        return $normalizedFirst === $normalizedSecond;
    }
}
