@php
    $channelDescriptions = [
        'whatsapp' => 'WhatsApp permite responder libremente dentro de la ventana de servicio abierta por el cliente. Si la ventana está cerrada, se requiere una plantilla aprobada.',
        'telegram' => 'Telegram no tiene ventana de servicio, por lo que las notificaciones pueden enviarse según tu configuración.',
    ];
@endphp

<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.10),rgba(22,163,74,0.08)_42%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="relative px-6 py-7 lg:px-8">
                <div class="max-w-4xl space-y-4">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Configuración
                    </div>
                    <div class="space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Configuración de notificaciones
                        </h1>
                        <p class="max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                            Define qué eventos envían mensajes automáticos a tus clientes.
                        </p>
                        <p class="max-w-3xl text-sm leading-6 text-slate-600">
                            Para controlar costos, puedes elegir qué estados notifican al cliente.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-8">
            @csrf

            @foreach ($channels as $channel)
                @php
                    $channelSettings = $settingsByChannel[$channel] ?? [];
                @endphp

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200/80 pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $channel === 'whatsapp' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200' }}">
                                {{ $channelLabels[$channel] }}
                            </div>
                            <h2 class="text-xl font-semibold text-brand-navy">{{ $channelLabels[$channel] }}</h2>
                            <p class="max-w-3xl text-sm leading-6 text-slate-600">{{ $channelDescriptions[$channel] }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <div class="font-semibold text-brand-navy">Eventos disponibles</div>
                            <div class="mt-1">{{ count($events) }} por canal</div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        @foreach ($events as $event)
                            @php
                                $setting = $channelSettings[$event] ?? null;
                                $eventKey = "settings.$channel.$event";
                            @endphp

                            <article class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4">
                                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-brand-navy">{{ $eventLabels[$event] }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ $event }}</p>
                                    </div>
                                    <label class="inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm">
                                        <input
                                            type="hidden"
                                            name="settings[{{ $channel }}][{{ $event }}][is_enabled]"
                                            value="0"
                                        >
                                        <input
                                            type="checkbox"
                                            name="settings[{{ $channel }}][{{ $event }}][is_enabled]"
                                            value="1"
                                            class="h-4 w-4 rounded border-slate-300 text-brand-primary focus:ring-brand-primary"
                                            @checked(old($eventKey . '.is_enabled', $setting?->is_enabled ?? false))
                                        >
                                        <span>Habilitado</span>
                                    </label>
                                </div>

                                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                    @if ($channel === 'whatsapp')
                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-700">Requiere ventana abierta</span>
                                            <input type="hidden" name="settings[{{ $channel }}][{{ $event }}][requires_open_service_window]" value="0">
                                            <input
                                                type="checkbox"
                                                name="settings[{{ $channel }}][{{ $event }}][requires_open_service_window]"
                                                value="1"
                                                class="h-4 w-4 rounded border-slate-300 text-brand-primary focus:ring-brand-primary"
                                                @checked(old($eventKey . '.requires_open_service_window', $setting?->requires_open_service_window ?? false))
                                            >
                                            <p class="mt-2 text-sm leading-6 text-slate-500">Si está activo, la notificación solo se enviará con la ventana de servicio abierta o con plantilla configurada.</p>
                                        </label>

                                        <label class="block">
                                            <span class="mb-2 block text-sm font-medium text-slate-700">Usar plantilla si la ventana está cerrada</span>
                                            <input type="hidden" name="settings[{{ $channel }}][{{ $event }}][use_template_if_window_closed]" value="0">
                                            <input
                                                type="checkbox"
                                                name="settings[{{ $channel }}][{{ $event }}][use_template_if_window_closed]"
                                                value="1"
                                                class="h-4 w-4 rounded border-slate-300 text-brand-primary focus:ring-brand-primary"
                                                @checked(old($eventKey . '.use_template_if_window_closed', $setting?->use_template_if_window_closed ?? false))
                                            >
                                            <p class="mt-2 text-sm leading-6 text-slate-500">Actívalo solo si ya tienes una plantilla aprobada para este evento.</p>
                                        </label>
                                    @endif

                                    <label class="block">
                                        <span class="mb-2 block text-sm font-medium text-slate-700">Nombre de plantilla</span>
                                        <input
                                            type="text"
                                            name="settings[{{ $channel }}][{{ $event }}][template_name]"
                                            value="{{ old($eventKey . '.template_name', $setting?->template_name) }}"
                                            class="w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                            placeholder="pedido_confirmado"
                                        >
                                    </label>

                                    <label class="block lg:col-span-2">
                                        <span class="mb-2 block text-sm font-medium text-slate-700">Mensaje manual</span>
                                        <textarea
                                            name="settings[{{ $channel }}][{{ $event }}][message_body]"
                                            rows="4"
                                            class="w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-primary focus:ring-brand-primary"
                                            placeholder="Tu pedido #{order_id} ya fue confirmado."
                                        >{{ old($eventKey . '.message_body', $setting?->message_body) }}</textarea>
                                    </label>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="flex items-center justify-end">
                <button type="submit" class="brand-btn-primary px-6 py-3">
                    Guardar configuración
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
