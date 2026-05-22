<button {{ $attributes->merge(['type' => 'submit', 'class' => 'brand-btn-danger']) }}>
    {{ $slot }}
</button>
