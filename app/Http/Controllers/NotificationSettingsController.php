<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organizationFor($request);
        $settings = $this->ensureDefaultSettings($organization);

        return view('settings.notifications', [
            'organization' => $organization,
            'settingsByChannel' => $this->settingsByChannel($settings),
            'channels' => NotificationSetting::CHANNELS,
            'events' => NotificationSetting::EVENTS,
            'channelLabels' => NotificationSetting::CHANNEL_LABELS,
            'eventLabels' => NotificationSetting::EVENT_LABELS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $this->organizationFor($request);
        $settings = $this->ensureDefaultSettings($organization)->keyBy(
            fn (NotificationSetting $setting): string => $setting->channel . '.' . $setting->event
        );

        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.*.is_enabled' => ['nullable'],
            'settings.*.*.requires_open_service_window' => ['nullable'],
            'settings.*.*.use_template_if_window_closed' => ['nullable'],
            'settings.*.*.template_name' => ['nullable', 'string', 'max:255'],
            'settings.*.*.message_body' => ['nullable', 'string', 'max:5000'],
        ]);

        foreach (NotificationSetting::CHANNELS as $channel) {
            foreach (NotificationSetting::EVENTS as $event) {
                $payload = data_get($validated, "settings.$channel.$event", []);
                $currentSetting = $settings->get($channel . '.' . $event);
                $defaults = NotificationSetting::defaultAttributes($channel, $event);

                NotificationSetting::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'channel' => $channel,
                        'event' => $event,
                    ],
                    [
                        'is_enabled' => $this->booleanValue($payload['is_enabled'] ?? ($currentSetting?->is_enabled ?? $defaults['is_enabled'])),
                        'requires_open_service_window' => $channel === NotificationSetting::CHANNEL_WHATSAPP
                            ? $this->booleanValue($payload['requires_open_service_window'] ?? ($currentSetting?->requires_open_service_window ?? $defaults['requires_open_service_window']))
                            : false,
                        'use_template_if_window_closed' => $channel === NotificationSetting::CHANNEL_WHATSAPP
                            ? $this->booleanValue($payload['use_template_if_window_closed'] ?? ($currentSetting?->use_template_if_window_closed ?? false))
                            : false,
                        'template_name' => $channel === NotificationSetting::CHANNEL_WHATSAPP
                            ? $this->nullableString($payload['template_name'] ?? $currentSetting?->template_name)
                            : null,
                        'message_body' => $this->nullableString($payload['message_body'] ?? $currentSetting?->message_body),
                        'metadata_json' => $currentSetting?->metadata_json,
                    ]
                );
            }
        }

        return redirect()
            ->route('settings.notifications.index')
            ->with('status', 'Configuracion de notificaciones actualizada.');
    }

    private function organizationFor(Request $request): Organization
    {
        $user = $request->user();
        abort_unless($user !== null && $user->organization_id !== null, 403);

        return Organization::query()->findOrFail($user->organization_id);
    }

    private function ensureDefaultSettings(Organization $organization): Collection
    {
        foreach (NotificationSetting::CHANNELS as $channel) {
            foreach (NotificationSetting::EVENTS as $event) {
                NotificationSetting::query()->firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'channel' => $channel,
                        'event' => $event,
                    ],
                    NotificationSetting::defaultAttributes($channel, $event),
                );
            }
        }

        return NotificationSetting::query()
            ->where('organization_id', $organization->id)
            ->get();
    }

    /**
     * @param  Collection<int, NotificationSetting>  $settings
     * @return array<string, array<string, NotificationSetting>>
     */
    private function settingsByChannel(Collection $settings): array
    {
        return $settings
            ->groupBy('channel')
            ->map(fn (Collection $channelSettings): array => $channelSettings->keyBy('event')->all())
            ->all();
    }

    private function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
