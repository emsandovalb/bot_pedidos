<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(22,163,74,0.14),rgba(20,110,219,0.07)_42%,rgba(255,255,255,1)_82%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Asistente de WhatsApp
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Configuración paso a paso para el canal principal
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Este wizard guía al equipo por la identidad del canal, el tono de respuesta y la operación interna. Todo es demo, sin API ni lógica de backend.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-primary justify-center">
                            Revisar estado
                        </a>
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Volver al hub
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    @foreach ($mockProfiles as $profile)
                        <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $profile['label'] }}</p>
                            <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $profile['value'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Wizard de activación</h2>
                        <p class="mt-1 text-sm text-slate-500">La vista de progreso debe sentirse clara y ejecutiva.</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        2/4 activo
                    </span>
                </div>

                <div class="mt-6 space-y-4">
                    @foreach ($steps as $step)
                        <article class="rounded-3xl border {{ $step['status'] === 'active' ? 'border-emerald-300 bg-emerald-50/60 shadow-sm' : 'border-slate-200/80 bg-slate-50/70' }} p-4 transition">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $step['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : ($step['status'] === 'active' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-500 ring-1 ring-inset ring-slate-200') }}">
                                        {{ $step['index'] }}
                                    </div>
                                    <div>
                                        <h3 class="text-base font-semibold text-brand-navy">{{ $step['title'] }}</h3>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $step['description'] }}</p>
                                    </div>
                                </div>

                                <span class="inline-flex self-start rounded-full px-3 py-1 text-xs font-semibold {{ $step['status'] === 'completed' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : ($step['status'] === 'active' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500') }}">
                                    {{ $step['status'] === 'completed' ? 'Completado' : ($step['status'] === 'active' ? 'En curso' : 'Pendiente') }}
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Plantillas demo</h2>
                            <p class="mt-1 text-sm text-slate-500">Mensajes listos para la experiencia inicial.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($templateSamples as $sample)
                            <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4">
                                <div class="text-sm font-semibold text-brand-navy">{{ $sample['title'] }}</div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $sample['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-[linear-gradient(180deg,rgba(16,185,129,0.08),rgba(255,255,255,1)_72%)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Checklist operativo</h2>
                            <p class="mt-1 text-sm text-slate-500">Lo necesario antes de una integración real.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($checklist as $item)
                            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200/70 bg-white p-4">
                                <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-100">✓</span>
                                <p class="text-sm leading-6 text-slate-700">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Resumen visual</h2>
                        <p class="mt-1 text-sm text-slate-500">Elementos que el equipo debería reconocer de inmediato.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Canal</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">WhatsApp</div>
                    </div>
                    <div class="rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4">
                        <div class="text-sm font-medium text-slate-500">Estado</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Preparado</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Modelo</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Demo</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Integración</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Pendiente</div>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Preview del tono</h2>
                        <p class="mt-1 text-sm text-slate-500">La experiencia debe sentirse cercana y comercial.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div class="rounded-[1.5rem] rounded-bl-md border border-emerald-200/80 bg-emerald-50/70 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Bienvenida</div>
                        <p class="mt-2 text-sm leading-6 text-slate-700">
                            Hola, gracias por escribir a Benditio. Este es el punto de entrada para pedidos y consultas en WhatsApp Business.
                        </p>
                    </div>
                    <div class="rounded-[1.5rem] rounded-br-md border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Siguiente paso</div>
                        <p class="mt-2 text-sm leading-6 text-slate-700">
                            Cuando el canal esté listo, el equipo podrá activar plantillas reales y conectar el backend sin rehacer la experiencia.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
