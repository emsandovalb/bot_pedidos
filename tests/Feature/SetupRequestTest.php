<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Models\SetupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_request(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();

        $this->actingAs($user)
            ->post(route('setup-requests.store'), [
                'channel_connection_id' => $connection->id,
                'contact_name' => 'Laura Gomez',
                'contact_phone' => '+502 5555 0101',
                'contact_email' => 'laura@example.com',
                'preferred_contact_time' => '09:00 - 12:00',
                'notes' => 'Necesita apoyo para arrancar el onboarding.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('setup_requests', [
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => 'Laura Gomez',
            'contact_phone' => '+502 5555 0101',
            'contact_email' => 'laura@example.com',
        ]);
    }

    public function test_list_requests(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();

        SetupRequest::query()->create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => 'Laura Gomez',
            'contact_phone' => '+502 5555 0101',
            'contact_email' => 'laura@example.com',
            'requested_at' => now()->subHours(4),
        ]);

        SetupRequest::query()->create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_IN_PROGRESS,
            'contact_name' => 'Mario Perez',
            'contact_phone' => '+502 5555 0102',
            'requested_at' => now()->subHours(2),
            'started_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('setup-requests.index'))
            ->assertOk()
            ->assertSeeText('Centro de configuraciones asistidas')
            ->assertSeeText('Laura Gomez')
            ->assertSeeText('Mario Perez')
            ->assertSeeText('Solicitudes abiertas');
    }

    public function test_update_status(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();
        $assignee = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_ADMIN,
            'name' => 'Operador',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $setupRequest = SetupRequest::query()->create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => 'Laura Gomez',
            'contact_phone' => '+502 5555 0101',
            'requested_at' => now()->subHours(3),
        ]);

        $this->actingAs($user)
            ->patch(route('setup-requests.update', $setupRequest), [
                'status' => SetupRequest::STATUS_IN_PROGRESS,
                'assigned_to' => $assignee->id,
                'notes' => 'Llamada realizada y datos verificados.',
                'started_at' => now()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('setup-requests.show', $setupRequest));

        $this->assertDatabaseHas('setup_requests', [
            'id' => $setupRequest->id,
            'status' => SetupRequest::STATUS_IN_PROGRESS,
            'assigned_to' => $assignee->id,
            'notes' => 'Llamada realizada y datos verificados.',
        ]);
    }

    public function test_dashboard_kpi(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();

        SetupRequest::query()->create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => 'Laura Gomez',
            'contact_phone' => '+502 5555 0101',
            'requested_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Configuraciones pendientes')
            ->assertSeeText('1 solicitud(es) abiertas requieren seguimiento');
    }

    public function test_wizard_creates_request(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();

        $this->actingAs($user)
            ->post(route('channels.whatsapp.onboarding.update'), [
                'display_name' => 'Benditio Pedidos',
                'phone_number' => '+502 5555 0101',
                'notes' => 'Necesita apoyo para el setup.',
                'needs_assisted_setup' => '1',
            ])
            ->assertRedirect(route('channels.whatsapp'));

        $this->actingAs($user)
            ->post(route('setup-requests.store'), [
                'channel_connection_id' => $connection->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('setup_requests', [
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'status' => SetupRequest::STATUS_OPEN,
            'contact_name' => 'Benditio Pedidos',
            'contact_phone' => '+502 5555 0101',
        ]);
    }

    public function test_completed_request(): void
    {
        [$user, $organization, $connection] = $this->makeOrganizationWithConnection();

        $setupRequest = SetupRequest::query()->create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'type' => SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP,
            'status' => SetupRequest::STATUS_IN_PROGRESS,
            'contact_name' => 'Laura Gomez',
            'contact_phone' => '+502 5555 0101',
            'requested_at' => now()->subHours(5),
            'started_at' => now()->subHours(2),
        ]);

        $this->actingAs($user)
            ->patch(route('setup-requests.update', $setupRequest), [
                'status' => SetupRequest::STATUS_COMPLETED,
                'notes' => 'Configuracion finalizada.',
            ])
            ->assertRedirect(route('setup-requests.show', $setupRequest));

        $fresh = $setupRequest->fresh();

        $this->assertNotNull($fresh);
        $this->assertSame(SetupRequest::STATUS_COMPLETED, $fresh->status);
        $this->assertSame('Configuracion finalizada.', $fresh->notes);
        $this->assertNotNull($fresh->completed_at);
    }

    private function makeOrganizationWithConnection(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Benditio',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        $connection = ChannelConnection::query()->create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_DRAFT,
            'display_name' => 'Benditio Pedidos',
            'phone_number' => '+502 5555 0101',
            'metadata_json' => [
                'notes' => 'Initial note.',
            ],
        ]);

        return [$user->fresh(), $organization->fresh(), $connection->fresh()];
    }
}
