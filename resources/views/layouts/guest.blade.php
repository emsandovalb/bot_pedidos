<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Benditio') }}</title>

        @include('layouts.partials.theme-head')

        @php($hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen overflow-x-hidden font-sans antialiased text-slate-900">
        @isset($fullPage)
            {{-- La página arma su propia pantalla completa (inicio de sesión). --}}
            {{ $slot }}
        @else
            {{-- Recuperar contraseña, verificar correo, etc.: una tarjeta centrada con el logo arriba. --}}
            <main class="surface-page flex min-h-screen flex-col items-center justify-center px-4 py-10">
                <a href="{{ url('/') }}" class="mb-6">
                    <x-application-logo class="h-14 w-auto" />
                </a>
                <div class="brand-card w-full max-w-md p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </main>
        @endisset
    </body>
</html>
