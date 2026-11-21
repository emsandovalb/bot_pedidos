@props([
    'label',
    'value' => null,
    'hint' => null,
    'icon' => null,
    'tone' => 'blue',
    'href' => null,
])

{{--
    Tarjeta de número al estilo OriA: tarjeta blanca con borde fino; el estado se lee en el ícono de color suave,
    no en un borde de color. Para un valor con formato propio, pásalo como contenido del componente.
--}}
@php
    $tones = [
        'blue' => 'bg-blue-50 text-blue-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'green' => 'bg-emerald-50 text-emerald-700',
        'violet' => 'bg-violet-50 text-violet-700',
        'sky' => 'bg-sky-50 text-sky-700',
        'red' => 'bg-red-50 text-red-700',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'stat-card flex flex-col gap-3 rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm',
        'transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-card' => $href,
    ]) }}
>
    @if ($icon)
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['blue'] }}">
            <x-icon :name="$icon" class="h-[19px] w-[19px]" />
        </span>
    @endif
    <div class="min-w-0">
        <div class="text-sm font-semibold text-slate-600 [overflow-wrap:anywhere]">{{ $label }}</div>
        <div class="mt-1 font-display text-3xl font-extrabold tracking-tight text-brand-navy">
            {{ $slot->isEmpty() ? $value : $slot }}
        </div>
        @if ($hint)
            <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
        @endif
    </div>
</{{ $tag }}>
