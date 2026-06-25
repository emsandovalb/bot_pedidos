<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="min-height:100%;background:#08162b;">
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
    <body class="overflow-x-hidden font-sans antialiased text-slate-900" style="margin:0;min-height:100vh;background:#08162b;">
        <main class="min-h-screen overflow-x-hidden bg-[linear-gradient(180deg,#f8fafc_0%,#eef4ff_55%,#f8fafc_100%)]" style="min-height:100vh;background:#08162b;">
            {{ $slot }}
        </main>
    </body>
</html>
