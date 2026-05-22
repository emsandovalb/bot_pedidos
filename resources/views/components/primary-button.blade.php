<button {{ $attributes->merge(['type' => 'submit', 'class' => 'brand-btn-primary']) }}>
    {{ $slot }}
</button>
