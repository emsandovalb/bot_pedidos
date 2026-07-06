<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChannelConnection;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class WebhookSimulatorController extends Controller
{
    private const TOOLKIT_MARKER = '[developer-toolkit]';

    private const CUSTOMER_PREFIX = 'developer-toolkit:customer:';

    private const PHONE_NUMBER_ID_TEST = 'PHONE_NUMBER_ID_TEST';

    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
        private readonly MessagingIngestionService $messagingIngestionService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);
        $formState = $this->defaultFormState($connection, $request->string('provider')->toString());

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection,
            formState: $formState,
            result: null,
        );
    }

    public function send(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);

        $validated = $request->validate([
            'provider' => ['required', 'in:whatsapp,telegram,instagram'],
            'payload_source' => ['required', 'in:fields,preview'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'message_id' => ['nullable', 'string', 'max:128'],
            'message_text' => ['nullable', 'string', 'max:5000'],
            'payload_preview' => ['nullable', 'string', 'max:20000'],
        ]);

        $payload = $validated['payload_source'] === 'preview'
            ? $this->decodedPayloadPreview($validated['payload_preview'] ?? null)
            : $this->buildPayloadFromFields($validated, $connection);

        $result = match ($validated['provider']) {
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

        $formState = $this->formStateFromValidated($connection->fresh(), $validated, $payload);

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $formState,
            result: $result,
        );
    }

    public function generate(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);

        $validated = $request->validate([
            'action' => ['required', 'in:scenario,quick,customers,qa'],
            'provider' => ['nullable', 'in:whatsapp,telegram,instagram'],
            'scenario' => ['nullable', 'string', 'max:80'],
            'count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'customer_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'qa_case' => ['nullable', 'string', 'max:80'],
        ]);

        $provider = $validated['provider'] ?? $request->string('provider')->toString() ?: 'whatsapp';
        $formState = $this->defaultFormState($connection, $provider);

        $result = match ($validated['action']) {
            'scenario' => $this->generateScenario($organization, $provider, (string) ($validated['scenario'] ?? 'ferreteria_pequena')),
            'quick' => $this->generateQuickOrders($organization, $provider, (int) ($validated['count'] ?? 1)),
            'customers' => $this->generateCustomersOnly($organization, (int) ($validated['customer_count'] ?? 10)),
            'qa' => $this->generateQaCase($organization, $provider, (string) ($validated['qa_case'] ?? 'busy_day')),
        };

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $formState,
            result: $result,
        );
    }

    public function reset(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);

        $validated = $request->validate([
            'scope' => ['required', 'in:orders,customers,environment'],
            'confirm' => ['required', 'accepted'],
        ]);

        $result = $this->resetToolkitData($organization, $validated['scope']);

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $this->defaultFormState($connection),
            result: $result,
        );
    }

    private function renderToolkitPage(
        Organization $organization,
        ChannelConnection $connection,
        array $formState,
        ?array $result,
    ): View {
        $provider = (string) ($formState['provider'] ?? 'whatsapp');

        return view('developer.webhook-simulator', [
            'connection' => $connection,
            'formState' => $formState,
            'payloadPreview' => $formState['payload_preview'] ?? '',
            'providerSpecs' => $this->providerSpecs(),
            'examples' => $this->examples($formState),
            'scenarios' => $this->scenarioDefinitions(),
            'quickCounts' => [1, 5, 20, 50, 100],
            'customerCounts' => [10, 50, 100],
            'qaCases' => $this->qaDefinitions(),
            'metrics' => $this->metricsFor($organization->id),
            'result' => $result,
            'developerMode' => true,
            'selectedProvider' => $provider,
        ]);
    }

    private function ensureDeveloperEnvironment(): void
    {
        abort_unless(app()->environment('local') || config('app.debug'), 404);
    }

    private function organizationFor(Request $request): Organization
    {
        $organization = $request->user()?->organization;

        abort_if($organization === null, 403);

        return $organization;
    }

    private function prepareConnection(int $organizationId): ChannelConnection
    {
        $connection = $this->whatsAppConfigurationService->loadConfiguration($organizationId);

        if (blank($connection->provider_phone_number_id)) {
            $connection->forceFill([
                'provider_phone_number_id' => self::PHONE_NUMBER_ID_TEST,
            ])->save();
        }

        return $connection->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormState(ChannelConnection $connection, ?string $provider = null): array
    {
        $provider = $provider !== null && $provider !== '' ? strtolower($provider) : 'whatsapp';
        $phoneNumberId = $connection->provider_phone_number_id ?? self::PHONE_NUMBER_ID_TEST;

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
    private function formStateFromValidated(ChannelConnection $connection, array $validated, array $payload): array
    {
        return array_merge(
            $this->defaultFormState($connection, (string) ($validated['provider'] ?? 'whatsapp')),
            [
                'provider' => $validated['provider'],
                'payload_source' => $validated['payload_source'],
                'phone_number_id' => $validated['phone_number_id'] ?? ($connection->provider_phone_number_id ?? self::PHONE_NUMBER_ID_TEST),
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
    private function buildPayloadFromFields(array $validated, ChannelConnection $connection): array
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
                                    'phone_number_id' => $validated['phone_number_id'] ?? $connection->provider_phone_number_id ?? self::PHONE_NUMBER_ID_TEST,
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
        $chatId = $this->telegramChatId((string) ($validated['customer_phone'] ?? '4001'));
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
                    'username' => $this->telegramUsername($senderName),
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
                'phone_number_id' => $connection->provider_phone_number_id ?? self::PHONE_NUMBER_ID_TEST,
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
    private function decodedPayloadPreview(?string $payloadPreview): array
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
        $phoneNumberId = $this->extractPhoneNumberId($payload) ?? ($validated['phone_number_id'] ?? $connection->provider_phone_number_id ?? self::PHONE_NUMBER_ID_TEST);

        if ($phoneNumberId !== '') {
            $connection->forceFill([
                'provider_phone_number_id' => $phoneNumberId,
            ])->save();
        }

        $simulatedRequest = Request::create(
            uri: '/webhooks/whatsapp',
            method: 'POST',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

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
                ? route('incoming-messages.index') . '#incoming-message-' . $latestIncomingMessage->id
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
        $branch = $this->branchForProvider($organization, 'telegram');
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $updateId = $this->stringValue($payload['update_id'] ?? null) ?? (string) ($validated['message_id'] ?? now()->timestamp);
        $chatId = $this->stringValue(data_get($message, 'chat.id')) ?? $this->telegramChatId((string) ($validated['customer_phone'] ?? '4001'));
        $messageText = $this->stringValue(data_get($message, 'text')) ?? '';

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
                external_user_id: $this->stringValue(data_get($message, 'from.id')),
                provider_username: $this->stringValue(data_get($message, 'from.username')),
                customer_name: $this->stringValue(data_get($message, 'from.first_name')) ?? $this->stringValue(data_get($message, 'chat.title')),
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
                ? route('incoming-messages.index') . '#incoming-message-' . $incomingMessage->id
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateScenario(Organization $organization, string $provider, string $scenarioKey): array
    {
        $definition = $this->scenarioDefinitions()[$scenarioKey] ?? $this->scenarioDefinitions()['ferreteria_pequena'];
        $providerMix = $definition['provider_mix'] ?? ['whatsapp' => $definition['orders']];
        $start = now()->subDays((int) ($definition['spread_days'] ?? 5));
        $customers = $this->generateCustomerPool($organization, (int) $definition['customers'], $provider);
        $messages = $this->buildScenarioMessages($definition, $customers, $providerMix, $start);

        return $this->runGeneratedMessages($organization, $messages, $definition, [
            'message' => 'Scenario generated: ' . $definition['label'] . '.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateQuickOrders(Organization $organization, string $provider, int $count): array
    {
        if ($provider === 'instagram') {
            return [
                'processed_count' => 0,
                'ignored_count' => 0,
                'failed_count' => 1,
                'generated_customers' => 0,
                'generated_orders' => 0,
                'whatsapp_count' => 0,
                'telegram_count' => 0,
                'vip_count' => 0,
                'duplicate_count' => 0,
                'execution_ms' => 0,
                'message' => 'Instagram is coming soon.',
                'order_url' => null,
                'incoming_message_url' => null,
            ];
        }

        $count = max(1, $count);
        $customers = $this->generateCustomerPool($organization, max(10, min(100, $count * 2)), $provider);
        $messages = [];

        for ($i = 0; $i < $count; $i++) {
            $customer = $customers[$i % count($customers)];
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $this->randomOrderText($provider, false),
                externalMessageId: $this->generatedMessageId($provider, 'quick', $i),
                receivedAt: now()->subMinutes($count - $i),
                variant: 'quick',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Quick generation',
            'customers' => count($customers),
            'orders' => $count,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateCustomersOnly(Organization $organization, int $count): array
    {
        $count = max(1, $count);
        $customers = $this->generateCustomerPool($organization, $count, 'whatsapp', true);
        $branch = $this->branchForProvider($organization, 'whatsapp');

        foreach ($customers as $customerData) {
            $this->upsertDemoCustomer($organization, $branch, $customerData);
        }

        return [
            'processed_count' => 0,
            'ignored_count' => 0,
            'failed_count' => 0,
            'generated_customers' => count($customers),
            'generated_orders' => 0,
            'whatsapp_count' => 0,
            'telegram_count' => 0,
            'vip_count' => 0,
            'duplicate_count' => 0,
            'execution_ms' => 0,
            'message' => 'Generated ' . count($customers) . ' demo customers.',
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateQaCase(Organization $organization, string $provider, string $qaCase): array
    {
        return match ($qaCase) {
            'duplicate' => $this->generateDuplicateScenario($organization, $provider),
            'vip' => $this->generateVipScenario($organization, $provider),
            'parser_failures' => $this->generateParserFailureScenario($organization, $provider),
            'unknown_products' => $this->generateUnknownProductsScenario($organization, $provider),
            'busy_day' => $this->generateBusyDayScenario($organization, $provider),
            'empty_inbox' => $this->resetToolkitData($organization, 'environment'),
            default => $this->generateBusyDayScenario($organization, $provider),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function generateDuplicateScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 4, $provider);
        $customer = $customers[0];
        $messages = [
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 1),
                receivedAt: now()->subMinutes(10),
                variant: 'duplicate_seed',
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin para hoy',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 2),
                receivedAt: now()->subMinutes(8),
                variant: 'duplicate_match',
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: '2 bolsas de jardin urgente',
                externalMessageId: $this->generatedMessageId($provider, 'dup', 3),
                receivedAt: now()->subMinutes(6),
                variant: 'duplicate_match',
            ),
        ];

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Duplicate scenario',
        ], [
            'message' => 'Created duplicate scenario with one customer and repeated demand.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateVipScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 2, $provider);
        $customer = $customers[0];
        $messages = [];

        for ($i = 0; $i < 22; $i++) {
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $this->vipOrderText($provider, $i),
                externalMessageId: $this->generatedMessageId($provider, 'vip', $i),
                receivedAt: now()->subHours(24 - $i),
                variant: $i < 20 ? 'vip' : 'vip_tail',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'VIP scenario',
        ], [
            'message' => 'Created a VIP customer with enough history to reach VIP classification.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateParserFailureScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 3, $provider);
        $messages = [
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[0],
                messageText: '',
                externalMessageId: $this->generatedMessageId($provider, 'fail', 1),
                receivedAt: now()->subMinutes(5),
                variant: 'parser_failure',
                forceMalformed: true,
            ),
            $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[1],
                messageText: '???',
                externalMessageId: $this->generatedMessageId($provider, 'fail', 2),
                receivedAt: now()->subMinutes(4),
                variant: 'parser_failure',
            ),
        ];

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Parser failures',
        ], [
            'message' => 'Created malformed and low-confidence messages for parser QA.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateUnknownProductsScenario(Organization $organization, string $provider): array
    {
        $customers = $this->generateCustomerPool($organization, 6, $provider);
        $messages = [];

        foreach (['3 cajas de pernos raros', '2 bultos de insumo x', '1 paquete de inventario zeta'] as $index => $text) {
            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customers[$index % count($customers)],
                messageText: $text,
                externalMessageId: $this->generatedMessageId($provider, 'unknown', $index + 1),
                receivedAt: now()->subMinutes(15 - $index),
                variant: 'unknown_product',
            );
        }

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Unknown products',
        ], [
            'message' => 'Created low-match products for alias and parser QA.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateBusyDayScenario(Organization $organization, string $provider): array
    {
        $definition = $this->scenarioDefinitions()['ferreteria_grande'];
        $providerMix = ['whatsapp' => 35, 'telegram' => 15];
        $customers = $this->generateCustomerPool($organization, 18, $provider);
        $messages = $this->buildScenarioMessages($definition, $customers, $providerMix, now()->subDays(1));

        return $this->runGeneratedMessages($organization, $messages, [
            'label' => 'Busy day',
        ], [
            'message' => 'Created a mixed high-volume day with randomized timing and customers.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $resultOverrides
     * @return array<string, mixed>
     */
    private function runGeneratedMessages(Organization $organization, array $messages, array $definition, array $resultOverrides = []): array
    {
        $start = microtime(true);
        $generatedOrders = 0;
        $generatedCustomers = [];
        $whatsappCount = 0;
        $telegramCount = 0;
        $duplicateCount = 0;

        foreach ($messages as $index => $payload) {
            $provider = (string) ($payload['provider'] ?? 'whatsapp');
            $customerData = $payload['customer'];
            $branch = $this->branchForProvider($organization, $provider);
            $customer = $this->upsertDemoCustomer($organization, $branch, $customerData);
            $generatedCustomers[$customer->external_id ?? $customer->id] = true;

            $ingestionResult = match ($provider) {
                'telegram' => $this->ingestTelegramGeneratedPayload($organization, $branch, $payload, $customer),
                default => $this->ingestWhatsAppGeneratedPayload($organization, $branch, $payload, $customer),
            };

            if (($ingestionResult['duplicate'] ?? false) === true) {
                $duplicateCount++;
            }

            if (($ingestionResult['order'] ?? null) instanceof Order) {
                $generatedOrders++;
                $this->tagGeneratedOrder(
                    order: $ingestionResult['order'],
                    customer: $customer,
                    scenario: (string) ($definition['label'] ?? 'Toolkit'),
                    provider: $provider,
                    variant: (string) ($payload['variant'] ?? 'standard'),
                );
            }

            if ($provider === 'whatsapp') {
                $whatsappCount++;
            } else {
                $telegramCount++;
            }
        }

        return [
            'processed_count' => $generatedOrders,
            'ignored_count' => $duplicateCount,
            'failed_count' => 0,
            'generated_customers' => count($generatedCustomers),
            'generated_orders' => $generatedOrders,
            'whatsapp_count' => $whatsappCount,
            'telegram_count' => $telegramCount,
            'vip_count' => $this->vipCustomerCount($organization->id),
            'duplicate_count' => $this->duplicateOrderCount($organization->id),
            'execution_ms' => (int) round((microtime(true) - $start) * 1000),
            'message' => $resultOverrides['message'] ?? ($definition['label'] ?? 'Toolkit generation complete.'),
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $customers
     * @param  array<string, int>  $providerMix
     * @return array<int, array<string, mixed>>
     */
    private function buildScenarioMessages(array $definition, array $customers, array $providerMix, Carbon $start): array
    {
        $messages = [];
        $providerSequence = [];

        foreach ($providerMix as $provider => $count) {
            for ($i = 0; $i < $count; $i++) {
                $providerSequence[] = $provider;
            }
        }

        $orders = (int) ($definition['orders'] ?? count($providerSequence));
        $providerSequence = array_slice(array_pad($providerSequence, $orders, 'whatsapp'), 0, $orders);
        shuffle($providerSequence);

        $vipTarget = max(1, (int) ($definition['vip'] ?? 0));
        $duplicateTarget = max(0, (int) ($definition['duplicates'] ?? 0));
        $customerPoolCount = count($customers);

        for ($i = 0; $i < $orders; $i++) {
            $provider = $providerSequence[$i] ?? 'whatsapp';
            $customerIndex = $i % max($customerPoolCount, 1);
            $customer = $customers[$customerIndex];
            $receivedAt = $start->copy()->addMinutes($i * 17 + random_int(0, 11));
            $variant = 'standard';
            $messageText = $this->randomOrderText($definition['category'] ?? 'ferreteria', false);

            if ($i < $vipTarget) {
                $customer = $customers[0];
                $messageText = $this->vipOrderText($provider, $i);
                $variant = 'vip';
            }

            if ($i < $duplicateTarget) {
                $customer = $customers[min(1, $customerPoolCount - 1)];
                $baseText = $this->randomOrderText($definition['category'] ?? 'ferreteria', false);
                $messageText = $i % 2 === 0 ? $baseText : $baseText . ' para hoy';
                $variant = 'duplicate';
            }

            if (($definition['category'] ?? '') === 'feria') {
                $messageText = $this->randomFeriaText($i);
            } elseif (($definition['category'] ?? '') === 'restaurant') {
                $messageText = $this->randomRestaurantText($i);
            } elseif (($definition['category'] ?? '') === 'supermercado') {
                $messageText = $this->randomSupermarketText($i);
            }

            $messages[] = $this->makeMessagePayload(
                provider: $provider,
                customer: $customer,
                messageText: $messageText,
                externalMessageId: $this->generatedMessageId($provider, (string) ($definition['slug'] ?? 'scenario'), $i),
                receivedAt: $receivedAt,
                variant: $variant,
            );
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function makeMessagePayload(
        string $provider,
        array $customer,
        string $messageText,
        string $externalMessageId,
        Carbon $receivedAt,
        string $variant,
        bool $forceMalformed = false,
    ): array {
        return [
            'provider' => $provider,
            'customer' => $customer,
            'message_text' => $messageText,
            'external_message_id' => $externalMessageId,
            'received_at' => $receivedAt,
            'variant' => $variant,
            'force_malformed' => $forceMalformed,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  \App\Models\Customer  $customer
     * @return array<string, mixed>
     */
    private function ingestWhatsAppGeneratedPayload(
        Organization $organization,
        Branch $branch,
        array $payload,
        Customer $customer,
    ): array {
        $rawPayload = $this->buildWhatsAppGeneratedPayload($payload, $customer);
        $request = Request::create(
            uri: '/webhooks/whatsapp',
            method: 'POST',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $result = $this->providerLifecycleManager->receiveWebhook('whatsapp', $request);
        $messageId = $this->stringValue($payload['external_message_id'] ?? null);

        $order = $messageId !== null
            ? Order::query()
                ->where('organization_id', $organization->id)
                ->where('source_channel', 'whatsapp')
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        $incomingMessage = $messageId !== null
            ? IncomingMessage::query()
                ->where('organization_id', $organization->id)
                ->where('provider', 'whatsapp')
                ->where('external_message_id', $messageId)
                ->latest('id')
                ->first()
            : null;

        return [
            'duplicate' => (bool) ($incomingMessage?->status === IncomingMessage::STATUS_DUPLICATE),
            'order' => $order,
            'incoming_message' => $incomingMessage,
            'result' => $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  \App\Models\Customer  $customer
     * @return array<string, mixed>
     */
    private function ingestTelegramGeneratedPayload(
        Organization $organization,
        Branch $branch,
        array $payload,
        Customer $customer,
    ): array {
        if (! empty($payload['force_malformed'])) {
            return [
                'duplicate' => false,
                'order' => null,
                'incoming_message' => null,
                'result' => null,
            ];
        }

        $chatId = $this->telegramChatId((string) ($customer->phone ?? '4001'));
        $dto = new IncomingMessageDTO(
            provider: 'telegram',
            external_message_id: (string) ($payload['external_message_id'] ?? $payload['message']['message_id'] ?? now()->timestamp),
            external_chat_id: $chatId,
            received_at: $payload['received_at'] instanceof Carbon ? $payload['received_at'] : now(),
            external_user_id: $chatId,
            provider_username: $this->telegramUsername((string) ($customer->name ?? 'Telegram Customer')),
            customer_name: (string) ($customer->name ?? 'Telegram Customer'),
            customer_phone: $chatId,
            message: (string) ($payload['message_text'] ?? ''),
            raw_payload: [
                'source' => 'developer-toolkit',
                'payload' => $payload,
            ],
            attachments: [],
        );

        $result = $this->messagingIngestionService->ingest($organization, $branch, $dto);

        return [
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'order' => $result['order'] ?? null,
            'incoming_message' => $result['incoming_message'] ?? null,
            'result' => $result,
        ];
    }

    /**
     * @param  \App\Models\Customer  $customer
     */
    private function buildWhatsAppGeneratedPayload(array $payload, Customer $customer): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'entry-toolkit-' . substr(md5((string) ($payload['external_message_id'] ?? '')), 0, 8),
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+50255550000',
                                    'phone_number_id' => self::PHONE_NUMBER_ID_TEST,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => [
                                            'name' => $customer->name ?? null,
                                        ],
                                        'wa_id' => $customer->phone ?? null,
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => $customer->phone ?? null,
                                        'id' => $payload['external_message_id'] ?? null,
                                        'timestamp' => (string) ($payload['received_at'] instanceof Carbon ? $payload['received_at']->timestamp : now()->timestamp),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => $payload['message_text'] ?? '',
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
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    private function upsertDemoCustomer(Organization $organization, Branch $branch, array $customerData): Customer
    {
        $name = trim((string) ($customerData['name'] ?? 'Cliente Demo'));
        $phone = trim((string) ($customerData['phone'] ?? $this->generatedCustomerPhone()));
        $externalId = (string) ($customerData['external_id'] ?? self::CUSTOMER_PREFIX . md5($organization->id . '|' . $phone));

        $customer = Customer::query()->firstOrNew([
            'organization_id' => $organization->id,
            'phone' => $phone,
        ]);

        $customer->fill([
            'branch_id' => $branch->id,
            'name' => $name,
            'external_id' => $externalId,
        ]);
        $customer->organization_id = $organization->id;
        $customer->phone = $phone;
        $customer->save();

        return $customer->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateCustomerPool(Organization $organization, int $count, string $provider, bool $customersOnly = false): array
    {
        $count = max(1, $count);
        $customers = [];

        for ($i = 0; $i < $count; $i++) {
            $first = self::firstNames()[$i % count(self::firstNames())];
            $last = self::lastNames()[(int) floor($i / count(self::firstNames())) % count(self::lastNames())];
            $name = $first . ' ' . $last;
            $phone = $provider === 'telegram' ? $this->telegramChatId('4' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)) : $this->generatedCustomerPhone($i);
            $customers[] = [
                'name' => $name,
                'phone' => $phone,
                'external_id' => self::CUSTOMER_PREFIX . $organization->id . ':' . $provider . ':' . ($i + 1),
                'customers_only' => $customersOnly,
            ];
        }

        return $customers;
    }

    /**
     * @return array<string, mixed>
     */
    private function branchForProvider(Organization $organization, string $provider): Branch
    {
        $provider = strtolower(trim($provider));
        $channelType = $provider === 'telegram' ? Branch::CHANNEL_TYPE_TELEGRAM : Branch::CHANNEL_TYPE_WHATSAPP;
        $identifier = $provider === 'telegram'
            ? '@developer-toolkit-' . $organization->id
            : 'developer-toolkit-whatsapp-' . $organization->id;

        return Branch::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'channel_identifier' => $identifier,
            ],
            [
                'name' => $provider === 'telegram' ? 'Developer Toolkit Telegram' : 'Developer Toolkit WhatsApp',
                'channel_type' => $channelType,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );
    }

    private function generatedCustomerPhone(?int $offset = null): string
    {
        $offset = $offset ?? random_int(1000, 9999);

        return '506' . str_pad((string) ((55510000 + $offset) % 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function telegramChatId(string $seed): string
    {
        $digits = preg_replace('/\D+/', '', $seed) ?? '';

        if ($digits === '') {
            $digits = (string) random_int(1000, 9999);
        }

        return $digits;
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

    private function generatedMessageId(string $provider, string $scope, int $index): string
    {
        return sprintf(
            '%s-%s-%04d',
            strtolower(trim($provider)),
            $this->slugify($scope),
            $index + 1,
        );
    }

    private function telegramUsername(string $name): ?string
    {
        $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? '');

        return $username !== '' ? $username . '_cr' : null;
    }

    /**
     * @return array<int, string>
     */
    private function firstNames(): array
    {
        return [
            'Maria',
            'Ana',
            'Carla',
            'Diana',
            'Jose',
            'Luis',
            'Karla',
            'Pablo',
            'Sofia',
            'Sergio',
            'Andrea',
            'Javier',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function lastNames(): array
    {
        return [
            'Lopez',
            'Perez',
            'Vargas',
            'Rojas',
            'Gonzalez',
            'Castro',
            'Morales',
            'Quesada',
            'Soto',
            'Mora',
            'Ramirez',
            'Chaves',
        ];
    }

    private function randomOrderText(string $family, bool $allowUnknown = false): string
    {
        $families = [
            'ferreteria' => [
                '2 bolsas de cemento',
                '3 tubos pvc de 2 pulgadas',
                '5 tornillos galvanizados',
                '4 bloques de concreto',
                '1 cubeta de pintura blanca',
                '2 mangueras de jardin',
            ],
            'supermercado' => [
                '2 kilos tomate',
                '1 lechuga',
                '5 pepinos',
                '2 piñas',
                '1 sandia',
                '3 cebollas',
                '1 caja de huevos',
            ],
            'restaurant' => [
                '2 casados con pollo',
                '1 ceviche mixto',
                '3 refrescos naturales',
                '2 hamburguesas especiales',
                '1 pizza familiar',
                '4 burritos de carne',
            ],
            'feria' => [
                '2 kilos tomate',
                '1 lechuga',
                '5 pepinos',
                '2 piñas',
                '1 sandia',
                '3 cebollas',
                '4 chiles dulces',
            ],
        ];

        $items = $families[$family] ?? $families['ferreteria'];

        if ($allowUnknown && random_int(1, 4) === 1) {
            return '3 cajas de inventario raro';
        }

        return $items[array_rand($items)];
    }

    private function vipOrderText(string $provider, int $index): string
    {
        $catalog = [
            'whatsapp' => [
                '2 bolsas de cemento',
                '1 cubeta de pintura blanca',
                '5 tubos pvc de 2 pulgadas',
                '3 paquetes de tornillos',
            ],
            'telegram' => [
                '2 bolsas de jardin',
                '1 caja de vasos',
                '3 paquetes de servilletas',
                '4 libras de arroz',
            ],
        ];

        $items = $catalog[$provider] ?? $catalog['whatsapp'];

        return $items[$index % count($items)] . ' urgente';
    }

    private function randomFeriaText(int $index): string
    {
        $phrases = [
            '2 kilos tomate',
            '1 lechuga',
            '5 pepinos',
            '2 piñas',
            '1 sandia',
            '3 cebollas',
            '1 manojo de cilantro',
            '4 chiles dulces',
        ];

        return $phrases[$index % count($phrases)];
    }

    private function randomRestaurantText(int $index): string
    {
        $phrases = [
            '2 casados con pollo',
            '1 ceviche mixto',
            '3 refrescos naturales',
            '2 hamburguesas especiales',
            '1 pizza familiar',
            '4 burritos de carne',
            '2 tacos al pastor',
            '1 orden de papas fritas',
        ];

        return $phrases[$index % count($phrases)];
    }

    private function randomSupermarketText(int $index): string
    {
        $phrases = [
            '2 bolsas de arroz',
            '1 cartucho de huevos',
            '3 panes molidos',
            '2 litros de leche',
            '1 caja de cereal',
            '5 bananos maduros',
            '2 paquetes de servilletas',
        ];

        return $phrases[$index % count($phrases)];
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioDefinitions(): array
    {
        return [
            'ferreteria_pequena' => [
                'slug' => 'ferreteria_pequena',
                'label' => 'Ferreteria pequena',
                'category' => 'ferreteria',
                'customers' => 15,
                'orders' => 30,
                'provider_mix' => ['whatsapp' => 20, 'telegram' => 10],
                'vip' => 3,
                'duplicates' => 2,
                'spread_days' => 3,
                'estimated_time' => '20-40s',
            ],
            'ferreteria_grande' => [
                'slug' => 'ferreteria_grande',
                'label' => 'Ferreteria grande',
                'category' => 'ferreteria',
                'customers' => 60,
                'orders' => 250,
                'provider_mix' => ['whatsapp' => 160, 'telegram' => 90],
                'vip' => 15,
                'duplicates' => 20,
                'spread_days' => 10,
                'estimated_time' => '2-5m',
            ],
            'tramo_de_feria' => [
                'slug' => 'tramo_de_feria',
                'label' => 'Tramo de feria',
                'category' => 'feria',
                'customers' => 20,
                'orders' => 50,
                'provider_mix' => ['whatsapp' => 30, 'telegram' => 20],
                'vip' => 4,
                'duplicates' => 4,
                'spread_days' => 4,
                'estimated_time' => '30-60s',
            ],
            'mini_supermercado' => [
                'slug' => 'mini_supermercado',
                'label' => 'Mini supermercado',
                'category' => 'supermercado',
                'customers' => 25,
                'orders' => 60,
                'provider_mix' => ['whatsapp' => 40, 'telegram' => 20],
                'vip' => 5,
                'duplicates' => 5,
                'spread_days' => 5,
                'estimated_time' => '40-80s',
            ],
            'distribuidora' => [
                'slug' => 'distribuidora',
                'label' => 'Distribuidora',
                'category' => 'ferreteria',
                'customers' => 40,
                'orders' => 100,
                'provider_mix' => ['whatsapp' => 70, 'telegram' => 30],
                'vip' => 8,
                'duplicates' => 10,
                'spread_days' => 6,
                'estimated_time' => '1-2m',
            ],
            'restaurant' => [
                'slug' => 'restaurant',
                'label' => 'Restaurant',
                'category' => 'restaurant',
                'customers' => 18,
                'orders' => 45,
                'provider_mix' => ['whatsapp' => 30, 'telegram' => 15],
                'vip' => 4,
                'duplicates' => 3,
                'spread_days' => 3,
                'estimated_time' => '25-50s',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function qaDefinitions(): array
    {
        return [
            ['key' => 'duplicate', 'label' => 'Create duplicate scenario', 'description' => 'Repeated demand from the same customer to trigger duplicate detection.'],
            ['key' => 'vip', 'label' => 'Create VIP scenario', 'description' => 'Many orders from one customer to classify it as VIP.'],
            ['key' => 'parser_failures', 'label' => 'Create parser failures', 'description' => 'Malformed or low-confidence payloads for parser QA.'],
            ['key' => 'unknown_products', 'label' => 'Create unknown products', 'description' => 'Products that should miss catalog matching.'],
            ['key' => 'busy_day', 'label' => 'Create busy day', 'description' => 'High-volume mixed provider traffic with natural timing.'],
            ['key' => 'empty_inbox', 'label' => 'Create empty inbox', 'description' => 'Clears demo inbox data for a blank operations view.'],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function examples(array $formState): array
    {
        $phoneNumberId = (string) ($formState['phone_number_id'] ?? self::PHONE_NUMBER_ID_TEST);

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
    private function providerSpecs(): array
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

    /**
     * @return array<string, mixed>
     */
    private function metricsFor(int $organizationId): array
    {
        return [
            'generated_customers' => Customer::query()
                ->where('organization_id', $organizationId)
                ->where('external_id', 'like', self::CUSTOMER_PREFIX . '%')
                ->count(),
            'generated_orders' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
                ->count(),
            'whatsapp' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
                ->where('source_channel', 'whatsapp')
                ->count(),
            'telegram' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
                ->where('source_channel', 'telegram')
                ->count(),
            'vip' => Customer::query()
                ->where('organization_id', $organizationId)
                ->where('external_id', 'like', self::CUSTOMER_PREFIX . '%')
                ->withCount([
                    'orders as toolkit_orders_count' => function ($query): void {
                        $query->where('notes', 'like', self::TOOLKIT_MARKER . '%');
                    },
                ])
                ->get()
                ->filter(static fn (Customer $customer): bool => (int) ($customer->toolkit_orders_count ?? 0) >= 20)
                ->count(),
            'duplicates' => Order::query()
                ->where('organization_id', $organizationId)
                ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
                ->whereNotNull('possible_duplicate_of_order_id')
                ->count(),
        ];
    }

    private function duplicateOrderCount(int $organizationId): int
    {
        return Order::query()
            ->where('organization_id', $organizationId)
            ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
            ->whereNotNull('possible_duplicate_of_order_id')
            ->count();
    }

    private function vipCustomerCount(int $organizationId): int
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('external_id', 'like', self::CUSTOMER_PREFIX . '%')
            ->withCount([
                'orders as toolkit_orders_count' => function ($query): void {
                    $query->where('notes', 'like', self::TOOLKIT_MARKER . '%');
                },
            ])
            ->get()
            ->filter(static fn (Customer $customer): bool => (int) ($customer->toolkit_orders_count ?? 0) >= 20)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function resetToolkitData(Organization $organization, string $scope): array
    {
        $deletedOrders = 0;
        $deletedCustomers = 0;

        DB::transaction(function () use ($organization, $scope, &$deletedOrders, &$deletedCustomers): void {
            if (in_array($scope, ['orders', 'environment'], true)) {
                $deletedOrders = Order::query()
                    ->where('organization_id', $organization->id)
                    ->where('notes', 'like', self::TOOLKIT_MARKER . '%')
                    ->delete();
            }

            if (in_array($scope, ['customers', 'environment'], true)) {
                $customerIds = Customer::query()
                    ->where('organization_id', $organization->id)
                    ->where('external_id', 'like', self::CUSTOMER_PREFIX . '%')
                    ->pluck('id');

                if ($customerIds->isNotEmpty()) {
                    Order::query()
                        ->whereIn('customer_id', $customerIds)
                        ->delete();
                }

                $deletedCustomers = Customer::query()
                    ->whereIn('id', $customerIds)
                    ->delete();
            }
        });

        return [
            'processed_count' => 0,
            'ignored_count' => 0,
            'failed_count' => 0,
            'generated_customers' => 0,
            'generated_orders' => 0,
            'whatsapp_count' => 0,
            'telegram_count' => 0,
            'vip_count' => 0,
            'duplicate_count' => 0,
            'execution_ms' => 0,
            'message' => sprintf(
                'Reset completed: %d orders and %d customers removed.',
                $deletedOrders,
                $deletedCustomers,
            ),
            'order_url' => null,
            'incoming_message_url' => null,
        ];
    }

    private function tagGeneratedOrder(Order $order, Customer $customer, string $scenario, string $provider, string $variant): void
    {
        $notes = sprintf('%s scenario=%s provider=%s variant=%s', self::TOOLKIT_MARKER, $this->slugify($scenario), $provider, $variant);

        $order->forceFill([
            'notes' => $notes,
        ])->save();

        if ($variant === 'vip' || $variant === 'vip_tail') {
            $order->forceFill([
                'status' => Order::STATUS_PENDING_REVIEW,
            ])->save();
        }

        $customer->forceFill([
            'external_id' => self::CUSTOMER_PREFIX . $customer->organization_id . ':' . $provider . ':' . $customer->phone,
        ])->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by_user_id' => null,
            'changed_via' => 'developer_toolkit',
            'reason' => 'Generated by developer toolkit.',
            'metadata_json' => [
                'scenario' => $scenario,
                'provider' => $provider,
                'variant' => $variant,
            ],
            'created_at' => now(),
        ]);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        return null;
    }
}
