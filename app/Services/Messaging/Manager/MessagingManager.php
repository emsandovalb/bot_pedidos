<?php

namespace App\Services\Messaging\Manager;

use App\Services\Messaging\Contracts\MessagingProvider;
use App\Services\Messaging\DTO\ProviderCapabilities;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\DTO\ProviderValidationResult;

class MessagingManager
{
    private readonly ProviderLifecycleManager $providerLifecycleManager;

    public function __construct(?ProviderLifecycleManager $providerLifecycleManager = null)
    {
        $this->providerLifecycleManager = $providerLifecycleManager ?? new ProviderLifecycleManager();
    }

    public function driver(?string $provider = null): MessagingProvider
    {
        return $this->providerLifecycleManager->driver($provider);
    }

    public function health(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        return $this->providerLifecycleManager->health($provider, $organizationId);
    }

    public function validate(?string $provider = null, ?int $organizationId = null): ProviderValidationResult|ProviderHealth
    {
        return $this->providerLifecycleManager->validate($provider, $organizationId);
    }

    public function capabilities(?string $provider = null): ProviderCapabilities
    {
        return $this->providerLifecycleManager->getCapabilities($provider);
    }

    public function connect(?string $provider = null): ProviderHealth
    {
        return $this->providerLifecycleManager->connect($provider);
    }

    public function disconnect(?string $provider = null): ProviderHealth
    {
        return $this->providerLifecycleManager->disconnect($provider);
    }

    public function refreshHealth(?string $provider = null, ?int $organizationId = null): ProviderHealth
    {
        return $this->providerLifecycleManager->refreshHealth($provider, $organizationId);
    }
}
