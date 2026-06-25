<?php

namespace App\Http\Controllers;

use App\Models\ChannelConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelOnboardingController extends ChannelController
{
    public function update(Request $request): RedirectResponse
    {
        $connection = $this->channelConnection(true);

        if ($connection === null) {
            abort(403);
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'has_whatsapp_business' => ['nullable', 'boolean'],
            'has_dedicated_number' => ['nullable', 'boolean'],
            'has_facebook_access' => ['nullable', 'boolean'],
            'has_meta_business' => ['nullable', 'boolean'],
            'needs_assisted_setup' => ['nullable', 'boolean'],
            'business_category' => ['nullable', 'string', 'max:255'],
            'expected_monthly_orders' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $metadata = $this->normalizedMetadata($connection->metadata_json);
        $metadata = array_merge($metadata, [
            'has_whatsapp_business' => $request->boolean('has_whatsapp_business'),
            'has_dedicated_number' => $request->boolean('has_dedicated_number'),
            'has_facebook_access' => $request->boolean('has_facebook_access'),
            'has_meta_business' => $request->boolean('has_meta_business'),
            'needs_assisted_setup' => $request->boolean('needs_assisted_setup'),
            'business_category' => $this->nullableValue($validated['business_category'] ?? null),
            'expected_monthly_orders' => $this->nullableValue($validated['expected_monthly_orders'] ?? null),
            'notes' => $this->nullableValue($validated['notes'] ?? null),
        ]);

        $status = $this->statusFromMetadata($metadata);

        DB::transaction(function () use ($connection, $validated, $metadata, $status): void {
            $connection->fill([
                'display_name' => $validated['display_name'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'status' => $status,
            ]);

            $connection->metadata_json = $metadata;
            $connection->save();
        });

        return redirect()
            ->route('channels.whatsapp')
            ->with('status', 'Configuracion de WhatsApp guardada.');
    }

    private function statusFromMetadata(array $metadata): string
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

        return match ($completedRequired) {
            0 => ChannelConnection::STATUS_DRAFT,
            count($requiredKeys) => ChannelConnection::STATUS_READY_FOR_SETUP,
            default => ChannelConnection::STATUS_PENDING,
        };
    }

    private function nullableValue(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }
}
