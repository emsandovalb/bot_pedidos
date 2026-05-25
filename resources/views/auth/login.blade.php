<x-guest-layout>
    <div class="text-center">
        <div class="flex justify-center">
            <x-application-logo class="block h-[16rem] w-auto max-w-full" />
        </div>

        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-brand-navy">Iniciar sesión</h1>
        <p class="mt-2 text-sm text-slate-500">Usa tu cuenta para acceder al panel de pedidos.</p>
    </div>

    <x-auth-session-status class="mt-6 mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-2xl px-4 py-3.5 text-base" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="usuario@ejemplo.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-2xl px-4 py-3.5 text-base"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-brand-primary shadow-sm focus:ring-brand-primary" name="remember">
                <span class="ms-2 text-sm font-medium text-slate-700">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-brand-primary underline-offset-4 hover:underline" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center py-3.5 text-base">
            Iniciar sesión
        </x-primary-button>
    </form>
</x-guest-layout>
