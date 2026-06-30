<?php

namespace App\Services\Messaging;

use App\Models\ChannelConnection;
use App\Services\Messaging\DTO\ProviderHealth;
use App\Services\Messaging\Manager\ProviderLifecycleManager;
use Illuminate\Support\Collection;

class ProviderHealthService
{
    public function __construct(private readonly ProviderLifecycleManager $providerLifecycleManager)
    {
    }

    public function refreshProvider(string $provider, ?int $organizationId = null): ProviderHealth
    {
        $health = $this->providerLifecycleManager->refreshHealth($provider, $organizationId);

        return $health;
    }

    /**
     * @return array<int, ProviderHealth>
     */
    public function refreshConnected(?int $organizationId = null): array
    {
        return ChannelConnection::query()
            ->where('status', ChannelConnection::STATUS_CONNECTED)
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->get()
            ->groupBy('channel')
            ->map(function (Collection $connections, string $provider) use ($organizationId): ProviderHealth {
                return $this->refreshProvider($provider, $organizationId);
            })
            ->values()
            ->all();
    }
}
