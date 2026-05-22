@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full border border-brand-primary/15 bg-brand-primary/10 px-3 py-2 text-sm font-medium leading-5 text-brand-primary focus:outline-none transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-full border border-transparent px-3 py-2 text-sm font-medium leading-5 text-slate-500 hover:border-slate-200 hover:bg-white hover:text-slate-900 focus:outline-none focus:text-slate-900 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
