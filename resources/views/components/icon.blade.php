@props(['name'])

@php
    // Íconos de línea (24 × 24, trazo 1.8), los mismos del menú lateral.
    $paths = [
        'list' => 'M4 6h16M4 12h16M4 18h10',
        'review' => 'M9 11h6M9 15h6M7 4h7l4 4v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z',
        'check' => 'M5 12.5 10 17l9-10',
        'fire' => 'M12 21a6 6 0 0 0 6-6c0-4-3-6-4-9-1 2-2 3-4 4 0-1 0-2-1-3-2 2-3 5-3 8a6 6 0 0 0 6 6Z',
        'truck' => 'M3 7h11v9H3V7Zm11 3h4l3 3v3h-7v-6ZM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
        'help' => 'M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'calendar' => 'M8 3v3M16 3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a1 1 0 0 1-1 2H6a1 1 0 0 1-1-2V7a2 2 0 0 1 2-2Z',
        'chart' => 'M4 19V5M4 19h16M8 16v-4m4 4V8m4 8v-6',
        'box' => 'M4 7.5 12 4l8 3.5v9L12 20l-8-3.5v-9ZM4 7.5l8 3.5 8-3.5M12 11v9',
        'alert' => 'M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
        'layers' => 'M12 4 3 9l9 5 9-5-9-5Zm-9 10 9 5 9-5',
    ];
@endphp

<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
    <path d="{{ $paths[$name] ?? $paths['list'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
</svg>
