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
            <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-[1.75rem] font-extrabold leading-tight tracking-[-0.03em] text-brand-navy sm:text-[2rem]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 max-w-3xl text-[0.95rem] text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2.5">
            {{ $actions }}
        </div>
    @endisset
</header>
