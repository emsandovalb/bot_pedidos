<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundMessage;
use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\Messaging\MessagingIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P0 #4 — la ingesta del webhook entrante se procesa en un job en cola, no
 * dentro del ciclo HTTP. El webhook persiste el mensaje y devuelve 200 al momento.
 */
class InboundMessageQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_persists_message_and_queues_processing(): void
    {
        Queue::fake();
        [$branch, $connection] = $this->context();

        $response = $this->postSignedWebhook($connection->provider_phone_number_id, 'wamid.queue-1', '2 bolsas de jardin');

        $response->assertOk()->assertSee('1 processed');

        // El mensaje quedó persistido en estado `received`, todavía sin pedido.
        $message = IncomingMessage::where('external_message_id', 'wamid.queue-1')->firstOrFail();
        $this->assertSame(IncomingMessage::STATUS_RECEIVED, $message->status);
        $this->assertDatabaseCount('orders', 0);

        // Y el procesamiento pesado quedó encolado.
        Queue::assertPushed(ProcessInboundMessage::class, fn (ProcessInboundMessage $job): bool => $job->incomingMessage->id === $message->id);
    }

    public function test_queued_job_runs_the_parser_and_creates_the_order(): void
    {
        [$branch, $connection] = $this->context();

        $this->postSignedWebhook($connection->provider_phone_number_id, 'wamid.queue-2', '2 bolsas de jardin')->assertOk();

        $message = IncomingMessage::where('external_message_id', 'wamid.queue-2')->firstOrFail();

        // Ejecutar el job (lo que haría el worker) crea el pedido.
        (new ProcessInboundMessage($message))->handle(app(MessagingIngestionService::class));

        $message->refresh();
        $this->assertSame(IncomingMessage::STATUS_PROCESSED, $message->status);
        $this->assertDatabaseHas('orders', [
            'organization_id' => $branch->organization_id,
            'source_channel' => 'whatsapp',
            'external_message_id' => 'wamid.queue-2',
        ]);
    }

    public function test_processing_is_idempotent(): void
    {
        [$branch, $connection] = $this->context();
        $this->postSignedWebhook($connection->provider_phone_number_id, 'wamid.queue-3', '2 bolsas de jardin')->assertOk();
        $message = IncomingMessage::where('external_message_id', 'wamid.queue-3')->firstOrFail();

        $svc = app(MessagingIngestionService::class);
        (new ProcessInboundMessage($message))->handle($svc);
        (new ProcessInboundMessage($message->fresh()))->handle($svc);

        $this->assertSame(1, Order::where('external_message_id', 'wamid.queue-3')->count());
    }

    /**
     * @return array{0: Branch, 1: ChannelConnection}
     */
    private function context(): array
    {
        config(['services.whatsapp.app_secret' => 'test-whatsapp-app-secret']);

        $organization = Organization::create(['name' => 'Queue Org', 'status' => Organization::STATUS_ACTIVE]);
        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Queue Branch',
            'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
            'channel_identifier' => 'queue-whatsapp-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);
        $connection = ChannelConnection::create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => ChannelConnection::CHANNEL_WHATSAPP,
            'provider_phone_number_id' => 'QUEUE_PHONE_ID',
            'provider_verify_token' => 'verify',
            'provider_access_token' => 'token',
            'provider_app_secret' => 'app-secret',
        ]);

        $product = Product::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Bolsas de jardin',
            'sku' => 'QUEUE-JARDIN',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        ProductAlias::create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'alias' => 'bolsas de jardin',
            'match_weight' => 100,
            'is_active' => true,
        ]);

        return [$branch->fresh(), $connection];
    }

    private function postSignedWebhook(string $phoneNumberId, string $messageId, string $text)
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'entry-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '+50255550000', 'phone_number_id' => $phoneNumberId],
                        'contacts' => [['profile' => ['name' => 'Cliente'], 'wa_id' => '50255510001']],
                        'messages' => [[
                            'from' => '50255510001',
                            'id' => $messageId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];

        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('webhooks.store', ['provider' => 'whatsapp']), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $content, 'test-whatsapp-app-secret'),
        ], $content);
    }
}
