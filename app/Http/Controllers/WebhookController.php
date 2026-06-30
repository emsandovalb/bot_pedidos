<?php

namespace App\Http\Controllers;

use App\Services\Messaging\Manager\ProviderLifecycleManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ProviderLifecycleManager $providerLifecycleManager,
    ) {
    }

    public function show(Request $request, string $provider): Response
    {
        try {
            $result = $this->providerLifecycleManager->verifyWebhook($provider, $request);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response((string) ($result->challenge ?? $result->message ?? ''), $result->status)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function store(Request $request, string $provider): Response
    {
        try {
            $result = $this->providerLifecycleManager->receiveWebhook($provider, $request);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response((string) ($result->message ?? ''), $result->status)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
