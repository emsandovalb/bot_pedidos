<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Borra las tablas del módulo de lotería, cuyo código se eliminó en el PR #2.
 * El orden respeta las llaves foráneas entre ellas.
 */
return new class extends Migration
{
    /**
     * Migraciones originales, en el orden en que se crearon.
     */
    private const ORIGINAL_MIGRATIONS = [
        '2026_05_11_180341_create_requests_table.php',
        '2026_05_11_195011_create_intake_request_events_table.php',
        '2026_05_11_210000_create_branch_daily_closures_table.php',
        '2026_05_14_000000_create_message_responses_table.php',
        '2026_05_18_000000_create_draws_table.php',
        '2026_05_18_000001_create_number_limits_table.php',
        '2026_05_18_000002_add_draw_id_to_requests_table.php',
    ];

    public function up(): void
    {
        Schema::dropIfExists('number_limits');
        Schema::dropIfExists('intake_request_events');
        Schema::dropIfExists('message_responses');
        Schema::dropIfExists('branch_daily_closures');
        Schema::dropIfExists('requests');
        Schema::dropIfExists('draws');
    }

    /**
     * Recrea las tablas vacías; los datos borrados no se recuperan.
     */
    public function down(): void
    {
        foreach (self::ORIGINAL_MIGRATIONS as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }
};
