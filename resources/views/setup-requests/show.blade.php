<x-app-layout>
    <div class="space-y-8">
        @php
            $statusLabel = $statusLabels[$setupRequest->status] ?? $setupRequest->status;
            $statusClass = $statusClasses[$setupRequest->status] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
            $currentAssigneeId = old('assigned_to', $setupRequest->assigned_to);
            $currentStatus = old('status', $setupRequest->status);
        @endphp

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.12),rgba(245,158,11,0.10)_46%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Configuracion WhatsApp
                    </div>
                    <div class="space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Configuracion WhatsApp</h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Gestion operativa de una solicitud asistida para preparar el canal sin tocar la integracion final.
                        </p>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200/80 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Estado</p>
                            <p class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $statusLabel }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $setupRequest->requested_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</p>
                        </div>
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl {{ $statusClass }}">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M9 12l2 2 4-4M20 12a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Empresa</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $setupRequest->organization?->name ?? 'Sin empresa' }}</div>
                <p class="mt-2 text-sm leading-6 text-slate-600">Solicitud creada para la organizacion activa.</p>
            </article>
            <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Contacto</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $setupRequest->contact_name }}</div>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $setupRequest->contact_email ?? 'Sin email' }}</p>
            </article>
            <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Checklist completado</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $progress['percentage'] }}%</div>
                <div class="mt-3 space-y-2">
                    @foreach ($checklist as $item)
                        <div class="flex items-center gap-3 text-sm {{ $item['done'] ? 'text-emerald-700' : 'text-slate-500' }}">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full {{ $item['done'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $item['done'] ? '✓' : '•' }}
                            </span>
                            <span>{{ $item['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="rounded-3xl border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Datos del canal</div>
                <div class="mt-2 text-xl font-semibold text-brand-navy">{{ $setupRequest->channelConnection?->display_name ?? 'WhatsApp' }}</div>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $setupRequest->channelConnection?->phone_number ?? 'Sin numero asociado' }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-6">
                <article class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Notas</h2>
                            <p class="mt-1 text-sm text-slate-500">Contexto operativo para el equipo que ejecuta la configuracion.</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50/70 p-5 text-sm leading-7 text-slate-700">
                        {{ $setupRequest->notes ?: 'Sin notas registradas.' }}
                    </div>
                </article>

                <article class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Timeline</h2>
                            <p class="mt-1 text-sm text-slate-500">Secuencia de la solicitud desde que se abrio hasta su cierre.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ($timeline as $item)
                            <article class="rounded-2xl border border-slate-200/70 bg-slate-50/70 p-4">
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
                </article>
            </div>

            <aside class="space-y-6">
                <form method="POST" action="{{ route('setup-requests.update', $setupRequest) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <article class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-brand-navy">Acciones</h2>
                                <p class="mt-1 text-sm text-slate-500">Programa, inicia o cierra la solicitud desde aqui.</p>
                            </div>
                        </div>

                        <div class="mt-5 space-y-4">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Estado</span>
                                <select name="status" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    @foreach ($statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Asignado</span>
                                <select name="assigned_to" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Sin asignar</option>
                                    @foreach ($assignedUsers as $user)
                                        <option value="{{ $user->id }}" @selected((string) $currentAssigneeId === (string) $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Inicio</span>
                                <input type="datetime-local" name="started_at" value="{{ old('started_at', optional($setupRequest->started_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Completado</span>
                                <input type="datetime-local" name="completed_at" value="{{ old('completed_at', optional($setupRequest->completed_at)->format('Y-m-d\TH:i')) }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Notas</span>
                                <textarea name="notes" rows="5" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $setupRequest->notes) }}</textarea>
                            </label>
                        </div>

                        <div class="mt-5 grid gap-3">
                            <button type="submit" class="brand-btn-secondary justify-center">Guardar cambios</button>
                            <button type="submit" name="transition_action" value="schedule" class="brand-btn-secondary justify-center">Programar</button>
                            <button type="submit" name="transition_action" value="start" class="brand-btn-primary justify-center">Iniciar configuracion</button>
                            <button type="submit" name="transition_action" value="complete" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-100">Marcar completado</button>
                            <button type="submit" name="transition_action" value="cancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300">Cancelar</button>
                        </div>
                    </article>
                </form>

                <article class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-amber-500 bg-[linear-gradient(180deg,rgba(245,158,11,0.08),rgba(255,255,255,1)_72%)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Lectura rapida</h2>
                            <p class="mt-1 text-sm text-slate-500">Resumen operativo para soporte.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 text-sm text-slate-600">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Asignado a</div>
                            <div class="mt-1 font-semibold text-brand-navy">{{ $setupRequest->assignedTo?->name ?? 'Sin asignar' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Contacto preferido</div>
                            <div class="mt-1 font-semibold text-brand-navy">{{ $setupRequest->preferred_contact_time ?? 'Sin definir' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tipo</div>
                            <div class="mt-1 font-semibold text-brand-navy">{{ $setupRequest->type }}</div>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>
</x-app-layout>
