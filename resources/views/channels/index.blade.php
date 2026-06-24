<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(22,163,74,0.12),rgba(20,110,219,0.07)_44%,rgba(255,255,255,1)_82%)] shadow-sm">
            <div class="relative grid gap-6 px-6 py-7 lg:grid-cols-[1.25fr_0.75fr] lg:px-8">
                <div class="relative space-y-5">
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Canales v1
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            WhatsApp Business como canal principal del producto
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Centro de control para iniciar el canal, seguir su estado y guiar al equipo por un onboarding claro, premium y listo para crecer.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.whatsapp') }}" class="brand-btn-primary justify-center">
                            Configurar WhatsApp
                        </a>
                        <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Ver estado
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ($channelHighlights as $highlight)
                        <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $highlight['label'] }}</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $highlight['value'] }}</p>
                                </div>
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl {{ $highlight['tone'] === 'green' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : ($highlight['tone'] === 'emerald' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : 'bg-blue-50 text-brand-primary ring-1 ring-inset ring-blue-100') }}">
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <path d="M12 8v8m-4-4h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    </svg>
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $highlight['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Ruta de activación</h2>
                        <p class="mt-1 text-sm text-slate-500">Paso a paso para hacer que WhatsApp sea el canal preferido.</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Demo guiada
                    </span>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($channelJourney as $step)
                        <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 transition hover:-translate-y-0.5 hover:bg-white">
                            <div class="flex items-start justify-between gap-4">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                                    {{ $step['step'] }}
                                </span>
                                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Paso</span>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-brand-navy">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $step['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Actividad reciente</h2>
                        <p class="mt-1 text-sm text-slate-500">Señales demo del flujo de canales.</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Visualización
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($activityFeed as $item)
                        <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-brand-navy">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $item['detail'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-200">
                                    {{ $item['time'] }}
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Lo que vas a habilitar</h2>
                        <p class="mt-1 text-sm text-slate-500">Vista estratégica del módulo antes de conectarlo a producción.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Onboarding</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Guiado</div>
                        <p class="mt-2 text-sm text-slate-600">Flujo claro para dejar listo el canal sin fricción.</p>
                    </div>
                    <div class="rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4">
                        <div class="text-sm font-medium text-slate-500">Estado</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Visible</div>
                        <p class="mt-2 text-sm text-slate-600">Indicadores demo para seguimiento operativo inmediato.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Integración</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Pendiente</div>
                        <p class="mt-2 text-sm text-slate-600">Sin API todavía, solo estructura y experiencia.</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Foco</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">WhatsApp</div>
                        <p class="mt-2 text-sm text-slate-600">El canal más importante del producto desde ahora.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-[linear-gradient(180deg,rgba(16,185,129,0.08),rgba(255,255,255,1)_70%)] p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Siguiente acción recomendada</h2>
                        <p class="mt-1 text-sm text-slate-500">Acceso rápido al wizard de configuración.</p>
                    </div>
                </div>

                <div class="mt-6 rounded-3xl border border-emerald-200/80 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-brand-navy">WhatsApp Business</div>
                            <div class="text-sm text-slate-500">Onboarding, estado y control del canal</div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($nextActions as $action)
                            <a href="{{ $action['href'] }}" class="{{ $action['tone'] === 'primary' ? 'brand-btn-primary' : 'brand-btn-secondary' }} w-full justify-center">
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
