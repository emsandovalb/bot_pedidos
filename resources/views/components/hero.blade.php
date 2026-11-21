@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
])

{{--
    Bloque de bienvenida marino, como el inicio de OriA. Marino en los dos temas.
    El slot `actions` va a la derecha: una acción principal blanca (.hero-btn) y, si hace falta, un enlace.
--}}
<section {{ $attributes->class('app-sidebar on-navy relative overflow-hidden rounded-[20px] px-6 py-7 shadow-card sm:px-9 sm:py-8') }}>
    <div class="pointer-events-none absolute -right-28 -top-36 h-[420px] w-[420px] rounded-full border border-white/5 shadow-[0_0_0_60px_rgba(255,255,255,0.025)]" aria-hidden="true"></div>
    <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-[#3fd394]">{{ $eyebrow }}</p>
            @endif
            <h1 class="mt-2 text-3xl font-extrabold leading-tight tracking-[-0.035em] text-white sm:text-[2.3rem]">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-2 text-[0.95rem] text-white/75">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-3 lg:flex-col lg:items-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
