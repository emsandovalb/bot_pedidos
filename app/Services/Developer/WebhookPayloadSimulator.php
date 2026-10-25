<?php

namespace App\Services\Developer;

use App\Models\ChannelConnection;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\Messaging\MessagingIngestionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Arma y envía los payloads de WhatsApp/Telegram que el desarrollador edita a
 * mano en el simulador de webhooks.
 */
class WebhookPayloadSimulator
{
    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly MessagingIngestionService $messagingIngestionService,
        private readonly ToolkitChannels $channels,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function send(Organization $organization, ChannelConnection $connection, array $payload, array $validated): array
    {
        return match ($validated['provider']) {
            'whatsapp' => $this->sendWhatsAppPayload($organization, $connection, $payload, $validated),
            'telegram' => $this->sendTelegramPayload($organization, $payload, $validated),
            default => [
                'processed_count' => 0,
                'ignored_count' => 0,
                'failed_count' => 1,
                'message' => 'Instagram is coming soon.',
                'order_url' => null,
                'incoming_message_url' => null,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFormState(ChannelConnection $connection, ?string $provider = null): array
    {
        $provider = $provider !== null && $provider !== '' ? strtolower($provider) : 'whatsapp';
        $phoneNumberId = $connection->provider_phone_number_id ?? ToolkitChannels::PHONE_NUMBER_ID_TEST;

        $formState = [
            'provider' => in_array($provider, ['whatsapp', 'telegram', 'instagram'], true) ? $provider : 'whatsapp',
            'payload_source' => 'fields',
            'phone_number_id' => $phoneNumberId,
            'customer_name' => 'Maria Lopez',
            'customer_phone' => '50255510001',
            'message_id' => $provider === 'telegram' ? '9010001' : 'wamid.simulator-001',
            'message_text' => $provider === 'telegram'
                ? '2 bolsas de jardin'
                : '2 bolsas de jardin',
            'payload_preview' => '',
        ];

        $formState['payload_preview'] = $provider === 'telegram'
            ? $this->payloadPreviewFromFields($connection, [
                'provider' => 'telegram',
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255510001',
                'message_id' => '9010001',
                'message_text' => '2 bolsas de jardin',
            ])
            : $this->payloadPreviewFromFields($connection, [
                'provider' => 'whatsapp',
                'customer_name' => 'Maria Lopez',
                'customer_phone' => '50255510001',
                'message_id' => 'wamid.simulator-001',
                'message_text' => '2 bolsas de jardin',
            ]);

        return $formState;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function formStateFromValidated(ChannelConnection $connection, array $validated, array $payload): array
    {
        return array_merge(
            $this->defaultFormState($connection, (string) ($validated['provider'] ?? 'whatsapp')),
            [
                'provider' => $validated['provider'],
                'payload_source' => $validated['payload_source'],
                'phone_number_id' => $validated['phone_number_id'] ?? ($connection->provider_phone_number_id ?? ToolkitChannels::PHONE_NUMBER_ID_TEST),
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'message_id' => $validated['message_id'] ?? null,
                'message_text' => $validated['message_text'] ?? null,
                'payload_preview' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function buildPayloadFromFields(array $validated, ChannelConnection $connection): array
    {
        $provider = (string) ($validated['provider'] ?? 'whatsapp');

        return match ($provider) {
            'telegram' => $this->buildTelegramPayloadFromFields($validated),
            'instagram' => [
                'object' => 'instagram',
                'status' => 'coming_soon',
            ],
            default => $this->buildWhatsAppPayloadFromFields($validated, $connection),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildWhatsAppPayloadFromFields(array $validated, ChannelConnection $connection): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-simulator-1',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => $validated['phone_number_id'] ?? $connection->provider_phone_number_id ?? ToolkitChannels::PHONE_NUMBER_ID_TEST,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => $validated['customer_name'] ?? null,
                                        ],
                                        'wa_id' => $validated['customer_phone'] ?? null,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $validated['customer_phone'] ?? null,
                                        'id' => $validated['message_id'] ?? null,
                                        'timestamp' => $timestamp,
                                        'type' => 'text',
                                        'text' => [
                                            'body' => $validated['message_text'] ?? '',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildTelegramPayloadFromFields(array $validated): array
    {
        $chatId = $this->channels->telegramChatId((string) ($validated['customer_phone'] ?? '4001'));
        $messageId = $this->stableTelegramUpdateId((string) ($validated['message_id'] ?? '9010001'));
        $senderName = trim((string) ($validated['customer_name'] ?? 'Maria Lopez'));

        return [
            'update_id' => $messageId,
            'message' => [
                'message_id' => $messageId + 100,
                'date' => now()->timestamp,
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => array_filter([
                    'id' => $chatId,
                    'username' => $this->channels->telegramUsername($senderName),
                    'first_name' => $senderName !== '' ? $senderName : 'Telegram',
                ], static fn ($value) => $value !== null),
                'text' => $validated['message_text'] ?? '',
            ],
        ];
    }

    private function payloadPreviewFromFields(ChannelConnection $connection, array $state): string
    {
        $provider = (string) ($state['provider'] ?? 'whatsapp');

        return (string) json_encode(
            $this->buildPayloadFromFields([
                'provider' => $provider,
                'phone_number_id' => $connection->provider_phone_number_id ?? ToolkitChannels::PHONE_NUMBER_ID_TEST,
                'customer_name' => $state['customer_name'] ?? 'Maria Lopez',
                'customer_phone' => $state['customer_phone'] ?? '50255510001',
                'message_id' => $state['message_id'] ?? ($provider === 'telegram' ? '9010001' : 'wamid.simulator-001'),
                'message_text' => $state['message_text'] ?? '2 bolsas de jardin',
            ], $connection),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function decodedPayloadPreview(?string $payloadPreview): array
    {
        $payloadPreview = trim((string) $payloadPreview);

        if ($payloadPreview === '') {
            throw ValidationException::withMessages([
                'payload_preview' => 'Payload preview is required when payload_source is preview.',
            ]);
        }

        try {
            $decoded = json_decode($payloadPreview, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'payload_preview' => 'Payload preview must be valid JSON.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'payload_preview' => 'Payload preview must be a JSON object.',
            ]);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPhoneNumberId(array $payload): ?string
    {
        $value = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractMessageId(array $payload): ?string
    {
        $value = data_get($payload, 'entry.0.changes.0.value.messages.0.id');

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sendWhatsAppPayload(
        Organization $organization,
        ChannelConnection $connection,
        array $payload,
        array $validated,
    ): array {
        $phoneNumberId = $this->extractPhoneNumberId($payload) ?? ($validated['phone_number_id'] ?? $connection->provider_phone_number_id ?? ToolkitChannels::PHONE_NUMBER_ID_TEST);

        if ($phoneNumberId !== '') {
            $connection->forceFill([
                'provider_phone_number_id' => $phoneNumberId,
            ])->save();
        }

        $simulatedRequest = SimulatedWhatsAppWebhook::request($payload);

        $result = $this->providerLifecycleManager->receiveWebhook('whatsapp', $simulatedRequest);

        $messageId = $this->extractMessageId($payload);
        $latestIncomingMessage = $messageId !== null
            ? IncomingMessage::query()
                ->where('organization_id', $organization->id)
                ->where('provider', ChannelConnection::CHANNEL_WHATSAPP)
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        $latestOrder = $messageId !== null
            ? Order::query()
                ->where('organization_id', $organization->id)
                ->where('source_channel', ChannelConnection::CHANNEL_WHATSAPP)
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        return [
            'processed_count' => $result->processed_count,
            'ignored_count' => $result->ignored_count,
            'failed_count' => $result->failed_count,
            'message' => $result->message ?? 'Webhook processed locally.',
            'order_url' => $latestOrder !== null ? route('orders.show', $latestOrder) : null,
            'incoming_message_url' => $latestIncomingMessage !== null
                ? route('incoming-messages.index').'#incoming-message-'.$latestIncomingMessage->id
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function sendTelegramPayload(
        Organization $organization,
        array $payload,
        array $validated,
    ): array {
        $branch = $this->channels->branchForProvider($organization, 'telegram');
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $updateId = $this->channels->stringValue($payload['update_id'] ?? null) ?? (string) ($validated['message_id'] ?? now()->timestamp);
        $chatId = $this->channels->stringValue(data_get($message, 'chat.id')) ?? $this->channels->telegramChatId((string) ($validated['customer_phone'] ?? '4001'));
        $messageText = $this->channels->stringValue(data_get($message, 'text')) ?? '';

        if ($messageText === '') {
            return [
                'processed_count' => 0,
                'ignored_count' => 0,
                'failed_count' => 1,
                'message' => 'Telegram payload is missing a text message.',
                'order_url' => null,
                'incoming_message_url' => null,
            ];
        }

        $result = $this->messagingIngestionService->ingest(
            organization: $organization,
            branch: $branch,
            message: new IncomingMessageDTO(
                provider: 'telegram',
                external_message_id: $updateId,
                external_chat_id: $chatId,
                received_at: Carbon::createFromTimestampUTC((int) ($payload['message']['date'] ?? now()->timestamp)),
                external_user_id: $this->channels->stringValue(data_get($message, 'from.id')),
                provider_username: $this->channels->stringValue(data_get($message, 'from.username')),
                customer_name: $this->channels->stringValue(data_get($message, 'from.first_name')) ?? $this->channels->stringValue(data_get($message, 'chat.title')),
                customer_phone: $chatId,
                message: $messageText,
                raw_payload: [
                    'source' => 'telegram',
                    'payload' => $payload,
                ],
                attachments: [],
            ),
        );

        $incomingMessage = $result['incoming_message'] ?? null;
        $order = $result['order'] ?? null;

        return [
            'processed_count' => $result['status'] === IncomingMessage::STATUS_PROCESSED ? 1 : 0,
            'ignored_count' => $result['duplicate'] ? 1 : 0,
            'failed_count' => $result['status'] === IncomingMessage::STATUS_FAILED ? 1 : 0,
            'message' => $result['status'] === IncomingMessage::STATUS_FAILED
                ? ($result['failure_reason'] ?? 'Telegram payload could not be processed.')
                : 'Telegram webhook processed locally.',
            'order_url' => $order !== null ? route('orders.show', $order) : null,
            'incoming_message_url' => $incomingMessage !== null
                ? route('incoming-messages.index').'#incoming-message-'.$incomingMessage->id
                : null,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function examples(array $formState): array
    {
        $phoneNumberId = (string) ($formState['phone_number_id'] ?? ToolkitChannels::PHONE_NUMBER_ID_TEST);

        return [
            'whatsapp' => [
                [
                    'key' => 'simple',
                    'label' => 'Pedido simple',
                    'description' => 'Un mensaje de texto que debe crear una orden.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'whatsapp',
                        'phone_number_id' => $phoneNumberId,
                        'customer_name' => 'Maria Lopez',
                        'customer_phone' => '50255510001',
                        'message_id' => 'wamid.simulator-simple',
                        'message_text' => '2 bolsas de jardin',
                    ],
                ],
                [
                    'key' => 'multiple',
                    'label' => 'Pedido multiple',
                    'description' => 'Mensaje con varios productos detectables.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'whatsapp',
                        'phone_number_id' => $phoneNumberId,
                        'customer_name' => 'Carlos Perez',
                        'customer_phone' => '50255510002',
                        'message_id' => 'wamid.simulator-multiple',
                        'message_text' => '2 bolsas de jardin, 1 caja de vasos y 3 paquetes de tortillas',
                    ],
                ],
                [
                    'key' => 'duplicate',
                    'label' => 'Mensaje duplicado',
                    'description' => 'Repite el mismo patron para validar idempotencia.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'whatsapp',
                        'phone_number_id' => $phoneNumberId,
                        'customer_name' => 'Maria Lopez',
                        'customer_phone' => '50255510001',
                        'message_id' => 'wamid.simulator-duplicate',
                        'message_text' => '2 bolsas de jardin',
                    ],
                ],
                [
                    'key' => 'status',
                    'label' => 'Payload de estado',
                    'description' => 'Entrega un status sin messages y debe ignorarse.',
                    'payload_source' => 'preview',
                    'payload_preview' => (string) json_encode([
                        'object' => 'whatsapp_business_account',
                        'entry' => [
                            [
                                'id' => 'entry-status-simulator',
                                'changes' => [
                                    [
                                        'field' => 'messages',
                                        'value' => [
                                            'messaging_product' => 'whatsapp',
                                            'metadata' => [
                                                'display_phone_number' => '+50255550000',
                                                'phone_number_id' => $phoneNumberId,
                                            ],
                                            'statuses' => [
                                                [
                                                    'id' => 'wamid.simulator-status',
                                                    'status' => 'delivered',
                                                    'timestamp' => (string) now()->timestamp,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'telegram' => [
                [
                    'key' => 'simple',
                    'label' => 'Update simple',
                    'description' => 'Telegram Update con texto natural para crear una orden.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'telegram',
                        'customer_name' => 'Maria Lopez',
                        'customer_phone' => '4001',
                        'message_id' => '9010001',
                        'message_text' => '2 bolsas de jardin',
                    ],
                ],
                [
                    'key' => 'multiple',
                    'label' => 'Pedido multiple',
                    'description' => 'Update con varios productos.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'telegram',
                        'customer_name' => 'Carlos Perez',
                        'customer_phone' => '4002',
                        'message_id' => '9010002',
                        'message_text' => '2 bolsas de jardin, 1 caja de vasos y 3 paquetes de tortillas',
                    ],
                ],
                [
                    'key' => 'duplicate',
                    'label' => 'Update duplicado',
                    'description' => 'Repite el mismo customer y texto para probar duplicidad.',
                    'payload_source' => 'fields',
                    'form' => [
                        'provider' => 'telegram',
                        'customer_name' => 'Maria Lopez',
                        'customer_phone' => '4001',
                        'message_id' => '9010003',
                        'message_text' => '2 bolsas de jardin',
                    ],
                ],
                [
                    'key' => 'status',
                    'label' => 'Actualizacion vacia',
                    'description' => 'Update sin texto para verificar descarte.',
                    'payload_source' => 'preview',
                    'payload_preview' => (string) json_encode([
                        'update_id' => 9010004,
                        'message' => [
                            'message_id' => 9010104,
                            'date' => now()->timestamp,
                            'chat' => [
                                'id' => 4004,
                                'type' => 'private',
                            ],
                            'from' => [
                                'id' => 4004,
                                'first_name' => 'Maria',
                            ],
                        ],
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
            'instagram' => [
                [
                    'key' => 'placeholder',
                    'label' => 'Coming soon',
                    'description' => 'Instagram is a placeholder only.',
                    'payload_source' => 'preview',
                    'payload_preview' => (string) json_encode([
                        'object' => 'instagram',
                        'status' => 'coming_soon',
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providerSpecs(): array
    {
        return [
            'whatsapp' => [
                'label' => 'WhatsApp',
                'subtitle' => 'Meta Cloud payloads',
                'required_fields' => ['Phone Number ID', 'Customer name', 'Customer phone', 'Message ID', 'Message text'],
                'examples' => ['Real Meta Cloud webhook shape', 'Supports status and non-text payloads'],
                'tone' => 'green',
            ],
            'telegram' => [
                'label' => 'Telegram',
                'subtitle' => 'Telegram Update payloads',
                'required_fields' => ['Chat ID', 'Sender name', 'Message ID', 'Message text'],
                'examples' => ['Telegram Update JSON', 'Natural language order messages'],
                'tone' => 'blue',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'subtitle' => 'Placeholder only',
                'required_fields' => ['Coming soon'],
                'examples' => ['No operational ingestion yet'],
                'tone' => 'rose',
            ],
        ];
    }

    private function stableTelegramUpdateId(string $seed): int
    {
        $seed = trim($seed);
        $digits = preg_replace('/\D+/', '', $seed) ?? '';

        if ($digits !== '') {
            return (int) $digits;
        }

        return abs(crc32($seed));
    }
}
