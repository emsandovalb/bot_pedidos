@php
    $logoPath = public_path('images/logo-fondo-blanco.png');
@endphp

@if (file_exists($logoPath))
    <img src="{{ asset('images/logo-fondo-blanco.png') }}" alt="{{ config('app.name', 'BotPedidos') }}" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    <svg viewBox="0 0 280 120" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
        <defs>
            <linearGradient id="brand-fallback" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#0A3D91"/>
                <stop offset="100%" stop-color="#081F4D"/>
            </linearGradient>
        </defs>
        <rect x="2" y="2" width="276" height="116" rx="24" fill="url(#brand-fallback)"/>
        <circle cx="64" cy="60" r="28" fill="none" stroke="#F5B400" stroke-width="10"/>
        <path d="M64 34v52M38 60h52" stroke="#F5B400" stroke-width="8" stroke-linecap="round"/>
        <text x="108" y="56" fill="#ffffff" font-size="24" font-weight="700" font-family="Poppins, Inter, system-ui, sans-serif">Bot</text>
        <text x="108" y="84" fill="#F5B400" font-size="24" font-weight="700" font-family="Poppins, Inter, system-ui, sans-serif">Pedidos</text>
    </svg>
@endif
