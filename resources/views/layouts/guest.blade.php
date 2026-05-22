<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700,800&display=swap" rel="stylesheet" />

        @php($hasFrontendAssets = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @if ($hasFrontendAssets)
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div class="relative min-h-screen overflow-hidden bg-[#f4f7fb]">
            <div class="absolute inset-0">
                <img
                    src="{{ asset('images/login-background.webp') }}"
                    alt=""
                    aria-hidden="true"
                    class="h-full w-full object-cover object-center opacity-25"
                >
            </div>

            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(10,61,145,0.10),transparent_34%),radial-gradient(circle_at_bottom_right,rgba(245,180,0,0.10),transparent_32%),linear-gradient(180deg,rgba(255,255,255,0.18),rgba(244,247,251,0.42))]"></div>

            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute left-[2%] top-[12%] h-72 w-72 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.28),rgba(255,255,255,0.08)_42%,transparent_75%)] blur-2xl"></div>
                <div class="absolute left-[18%] top-[28%] h-96 w-96 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.22),rgba(255,255,255,0.04)_40%,transparent_74%)] blur-3xl"></div>
                <div class="absolute right-[8%] top-[8%] h-80 w-80 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.24),rgba(255,255,255,0.04)_40%,transparent_74%)] blur-3xl"></div>
                <div class="absolute right-[12%] top-[40%] h-72 w-72 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.2),rgba(255,255,255,0.03)_40%,transparent_74%)] blur-3xl"></div>
                <div class="absolute left-[18%] bottom-[8%] h-64 w-64 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.2),rgba(255,255,255,0.04)_40%,transparent_74%)] blur-3xl"></div>
                <div class="absolute right-[20%] bottom-[10%] h-72 w-72 rounded-full bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.18),rgba(255,255,255,0.03)_40%,transparent_74%)] blur-3xl"></div>
            </div>

            <div class="relative flex min-h-screen items-center justify-center px-4 py-12">
                <div class="brand-card w-full max-w-[550px] px-6 py-8 sm:min-h-[660px] sm:px-8 sm:py-10">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
