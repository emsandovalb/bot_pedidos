@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-brand-success/20 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 shadow-sm']) }}>
        {{ $status }}
    </div>
@endif
