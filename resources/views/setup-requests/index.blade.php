<x-app-layout>
    <div class="space-y-8">
        @php
            $statusLabels = $statusLabels ?? [];
            $statusClasses = $statusClasses ?? [];
        @endphp

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(245,158,11,0.14),rgba(20,110,219,0.08)_42%,rgba(255,255,255,1)_82%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 ring-1 ring-inset ring-amber-100">
                        Support
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Centro de configuraciones asistidas
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Gestiona las solicitudes de onboarding manual para WhatsApp con una vista operativa, limpia y lista para escalar.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <a href="{{ route('channels.whatsapp') }}" class="rounded-2xl border border-amber-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Accion rapida</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">Abrir wizard de WhatsApp</div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Regresa al flujo de onboarding para solicitar ayuda o completar datos.</p>
                    </a>
                    <a href="{{ route('channels.whatsapp.status') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-primary/30">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Estado del canal</div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">WhatsApp</div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Consulta la lectura operativa del canal con contexto de soporte.</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics as $metric)
                <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-slate-500">{{ $metric['label'] }}</div>
                            <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $metric['value'] }}</div>
                            <p class="mt-1 text-sm text-slate-600">{{ $metric['detail'] }}</p>
                        </div>
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl {{ $metric['tone'] === 'amber' ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100' : ($metric['tone'] === 'blue' ? 'bg-blue-50 text-brand-primary ring-1 ring-inset ring-blue-100' : ($metric['tone'] === 'emerald' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200')) }}">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M4 12h16M12 4v16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </span>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy">Solicitudes</h2>
                    <p class="mt-1 text-sm text-slate-500">Listado operativo con estado, empresa, contacto y responsable.</p>
                </div>
                <a href="{{ route('channels.whatsapp') }}" class="brand-btn-secondary justify-center">
                    Nueva solicitud
                </a>
            </div>

            <div class="mt-6 overflow-hidden rounded-[1.75rem] border border-slate-200/70">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Estado</th>
                                <th class="px-5 py-4">Empresa</th>
                                <th class="px-5 py-4">Contacto</th>
                                <th class="px-5 py-4">Telefono</th>
                                <th class="px-5 py-4">Solicitado</th>
                                <th class="px-5 py-4">Canal</th>
                                <th class="px-5 py-4">Asignado</th>
                                <th class="px-5 py-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($requests as $request)
                                @php
                                    $statusLabel = $statusLabels[$request->status] ?? $request->status;
                                    $statusClass = $statusClasses[$request->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
                                @endphp
                                <tr class="align-top transition hover:bg-slate-50/70">
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-brand-navy">{{ $request->organization?->name ?? 'Sin empresa' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">#{{ $request->id }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <div class="font-medium text-brand-navy">{{ $request->contact_name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $request->contact_email ?? 'Sin email' }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $request->contact_phone }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $request->requested_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <div class="font-medium text-brand-navy">{{ $request->channelConnection?->display_name ?? 'WhatsApp' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $request->channelConnection?->phone_number ?? 'Sin canal' }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">
                                        {{ $request->assignedTo?->name ?? 'Sin asignar' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('setup-requests.show', $request) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                                            Abrir
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-sm text-slate-500">
                                        No hay solicitudes de configuracion asistida.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                {{ $requests->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
