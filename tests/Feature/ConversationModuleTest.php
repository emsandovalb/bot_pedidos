<?php

namespace Tests\Feature;

use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\CustomerIdentity;
use App\Models\Message;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConversationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_and_message_wire_up_to_existing_records(): void
    {
        $organization = Organization::create([
            'name' => 'Conversations Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $agent = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_SELLER,
            'name' => 'Agent',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $connection = ChannelConnection::create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_CONNECTED,
        ]);

        // agent_mode default = 'training'.
        $this->assertTrue($connection->isTraining());
        $this->assertFalse($connection->isActive());
        $this->assertFalse($connection->isPaused());

        $identity = CustomerIdentity::create([
            'organization_id' => $organization->id,
            'provider' => 'whatsapp',
            'external_user_id' => '50255500001',
            'phone' => '+50255500001',
            'normalized_phone' => '50255500001',
            'display_name' => 'Cliente WA',
            'service_window_expires_at' => now()->addHours(24),
        ]);

        $conversation = Conversation::create([
            'organization_id' => $organization->id,
            'channel_connection_id' => $connection->id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'customer_identity_id' => $identity->id,
            'status' => Conversation::STATUS_OPEN,
            'assigned_user_id' => $agent->id,
            'service_window_expires_at' => $identity->service_window_expires_at,
        ]);

        // Relaciones inversas sobre modelos existentes.
        $this->assertTrue($connection->conversations->contains($conversation));
        $this->assertTrue($identity->conversations->contains($conversation));

        // withinServiceWindow(): abierta ahora, cerrada al expirar.
        $this->assertTrue($conversation->withinServiceWindow());
        $conversation->service_window_expires_at = now()->subMinute();
        $this->assertFalse($conversation->withinServiceWindow());

        // Borrador redactado por la IA en modo Entrenamiento.
        $draft = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => Message::DIRECTION_OUTBOUND,
            'sender_type' => Message::SENDER_AI_AGENT,
            'status' => Message::STATUS_DRAFT,
            'content' => 'Hola, gracias por tu mensaje. Enseguida lo revisamos.',
            'ai_generated' => true,
            'ai_model' => 'claude-sonnet-5',
            'ai_tokens_input' => 120,
            'ai_tokens_output' => 30,
            'ai_cost' => 0.000450,
        ]);

        $this->assertTrue($draft->isDraft());
        $this->assertTrue($conversation->messages()->whereKey($draft->id)->exists());

        // approveWithEdit(): guarda el original en ai_draft_content y marca revisor.
        $draft->approveWithEdit('Hola, gracias por escribir. Lo reviso ahora mismo.', $agent->id);
        $draft->refresh();

        $this->assertSame(Message::STATUS_APPROVED, $draft->status);
        $this->assertSame('Hola, gracias por escribir. Lo reviso ahora mismo.', $draft->content);
        $this->assertSame('Hola, gracias por tu mensaje. Enseguida lo revisamos.', $draft->ai_draft_content);
        $this->assertSame($agent->id, $draft->reviewed_by);
        $this->assertNotNull($draft->reviewed_at);
    }

    public function test_conversation_module_does_not_touch_the_order_intake_pipeline(): void
    {
        // Las tablas del pipeline de pedidos siguen existiendo e intactas.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('incoming_messages'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('requests'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('orders'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('messages', 'incoming_message_id'));
    }
}
