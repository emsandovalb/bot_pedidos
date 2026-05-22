@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'brand-input rounded-xl shadow-sm transition placeholder:text-slate-400']) }}>
