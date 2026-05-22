@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl border border-brand-primary/10 bg-brand-primary/10 px-4 py-3 text-start text-base font-medium text-brand-primary focus:outline-none focus:text-brand-primary transition duration-150 ease-in-out'
            : 'block w-full rounded-xl border border-transparent px-4 py-3 text-start text-base font-medium text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:text-slate-900 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
