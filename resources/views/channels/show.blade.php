<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(14,165,233,0.12),rgba(22,163,74,0.08)_46%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Channel Detail
                    </div>

                    <div class="flex items-start gap-4">
                        @php
                            $logoClasses = match ($providerLogo['accent']) {
                                'blue' => 'bg-blue-50 text-blue-600 ring-blue-100',
                                'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
                                'rose' => 'bg-rose-50 text-rose-600 ring-rose-100',
                                default => 'bg-slate-50 text-slate-600 ring-slate-100',
                            };
                        @endphp
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl ring-1 ring-inset {{ $logoClasses }}">
                            <svg viewBox="0 0 24 24" fill="none" class="h-8 w-8" aria-hidden="true">
                                <path d="{{ $providerLogo['path'] }}" fill="currentColor" />
                            </svg>
                        </div>

                        <div class="space-y-3">
                            <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                                {{ $providerLabel }}
                            </h1>
                            <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                                {{ $providerDescription }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                                    Status: {{ $health->status }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                                    Version: {{ $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1' }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                                    Webhook: {{ $health->webhook_status }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                                    Credentials: {{ $health->credentials_status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.index') }}" class="brand-btn-secondary justify-center">
                            Back to Hub
                        </a>
                        <form method="POST" action="{{ route('channels.health-check', $provider) }}">
                            @csrf
                            <button type="submit" class="brand-btn-primary w-full justify-center sm:w-auto">
                                Health Check
                            </button>
                        </form>
                        <form method="POST" action="{{ route('channels.disconnect', $provider) }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center sm:w-auto">
                                Disconnect
                            </button>
                        </form>
                        <form method="POST" action="{{ route('channels.connect', $provider) }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center sm:w-auto">
                                Reconnect
                            </button>
                        </form>
                        <a href="{{ $provider === 'whatsapp' ? route('channels.whatsapp.configuration') : route('channels.show', $provider) . '#connection' }}" class="brand-btn-secondary justify-center">
                            Configure
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach ($overview as $item)
                        <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $item['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $item['value'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div id="connection" class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Configuration</h2>
                        <p class="mt-1 text-sm text-slate-500">Connection data, metadata and validation.</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        {{ $connection ? 'Persisted' : 'No connection yet' }}
                    </span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Display name</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->display_name ?? 'Sin dato' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Phone number</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->phone_number ?? 'Sin dato' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Provider version</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->provider_version ?? $connection?->version ?? $health->version ?? 'v1' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Health status</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->health_status ?? $health->status }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Webhook status</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->webhook_status ?? $health->webhook_status }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Credentials status</div>
                        <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $connection?->credentials_status ?? $health->credentials_status }}</div>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Metadata</div>
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-4 text-xs leading-6 text-slate-600">{{ json_encode($connection?->metadata_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4">
                    <div class="text-sm font-medium text-slate-500">Validation</div>
                    @php
                        $validationState = $validation instanceof \App\Services\Messaging\DTO\ProviderHealth
                            ? $validation->status
                            : ($validation->valid ? 'valid' : 'invalid');
                    @endphp
                    <div class="mt-1 text-lg font-semibold text-brand-navy">{{ \Illuminate\Support\Str::headline($validationState) }}</div>
                    @if ($validationErrors !== [])
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            @foreach ($validationErrors as $error)
                                <li class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($validationWarnings !== [])
                        <ul class="mt-3 space-y-2 text-sm text-amber-700">
                            @foreach ($validationWarnings as $warning)
                                <li class="rounded-xl bg-amber-50 px-3 py-2 ring-1 ring-inset ring-amber-100">{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div id="health" class="rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(180deg,rgba(22,163,74,0.08),rgba(255,255,255,1)_72%)] p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Health</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest lifecycle snapshot from the provider manager.</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-navy ring-1 ring-inset ring-slate-200">
                        {{ $health->status }}
                    </span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Connected</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $health->connected ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Healthy</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $health->healthy ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Last Health Check</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $connection?->last_health_check_at?->format('d/m/Y H:i') ?? $health->last_health_check_at?->format('d/m/Y H:i') ?? 'Sin dato' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Latency</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $health->latency_ms !== null ? $health->latency_ms . ' ms' : 'Sin dato' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Webhook</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $health->webhook_status }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Credentials</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $health->credentials_status }}</div>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4">
                    <div class="text-sm font-medium text-slate-500">Recent Errors</div>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600">
                        @foreach ($recentErrors as $error)
                            <li class="rounded-xl bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Capabilities</h2>
                        <p class="mt-1 text-sm text-slate-500">The provider advertises the same capability surface across channels.</p>
                    </div>
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        DTO-backed
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($capabilityItems as $item)
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-slate-50/70 px-4 py-3">
                            <div class="text-sm font-medium text-brand-navy">{{ $item['label'] }}</div>
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-full {{ $item['enabled'] ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100' : 'bg-slate-100 text-slate-400 ring-1 ring-inset ring-slate-200' }}">
                                {!! $item['enabled'] ? '&#10003;' : '&#10007;' !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Webhook & Activity</h2>
                        <p class="mt-1 text-sm text-slate-500">Lifecycle history, recent activity and future readiness.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    @foreach ($recentActivity as $item)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4">
                            <div class="text-sm font-medium text-slate-500">{{ $item['label'] }}</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $item['value'] }}</div>
                            <p class="mt-2 text-sm text-slate-600">{{ $item['detail'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 rounded-2xl border border-blue-200/80 bg-blue-50/60 p-4 text-sm leading-6 text-slate-600">
                    {{ $futureReady['title'] }}: {{ $futureReady['description'] }}
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200/80 bg-white p-4">
                    <div class="text-sm font-medium text-slate-500">Recent Errors</div>
                    <div class="mt-3 space-y-2">
                        @foreach ($recentErrors as $error)
                            <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600 ring-1 ring-inset ring-slate-200">{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
