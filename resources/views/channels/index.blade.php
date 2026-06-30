<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(14,165,233,0.12),rgba(22,163,74,0.08)_46%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-5">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Channels Hub
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Benditio is now provider-first
                        </h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Telegram is live. WhatsApp and Instagram use the same lifecycle surface so every new provider can plug in with one class.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('channels.show', 'telegram') }}" class="brand-btn-primary justify-center">
                            Open Telegram
                        </a>
                        <a href="{{ route('channels.show', 'whatsapp') }}" class="brand-btn-secondary justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Inspect WhatsApp
                        </a>
                        <a href="{{ route('channels.show', 'instagram') }}" class="brand-btn-secondary justify-center">
                            View Instagram
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <article class="rounded-2xl border border-slate-200/80 bg-white/95 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Providers</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['providers'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Registered in the messaging registry.</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Connected</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['connected'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Providers with an active channel record.</p>
                    </article>
                    <article class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Attention</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">{{ $hubSummary['warning'] + $hubSummary['unknown'] + $hubSummary['coming_soon'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Providers still in placeholder or warning mode.</p>
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
                <article class="rounded-[2rem] border {{ $cardBorder }} bg-white p-6 shadow-sm transition hover:-translate-y-0.5">
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
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Health</div>
                            <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $card['health_label'] }}</div>
                            <div class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[0.7rem] font-semibold uppercase tracking-[0.12em] {{ $badgeBg }}">
                                {{ \Illuminate\Support\Str::headline($card['webhook_status']) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Version</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['version'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Credentials</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['credentials_status'] }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last received</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_received_message_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last sent</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_sent_message_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last health check</div>
                            <div class="mt-1 text-lg font-semibold text-brand-navy">{{ $card['last_health_check_at']?->format('d/m H:i') ?? 'Sin dato' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 sm:col-span-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Last verification</div>
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
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                                {{ $feature }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <a href="{{ $card['route'] }}" class="brand-btn-primary justify-center">
                            View Details
                        </a>
                        <form method="POST" action="{{ $card['health_check_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Health Check
                            </button>
                        </form>
                        <form method="POST" action="{{ $card['disconnect_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Disconnect
                            </button>
                        </form>
                        <form method="POST" action="{{ $card['connect_route'] }}">
                            @csrf
                            <button type="submit" class="brand-btn-secondary w-full justify-center">
                                Reconnect
                            </button>
                        </form>
                        @if ($card['verify_endpoint_route'] !== null)
                            <form method="POST" action="{{ $card['verify_endpoint_route'] }}">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                                    Verify Endpoint
                                </button>
                            </form>
                        @endif
                        <a href="{{ $card['configure_route'] }}" class="brand-btn-secondary justify-center">
                            Configure
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Lifecycle stance</h2>
                        <p class="mt-1 text-sm text-slate-500">Every provider exposes the same lifecycle surface.</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100">
                        Provider-agnostic
                    </span>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/60 p-4">
                        <div class="text-sm font-medium text-slate-500">Health</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Visible</div>
                        <p class="mt-2 text-sm text-slate-600">Each provider reports health, credentials and webhook readiness.</p>
                    </div>
                    <div class="rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4">
                        <div class="text-sm font-medium text-slate-500">Capabilities</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Explicit</div>
                        <p class="mt-2 text-sm text-slate-600">Send, receive, buttons, files and reactions are surfaced per provider.</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 p-4">
                        <div class="text-sm font-medium text-slate-500">Unknown providers</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">Safe</div>
                        <p class="mt-2 text-sm text-slate-600">Unsupported slugs resolve to harmless placeholder DTOs.</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/70 bg-white p-4">
                        <div class="text-sm font-medium text-slate-500">Roadmap</div>
                        <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">New class only</div>
                        <p class="mt-2 text-sm text-slate-600">New providers should plug in by implementing their provider class.</p>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(180deg,rgba(14,165,233,0.08),rgba(255,255,255,1)_68%)] p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy">Legacy access</h2>
                        <p class="mt-1 text-sm text-slate-500">WhatsApp setup stays available while the shared lifecycle lands.</p>
                    </div>
                </div>

                <div class="mt-6 rounded-3xl border border-blue-200/80 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-brand-primary ring-1 ring-inset ring-blue-100">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-brand-navy">WhatsApp Business</div>
                            <div class="text-sm text-slate-500">Onboarding and provider detail now share the same architecture.</div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <a href="{{ route('channels.whatsapp') }}" class="brand-btn-primary w-full justify-center">
                            Open WhatsApp setup
                        </a>
                        <a href="{{ route('channels.whatsapp.configuration') }}" class="brand-btn-secondary w-full justify-center border-emerald-200 text-emerald-800 hover:border-emerald-300 hover:text-emerald-900">
                            Open WhatsApp configuration
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
