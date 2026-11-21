<x-app-layout>
    @php
        $configuration = $configuration ?? [];
        $validationErrors = $validationErrors ?? ($validation->errors ?? []);
        $validationWarnings = $validationWarnings ?? ($validation->warnings ?? []);
        $statusLabel = $configuration['provider_configuration_status'] ?? 'draft';
        $lastValidationAt = $configuration['provider_last_validation_at'] ?? null;
        $lastValidationError = $configuration['provider_last_validation_error'] ?? null;
        $maskedSecretSuffix = fn (?string $value): string => $value !== null && $value !== '' ? $value : 'No secret stored';
        $fieldValue = fn (string $key, mixed $fallback = null) => old($key, $configuration[$key] ?? $fallback);
        $secretValue = fn (string $key) => old($key, '');
    @endphp

    <div class="space-y-8">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <section class="page-intro">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-5">
                    <p class="page-eyebrow">Preparación de WhatsApp</p>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="page-title mt-1">
                            Configuración de WhatsApp Cloud API
                        </h1>
                        <p class="page-subtitle mt-2">
                            Guarda las credenciales de Meta, revisa que tengan el formato correcto sin conectarte todavía y deja el canal listo para la verificación de Meta.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center">
                            Volver a canales
                        </a>
                        <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Ver estado del canal
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Configuración</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ \Illuminate\Support\Str::headline($statusLabel) }}</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Webhook</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $isReadyForWebhook ? 'Listo' : 'En espera' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Validación</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $lastValidationAt?->format('d/m/Y H:i') ?? 'Nunca' }}</p>
                    </article>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <div class="font-semibold">Corrige los campos marcados antes de guardar.</div>
                <ul class="mt-2 space-y-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('channels.whatsapp.configuration.save') }}" class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            @csrf

            <div class="space-y-6">
                <section class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Aplicación</h2>
                            <p class="mt-1 text-sm text-slate-500">Los datos de la aplicación de Meta que usa el canal.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary">
                            Encrypted storage
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">App ID</span>
                            <input type="text" name="provider_app_id" value="{{ $fieldValue('provider_app_id') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="123456789012345">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">App Secret</span>
                            <input type="password" name="provider_app_secret" value="{{ $secretValue('provider_app_secret') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Leave blank to keep current secret">
                            <p class="mt-2 text-xs text-slate-500">Current: {{ $maskedSecretSuffix($configuration['provider_app_secret'] ?? null) }}</p>
                        </label>
                    </div>
                </section>

                <section class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Negocio</h2>
                        <p class="mt-1 text-sm text-slate-500">La identidad del negocio asociada a este canal de WhatsApp.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Business Account ID</span>
                            <input type="text" name="provider_business_account_id" value="{{ $fieldValue('provider_business_account_id') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="123456789012345">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Phone Number ID</span>
                            <input type="text" name="provider_phone_number_id" value="{{ $fieldValue('provider_phone_number_id') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="123456789012345">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Teléfono visible</span>
                            <input type="text" name="provider_display_phone" value="{{ $fieldValue('provider_display_phone') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="+502 5555 0101">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Nombre del negocio</span>
                            <input type="text" name="provider_business_name" value="{{ $fieldValue('provider_business_name') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Benditio">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">País del negocio</span>
                            <input type="text" name="provider_business_country" value="{{ $fieldValue('provider_business_country') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm uppercase tracking-[0.12em] shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="GT">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Zona horaria del negocio</span>
                            <input type="text" name="provider_business_timezone" value="{{ $fieldValue('provider_business_timezone') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="America/Guatemala">
                        </label>
                    </div>
                </section>

                <section class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">API</h2>
                        <p class="mt-1 text-sm text-slate-500">La versión y los tokens se guardan cifrados cuando corresponde.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Access Token</span>
                            <input type="password" name="provider_access_token" value="{{ $secretValue('provider_access_token') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Leave blank to keep current token">
                            <p class="mt-2 text-xs text-slate-500">Current: {{ $maskedSecretSuffix($configuration['provider_access_token'] ?? null) }}</p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Verify Token</span>
                            <input type="password" name="provider_verify_token" value="{{ $secretValue('provider_verify_token') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Leave blank to keep current verify token">
                            <p class="mt-2 text-xs text-slate-500">Current: {{ $maskedSecretSuffix($configuration['provider_verify_token'] ?? null) }}</p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Webhook Secret</span>
                            <input type="password" name="provider_webhook_secret" value="{{ $secretValue('provider_webhook_secret') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Optional, leave blank to keep current secret">
                            <p class="mt-2 text-xs text-slate-500">Current: {{ $maskedSecretSuffix($configuration['provider_webhook_secret'] ?? null) }}</p>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Versión de la API</span>
                            <input type="text" name="provider_api_version" value="{{ $fieldValue('provider_api_version') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="v21.0">
                        </label>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-[20px] border border-slate-200/70 surface-tint-green p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Validación</h2>
                            <p class="mt-1 text-sm text-slate-500">Solo se revisa el formato aquí; no se envía nada a Meta.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Estado</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ \Illuminate\Support\Str::headline($statusLabel) }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Webhook</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $isReadyForWebhook ? 'Listo' : 'En espera' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Última validación</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $lastValidationAt?->format('d/m/Y H:i') ?? 'Nunca' }}</div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Último error</div>
                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $lastValidationError ?? 'Ninguno' }}</div>
                    </div>

                    @if ($validationErrors !== [])
                        <div class="mt-5 space-y-2">
                            @foreach ($validationErrors as $error)
                                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    @if ($validationWarnings !== [])
                        <div class="mt-5 space-y-2">
                            @foreach ($validationWarnings as $warning)
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Seguridad</h2>
                            <p class="mt-1 text-sm text-slate-500">Los secretos se guardan cifrados y aquí solo se muestran ocultos.</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach ([
                            'provider_app_secret' => 'App Secret',
                            'provider_access_token' => 'Access Token',
                            'provider_verify_token' => 'Verify Token',
                            'provider_webhook_secret' => 'Webhook Secret',
                        ] as $key => $label)
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                                <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                                <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                                    {{ $maskedSecretSuffix($configuration[$key] ?? null) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-[20px] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Acciones</h2>
                            <p class="mt-1 text-sm text-slate-500">Valida o guarda la configuración actual.</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3">
                        <button type="submit" name="action" value="validate" class="brand-btn-secondary justify-center">
                            Validar configuración
                        </button>
                        <button type="submit" name="action" value="save" class="brand-btn-primary justify-center">
                            Guardar
                        </button>
                        <button type="reset" class="brand-btn-secondary justify-center">
                            Deshacer cambios
                        </button>
                    </div>
                </section>
            </aside>
        </form>
    </div>
</x-app-layout>
