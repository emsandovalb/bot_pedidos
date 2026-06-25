<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
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
