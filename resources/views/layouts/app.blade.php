<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Benditio') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        @php($hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div x-data="{ sidebarOpen: false }" class="brand-shell min-h-screen">
            <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,rgba(20,110,219,0.16),transparent_68%)]"></div>
            <div class="relative flex min-h-screen">
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden"
                    @click="sidebarOpen = false"
                ></div>

                <aside
                    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200/80 bg-white text-slate-900 shadow-[0_24px_60px_-28px_rgba(15,23,42,0.18)] transition-transform duration-200 ease-in-out lg:translate-x-0"
                    :class="{ 'translate-x-0': sidebarOpen }"
                >
                    <div class="px-5 pt-5">
                        <div class="rounded-[1.75rem] border border-slate-200/80 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] px-5 py-4 shadow-sm">
                            <a href="{{ route('dashboard') }}" class="block">
                                <x-application-logo class="h-20 w-full max-w-[240px]" />
                            </a>
                        </div>
                    </div>

                    <nav class="flex-1 space-y-1 px-4 py-5">
                        @foreach ([
                            ['label' => 'Panel', 'href' => 'dashboard', 'active' => 'dashboard', 'icon' => 'M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-8.5Z'],
                            ['label' => 'Analítica', 'href' => 'analytics.index', 'active' => 'analytics.*', 'icon' => 'M4 19V5M4 19h16M8 16v-4m4 4V8m4 8v-6'],
                            ['label' => 'Pedidos', 'href' => 'orders.index', 'active' => 'orders.*', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
                            ['label' => 'Revisión de pedidos', 'href' => 'order-reviews.index', 'active' => 'order-reviews.*', 'icon' => 'M9 11h6M9 15h6M7 4h7l4 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z'],
                            ['label' => 'Catálogo de productos', 'href' => 'products.index', 'active' => 'products.*', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
                            ['label' => 'Sucursales', 'href' => 'branches.index', 'active' => 'branches.*', 'icon' => 'M5 20V9l7-4 7 4v11M9 20v-6h6v6M8 11h.01M12 11h.01M16 11h.01'],
                            ['label' => 'Cierres diarios', 'href' => 'daily-order-closures.index', 'active' => 'daily-order-closures.*', 'icon' => 'M8 3v3M16 3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a1 1 0 0 1-1 2H6a1 1 0 0 1-1-2V7a2 2 0 0 1 2-2Z'],
                            ['label' => 'Bandeja de mensajes', 'href' => 'incoming-messages.index', 'active' => 'incoming-messages.*', 'icon' => 'M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z'],
                        ] as $item)
                            <a
                                href="{{ route($item['href']) }}"
                                @class([
                                    'group relative flex items-center gap-3 rounded-2xl border border-brand-primary bg-[linear-gradient(90deg,rgba(20,110,219,0.10),rgba(22,163,74,0.08))] px-4 py-3 text-sm font-medium text-brand-navy shadow-sm transition' => request()->routeIs($item['active']),
                                    'group relative flex items-center gap-3 rounded-2xl border border-transparent px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-brand-navy' => ! request()->routeIs($item['active']),
                                ])
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 transition-colors {{ request()->routeIs($item['active']) ? 'text-brand-primary' : 'text-slate-400 group-hover:text-brand-primary' }}" aria-hidden="true">
                                    <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>{{ $item['label'] }}</span>
                                @if (request()->routeIs($item['active']))
                                    <span class="absolute inset-y-0 right-0 w-1 rounded-l-full bg-brand-primary"></span>
                                @endif
                            </a>
                        @endforeach

                        <div class="px-2 pt-6">
                            <div class="px-2 text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-slate-400">
                                Canales
                            </div>
                            <div class="mt-3 space-y-1">
                                @foreach ([
                                    ['label' => 'Hub', 'href' => 'channels.index', 'active' => 'channels.index', 'icon' => 'M4 7h16M4 12h10M4 17h16', 'accent' => 'blue'],
                                    ['label' => 'WhatsApp', 'href' => 'channels.whatsapp', 'active' => 'channels.whatsapp*', 'icon' => 'M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z', 'accent' => 'green', 'badge' => 'Principal'],
                                    ['label' => 'Estado', 'href' => 'channels.whatsapp.status', 'active' => 'channels.whatsapp.status', 'icon' => 'M12 6v6l4 2', 'accent' => 'emerald'],
                                ] as $item)
                                    @php
                                        $isChannelActive = request()->routeIs($item['active']);
                                        $activeStyles = match ($item['accent']) {
                                            'green' => 'border-emerald-300 bg-emerald-50/80 text-emerald-950 shadow-sm',
                                            'emerald' => 'border-emerald-300 bg-emerald-50/70 text-emerald-950 shadow-sm',
                                            default => 'border-brand-primary bg-[linear-gradient(90deg,rgba(20,110,219,0.10),rgba(22,163,74,0.08))] text-brand-navy shadow-sm',
                                        };
                                        $inactiveStyles = 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-brand-navy';
                                    @endphp

                                    <a
                                        href="{{ route($item['href']) }}"
                                        @class([
                                            'group relative flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-medium transition' => true,
                                            $activeStyles => $isChannelActive,
                                            $inactiveStyles => ! $isChannelActive,
                                        ])
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 transition-colors {{ $isChannelActive ? 'text-emerald-600' : 'text-slate-400 group-hover:text-emerald-600' }}" aria-hidden="true">
                                            <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span class="flex-1">{{ $item['label'] }}</span>
                                        @if (! empty($item['badge']))
                                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                                {{ $item['badge'] }}
                                            </span>
                                        @endif
                                        @if ($isChannelActive)
                                            <span class="absolute inset-y-0 right-0 w-1 rounded-l-full bg-emerald-500"></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </nav>

                    <div class="border-t border-slate-200/80 px-4 py-5">
                        <div class="rounded-3xl border border-slate-200/80 bg-slate-50 p-4">
                            <div class="text-sm font-semibold text-brand-navy">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">{{ auth()->user()->email }}</div>
                            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                <span class="brand-badge bg-brand-primary/10 text-brand-primary">Activo</span>
                                <span>{{ auth()->user()->role }}</span>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full">
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
                    <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
                        <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center gap-3">
                                <button type="button" class="brand-btn-secondary lg:hidden" @click="sidebarOpen = true">
                                    Menú
                                </button>
                                <div>
                                    <div class="text-sm font-semibold text-brand-navy">{{ auth()->user()->name }}</div>
                                    <div class="text-xs text-slate-500">{{ auth()->user()->role }}</div>
                                </div>
                            </div>

                            <div class="hidden items-center gap-3 sm:flex">
                                <div class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">
                                    {{ now()->format('M d, Y') }}
                                </div>
                                <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="brand-btn-primary">
                                    Pedidos pendientes
                                </a>
                            </div>
                        </div>
                    </header>

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <div class="mx-auto w-full max-w-[1600px]">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
