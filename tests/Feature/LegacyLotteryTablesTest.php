<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyLotteryTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_lottery_tables_are_dropped(): void
    {
        foreach (['requests', 'intake_request_events', 'branch_daily_closures', 'message_responses', 'draws', 'number_limits'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table.' should not exist');
        }
    }
}
