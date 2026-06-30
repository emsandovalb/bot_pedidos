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

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(14,165,233,0.12),rgba(22,163,74,0.08)_46%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-5">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        WhatsApp Provider Readiness
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Meta Cloud configuration is ready to plug in
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Save your provider credentials locally, validate formats without HTTP requests, and keep the channel prepared for Meta verification.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center">
                            Back to hub
                        </a>
                        <a href="{{ route('channels.whatsapp.status') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            View channel status
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Configuration</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ \Illuminate\Support\Str::headline($statusLabel) }}</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Webhook</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $isReadyForWebhook ? 'Ready' : 'Waiting' }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Validation</p>
                        <p class="mt-2 text-lg font-semibold tracking-tight text-brand-navy">{{ $lastValidationAt?->format('d/m/Y H:i') ?? 'Never' }}</p>
                    </article>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <div class="font-semibold">Fix the highlighted fields before saving.</div>
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
                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Application</h2>
                            <p class="mt-1 text-sm text-slate-500">Store the Meta application values used by the channel.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
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

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Business</h2>
                        <p class="mt-1 text-sm text-slate-500">Keep the business identity attached to this WhatsApp channel.</p>
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
                            <span class="text-sm font-medium text-slate-700">Display Phone</span>
                            <input type="text" name="provider_display_phone" value="{{ $fieldValue('provider_display_phone') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="+502 5555 0101">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Business Name</span>
                            <input type="text" name="provider_business_name" value="{{ $fieldValue('provider_business_name') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Benditio">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Business Country</span>
                            <input type="text" name="provider_business_country" value="{{ $fieldValue('provider_business_country') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm uppercase tracking-[0.12em] shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="GT">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700">Business Timezone</span>
                            <input type="text" name="provider_business_timezone" value="{{ $fieldValue('provider_business_timezone') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="America/Guatemala">
                        </label>
                    </div>
                </section>

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">API</h2>
                        <p class="mt-1 text-sm text-slate-500">Version and token values are stored encrypted when applicable.</p>
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
                            <span class="text-sm font-medium text-slate-700">API Version</span>
                            <input type="text" name="provider_api_version" value="{{ $fieldValue('provider_api_version') }}" class="mt-2 w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="v21.0">
                        </label>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(180deg,rgba(16,185,129,0.08),rgba(255,255,255,1)_76%)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Validation</h2>
                            <p class="mt-1 text-sm text-slate-500">Local checks only. No Meta HTTP requests are sent.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ \Illuminate\Support\Str::headline($statusLabel) }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Webhook</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $isReadyForWebhook ? 'Ready' : 'Waiting' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last Validation</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $lastValidationAt?->format('d/m/Y H:i') ?? 'Never' }}</div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Last Error</div>
                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $lastValidationError ?? 'None' }}</div>
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

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Security</h2>
                            <p class="mt-1 text-sm text-slate-500">Secrets remain encrypted at rest and are shown only as masked previews.</p>
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

                <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-navy">Actions</h2>
                            <p class="mt-1 text-sm text-slate-500">Validate or save the current provider state.</p>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3">
                        <button type="submit" name="action" value="validate" class="brand-btn-secondary justify-center">
                            Validate Configuration
                        </button>
                        <button type="submit" name="action" value="save" class="brand-btn-primary justify-center">
                            Save
                        </button>
                        <button type="reset" class="brand-btn-secondary justify-center">
                            Reset
                        </button>
                    </div>
                </section>
            </aside>
        </form>
    </div>
</x-app-layout>
