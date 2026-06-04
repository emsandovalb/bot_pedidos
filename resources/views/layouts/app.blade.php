<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'BotPedidos') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        @php($hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div x-data="{ sidebarOpen: false }" class="brand-shell min-h-screen">
            <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,rgba(10,61,145,0.18),transparent_68%)]"></div>
            <div class="relative flex min-h-screen">
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden"
                    @click="sidebarOpen = false"
                ></div>

                <aside
                    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-white/10 bg-[linear-gradient(180deg,#081F4D_0%,#0A3D91_100%)] text-white shadow-[0_24px_60px_-28px_rgba(8,31,77,0.75)] transition-transform duration-200 ease-in-out lg:translate-x-0"
                    :class="{ 'translate-x-0': sidebarOpen }"
                >
                    <div class="px-5 pt-5">
                        <div class="rounded-[1.75rem] bg-white px-5 py-4 shadow-[0_18px_36px_-26px_rgba(255,255,255,0.45)]">
                            <a href="{{ route('dashboard') }}" class="block">
                                <x-application-logo class="h-24 w-full max-w-[250px]" />
                            </a>
                        </div>
                    </div>

                    <nav class="flex-1 space-y-1 px-4 py-5">
                        @foreach ([
                            ['label' => 'Panel', 'href' => 'dashboard', 'active' => 'dashboard', 'icon' => 'M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-8.5Z'],
                            ['label' => 'Analítica', 'href' => 'analytics.index', 'active' => 'analytics.*', 'icon' => 'M4 19V5M4 19h16M8 16v-4m4 4V8m4 8v-6'],
                            ['label' => 'Pedidos', 'href' => 'orders.index', 'active' => 'orders.*', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
                            ['label' => 'Revision de pedidos', 'href' => 'order-reviews.index', 'active' => 'order-reviews.*', 'icon' => 'M9 11h6M9 15h6M7 4h7l4 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z'],
                            ['label' => 'Catalogo de productos', 'href' => 'products.index', 'active' => 'products.*', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
                            ['label' => 'Sucursales', 'href' => 'branches.index', 'active' => 'branches.*', 'icon' => 'M5 20V9l7-4 7 4v11M9 20v-6h6v6M8 11h.01M12 11h.01M16 11h.01'],
                            ['label' => 'Cierres diarios', 'href' => 'daily-order-closures.index', 'active' => 'daily-order-closures.*', 'icon' => 'M8 3v3M16 3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a1 1 0 0 1-1 2H6a1 1 0 0 1-1-2V7a2 2 0 0 1 2-2Z'],
                            ['label' => 'Bandeja de mensajes', 'href' => 'incoming-messages.index', 'active' => 'incoming-messages.*', 'icon' => 'M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z'],
                        ] as $item)
                            <a
                                href="{{ route($item['href']) }}"
                                class="group relative flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
                                @class([
                                    'border-brand-gold bg-[linear-gradient(90deg,rgba(10,61,145,0.98),rgba(8,31,77,0.92))] text-white shadow-[0_14px_26px_-18px_rgba(245,180,0,0.55)]' => request()->routeIs($item['active']),
                                    'border-transparent text-white/78 hover:bg-white/10 hover:text-white' => ! request()->routeIs($item['active']),
                                ])
                            >
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0 transition-colors {{ request()->routeIs($item['active']) ? 'text-brand-gold' : 'text-white/75 group-hover:text-white' }}" aria-hidden="true">
                                    <path d="{{ $item['icon'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>{{ $item['label'] }}</span>
                                @if (request()->routeIs($item['active']))
                                    <span class="absolute inset-y-0 right-0 w-1 rounded-l-full bg-brand-gold"></span>
                                @endif
                            </a>
                        @endforeach
                    </nav>

                    <div class="border-t border-white/10 px-4 py-5">
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-4">
                            <div class="text-sm font-semibold text-white">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-white/70">{{ auth()->user()->email }}</div>
                            <div class="mt-3 flex items-center gap-2 text-xs text-white/70">
                                <span class="brand-badge bg-brand-gold/15 text-brand-gold">Active</span>
                                <span>{{ auth()->user()->role }}</span>
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                @csrf
                                <button type="submit" class="brand-btn-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">
                                    Cerrar sesion
                                </button>
                            </form>
                        </div>
                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
                    <header class="sticky top-0 z-20 border-b border-white/70 bg-white/85 backdrop-blur-xl">
                        <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center gap-3">
                                <button type="button" class="brand-btn-secondary lg:hidden" @click="sidebarOpen = true">
                                    Menu
                                </button>
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</div>
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
