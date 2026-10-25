<?php

namespace App\Http\Controllers;

use App\Services\Channels\ChannelHubPresenter;
use App\Services\Channels\ChannelProviderLabels;
use App\Services\Channels\WhatsAppOnboardingPresenter;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use App\Services\WhatsAppConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
        private readonly WhatsAppConfigurationService $whatsAppConfigurationService,
        private readonly ChannelHubPresenter $channelHub,
        private readonly ChannelProviderLabels $labels,
        private readonly WhatsAppOnboardingPresenter $whatsAppOnboarding,
    ) {}

    public function index(): View
    {
        return view('channels.index', [
            'providerCards' => $this->channelHub->providerCards(request()->user()?->organization_id),
            'hubSummary' => $this->channelHub->hubSummary(),
        ]);
    }

    public function show(string $provider): View
    {
        $provider = $this->labels->normalizeProvider($provider);

        return view('channels.show', $this->channelHub->providerDetail($provider, request()->user()?->organization_id));
    }

    public function healthCheck(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->labels->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->refreshHealth($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', 'Health check updated for '.$this->labels->providerLabel($provider).'.');
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->labels->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->connect($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', $this->labels->providerLabel($provider).' connected.');
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->labels->normalizeProvider($provider);
        $organizationId = $request->user()?->organization_id;

        $this->providerLifecycleManager->disconnect($provider, $organizationId);

        return redirect()
            ->route('channels.show', $provider)
            ->with('status', $this->labels->providerLabel($provider).' disconnected.');
    }

    public function whatsapp(): View
    {
        return view('channels.whatsapp', $this->whatsAppOnboarding->setupPage(request()->user()?->organization_id));
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
        return view('channels.whatsapp-status', $this->whatsAppOnboarding->statusPage(request()->user()?->organization_id));
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
}
