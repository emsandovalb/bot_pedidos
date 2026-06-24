<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(16,185,129,0.12),rgba(20,110,219,0.07)_40%,rgba(255,255,255,1)_82%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Estado de WhatsApp
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Dashboard de salud y actividad del canal
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Vista ejecutiva para revisar conexión, mensajes en espera y el rendimiento esperado del canal principal.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.whatsapp') }}" class="brand-btn-primary justify-center">
                            Volver al wizard
                        </a>
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Ir al hub
                        </a>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-emerald-200/80 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $connectionStatus['label'] }}</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $connectionStatus['value'] }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $connectionStatus['description'] }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M9 12l2 2 4-4M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-semibold text-brand-navy">{{ $connectionStatus['lastChecked'] }}</div>
                        <p class="mt-1 text-sm text-slate-500">Referencia persistida para el panel de monitoreo.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($statusMetrics as $metric)
                <article class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-sm">
                    <div class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</div>
                    <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $metric['value'] }}</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $metric['detail'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Chequeos de salud</h2>
                        <p class="mt-1 text-sm text-slate-500">Controles visuales para entender la preparación del canal.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @foreach ($healthChecks as $check)
                        <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-brand-navy">{{ $check['title'] }}</div>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $check['detail'] }}</p>
                                </div>
                                <span class="rounded-full {{ $check['state'] === 'Ok' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : ($check['state'] === 'Pendiente' ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100' : 'bg-slate-100 text-slate-600') }} px-3 py-1 text-xs font-semibold">
                                    {{ $check['state'] }}
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-[linear-gradient(180deg,rgba(20,110,219,0.07),rgba(255,255,255,1)_74%)] p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Cronología demo</h2>
                        <p class="mt-1 text-sm text-slate-500">Actividad que el equipo podría ver al abrir el panel.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($timeline as $item)
                        <article class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                                    {{ $item['time'] }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-brand-navy">{{ $item['title'] }}</div>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $item['detail'] }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Interpretación rápida</h2>
                        <p class="mt-1 text-sm text-slate-500">El canal está preparado para crecer cuando exista integración.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Lectura actual</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Operativo en modo demo</div>
                    </div>
                    <div class="rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4">
                        <div class="text-sm font-medium text-slate-500">Próximo paso</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Conectar API</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Riesgo</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Bajo</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Prioridad</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Alta</div>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Lo que viene</h2>
                        <p class="mt-1 text-sm text-slate-500">Piezas que normalmente se conectarían después.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Webhooks</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">Pendiente</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Plantillas</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">Demo</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Derivación</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">Manual</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Monitoreo</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">Visual</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
