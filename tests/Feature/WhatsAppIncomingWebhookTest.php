<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Models\WebhookEvent;
use App\Services\Messaging\MessagingIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WhatsAppIncomingWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_payload_creates_incoming_message_and_order(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.text-1', '50255510001', '2 bolsas de jardin', '1719230400', 'Maria Lopez'),
        ], $connection->provider_phone_number_id, 'Maria Lopez'));

        $response->assertOk();

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'provider' => 'whatsapp',
            'external_message_id' => 'wamid.text-1',
            'from_identifier' => '50255510001',
            'raw_text' => '2 bolsas de jardin',
        ]);

        $this->assertDatabaseHas('orders', [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'source_channel' => 'whatsapp',
            'external_message_id' => 'wamid.text-1',
        ]);

        $this->assertDatabaseHas('webhook_events', [
            'organization_id' => $organization->id,
            'provider' => 'whatsapp',
            'event_type' => 'incoming_message',
            'method' => 'POST',
            'status' => '200',
        ]);
    }

    public function test_uses_phone_number_id_to_resolve_organization(): void
    {
        $firstOrganization = Organization::query()->create([
            'name' => 'First Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        Branch::query()->create([
            'organization_id' => $firstOrganization->id,
            'name' => 'First Branch',
            'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
            'channel_identifier' => 'first-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection('555550000000099');

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.resolve-1', '50255510002', '2 bolsas de jardin', '1719230401', 'Customer Resolve'),
        ], $connection->provider_phone_number_id, 'Customer Resolve'));

        $response->assertOk();

        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'external_message_id' => 'wamid.resolve-1',
        ]);

        $this->assertDatabaseMissing('incoming_messages', [
            'organization_id' => $firstOrganization->id,
            'external_message_id' => 'wamid.resolve-1',
        ]);
    }

    public function test_unknown_phone_number_id_returns_200_and_creates_no_order(): void
    {
        $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.unknown-1', '50255510003', '2 bolsas de jardin', '1719230402', 'Unknown Connection'),
        ], '999999999999999', 'Unknown Connection'));

        $response->assertOk();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'whatsapp',
            'event_type' => 'unknown_connection',
            'status' => '200',
        ]);
    }

    public function test_status_payload_is_ignored(): void
    {
        $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-status-1',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => '555550000000001',
                                ],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.status-1',
                                        'status' => 'delivered',
                                        'timestamp' => '1719230403',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'whatsapp',
            'event_type' => 'ignored_status',
            'status' => '200',
        ]);
    }

    public function test_non_text_message_is_ignored(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            [
                'from' => '50255510004',
                'id' => 'wamid.image-1',
                'timestamp' => '1719230404',
                'type' => 'image',
                'image' => [
                    'id' => 'media-1',
                    'mime_type' => 'image/jpeg',
                    'caption' => 'photo',
                ],
            ],
        ], $connection->provider_phone_number_id, 'Photo Customer'));

        $response->assertOk();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseHas('webhook_events', [
            'organization_id' => $organization->id,
            'provider' => 'whatsapp',
            'event_type' => 'ignored_non_text',
            'status' => '200',
        ]);
    }

    public function test_duplicate_whatsapp_message_does_not_create_duplicate_order(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();
        $payload = $this->payload([
            $this->textMessage('wamid.dup-1', '50255510005', '2 bolsas de jardin', '1719230405', 'Duplicate Customer'),
        ], $connection->provider_phone_number_id, 'Duplicate Customer');

        $firstResponse = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $payload);
        $secondResponse = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $payload);

        $firstResponse->assertOk();
        $secondResponse->assertOk();

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'external_message_id' => 'wamid.dup-1',
        ]);
    }

    public function test_contact_profile_name_maps_to_customer_name_and_display_name(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.name-1', '50255510006', '2 bolsas de jardin', '1719230406', 'Maria Lopez'),
        ], $connection->provider_phone_number_id, 'Maria Lopez'));

        $response->assertOk();

        $customer = Customer::query()
            ->where('organization_id', $organization->id)
            ->where('phone', '50255510006')
            ->firstOrFail();

        $identity = CustomerIdentity::query()
            ->where('organization_id', $organization->id)
            ->where('provider', 'whatsapp')
            ->where('external_chat_id', '50255510006')
            ->firstOrFail();

        $this->assertSame('Maria Lopez', $customer->name);
        $this->assertSame('Maria Lopez', $identity->display_name);
        $this->assertSame($customer->id, $identity->customer_id);
    }

    public function test_webhook_event_is_logged(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.log-1', '50255510007', '2 bolsas de jardin', '1719230407', 'Log Customer'),
        ], $connection->provider_phone_number_id, 'Log Customer'));

        $response->assertOk();

        $event = WebhookEvent::query()->latest('id')->firstOrFail();

        $this->assertSame($organization->id, $event->organization_id);
        $this->assertSame('whatsapp', $event->provider);
        $this->assertSame('incoming_message', $event->event_type);
        $this->assertSame('POST', $event->method);
        $this->assertSame('200', $event->status);
        $this->assertSame($connection->provider_phone_number_id, $event->payload_json['phone_number_id'] ?? null);
        $this->assertSame(['wamid.log-1'], $event->payload_json['message_ids'] ?? []);
    }

    public function test_partial_failure_does_not_fail_whole_webhook(): void
    {
        [$organization, $branch, $connection] = $this->makeOrganizationBranchConnection();

        $ingestionService = Mockery::mock(MessagingIngestionService::class);
        $ingestionService->shouldReceive('ingest')
            ->once()
            ->andThrow(new RuntimeException('First message failed.'));
        $ingestionService->shouldReceive('ingest')
            ->once()
            ->andReturn([
                'duplicate' => false,
                'incoming_message' => new IncomingMessage(),
                'order' => new Order(),
                'customer' => new Customer(),
                'status' => 'processed',
            ]);

        $this->app->instance(MessagingIngestionService::class, $ingestionService);

        $response = $this->postJson(route('webhooks.store', ['provider' => 'whatsapp']), $this->payload([
            $this->textMessage('wamid.partial-1', '50255510008', '2 bolsas de jardin', '1719230408', 'Partial Customer'),
            $this->textMessage('wamid.partial-2', '50255510008', '1 caja de vasos', '1719230409', 'Partial Customer'),
        ], $connection->provider_phone_number_id, 'Partial Customer'));

        $response->assertOk();
        $response->assertSee('1 processed');
        $response->assertSee('1 failed');

        $this->assertDatabaseHas('webhook_events', [
            'organization_id' => $organization->id,
            'provider' => 'whatsapp',
            'event_type' => 'failed',
            'status' => '200',
        ]);
        $this->assertDatabaseHas('webhook_events', [
            'organization_id' => $organization->id,
            'provider' => 'whatsapp',
            'event_type' => 'incoming_message',
            'status' => '200',
        ]);
    }

    /**
     * @return array{0: Organization, 1: Branch, 2: ChannelConnection}
     */
    private function makeOrganizationBranchConnection(?string $phoneNumberId = '555550000000001'): array
    {
        $organization = Organization::query()->create([
            'name' => 'WhatsApp Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::query()->create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Branch',
            'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
            'channel_identifier' => 'whatsapp-branch',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        $connection = ChannelConnection::query()->create([
            'organization_id' => $organization->id,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_CONNECTED,
            'provider' => ChannelConnection::CHANNEL_WHATSAPP,
            'provider_phone_number_id' => $phoneNumberId,
            'provider_business_account_id' => '555550000000002',
            'provider_verify_token' => 'verify-token-value',
            'provider_access_token' => 'access-token-value',
            'provider_app_id' => '123456789012345',
            'provider_app_secret' => 'app-secret-value',
            'provider_webhook_secret' => 'webhook-secret-value',
        ]);

        return [$organization, $branch, $connection];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function payload(array $messages, string $phoneNumberId, string $contactName = 'Maria Lopez'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-1',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => $phoneNumberId,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => $contactName,
                                        ],
                                        'wa_id' => '50255510001',
                                    ],
                                ],
                                'messages' => $messages,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessage(string $messageId, string $from, string $body, string $timestamp, string $customerName): array
    {
        return [
            'from' => $from,
            'id' => $messageId,
            'timestamp' => $timestamp,
            'type' => 'text',
            'text' => [
                'body' => $body,
            ],
            'contacts' => [
                [
                    'profile' => [
                        'name' => $customerName,
                    ],
                    'wa_id' => $from,
                ],
            ],
        ];
    }
}
