<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Benditio') }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-white text-slate-900">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(20,110,219,0.10),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(22,163,74,0.10),transparent_30%),linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)]"></div>

            <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-16">
                <div class="grid w-full gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
                    <div class="space-y-8">
                        <div class="flex items-center gap-4">
                            <a href="{{ url('/') }}" class="inline-flex items-center no-underline">
                                <x-application-logo class="h-16 w-auto" />
                            </a>
                            <div class="brand-badge bg-brand-primary/10 text-brand-primary">Plataforma operativa</div>
                        </div>
                        <div class="space-y-4">
                            <h1 class="text-5xl font-semibold tracking-tight text-brand-navy sm:text-6xl">Convierte mensajes en ventas.</h1>
                            <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                Gestión de pedidos por mensajería para equipos que necesitan revisar, clasificar y cerrar operaciones sin perder el hilo del canal.
                            </p>
                        </div>

                        <div class="grid gap-3 text-sm text-slate-600 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">Bandeja de mensajes</div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">Revisión de pedidos</div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">Cierres diarios</div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @auth
                                <a href="{{ route('dashboard') }}" class="brand-btn-primary">Ir al panel</a>
                            @else
                                <a href="{{ route('login') }}" class="brand-btn-primary">Entrar</a>
                            @endauth
                            <div class="brand-btn-secondary">Plataforma de ventas por Telegram y WhatsApp</div>
                        </div>
                    </div>

                    <div class="brand-card p-6 sm:p-8">
                        <div class="space-y-5">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Diseño de interfaz</div>
                                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">Panel claro, directo y operativo</h2>
                                <p class="mt-3 text-sm leading-6 text-slate-600">
                                    El sistema reúne pedidos, revisión, catálogo y cierres en una sola vista simple para operar sin fricción.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-sm font-medium text-slate-500">Canales</div>
                                    <div class="mt-2 text-lg font-semibold text-brand-navy">Telegram y WhatsApp</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-sm font-medium text-slate-500">Soporte operativo</div>
                                    <div class="mt-2 text-lg font-semibold text-brand-navy">Ventas guiadas</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-sm font-medium text-slate-500">Enfoque</div>
                                    <div class="mt-2 text-lg font-semibold text-brand-navy">Pedidos y seguimiento</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-sm font-medium text-slate-500">Marca</div>
                                    <div class="mt-2 text-lg font-semibold text-brand-navy">Benditio</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
