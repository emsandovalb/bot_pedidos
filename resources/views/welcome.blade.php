<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'BotPedidos') }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(10,61,145,0.45),transparent_40%),linear-gradient(180deg,#081F4D_0%,#0B1220_100%)]"></div>
            <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-16">
                <div class="max-w-3xl space-y-8">
                    <div class="brand-badge bg-white/10 text-white">Bot de pedidos</div>
                    <div class="space-y-4">
                        <h1 class="text-5xl font-semibold tracking-tight sm:text-6xl">BotPedidos</h1>
                        <p class="max-w-2xl text-lg text-slate-200">
                            Recepción automática de pedidos por chat. Telegram primero. WhatsApp próximamente.
                        </p>
                    </div>

                    <div class="grid gap-3 text-sm text-slate-200 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">Bandeja de mensajes</div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">Revisión de pedidos</div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">Cierres diarios</div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="brand-btn-primary">Ir al panel</a>
                        @else
                            <a href="{{ route('login') }}" class="brand-btn-primary">Entrar</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
