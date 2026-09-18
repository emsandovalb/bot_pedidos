<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_public_and_reports_ok(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'time', 'checks' => [
                'database',
                'last_inbound_message_at',
                'minutes_since_last_inbound',
                'failed_messages_last_hour',
                'orders_pending_review',
            ]]);
    }

    public function test_health_reports_inbound_message_recency(): void
    {
        $organization = Organization::create(['name' => 'Health Org', 'status' => Organization::STATUS_ACTIVE]);
        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Health Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@health',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        IncomingMessage::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'provider' => 'telegram',
            'channel_type' => 'telegram',
            'from_identifier' => '123',
            'to_identifier' => '@health',
            'raw_text' => 'hola',
            'external_message_id' => 'h-1',
            'status' => IncomingMessage::STATUS_RECEIVED,
            'received_at' => now()->subMinutes(5),
        ]);

        $response = $this->getJson('/health');

        $response->assertOk();
        $this->assertNotNull($response->json('checks.last_inbound_message_at'));
        $this->assertLessThanOrEqual(6, $response->json('checks.minutes_since_last_inbound'));
    }
}
