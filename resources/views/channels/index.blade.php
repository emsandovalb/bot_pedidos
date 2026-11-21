<x-app-layout>
    <div class="space-y-8">
        <section class="page-intro">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-5">
                    <p class="page-eyebrow">Canales</p>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="page-title mt-1">
                            Tus canales de mensajes
                        </h1>
                        <p class="page-subtitle mt-2">
                            Telegram ya recibe pedidos. WhatsApp e Instagram se manejan igual, desde esta misma pantalla.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.show', 'telegram') }}" class="brand-btn-primary justify-center">
                            Abrir Telegram
                        </a>
                        <a href="{{ route('channels.show', 'whatsapp') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Ver WhatsApp
                        </a>
                        <a href="{{ route('channels.show', 'instagram') }}" class="brand-btn-secondary justify-center">
                            Ver Instagram
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Canales</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['providers'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Canales disponibles en BotPedidos.</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Conectados</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['connected'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Canales con una conexión activa.</p>
                    </article>
                    <article class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Atención</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['warning'] + $hubSummary['unknown'] + $hubSummary['coming_soon'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Canales sin configurar o con alertas.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            @foreach ($providerCards as $card)
                @php
                    $tone = $card['status_tone'];
                    $cardBorder = match ($tone) {
                        'emerald' => 'border-emerald-200/80',
                        'rose' => 'border-rose-200/80',
                        'amber' => 'border-amber-200/80',
                        default => 'border-slate-200/80',
                    };
                    $logoClasses = match ($card['logo']['accent']) {
                        'blue' => 'bg-blue-50 text-blue-600 ring-blue-100',
                        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
                        'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
                        default => 'bg-slate-50 text-slate-600 ring-slate-100',
                    };
                    $badgeBg = match ($tone) {
                        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                        'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
                        'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
                        default => 'bg-slate-50 text-slate-600 ring-slate-100',
                    };
                @endphp
                <article class="rounded-[20px] border {{ $cardBorder }} bg-white p-6 shadow-sm transition hover:-translate-y-0.5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl ring-1 ring-inset {{ $logoClasses }}">
                                <svg viewBox="0 0 24 24" fill="none" class="h-7 w-7" aria-hidden="true">
                                    <path d="{{ $card['logo']['path'] }}" fill="currentColor" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $card['label'] }}</p>
                                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $card['status'] }}</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $card['description'] }}</p>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-right">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Estado</div>
                            <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $card['health_label'] }}</div>
                            <div class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[0.7rem] font-semibold uppercase tracking-[0.12em] {{ $badgeBg }}">
                                {{ \Illuminate\Support\Str::headline($card['webhook_status']) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Versión</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['version'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Credenciales</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['credentials_status'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Último recibido</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_received_message_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Último enviado</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_sent_message_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Última revisión</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_health_check_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Última verificación</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">
                                @php
                                    $lastVerification = $card['last_webhook_verification_at'] ?? null;
                                @endphp
                                {{ $lastVerification?->format('d/m H:i') ?? 'Pending' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($card['capability_preview'] as $feature)
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary">
                                {{ $feature }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <a href="{{ $card['route'] }}" class="brand-btn-primary justify-center">
                            Ver detalle
                        </a>
                        <form method="POST" action="{{ $card['health_check_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Revisar conexión
                            </button>
                        </form>
                        <form method="POST" action="{{ $card['disconnect_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Desconectar
                            </button>
                        </form>
                        <form method="POST" action="{{ $card['connect_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Reconectar
                            </button>
                        </form>
                        @if ($card['verify_endpoint_route'] !== null)
                            <form method="POST" action="{{ $card['verify_endpoint_route'] }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                                    Verificar webhook
                                </button>
                            </form>
                        @endif
                        <a href="{{ $card['configure_route'] }}" class="brand-btn-secondary justify-center">
                            Configurar
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Cómo funcionan</h2>
                        <p class="mt-1 text-sm text-slate-500">Todos los canales se manejan igual.</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Para todos los canales
                    </span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Estado</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">A la vista</div>
                        <p class="mt-2 text-sm text-slate-600">Cada canal muestra su estado, credenciales y webhook.</p>
                    </div>
                    <div class="rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4">
                        <div class="text-sm font-medium text-slate-500">Funciones</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Claras</div>
                        <p class="mt-2 text-sm text-slate-600">Enviar, recibir, botones, archivos y reacciones, por canal.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Canales desconocidos</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Seguros</div>
                        <p class="mt-2 text-sm text-slate-600">Un canal que no existe no rompe nada.</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Próximos canales</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Fáciles de sumar</div>
                        <p class="mt-2 text-sm text-slate-600">Un canal nuevo se suma sin tocar los demás.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[20px] border border-slate-200/70 surface-tint-sky p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Acceso anterior</h2>
                        <p class="mt-1 text-sm text-slate-500">La configuración de WhatsApp sigue disponible aquí.</p>
                    </div>
                </div>

                <div class="mt-6 rounded-3xl border border-blue-200/80 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-brand-primary">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-brand-navy">WhatsApp Business</div>
                            <div class="text-sm text-slate-500">Configuración y detalle del canal en un solo lugar.</div>
                        </div>
                    </div>

                    @can('manage-settings')
                    <div class="mt-5 space-y-3">
                        <a href="{{ route('channels.whatsapp') }}" class="brand-btn-primary w-full justify-center">
                            Configurar WhatsApp
                        </a>
                        <a href="{{ route('channels.whatsapp.configuration') }}" class="brand-btn-secondary w-full justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Credenciales de WhatsApp
                        </a>
                    </div>
                    @endcan
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
