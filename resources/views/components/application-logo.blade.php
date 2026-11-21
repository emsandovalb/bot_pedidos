@props([
    'src' => asset('images/benditio-logo.svg'),
    'darkSrc' => asset('images/benditio-logo-oscuro.svg'),
])

{{-- En modo oscuro se muestra el logo en blanco, sin el recuadro blanco del original. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-start overflow-hidden']) }}>
    <img
        src="{{ $src }}"
        alt="Benditio"
        class="block h-full w-auto select-none dark:hidden"
    >
    <img
        src="{{ $darkSrc }}"
        alt="Benditio"
        class="hidden h-full w-auto select-none dark:block"
    >
</span>
