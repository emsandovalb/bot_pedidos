<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use App\Models\SetupRequest;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;
use App\Services\Messaging\Manager\MessagingManager;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChannelController extends Controller
{
    public function __construct(
        private readonly MessagingManager $messagingManager,
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
    ) {
    }

    public function index(): View
    {
        return view('channels.index', [
            'providerCards' => $this->providerCards(),
            'hubSummary' => $this->hubSummary(),
        ]);
    }

    public function show(string $provider): View
    {
        $provider = $this->normalizeProvider($provider);
        $connection = $this->providerConnection($provider);
        $organizationId = request()->user()?->organization_id;
        $health = $this->messagingManager->health($provider, $organizationId);
        $validation = $this->messagingManager->validate($provider, $organizationId);
        $validationErrors = $this->validationErrors($validation);
        $validationWarnings = $this->validationWarnings($validation);
        $capabilities = $this->messagingManager->capabilities($provider);

        return view('channels.show', [
            'provider' => $provider,
            'providerLabel' => $this->providerLabel($provider),
            'providerDescription' => $this->providerDescription($provider),
            'providerBanner' => $this->providerBanner($provider, $health->status),
            'providerLogo' => $this->providerLogo($provider),
            'connection' => $connection,
            'health' => $health,
            'validation' => $validation,
            'capabilities' => $capabilities,
            'capabilityItems' => $this->capabilityItems($capabilities->toArray()),
            'logs' => $this->providerLogs($provider, $connection, $health),
            'futureFeatures' => $this->futureFeatures($provider, $capabilities->toArray()),
            'overview' => $this->overviewItems($provider, $connection, $health),
            'validation' => $validation,
            'validationErrors' => $validationErrors,
            'validationWarnings' => $validationWarnings,
            'recentErrors' => $this->recentErrors($connection, $validationErrors),
            'recentActivity' => $this->recentActivity($connection, $health),
            'futureReady' => $this->futureReadyBanner($provider, $health->status),
            'actions' => $this->providerActions($provider),
        ]);
    }

    public function healthCheck(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->refreshHealth($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', 'Health check updated for ' . $this->providerLabel($provider) . '.');
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->connect($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', $this->providerLabel($provider) . ' connected.');
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->disconnect($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', $this->providerLabel($provider) . ' disconnected.');
    }

    public function whatsapp(): View
    {
        $connection = $this->channelConnection(true);
        $metadata = $this->normalizedMetadata($connection?->metadata_json);
        $readiness = $this->readinessState($metadata);

        return view('channels.whatsapp', [
            'connection' => $connection,
            'metadata' => $metadata,
            'readiness' => $readiness,
            'statusLabel' => $this->statusLabel($connection),
            'stepper' => [
                [
                    'index' => 1,
                    'title' => 'Requisitos',
                    'description' => 'Checklist base para saber si el negocio esta listo.',
                    'status' => $this->stepperStatus(1, $readiness),
                ],
                [
                    'index' => 2,
                    'title' => 'Numero',
                    'description' => 'Nombre y numero principal del canal.',
                    'status' => $this->stepperStatus(2, $readiness),
                ],
                [
                    'index' => 3,
                    'title' => 'Meta/Facebook',
                    'description' => 'Accesos y capacidad operativa para continuar.',
                    'status' => $this->stepperStatus(3, $readiness),
                ],
                [
                    'index' => 4,
                    'title' => 'Ayuda',
                    'description' => 'Indicaciones para configuracion asistida.',
                    'status' => $this->stepperStatus(4, $readiness),
                ],
                [
                    'index' => 5,
                    'title' => 'Resumen',
                    'description' => 'Vista final de preparacion del canal.',
                    'status' => 'current',
                ],
            ],
            'checklistItems' => [
                [
                    'key' => 'has_whatsapp_business',
                    'label' => 'Tengo WhatsApp Business',
                    'description' => 'La cuenta del negocio ya existe y puede operar.',
                ],
                [
                    'key' => 'has_dedicated_number',
                    'label' => 'Tengo un numero exclusivo para el negocio',
                    'description' => 'El canal no comparte linea con uso personal.',
                ],
                [
                    'key' => 'has_facebook_access',
                    'label' => 'Tengo acceso a Facebook/Meta',
                    'description' => 'Puedo entrar al ecosistema de Meta cuando sea necesario.',
                ],
                [
                    'key' => 'has_meta_business',
                    'label' => 'Tengo o puedo crear Meta Business Manager',
                    'description' => 'El negocio puede gestionar activos y permisos.',
                ],
            ],
        ]);
    }

    public function whatsappConfiguration(): View
    {
        $organizationId = request()->user()?->organization_id;

        abort_if($organizationId === null, 403);

        $connection = $this->whatsAppConfigurationService->loadConfiguration($organizationId);
        $validation = $this->whatsAppConfigurationService->validateConfiguration($connection);

        return view('channels.whatsapp-configuration', [
            'connection' => $connection,
            'configuration' => $this->whatsAppConfigurationService->maskSensitiveData($connection),
            'validation' => $validation,
            'isReadyForWebhook' => $this->whatsAppConfigurationService->isReadyForWebhook($connection),
        ]);
    }

    public function saveWhatsappConfiguration(Request $request): RedirectResponse
    {
        $organizationId = $request->user()?->organization_id;

        abort_if($organizationId === null, 403);

        $validated = $request->validate([
            'provider_app_id' => ['nullable', 'string', 'max:25', 'regex:/^\d{5,25}$/'],
            'provider_app_secret' => ['nullable', 'string', 'min:8', 'max:255'],
            'provider_access_token' => ['nullable', 'string', 'min:8', 'max:255'],
            'provider_verify_token' => ['nullable', 'string', 'min:8', 'max:255'],
            'provider_webhook_secret' => ['nullable', 'string', 'min:8', 'max:255'],
            'provider_phone_number_id' => ['nullable', 'string', 'max:25', 'regex:/^\d{5,25}$/'],
            'provider_business_account_id' => ['nullable', 'string', 'max:25', 'regex:/^\d{5,25}$/'],
            'provider_display_phone' => ['nullable', 'string', 'max:32'],
            'provider_api_version' => ['nullable', 'string', 'max:16', 'regex:/^v\d+(?:\.\d+)?$/'],
            'provider_business_name' => ['nullable', 'string', 'max:120'],
            'provider_business_timezone' => ['nullable', 'string', 'max:64'],
            'provider_business_country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
        ]);

        $connection = $this->whatsAppConfigurationService->saveConfiguration($organizationId, $validated);
        $action = $request->string('action')->toString();

        return redirect()
            ->route('channels.whatsapp.configuration')
            ->with('status', $action === 'validate'
                ? 'WhatsApp configuration validated locally.'
                : 'WhatsApp configuration saved.')
            ->with('configuration_status', $connection->provider_configuration_status);
    }

    public function status(): View
    {
        $connection = $this->channelConnection();
        $openSetupRequest = SetupRequest::query()
            ->where('organization_id', request()->user()?->organization_id)
            ->where('type', SetupRequest::TYPE_WHATSAPP_ASSISTED_SETUP)
            ->where('status', SetupRequest::STATUS_OPEN)
            ->latest('requested_at')
            ->first();

        return view('channels.whatsapp-status', [
            'openSetupRequest' => $openSetupRequest,
            'connectionStatus' => [
                'label' => 'Canal WhatsApp',
                'value' => $this->connectionValue($connection),
                'description' => $connection
                    ? 'Registro persistido listo para futuras integraciones.'
                    : 'No existe un registro persistido para esta organizacion.',
                'lastChecked' => $connection?->last_sync_at?->format('d/m/Y H:i') ?? 'Sin sincronizacion',
            ],
            'statusMetrics' => [
                [
                    'label' => 'Estado',
                    'value' => $this->statusLabel($connection),
                    'detail' => 'Base persistida, lista para ampliar el flujo.',
                ],
                [
                    'label' => 'Proveedor',
                    'value' => $connection?->provider ?? 'Pendiente',
                    'detail' => 'Preparado para Embedded Signup.',
                ],
                [
                    'label' => 'Business ID',
                    'value' => $connection?->external_business_id ?? 'Sin dato',
                    'detail' => 'Campo externo reservado para Meta.',
                ],
                [
                    'label' => 'Phone Number ID',
                    'value' => $connection?->external_phone_number_id ?? 'Sin dato',
                    'detail' => 'Campo externo reservado para WhatsApp.',
                ],
            ],
            'healthChecks' => [
                [
                    'title' => 'Numero verificado',
                    'state' => $connection?->phone_number ? 'Ok' : 'Pendiente',
                    'detail' => $connection?->phone_number ?? 'Numero pendiente de configuracion.',
                ],
                [
                    'title' => 'Mensajes de bienvenida',
                    'state' => $connection ? 'Ok' : 'Demo',
                    'detail' => $connection
                        ? 'Estructura lista para persistir plantillas.'
                        : 'Plantilla de entrada cargada en el asistente.',
                ],
                [
                    'title' => 'Derivacion manual',
                    'state' => 'Pendiente',
                    'detail' => 'Lista para conexion con el flujo operativo real.',
                ],
                [
                    'title' => 'Monitoreo de entregas',
                    'state' => 'Demo',
                    'detail' => 'Panel visual para cuando exista integracion real.',
                ],
            ],
            'timeline' => [
                [
                    'time' => $connection?->connected_at?->format('H:i') ?? 'Sin dato',
                    'title' => 'Conexion registrada',
                    'detail' => $connection
                        ? 'El canal quedo persistido en la base de datos.'
                        : 'Todavia no existe una conexion registrada.',
                ],
                [
                    'time' => $connection?->last_sync_at?->format('H:i') ?? 'Sin sincronizacion',
                    'title' => 'Ultima sincronizacion',
                    'detail' => 'Lista para alimentar Embedded Signup y estados externos.',
                ],
                [
                    'time' => 'Ahora',
                    'title' => 'Preparacion arquitectonica',
                    'detail' => 'Sin integrar Meta ni WhatsApp todavia, solo persistencia local.',
                ],
            ],
        ]);
    }

    public function verifyEndpoint(Request $request): RedirectResponse
    {
        $organizationId = $request->user()?->organization_id;

        abort_if($organizationId === null, 403);

        $validation = $this->whatsAppConfigurationService->validateConfiguration(
            $this->whatsAppConfigurationService->loadConfiguration($organizationId)
        );

        $this->providerLifecycleManager->refreshHealth('whatsapp', $organizationId);

        return redirect()
            ->route('channels.index')
            ->with('status', $validation->valid
                ? 'WhatsApp endpoint validated locally.'
                : 'WhatsApp endpoint validation failed locally.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providerCards(): array
    {
        return collect(config('messaging.providers', ['telegram', 'whatsapp', 'instagram']))
            ->map(function (string $provider): array {
                $provider = $this->normalizeProvider($provider);
                $connection = $this->providerConnection($provider);
                $organizationId = request()->user()?->organization_id;
                $health = $this->messagingManager->health($provider, $organizationId);
                $capabilities = $this->messagingManager->capabilities($provider);
                $lastWebhookVerificationAt = data_get($connection?->provider_metadata_json ?? [], 'last_webhook_verification_at');
                $lastWebhookVerificationAt = is_string($lastWebhookVerificationAt)
                    ? \Illuminate\Support\Carbon::parse($lastWebhookVerificationAt)
                    : ($lastWebhookVerificationAt instanceof \DateTimeInterface ? $lastWebhookVerificationAt : null);

                return [
                    'slug' => $provider,
                    'label' => $this->providerLabel($provider),
                    'description' => $this->providerDescription($provider),
                    'logo' => $this->providerLogo($provider),
                    'status' => $this->providerStatusLabel($health->status),
                    'status_tone' => $this->statusTone($health->status),
                    'health' => $health,
                    'health_label' => $this->healthLabel($health->status),
                    'capabilities' => $capabilities,
                    'capability_preview' => $this->capabilityPreview($capabilities->toArray()),
                    'version' => $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1',
                    'health_status' => $connection?->health_status ?? $health->status,
                    'webhook_status' => $connection?->webhook_status ?? $health->webhook_status,
                    'credentials_status' => $connection?->credentials_status ?? $health->credentials_status,
                    'last_health_check_at' => $connection?->last_health_check_at ?? $connection?->health_checked_at ?? $health->last_health_check_at,
                    'last_webhook_verification_at' => $lastWebhookVerificationAt,
                    'last_received_message_at' => $connection?->last_received_message_at ?? $connection?->last_message_received_at,
                    'last_sent_message_at' => $connection?->last_sent_message_at ?? $connection?->last_message_sent_at,
                    'route' => route('channels.show', $provider),
                    'health_check_route' => route('channels.health-check', $provider),
                    'connect_route' => route('channels.connect', $provider),
                    'disconnect_route' => route('channels.disconnect', $provider),
                    'verify_endpoint_route' => $provider === 'whatsapp' ? route('channels.whatsapp.verify-endpoint') : null,
                    'configure_route' => $provider === 'whatsapp'
                        ? route('channels.whatsapp.configuration')
                        : route('channels.show', [$provider]) . '#connection',
                    'is_placeholder' => in_array($provider, ['whatsapp', 'instagram'], true),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function hubSummary(): array
    {
        $providers = config('messaging.providers', ['telegram', 'whatsapp', 'instagram']);
        $healthCounts = collect($providers)
            ->map(fn (string $provider): string => $this->messagingManager->health($provider)->status)
            ->countBy()
            ->all();

        return [
            'providers' => count($providers),
            'connected' => $healthCounts['connected'] ?? 0,
            'healthy' => $healthCounts['healthy'] ?? 0,
            'warning' => $healthCounts['warning'] ?? 0,
            'coming_soon' => $healthCounts['coming_soon'] ?? 0,
            'unknown' => $healthCounts['unknown'] ?? 0,
        ];
    }

    protected function channelConnection(bool $create = false): ?ChannelConnection
    {
        $organizationId = request()->user()?->organization_id;

        if ($organizationId === null) {
            return null;
        }

        $connection = ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->where('channel', ChannelConnection::CHANNEL_WHATSAPP)
            ->latest('id')
            ->first();

        if ($connection !== null || ! $create) {
            return $connection;
        }

        return ChannelConnection::query()->create([
            'organization_id' => $organizationId,
            'channel' => ChannelConnection::CHANNEL_WHATSAPP,
            'status' => ChannelConnection::STATUS_DRAFT,
            'metadata_json' => $this->defaultMetadata(),
        ]);
    }

    protected function providerConnection(string $provider): ?ChannelConnection
    {
        $organizationId = request()->user()?->organization_id;

        if ($organizationId === null) {
            return null;
        }

        return ChannelConnection::query()
            ->where('organization_id', $organizationId)
            ->where('channel', $provider)
            ->latest('id')
            ->first();
    }

    protected function connectionValue(?ChannelConnection $connection): string
    {
        return $connection?->display_name
            ?? $connection?->phone_number
            ?? 'No conectado';
    }

    protected function statusLabel(?ChannelConnection $connection): string
    {
        if ($connection === null) {
            return 'No conectado';
        }

        return match ($connection->status) {
            ChannelConnection::STATUS_DRAFT => 'Borrador',
            ChannelConnection::STATUS_PENDING => 'Pendiente',
            ChannelConnection::STATUS_READY_FOR_SETUP => 'Listo para configuracion asistida',
            ChannelConnection::STATUS_CONNECTED => 'Conectado',
            default => Str::headline($connection->status),
        };
    }

    protected function defaultMetadata(): array
    {
        return [
            'has_whatsapp_business' => false,
            'has_dedicated_number' => false,
            'has_facebook_access' => false,
            'has_meta_business' => false,
            'needs_assisted_setup' => false,
            'business_category' => null,
            'expected_monthly_orders' => null,
            'notes' => null,
        ];
    }

    protected function normalizedMetadata(?array $metadata): array
    {
        return array_merge($this->defaultMetadata(), $metadata ?? []);
    }

    protected function readinessState(array $metadata): array
    {
        $requiredKeys = [
            'has_whatsapp_business',
            'has_dedicated_number',
            'has_facebook_access',
            'has_meta_business',
        ];

        $completedRequired = collect($requiredKeys)->filter(
            fn (string $key): bool => (bool) ($metadata[$key] ?? false)
        )->count();

        $percentage = intdiv($completedRequired * 100, count($requiredKeys));

        $status = match ($completedRequired) {
            0 => ChannelConnection::STATUS_DRAFT,
            count($requiredKeys) => ChannelConnection::STATUS_READY_FOR_SETUP,
            default => ChannelConnection::STATUS_PENDING,
        };

        return [
            'percentage' => $percentage,
            'completed_required' => $completedRequired,
            'required_total' => count($requiredKeys),
            'status' => $status,
            'assisted_setup' => (bool) ($metadata['needs_assisted_setup'] ?? false),
        ];
    }

    protected function stepperStatus(int $step, array $readiness): string
    {
        $percentage = $readiness['percentage'];

        return match ($step) {
            1 => $percentage >= 25 ? 'completed' : 'active',
            2 => $percentage >= 50 ? 'completed' : ($percentage >= 25 ? 'active' : 'pending'),
            3 => $percentage >= 75 ? 'completed' : ($percentage >= 50 ? 'active' : 'pending'),
            4 => $percentage >= 100 ? 'completed' : ($percentage >= 75 ? 'active' : 'pending'),
            default => $percentage >= 100 ? 'completed' : 'current',
        };
    }

    private function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }

    private function providerLabel(string $provider): string
    {
        return match ($provider) {
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            default => Str::headline($provider),
        };
    }

    private function providerDescription(string $provider): string
    {
        return match ($provider) {
            'telegram' => 'Telegram is live and exposes the real lifecycle surface.',
            'whatsapp' => 'WhatsApp is locally configured and waiting for Meta verification.',
            'instagram' => 'Coming soon. The provider shell is ready, but Meta integration is not.',
            default => 'Proveedor sin descripcion registrada.',
        };
    }

    private function providerBanner(string $provider, string $status): array
    {
        return [
            'label' => $this->providerLabel($provider),
            'status' => $this->providerStatusLabel($status),
            'description' => match ($provider) {
                'telegram' => 'Telegram runs on the real messaging provider lifecycle.',
                'whatsapp' => 'WhatsApp now exposes local readiness and keeps Meta verification as the next step.',
                'instagram' => 'Instagram is reserved as a future provider in the same lifecycle.',
                default => 'Lifecycle surface available for this provider.',
            },
        ];
    }

    private function providerLogo(string $provider): array
    {
        return match ($provider) {
            'telegram' => [
                'accent' => 'blue',
                'path' => 'M21.8 4.7 3.6 11.4c-1 .4-1 1.8.1 2.1l4.7 1.4 1.8 5.2c.3.9 1.5 1.1 2.1.3l2.8-3.3 4.8 3.5c.8.6 1.9.1 2.1-.9l2.8-13.8c.2-1.1-.9-2-2-1.7Z',
            ],
            'instagram' => [
                'accent' => 'rose',
                'path' => 'M7.5 3.5h9A4 4 0 0 1 20.5 7.5v9a4 4 0 0 1-4 4h-9a4 4 0 0 1-4-4v-9a4 4 0 0 1 4-4Zm9.5 2.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2ZM12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
            ],
            default => [
                'accent' => 'emerald',
                'path' => 'M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z',
            ],
        };
    }

    private function providerStatusLabel(string $status): string
    {
        return match ($status) {
            'healthy', 'connected' => 'Activo',
            'ready' => 'Ready',
            'coming_soon' => 'Coming Soon',
            'warning' => 'Warning',
            'disconnected' => 'Disconnected',
            'expired' => 'Expired',
            default => 'Unknown',
        };
    }

    private function healthLabel(string $status): string
    {
        return match ($status) {
            'healthy' => 'Healthy',
            'connected' => 'Connected',
            'ready' => 'Ready',
            'coming_soon' => 'Coming Soon',
            'warning' => 'Warning',
            'disconnected' => 'Disconnected',
            'expired' => 'Expired',
            default => 'Unknown',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'healthy', 'connected' => 'emerald',
            'coming_soon' => 'rose',
            'warning' => 'amber',
            'disconnected' => 'slate',
            default => 'slate',
        };
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, string>
     */
    private function capabilityPreview(array $capabilities): array
    {
        return collect([
            'send_messages' => 'Send',
            'receive_messages' => 'Receive',
            'buttons' => 'Buttons',
            'templates' => 'Templates',
            'catalog' => 'Catalog',
        ])
            ->filter(fn (string $label, string $key): bool => (bool) ($capabilities[$key] ?? false))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    private function capabilityItems(array $capabilities): array
    {
        $labels = [
            'receive_messages' => 'Receive Messages',
            'send_messages' => 'Send Messages',
            'images' => 'Images',
            'files' => 'Files',
            'audio' => 'Audio',
            'video' => 'Video',
            'templates' => 'Templates',
            'catalog' => 'Catalog',
            'reactions' => 'Reactions',
            'buttons' => 'Buttons',
            'location' => 'Location',
            'contacts' => 'Contacts',
            'send_images' => 'Images',
            'send_documents' => 'Files',
            'send_audio' => 'Audio',
            'send_video' => 'Video',
            'interactive_buttons' => 'Interactive Buttons',
            'typing_indicator' => 'Typing Indicator',
            'delivery_receipts' => 'Delivery Receipts',
            'read_receipts' => 'Read Receipts',
            'voice_notes' => 'Voice Notes',
            'reaction_support' => 'Reaction Support',
        ];

        return collect($labels)
            ->map(function (string $label, string $key) use ($capabilities): array {
                return [
                    'key' => $key,
                    'label' => $label,
                    'enabled' => (bool) ($capabilities[$key] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providerLogs(string $provider, ?ChannelConnection $connection, \App\Services\Messaging\DTO\ProviderHealth $health): array
    {
        return [
            [
                'title' => 'Connected',
                'detail' => $connection?->connected_at ? 'Conexion registrada en la base de datos.' : 'Sin conexion persistida.',
                'time' => $connection?->connected_at?->format('d/m H:i') ?? 'Sin dato',
            ],
            [
                'title' => 'Webhook Verified',
                'detail' => 'Current webhook state: ' . \Illuminate\Support\Str::headline($health->webhook_status),
                'time' => 'Ahora',
            ],
            [
                'title' => 'Health Checked',
                'detail' => 'Estado actual: ' . $this->healthLabel($health->status),
                'time' => $connection?->last_health_check_at?->format('d/m H:i') ?? $connection?->health_checked_at?->format('d/m H:i') ?? 'Hace poco',
            ],
            [
                'title' => 'Provider Error',
                'detail' => $connection?->last_error ?? $health->last_error ?? 'Sin errores registrados.',
                'time' => $connection?->last_ping?->format('d/m H:i') ?? $health->last_ping?->format('d/m H:i') ?? 'Sin dato',
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array<int, array<string, mixed>>
     */
    private function futureFeatures(string $provider, array $capabilities): array
    {
        $items = [
            [
                'label' => 'Reconnect',
                'description' => 'Preparado para reactivar el canal desde el mismo lifecycle.',
                'enabled' => (bool) ($capabilities['send_messages'] ?? false),
            ],
            [
                'label' => 'Disconnect',
                'description' => 'Desconexion segura sin tocar parser ni ordenes.',
                'enabled' => true,
            ],
            [
                'label' => 'Webhook flow',
                'description' => 'Listo para la integracion del adaptador cuando llegue Meta o cualquier otro proveedor.',
                'enabled' => (bool) ($capabilities['receive_messages'] ?? false),
            ],
        ];

        if ($provider === 'whatsapp') {
            $items[] = [
                'label' => 'Meta Cloud API',
                'description' => 'Pendiente para el siguiente sprint, sin implementarlo ahora.',
                'enabled' => false,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{label: string, value: string, detail: string}>
     */
    private function recentActivity(?ChannelConnection $connection, \App\Services\Messaging\DTO\ProviderHealth $health): array
    {
        return [
            [
                'label' => 'Last received',
                'value' => $connection?->last_received_message_at?->format('d/m/Y H:i') ?? $connection?->last_message_received_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Most recent incoming lifecycle event.',
            ],
            [
                'label' => 'Last sent',
                'value' => $connection?->last_sent_message_at?->format('d/m/Y H:i') ?? $connection?->last_message_sent_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Most recent outbound lifecycle event.',
            ],
            [
                'label' => 'Last health check',
                'value' => $connection?->last_health_check_at?->format('d/m/Y H:i') ?? $connection?->health_checked_at?->format('d/m/Y H:i') ?? $health->last_health_check_at?->format('d/m/Y H:i') ?? 'Sin dato',
                'detail' => 'Latest provider health refresh.',
            ],
        ];
    }

    /**
     * @param  array<int, string>  $validationErrors
     * @return array<int, string>
     */
    private function recentErrors(?ChannelConnection $connection, array $validationErrors): array
    {
        $errors = array_filter([
            $connection?->last_error,
            ...$validationErrors,
        ]);

        return $errors !== [] ? array_values(array_unique($errors)) : ['Sin errores recientes.'];
    }

    /**
     * @param  ProviderHealth|ProviderValidationResult  $validation
     * @return array<int, string>
     */
    private function validationErrors(ProviderHealth|ProviderValidationResult $validation): array
    {
        if ($validation instanceof ProviderValidationResult) {
            return $validation->errors;
        }

        return $validation->last_error !== null ? [$validation->last_error] : [];
    }

    /**
     * @param  ProviderHealth|ProviderValidationResult  $validation
     * @return array<int, string>
     */
    private function validationWarnings(ProviderHealth|ProviderValidationResult $validation): array
    {
        if ($validation instanceof ProviderValidationResult) {
            return $validation->warnings;
        }

        return $validation->metadata['validation_warnings'] ?? [];
    }

    private function futureReadyBanner(string $provider, string $status): array
    {
        return [
            'title' => $provider === 'instagram'
                ? 'Coming Soon'
                : 'Future Ready',
            'description' => match ($provider) {
                'telegram' => 'Telegram is live. New providers can reuse this lifecycle immediately.',
                'whatsapp' => 'WhatsApp configuration is ready locally and now waits for Meta verification.',
                'instagram' => 'Instagram is reserved and ready to plug into the same provider interface.',
                default => 'This provider uses the shared lifecycle surface.',
            },
            'status' => $this->providerStatusLabel($status),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function providerActions(string $provider): array
    {
        return [
            [
                'label' => 'View Details',
                'href' => route('channels.show', $provider),
                'style' => 'primary',
            ],
            [
                'label' => 'Configure',
                'href' => $provider === 'whatsapp' ? route('channels.whatsapp.configuration') : route('channels.show', $provider) . '#connection',
                'style' => 'secondary',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overviewItems(string $provider, ?ChannelConnection $connection, \App\Services\Messaging\DTO\ProviderHealth $health): array
    {
        return [
            [
                'label' => 'Provider',
                'value' => $this->providerLabel($provider),
            ],
            [
                'label' => 'Health',
                'value' => $this->healthLabel($health->status),
            ],
            [
                'label' => 'Version',
                'value' => $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1',
            ],
            [
                'label' => 'Last ping',
                'value' => $connection?->last_ping?->format('d/m/Y H:i') ?? ($health->last_ping?->format('d/m/Y H:i') ?? 'Sin dato'),
            ],
            [
                'label' => 'Credentials',
                'value' => $connection?->credentials_status ?? $health->credentials_status ?? 'unknown',
            ],
            [
                'label' => 'Webhook',
                'value' => $connection?->webhook_status ?? $health->webhook_status ?? 'unknown',
            ],
        ];
    }
}
