<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Models\Organization;
use App\Services\Developer\BusinessScenarioService;
use App\Services\Developer\ScenarioGeneratorService;
use App\Services\Developer\ToolkitChannels;
use App\Services\Developer\ToolkitDataGenerator;
use App\Services\Developer\ToolkitScenarioCatalog;
use App\Services\Developer\WebhookPayloadSimulator;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebhookSimulatorController extends Controller
{
    public function __construct(
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
        private readonly ScenarioGeneratorService $scenarioGeneratorService,
        private readonly BusinessScenarioService $businessScenarioService,
        private readonly WebhookPayloadSimulator $webhookPayloadSimulator,
        private readonly ToolkitDataGenerator $toolkitDataGenerator,
        private readonly ToolkitScenarioCatalog $toolkitScenarioCatalog,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);
        $formState = $this->webhookPayloadSimulator->defaultFormState($connection, $request->string('provider')->toString());

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
            ? $this->webhookPayloadSimulator->decodedPayloadPreview($validated['payload_preview'] ?? null)
            : $this->webhookPayloadSimulator->buildPayloadFromFields($validated, $connection);

        $result = $this->webhookPayloadSimulator->send($organization, $connection, $payload, $validated);

        $formState = $this->webhookPayloadSimulator->formStateFromValidated($connection->fresh(), $validated, $payload);

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
            'action' => ['required', 'in:scenario,quick,customers,qa,business_scenario,business_custom_message,business_random_messages,business_day,business_reset'],
            'provider' => ['nullable', 'in:whatsapp,telegram,instagram'],
            'scenario' => ['nullable', 'string', 'max:80'],
            'count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'customer_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'qa_case' => ['nullable', 'string', 'max:80'],
            'customer_mode' => ['nullable', 'in:new,existing'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'message' => ['nullable', 'string', 'max:5000'],
            'business_count' => ['nullable', 'integer', 'min:1', 'max:250'],
            'speed' => ['nullable', 'integer', 'min:1', 'max:10'],
            'business_reset_scope' => ['nullable', 'in:today,demo_customers,demo_orders,messages,notifications,webhook_logs,environment'],
        ]);

        $provider = $validated['provider'] ?? $request->string('provider')->toString() ?: 'whatsapp';
        $formState = $this->webhookPayloadSimulator->defaultFormState($connection, $provider);

        $result = match ($validated['action']) {
            'scenario' => (string) ($validated['scenario'] ?? 'ferreteria_pequena') === 'ferreteria_pequena'
                ? $this->scenarioGeneratorService->generateSmallHardwareStoreScenario($organization)
                : $this->toolkitDataGenerator->generateScenario($organization, $provider, (string) ($validated['scenario'] ?? 'ferreteria_pequena')),
            'quick' => $this->toolkitDataGenerator->generateQuickOrders($organization, $provider, (int) ($validated['count'] ?? 1)),
            'customers' => $this->toolkitDataGenerator->generateCustomersOnly($organization, (int) ($validated['customer_count'] ?? 10)),
            'qa' => $this->toolkitDataGenerator->generateQaCase($organization, $provider, (string) ($validated['qa_case'] ?? 'busy_day')),
            'business_scenario' => $this->businessScenarioService->generateScenario($organization, (string) ($validated['scenario'] ?? 'small_hardware_store')),
            'business_custom_message' => $this->businessScenarioService->generateCustomMessage($organization, $validated),
            'business_random_messages' => $this->businessScenarioService->generateRandomMessages($organization, (int) ($validated['business_count'] ?? 10)),
            'business_day' => $this->businessScenarioService->simulateBusinessDay($organization, (int) ($validated['speed'] ?? 1)),
            'business_reset' => $this->businessScenarioService->reset($organization, (string) ($validated['business_reset_scope'] ?? 'today')),
        };

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $formState,
            result: $result,
        );
    }

    public function generateSmallHardwareStoreScenario(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);
        $result = $this->scenarioGeneratorService->generateSmallHardwareStoreScenario($organization);

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $this->webhookPayloadSimulator->defaultFormState($connection),
            result: $result,
        );
    }

    public function resetDemoData(Request $request): View
    {
        $this->ensureDeveloperEnvironment();

        $organization = $this->organizationFor($request);
        $connection = $this->prepareConnection($organization->id);
        $result = $this->scenarioGeneratorService->resetGeneratedDemoData($organization);

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $this->webhookPayloadSimulator->defaultFormState($connection),
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

        $result = $this->toolkitDataGenerator->resetToolkitData($organization, $validated['scope']);

        return $this->renderToolkitPage(
            organization: $organization,
            connection: $connection->fresh(),
            formState: $this->webhookPayloadSimulator->defaultFormState($connection),
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
            'providerSpecs' => $this->webhookPayloadSimulator->providerSpecs(),
            'examples' => $this->webhookPayloadSimulator->examples($formState),
            'scenarios' => $this->toolkitScenarioCatalog->scenarioDefinitions(),
            'quickCounts' => [1, 5, 20, 50, 100],
            'customerCounts' => [10, 50, 100],
            'qaCases' => $this->toolkitScenarioCatalog->qaDefinitions(),
            'businessScenarios' => $this->businessScenarioService->scenarios(),
            'simulationSpeeds' => $this->businessScenarioService->simulationSpeeds(),
            'businessMetrics' => $this->businessScenarioService->metrics($organization),
            'metrics' => $this->toolkitDataGenerator->metricsFor($organization->id),
            'result' => $result,
            'developerMode' => true,
            'selectedProvider' => $provider,
        ]);
    }

    private function ensureDeveloperEnvironment(): void
    {
        abort_unless(config('features.developer_toolkit_enabled'), 404);

        // El toolkit necesita ver el resultado de la ingesta al momento, así que
        // el mensaje entrante se procesa de forma síncrona en vez de encolarse.
        config(['messaging.inbound_queue' => false]);
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
                'provider_phone_number_id' => ToolkitChannels::PHONE_NUMBER_ID_TEST,
            ])->save();
        }

        return $connection->fresh();
    }
}
