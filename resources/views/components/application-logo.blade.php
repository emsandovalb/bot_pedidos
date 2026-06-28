@props([
    'src' => asset('images/benditio-logo.svg'),
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-start overflow-hidden']) }}>
    <img
        src="{{ $src }}"
        alt="Benditio"
        class="block h-full w-auto select-none"
    >
</span>
