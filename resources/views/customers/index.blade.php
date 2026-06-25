<x-app-layout>
    <div class="space-y-8">
        @php
            $channelLabels = [
                'whatsapp' => 'WhatsApp',
                'telegram' => 'Telegram',
                'instagram' => 'Instagram',
                'messenger' => 'Messenger',
                'email' => 'Email',
                'sms' => 'SMS',
                'web' => 'Web',
            ];

            $currentSearch = $filters['search'] ?? '';
        @endphp

        <section class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.06)_38%,rgba(255,255,255,1)_82%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.4)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio Customer Identity
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Clientes</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Unifica clientes y canales para entender quién compra en tu negocio.
                    </p>
                </div>

                <form method="GET" action="{{ route('customers.index') }}" class="flex w-full max-w-xl flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <label for="search" class="sr-only">Buscar cliente</label>
                        <input
                            id="search"
                            name="search"
                            value="{{ $currentSearch }}"
                            placeholder="Buscar por nombre, teléfono o usuario"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-brand-primary focus:ring-4 focus:ring-brand-primary/10"
                        >
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                            Buscar
                        </button>
                        @if ($currentSearch !== '')
                            <a href="{{ route('customers.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50">
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Clientes registrados</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $registeredCustomersCount }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Clientes con múltiples canales</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $multiChannelCustomersCount }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Clientes activos hoy</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $activeTodayCount }}</div>
            </div>

            <div class="rounded-[24px] border border-slate-200/70 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Identidades registradas</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $registeredIdentitiesCount }}</div>
            </div>
        </section>

        <section class="space-y-4">
            @forelse ($customers as $customer)
                @php
                    $displayName = $customer->name ?: $customer->phone ?: $customer->external_id ?: 'Cliente sin nombre';
                    $latestActivityAt = $customer->latest_activity_at ? \Illuminate\Support\Carbon::parse($customer->latest_activity_at) : null;
                    $channels = $customer->customerIdentities
                        ->pluck('provider')
                        ->filter()
                        ->unique()
                        ->values()
                        ->map(fn ($provider) => $channelLabels[$provider] ?? \Illuminate\Support\Str::headline((string) $provider));
                @endphp

                <article class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-[0_18px_50px_-32px_rgba(15,23,42,0.34)] transition hover:-translate-y-0.5 hover:shadow-[0_24px_60px_-34px_rgba(15,23,42,0.4)]">
                    <div class="h-1 bg-gradient-to-r from-[#146EDB] to-emerald-500"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1 space-y-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div>
                                        <h2 class="text-xl font-semibold text-brand-navy">{{ $displayName }}</h2>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ $customer->phone ?? 'Sin teléfono' }}
                                            @if ($customer->external_id)
                                                · {{ $customer->external_id }}
                                            @endif
                                        </p>
                                    </div>

                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                        #{{ $customer->id }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @forelse ($channels as $channel)
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                                            {{ $channel }}
                                        </span>
                                    @empty
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                            Sin canal registrado
                                        </span>
                                    @endforelse
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Total de pedidos</div>
                                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $customer->orders_count }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Identidades</div>
                                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $customer->customer_identities_count }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Última actividad</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $latestActivityAt?->format('d/m/Y H:i') ?? 'Sin actividad' }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Canales</div>
                                        <div class="mt-1 text-2xl font-semibold text-brand-navy">{{ $channels->count() }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col gap-3 lg:w-52">
                                <a href="{{ route('customers.show', $customer) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                    Ver cliente
                                </a>
                                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                                    Vista unificada de pedidos, canales e identidades.
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[28px] border border-dashed border-blue-200 bg-blue-50/70 px-6 py-14 text-center shadow-sm">
                    <div class="mx-auto max-w-md">
                        <h2 class="text-lg font-semibold text-brand-navy">Aún no hay clientes.</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Los clientes aparecerán cuando Benditio reciba pedidos.</p>
                    </div>
                </div>
            @endforelse
        </section>

        <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            {{ $customers->links() }}
        </div>
    </div>
</x-app-layout>
