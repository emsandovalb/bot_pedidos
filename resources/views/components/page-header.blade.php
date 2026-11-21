@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
])

{{--
    Encabezado de pantalla al estilo OriA: título directo sobre la página, sin caja de color.
    Las acciones van en el slot `actions` y se alinean a la derecha (debajo en celular).
--}}
<header {{ $attributes->class('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="page-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="page-title mt-1">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle mt-1.5">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2.5">
            {{ $actions }}
        </div>
    @endisset
</header>
