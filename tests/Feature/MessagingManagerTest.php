<?php

namespace Tests\Feature;

use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\DTO\OutgoingMessageDTO;
use App\Services\Messaging\Manager\MessagingManager;
use App\Services\Messaging\Providers\TelegramProvider;
use App\Services\Messaging\Providers\WhatsAppCloudProvider;
use DateTimeImmutable;
use Tests\TestCase;

class MessagingManagerTest extends TestCase
{
    public function test_telegram_returns_telegram_provider(): void
    {
        $manager = new MessagingManager();

        $provider = $manager->driver('telegram');

        $this->assertInstanceOf(TelegramProvider::class, $provider);
        $this->assertSame('telegram', $provider->providerName());
    }

    public function test_whatsapp_returns_whatsapp_cloud_provider(): void
    {
        $manager = new MessagingManager();

        $provider = $manager->driver('whatsapp');

        $this->assertInstanceOf(WhatsAppCloudProvider::class, $provider);
        $this->assertSame('whatsapp', $provider->providerName());
    }

    public function test_dtos_instantiate_correctly(): void
    {
        $receivedAt = new DateTimeImmutable('2026-06-24 12:00:00');

        $incoming = new IncomingMessageDTO(
            provider: 'telegram',
            external_message_id: 'msg-123',
            external_chat_id: 'chat-456',
            customer_name: 'Maria',
            customer_phone: '+50255550000',
            message: '1000 al 28 2pm',
            received_at: $receivedAt,
            raw_payload: ['update_id' => 9001],
            attachments: [['type' => 'image']],
        );

        $outgoing = new OutgoingMessageDTO(
            provider: 'whatsapp',
            phone: '+50255550000',
            message: 'Pedido recibido',
            attachments: [['type' => 'document']],
            metadata: ['locale' => 'es_GT'],
        );

        $this->assertSame('telegram', $incoming->provider);
        $this->assertSame('msg-123', $incoming->external_message_id);
        $this->assertSame('chat-456', $incoming->external_chat_id);
        $this->assertSame('Maria', $incoming->customer_name);
        $this->assertSame('+50255550000', $incoming->customer_phone);
        $this->assertSame('1000 al 28 2pm', $incoming->message);
        $this->assertSame($receivedAt, $incoming->received_at);
        $this->assertSame(['update_id' => 9001], $incoming->raw_payload);
        $this->assertSame([['type' => 'image']], $incoming->attachments);

        $this->assertSame('whatsapp', $outgoing->provider);
        $this->assertSame('+50255550000', $outgoing->phone);
        $this->assertSame('Pedido recibido', $outgoing->message);
        $this->assertSame([['type' => 'document']], $outgoing->attachments);
        $this->assertSame(['locale' => 'es_GT'], $outgoing->metadata);
    }
}
