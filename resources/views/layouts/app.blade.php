<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Benditio') }}</title>

        @include('layouts.partials.theme-head')

        @php
            $hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
        @endphp
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    @php
        // Menú agrupado como en OriA. 'active' es el patrón de ruta; 'provider' distingue los canales.
        $icons = [
            'panel' => 'M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1v-8.5Z',
            'chart' => 'M4 19V5M4 19h16M8 16v-4m4 4V8m4 8v-6',
            'ops' => 'M3.5 7.5h17M6 4.5h12M6 10.5h12M6 15.5h12M6 20.5h12',
            'simple' => 'M8 12h8M8 16h5M4 6h16M4 18h16',
            'list' => 'M4 6h16M4 12h16M4 18h10',
            'review' => 'M9 11h6M9 15h6M7 4h7l4 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z',
            'users' => 'M17 21a4 4 0 0 0-8 0M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-2a3 3 0 0 0-3-3m-4-5a3 3 0 0 1 0 6',
            'box' => 'M4 7.5 12 4l8 3.5v9L12 20l-8-3.5v-9ZM4 7.5l8 3.5 8-3.5M12 11v9',
            'store' => 'M5 20V9l7-4 7 4v11M9 20v-6h6v6M8 11h.01M12 11h.01M16 11h.01',
            'calendar' => 'M8 3v3M16 3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a1 1 0 0 1-1 2H6a1 1 0 0 1-1-2V7a2 2 0 0 1 2-2Z',
            'inbox' => 'M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z',
            'whatsapp' => 'M12 2a10 10 0 1 0 5.1 18.6L22 22l-1.4-4.9A10 10 0 0 0 12 2Z',
            'telegram' => 'M21.8 4.7 3.6 11.4c-1 .4-1 1.8.1 2.1l4.7 1.4 1.8 5.2c.3.9 1.5 1.1 2.1.3l2.8-3.3 4.8 3.5c.8.6 1.9.1 2.1-.9l2.8-13.8c.2-1.1-.9-2-2-1.7Z',
            'instagram' => 'M7.5 3.5h9A4 4 0 0 1 20.5 7.5v9a4 4 0 0 1-4 4h-9a4 4 0 0 1-4-4v-9a4 4 0 0 1 4-4Zm9.5 2.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2ZM12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z',
            'bell' => 'M6 16V11a6 6 0 1 1 12 0v5l2 2H4l2-2Zm4 4h4',
            'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
            'wallet' => 'M3 7h18M3 7v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7M3 7l2-3h14l2 3M8 14h3',
            'help' => 'M12 8v8m-4-4h8M4 6h16M4 18h16',
            'code' => 'M8 7V4m8 3V4M6 11h12M5 20h14a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1Zm3-5h.01M12 15h.01M16 15h.01',
        ];

        $navGroups = [
            ['label' => null, 'items' => [
                ['label' => 'Panel', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'panel'],
                ['label' => 'Analítica', 'route' => 'analytics.index', 'active' => 'analytics.*', 'icon' => 'chart'],
            ]],
            ['label' => 'Operación', 'items' => [
                ['label' => 'Operaciones', 'route' => 'operations.index', 'active' => 'operations.index', 'icon' => 'ops'],
                ['label' => 'Modo simple', 'route' => 'operations.focus', 'active' => 'operations.focus', 'icon' => 'simple'],
                ['label' => 'Pedidos', 'href' => route('orders.index'), 'active' => 'orders.*', 'icon' => 'list'],
                ['label' => 'Revisión de pedidos', 'route' => 'order-reviews.index', 'active' => 'order-reviews.*', 'icon' => 'review'],
            ]],
            ['label' => 'Negocio', 'items' => [
                ['label' => 'Clientes', 'route' => 'customers.index', 'active' => 'customers.*', 'icon' => 'users'],
                ['label' => 'Catálogo de productos', 'route' => 'products.index', 'active' => 'products.*', 'icon' => 'box'],
                ['label' => 'Sucursales', 'route' => 'branches.index', 'active' => 'branches.*', 'icon' => 'store'],
                ['label' => 'Cierres diarios', 'route' => 'daily-order-closures.index', 'active' => 'daily-order-closures.*', 'icon' => 'calendar'],
            ]],
            ['label' => 'Mensajes', 'items' => [
                ['label' => 'Bandeja de mensajes', 'route' => 'incoming-messages.index', 'active' => 'incoming-messages.*', 'icon' => 'inbox'],
                ['label' => 'WhatsApp', 'href' => route('channels.show', ['whatsapp']), 'active' => 'channels.show', 'provider' => 'whatsapp', 'icon' => 'whatsapp', 'badge' => 'Configurar'],
                ['label' => 'Telegram', 'href' => route('channels.show', ['telegram']), 'active' => 'channels.show', 'provider' => 'telegram', 'icon' => 'telegram'],
                ['label' => 'Instagram', 'href' => route('channels.show', ['instagram']), 'active' => 'channels.show', 'provider' => 'instagram', 'icon' => 'instagram', 'badge' => 'Pronto'],
            ]],
        ];

        if (auth()->user()->can('manage-settings')) {
            $settings = [
                ['label' => 'Notificaciones', 'route' => 'settings.notifications.index', 'active' => 'settings.notifications.*', 'icon' => 'bell'],
                ['label' => 'Oria', 'route' => 'settings.oria.index', 'active' => 'settings.oria.index', 'icon' => 'link'],
            ];

            if ($oriaPlus ?? false) {
                $settings[] = ['label' => 'Pagar servicios', 'route' => 'settings.oria.pay-services', 'active' => 'settings.oria.pay-services', 'icon' => 'wallet', 'badge' => 'Plus', 'plus' => true];
            }

            $navGroups[] = ['label' => 'Ajustes', 'items' => $settings];
        }

        $navGroups[] = ['label' => 'Soporte', 'items' => [
            ['label' => 'Configuraciones', 'route' => 'setup-requests.index', 'active' => 'setup-requests.*', 'icon' => 'help'],
        ]];

        if (app()->environment('local') || config('app.debug')) {
            $navGroups[] = ['label' => 'Desarrollo', 'items' => [
                ['label' => 'Developer Toolkit', 'route' => 'developer.webhook-simulator', 'active' => 'developer.webhook-simulator*', 'icon' => 'code'],
            ]];
        }

        $isActive = fn (array $item): bool => request()->routeIs($item['active'])
            && (! isset($item['provider']) || request()->route('provider') === $item['provider']);

        $currentLabel = collect($navGroups)->pluck('items')->flatten(1)->first($isActive)['label'] ?? null;

        $authUser = auth()->user();
        $initials = collect(preg_split('/\s+/', trim($authUser->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
        $firstName = strtok($authUser->name, ' ');
    @endphp
    <body class="font-sans antialiased text-slate-900">
        <div x-data="{ sidebarOpen: false }" class="brand-shell min-h-screen">
            <div class="relative flex min-h-screen">
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-sm lg:hidden"
                    @click="sidebarOpen = false"
                ></div>

                <aside
                    class="app-sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col shadow-raised transition-transform duration-200 ease-in-out lg:translate-x-0"
                    :class="{ 'translate-x-0': sidebarOpen }"
                    aria-label="Menú principal"
                >
                    <div class="px-6 pb-4 pt-7">
                        <a href="{{ route('dashboard') }}" class="inline-flex flex-col gap-1 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                            <img src="{{ asset('images/benditio-logo-blanco.svg') }}" alt="Benditio" class="h-9 w-auto self-start">
                            <span class="text-xs font-semibold text-white/70">Pedidos por WhatsApp y Telegram</span>
                        </a>
                    </div>

                    <nav class="min-h-0 flex-1 overflow-y-auto px-4 pb-6">
                        @foreach ($navGroups as $group)
                            @if ($group['label'])
                                <div class="nav-group-label">{{ $group['label'] }}</div>
                            @endif
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    @php($active = $isActive($item))
                                    <a
                                        href="{{ $item['href'] ?? route($item['route']) }}"
                                        @class(['nav-link', 'is-active' => $active])
                                        @if ($active) aria-current="page" @endif
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5 shrink-0" aria-hidden="true">
                                            <path d="{{ $icons[$item['icon']] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (! empty($item['badge']))
                                            <span @class(['nav-badge', 'is-plus' => $item['plus'] ?? false])>{{ $item['badge'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </nav>

                    <div class="border-t border-white/10 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="avatar h-9 w-9">{{ $initials }}</span>
                            <a href="{{ route('profile.edit') }}" class="min-w-0 flex-1 rounded-md hover:opacity-90">
                                <span class="block truncate text-sm font-bold text-white">{{ $authUser->name }}</span>
                                <span class="block truncate text-xs text-white/70">{{ $authUser->email }}</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-[9px] border border-white/20 text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                                    aria-label="Cerrar sesión"
                                    title="Cerrar sesión"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                        <path d="M15 12H4m0 0 4-4m-4 4 4 4M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
                    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
                        <div class="flex h-[72px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-10">
                            <div class="flex min-w-0 items-center gap-3">
                                <button
                                    type="button"
                                    class="brand-btn-secondary gap-2 px-3 lg:hidden"
                                    @click="sidebarOpen = true"
                                    aria-label="Abrir menú"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                    </svg>
                                    <span class="hidden sm:inline">Menú</span>
                                </button>
                                <nav aria-label="Ruta" class="min-w-0 truncate text-sm text-slate-500">
                                    <a href="{{ route('dashboard') }}" class="hover:text-brand-navy">Benditio</a>
                                    @if ($currentLabel)
                                        <span class="mx-1.5 text-slate-400">/</span>
                                        <span class="font-bold text-brand-navy" aria-current="page">{{ $currentLabel }}</span>
                                    @endif
                                </nav>
                            </div>

                            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                                <span class="hidden rounded-full border border-slate-200 px-3.5 py-1.5 text-sm font-semibold text-slate-600 md:inline-flex">
                                    {{ now()->locale('es')->translatedFormat('j M Y') }}
                                </span>
                                <button
                                    type="button"
                                    class="theme-toggle"
                                    x-data="themeToggle"
                                    @click="toggle()"
                                    :aria-label="label"
                                    :title="label"
                                    aria-label="Cambiar entre modo claro y oscuro"
                                >
                                    <svg x-show="!dark" viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <svg x-show="dark" x-cloak viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <path d="M12 4V2m0 20v-2m8-8h2M2 12h2m13.7-5.7 1.4-1.4M4.9 19.1l1.4-1.4m0-11.4L4.9 4.9m14.2 14.2-1.4-1.4M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                                <a href="{{ route('operations.index') }}" class="brand-btn-primary hidden sm:inline-flex">
                                    Pedidos pendientes
                                </a>
                                <a href="{{ route('profile.edit') }}" class="hidden items-center gap-2 rounded-full border border-slate-200 py-1 pl-1 pr-3.5 text-sm font-bold text-brand-navy transition hover:border-slate-300 xl:inline-flex">
                                    <span class="avatar h-8 w-8">{{ $initials }}</span>
                                    Hola, {{ $firstName }}
                                </a>
                            </div>
                        </div>
                    </header>

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                        <div class="mx-auto w-full max-w-[1600px]">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
