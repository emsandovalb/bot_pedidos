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

    public function test_whatsapp_setup_creates_connection_if_missing(): void
    {
        $user = $this->createOrganizationUser();

        $this->assertDatabaseCount('channel_connections', 0);

        $this->actingAs($user)
            ->get(route('channels.whatsapp'))
            ->assertOk()
            ->assertSeeText('Configuracion de WhatsApp Business');

        $this->assertDatabaseCount('channel_connections', 1);

        $connection = ChannelConnection::query()->first();

        $this->assertNotNull($connection);
        $this->assertSame($user->organization_id, $connection->organization_id);
        $this->assertSame(ChannelConnection::CHANNEL_WHATSAPP, $connection->channel);
        $this->assertSame(ChannelConnection::STATUS_DRAFT, $connection->status);
    }

    public function test_whatsapp_setup_saves_checklist_metadata(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)->get(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'has_whatsapp_business' => '1',
                'has_dedicated_number' => '1',
                'has_facebook_access' => '0',
                'has_meta_business' => '0',
                'needs_assisted_setup' => '1',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $connection = $this->latestConnectionFor($user);

        $this->assertSame(true, $connection->metadata_json['has_whatsapp_business']);
        $this->assertSame(true, $connection->metadata_json['has_dedicated_number']);
        $this->assertSame(false, $connection->metadata_json['has_facebook_access']);
        $this->assertSame(false, $connection->metadata_json['has_meta_business']);
        $this->assertSame(true, $connection->metadata_json['needs_assisted_setup']);
    }

    public function test_whatsapp_setup_saves_phone_number_and_display_name(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)->get(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'display_name' => 'Benditio Pedidos',
                'phone_number' => '+502 5555 0101',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $connection = $this->latestConnectionFor($user);

        $this->assertSame('Benditio Pedidos', $connection->display_name);
        $this->assertSame('+502 5555 0101', $connection->phone_number);
    }

    public function test_whatsapp_setup_sets_status_pending_for_partial_checklist(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)->get(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'has_whatsapp_business' => '1',
                'has_dedicated_number' => '0',
                'has_facebook_access' => '0',
                'has_meta_business' => '0',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $connection = $this->latestConnectionFor($user);

        $this->assertSame(ChannelConnection::STATUS_PENDING, $connection->status);
    }

    public function test_whatsapp_setup_sets_status_ready_for_setup_when_all_required_items_are_true(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)->get(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'has_whatsapp_business' => '1',
                'has_dedicated_number' => '1',
                'has_facebook_access' => '1',
                'has_meta_business' => '1',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $connection = $this->latestConnectionFor($user);

        $this->assertSame(ChannelConnection::STATUS_READY_FOR_SETUP, $connection->status);
    }

    public function test_whatsapp_setup_does_not_set_connected_status(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)->get(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'has_whatsapp_business' => '1',
                'has_dedicated_number' => '1',
                'has_facebook_access' => '1',
                'has_meta_business' => '1',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $connection = $this->latestConnectionFor($user);

        $this->assertNotSame(ChannelConnection::STATUS_CONNECTED, $connection->status);
        $this->assertSame(ChannelConnection::STATUS_READY_FOR_SETUP, $connection->status);
    }

    public function test_whatsapp_setup_page_shows_readiness_summary(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->get(route('channels.whatsapp'))
            ->assertOk()
            ->assertSeeText('Resumen de preparacion')
            ->assertSeeText('0%')
            ->assertSeeText('Requisitos');
    }

    public function test_status_shows_no_conectado_when_no_connection_exists(): void
    {
        $user = $this->createOrganizationUser();

        $this->actingAs($user)
            ->get(route('channels.whatsapp.status'))
            ->assertOk()
            ->assertSeeText('No conectado')
            ->assertSeeText('Sin sincronizacion');
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

    private function latestConnectionFor(User $user): ChannelConnection
    {
        return ChannelConnection::query()
            ->where('organization_id', $user->organization_id)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->latest('id')
            ->firstOrFail();
    }
}
