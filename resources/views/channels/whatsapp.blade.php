<x-app-layout>
    @php
        $metadata = $metadata ?? [];
        $readiness = $readiness ?? ['percentage' => 0, 'completed_required' => 0, 'required_total' => 4, 'status' => 'draft', 'assisted_setup' => false];
        $statusLabel = $statusLabel ?? 'Borrador';
        $displayNameValue = old('display_name', $connection?->display_name);
        $phoneNumberValue = old('phone_number', $connection?->phone_number);
        $businessCategoryValue = old('business_category', data_get($metadata, 'business_category'));
        $expectedMonthlyOrdersValue = old('expected_monthly_orders', data_get($metadata, 'expected_monthly_orders'));
        $notesValue = old('notes', data_get($metadata, 'notes'));
        $readinessPercentage = (int) ($readiness['percentage'] ?? 0);
        $requiredTotal = (int) ($readiness['required_total'] ?? 4);
        $requiredCompleted = (int) ($readiness['completed_required'] ?? 0);
        $requiredItems = [
            'has_whatsapp_business' => (bool) data_get($metadata, 'has_whatsapp_business'),
            'has_dedicated_number' => (bool) data_get($metadata, 'has_dedicated_number'),
            'has_facebook_access' => (bool) data_get($metadata, 'has_facebook_access'),
            'has_meta_business' => (bool) data_get($metadata, 'has_meta_business'),
            'needs_assisted_setup' => (bool) data_get($metadata, 'needs_assisted_setup'),
        ];
        $readyForAssistedSetup = $readinessPercentage === 100 && $statusLabel !== 'Conectado';
    @endphp

    <div class="space-y-8">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <div class="font-semibold">Revisa los campos marcados.</div>
                <ul class="mt-2 space-y-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(22,163,74,0.14),rgba(20,110,219,0.08)_46%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                <div class="space-y-5">
                    <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Configuracion de WhatsApp Business
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Onboarding real para dejar listo el canal
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Completa el checklist, registra el numero y deja guardada la preparacion del negocio en
                            <span class="font-semibold text-brand-navy">ChannelConnection.metadata_json</span> sin tocar la integracion de Meta todavia.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Volver al hub
                        </a>
                        <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-secondary justify-center">
                            Ver estado del canal
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Estado</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $statusLabel }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Preparacion</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $readinessPercentage }}%</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Canal</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $connection?->display_name ?? 'WhatsApp' }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy">Progreso del onboarding</h2>
                    <p class="mt-1 text-sm text-slate-500">Cada paso refleja el estado actual del setup antes de integrar Meta.</p>
                </div>
                <div class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                    {{ $requiredCompleted }}/{{ $requiredTotal }} requisitos completados
                </div>
            </div>

            <div class="mt-6 h-3 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-[linear-gradient(90deg,#16a34a,#146edb)] transition-all" style="width: {{ $readinessPercentage }}%"></div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-5">
                @foreach ($stepper as $step)
                    @php
                        $stepStyles = match ($step['status']) {
                            'completed' => 'border-emerald-200 bg-emerald-50/80 text-emerald-800',
                            'active' => 'border-brand-primary bg-blue-50/80 text-brand-navy',
                            default => 'border-slate-200 bg-slate-50 text-slate-600',
                        };
                    @endphp
                    <div class="rounded-2xl border p-4 {{ $stepStyles }}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/80 text-sm font-semibold ring-1 ring-inset ring-black/5">
                                {{ $step['index'] }}
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-[0.14em]">
                                {{ $step['status'] === 'completed' ? 'Completado' : ($step['status'] === 'active' ? 'Activo' : 'Pendiente') }}
                            </span>
                        </div>
                        <h3 class="mt-4 text-sm font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-1 text-sm leading-6">{{ $step['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('channels.whatsapp.onboarding.update') }}" class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            @csrf

            <div class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Requisitos</h2>
                            <p class="mt-1 text-sm text-slate-500">Checklist base para evaluar la madurez del canal.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        @foreach ($checklistItems as $item)
                            <label class="group flex cursor-pointer gap-4 rounded-3xl border p-4 transition {{ $requiredItems[$item['key']] ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50/60 hover:border-slate-300' }}">
                                <input type="checkbox" name="{{ $item['key'] }}" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked(old($item['key'], $requiredItems[$item['key']]))>
                                <div>
                                    <div class="text-sm font-semibold text-brand-navy">{{ $item['label'] }}</div>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $item['description'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Numero del negocio</h2>
                            <p class="mt-1 text-sm text-slate-500">Guarda el nombre visible y el numero que usara el canal.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Nombre visible</span>
                            <input
                                type="text"
                                name="display_name"
                                value="{{ $displayNameValue }}"
                                class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Benditio Pedidos"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Telefono</span>
                            <input
                                type="text"
                                name="phone_number"
                                value="{{ $phoneNumberValue }}"
                                class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="+502 5555 0101"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Categoria del negocio</span>
                            <input
                                type="text"
                                name="business_category"
                                value="{{ $businessCategoryValue }}"
                                class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Restaurante, retail, servicios..."
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Pedidos mensuales esperados</span>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                name="expected_monthly_orders"
                                value="{{ $expectedMonthlyOrdersValue }}"
                                class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="120"
                            >
                        </label>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Acceso a Meta/Facebook</h2>
                            <p class="mt-1 text-sm text-slate-500">Confirma si el equipo ya puede continuar con la validacion externa.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <label class="flex items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                            <input type="checkbox" name="has_facebook_access" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked(old('has_facebook_access', $requiredItems['has_facebook_access']))>
                            <span>
                                <span class="block text-sm font-semibold text-brand-navy">Tengo acceso a Facebook/Meta</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-600">Necesario para entrar al panel de negocio y revisar activos.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                            <input type="checkbox" name="has_meta_business" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked(old('has_meta_business', $requiredItems['has_meta_business']))>
                            <span>
                                <span class="block text-sm font-semibold text-brand-navy">Tengo o puedo crear Meta Business Manager</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-600">Permite administrar permisos, activos y configuracion del negocio.</span>
                            </span>
                        </label>

                        <div class="rounded-3xl border border-blue-200 bg-blue-50/70 p-4">
                            <div class="text-sm font-semibold text-brand-navy">Estado de Meta</div>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                Si marcas las cuatro condiciones requeridas, el canal quedara como <span class="font-semibold text-brand-navy">Listo para configuracion asistida</span> sin cambiar la integracion real.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Onboarding asistido</h2>
                            <p class="mt-1 text-sm text-slate-500">Deja notas operativas para el equipo que va a seguir con el setup.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <label class="flex items-start gap-3 rounded-3xl border border-slate-200 bg-slate-50/70 p-4">
                            <input type="checkbox" name="needs_assisted_setup" value="1" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" @checked(old('needs_assisted_setup', $requiredItems['needs_assisted_setup']))>
                            <span>
                                <span class="block text-sm font-semibold text-brand-navy">Necesito configuracion asistida</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-600">Marca esto si el equipo tecnico debe continuar contigo.</span>
                            </span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Notas</span>
                            <textarea
                                name="notes"
                                rows="5"
                                class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                placeholder="Describe restricciones, horarios, dudas o pasos pendientes."
                            >{{ $notesValue }}</textarea>
                        </label>
                    </div>
                </section>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="submit" class="brand-btn-primary justify-center">
                        Guardar configuracion
                    </button>
                    <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-secondary justify-center">
                        Ver estado del canal
                    </a>
                </div>
            </div>

            <aside class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-[linear-gradient(180deg,rgba(16,185,129,0.08),rgba(255,255,255,1)_74%)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Resumen de preparacion</h2>
                            <p class="mt-1 text-sm text-slate-500">Vista final para decidir si el canal esta listo para seguir con el equipo.</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-end gap-4">
                        <div class="text-5xl font-semibold tracking-tight text-brand-navy">{{ $readinessPercentage }}%</div>
                        <div class="pb-1 text-sm text-slate-500">readiness</div>
                    </div>

                    <div class="mt-5 rounded-3xl border border-emerald-200 bg-white p-4">
                        <div class="text-sm font-semibold text-brand-navy">Estado calculado</div>
                        <div class="mt-1 text-sm text-slate-600">{{ $statusLabel }}</div>
                    </div>

                    @if ($readyForAssistedSetup)
                        <div class="mt-4 rounded-3xl border border-emerald-200 bg-emerald-50/70 p-4 text-sm font-semibold text-emerald-800">
                            Listo para configuracion asistida
                        </div>
                    @endif

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Conexion</div>
                            <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $connection?->display_name ?? 'Pendiente' }}</div>
                            <div class="mt-1 text-sm text-slate-600">{{ $connection?->phone_number ?? 'Sin numero registrado' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Categoria</div>
                            <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $businessCategoryValue ?: 'Sin definir' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pedidos mensuales esperados</div>
                            <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $expectedMonthlyOrdersValue !== null && $expectedMonthlyOrdersValue !== '' ? $expectedMonthlyOrdersValue : 'Sin definir' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Checklist persistido</h2>
                            <p class="mt-1 text-sm text-slate-500">Los datos se guardan en metadata_json sin borrar claves ajenas.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ([
                            'has_whatsapp_business' => 'WhatsApp Business',
                            'has_dedicated_number' => 'Numero exclusivo',
                            'has_facebook_access' => 'Acceso Meta/Facebook',
                            'has_meta_business' => 'Meta Business Manager',
                            'needs_assisted_setup' => 'Configuracion asistida',
                        ] as $key => $label)
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                                <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $requiredItems[$key] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $requiredItems[$key] ? 'Si' : 'No' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </form>
    </div>
</x-app-layout>
