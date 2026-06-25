<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\CustomerIdentityResolver;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\OrderIngestionService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MessagingIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_message_returns_existing_result_without_creating_new_order(): void
    {
        [$organization, $branch] = $this->makeOrganizationAndBranch();
        $service = app(MessagingIngestionService::class);

        $message = new IncomingMessageDTO(
            provider: 'telegram',
            external_message_id: 'msg-123',
            external_chat_id: 'chat-456',
            customer_name: 'Maria',
            customer_phone: '+50255550000',
            message: '2 bolsas de jardin',
            received_at: new DateTimeImmutable('2026-06-24 12:00:00'),
            raw_payload: ['update_id' => 123],
            attachments: [],
        );

        $firstResult = $service->ingest($organization, $branch, $message);
        $secondResult = $service->ingest($organization, $branch, $message);

        $this->assertFalse($firstResult['duplicate']);
        $this->assertTrue($secondResult['duplicate']);
        $this->assertSame($firstResult['incoming_message']->id, $secondResult['incoming_message']->id);
        $this->assertSame($firstResult['order']->id, $secondResult['order']->id);
        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_failure_marks_incoming_message_failed_and_keeps_payload(): void
    {
        [$organization, $branch] = $this->makeOrganizationAndBranch();
        $incomingMessage = new IncomingMessageDTO(
            provider: 'whatsapp',
            external_message_id: 'wa-123',
            external_chat_id: 'chat-999',
            customer_name: 'Ana',
            customer_phone: '+50255550001',
            message: 'hola',
            received_at: new DateTimeImmutable('2026-06-24 13:00:00'),
            raw_payload: ['entry' => [['id' => '1']]],
            attachments: [['type' => 'image']],
        );

        $mock = Mockery::mock(OrderIngestionService::class);
        $mock->shouldReceive('ingest')
            ->once()
            ->andThrow(new RuntimeException('Parser exploded.'));

        $this->app->instance(OrderIngestionService::class, $mock);

        $result = app(MessagingIngestionService::class)->ingest($organization, $branch, $incomingMessage);

        $this->assertSame(IncomingMessage::STATUS_FAILED, $result['incoming_message']->fresh()->status);
        $this->assertSame('Parser exploded.', $result['failure_reason']);
        $this->assertNull($result['order']);
        $this->assertSame(['entry' => [['id' => '1']]], $result['incoming_message']->payload_json['raw_payload']);
        $this->assertSame([['type' => 'image']], $result['incoming_message']->payload_json['attachments']);
        $this->assertSame('Parser exploded.', $result['incoming_message']->status_reason);
        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_provider_identity_is_resolved_before_order_ingestion(): void
    {
        [$organization, $branch] = $this->makeOrganizationAndBranch();
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => 'Resolved Customer',
            'phone' => '+50255550002',
            'external_id' => null,
        ]);

        $customerIdentity = CustomerIdentity::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'provider' => 'telegram',
            'external_user_id' => 'tg-user-1',
            'external_chat_id' => 'tg-chat-1',
            'provider_username' => '@resolved',
            'phone' => '+50255550002',
            'normalized_phone' => '+50255550002',
            'display_name' => 'Resolved Customer',
            'confidence_score' => 100,
            'is_primary' => false,
            'metadata_json' => ['source' => 'telegram'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $resolver = Mockery::mock(CustomerIdentityResolver::class);
        $resolver->shouldReceive('resolve')
            ->once()
            ->ordered()
            ->withArgs(function (
                int $organizationId,
                string $provider,
                ?string $externalUserId,
                ?string $externalChatId,
                ?string $providerUsername,
                ?string $phone,
                ?string $displayName,
                ?string $email,
                ?array $metadata
            ) use ($organization): bool {
                return $organizationId === $organization->id
                    && $provider === 'telegram'
                    && $externalUserId === 'tg-user-1'
                    && $externalChatId === 'tg-chat-1'
                    && $providerUsername === '@resolved'
                    && $phone === '+50255550002'
                    && $displayName === 'Resolved Customer'
                    && $email === 'resolved@example.com'
                    && $metadata === ['source' => 'telegram'];
            })
            ->andReturn([
                'customer' => $customer,
                'customer_identity' => $customerIdentity,
                'match_type' => 'exact_provider_match',
                'confidence_score' => 100,
            ]);

        $this->app->instance(CustomerIdentityResolver::class, $resolver);

        $result = app(MessagingIngestionService::class)->ingest(
            $organization,
            $branch,
            new IncomingMessageDTO(
                provider: 'telegram',
                external_message_id: 'msg-identity-1',
                external_chat_id: 'tg-chat-1',
                received_at: new DateTimeImmutable('2026-06-24 14:00:00'),
                external_user_id: 'tg-user-1',
                provider_username: '@resolved',
                customer_name: 'Resolved Customer',
                customer_phone: '+50255550002',
                email: 'resolved@example.com',
                metadata: ['source' => 'telegram'],
                message: '2 bolsas de jardin',
                raw_payload: ['update_id' => 321],
                attachments: [],
            )
        );

        $this->assertFalse($result['duplicate']);
        $this->assertSame($customer->id, $result['customer']->id);
        $this->assertNotNull($result['order']);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'provider' => 'telegram',
            'external_message_id' => 'msg-identity-1',
        ]);
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * @return array{0: Organization, 1: Branch}
     */
    private function makeOrganizationAndBranch(): array
    {
        $organization = Organization::create([
            'name' => 'Messaging Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Main Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@messaging',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        return [$organization, $branch];
    }
}
