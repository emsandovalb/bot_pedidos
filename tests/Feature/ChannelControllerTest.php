<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_shows_no_conectado_when_no_connection_exists(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->get(route('channels.whatsapp.status'))
            ->assertOk()
            ->assertSeeText('No conectado')
            ->assertSeeText('Sin sincronización');
    }

    public function test_status_reads_channel_connection_from_database(): void
    {
        $user = $this->createOrganizationUser();

        ChannelConnection::query()->create([
            'organization_id' => $user->organization_id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_DRAFT,
            'display_name' => 'Benditio Pedidos',
            'phone_number' => '+502 5555 0101',
            'provider' => 'Embedded Signup',
            'external_business_id' => 'biz_123',
            'external_phone_number_id' => 'phone_456',
            'quality_rating' => 'green',
            'metadata_json' => [
                'source' => 'embedded-signup',
            ],
            'connected_at' => now()->subHour(),
            'last_sync_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('channels.whatsapp.status'))
            ->assertOk()
            ->assertSeeText('Benditio Pedidos')
            ->assertSeeText('+502 5555 0101')
            ->assertSeeText('Embedded Signup')
            ->assertSeeText('biz_123')
            ->assertSeeText('phone_456')
            ->assertSeeText('Borrador');
    }

    private function createOrganizationUser(): User
    {
        $organization = Organization::query()->create([
            'name' => 'Benditio',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        return User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);
    }
}
