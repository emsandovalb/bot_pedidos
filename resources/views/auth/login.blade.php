<x-guest-layout>
    <x-slot name="fullPage">1</x-slot>

    <div class="grid min-h-screen lg:grid-cols-[1.12fr_1fr]">
        {{-- Izquierda: qué hace Benditio. Marino en los dos temas. --}}
        <section class="app-sidebar on-navy relative flex flex-col justify-between gap-8 overflow-hidden px-6 py-8 sm:px-10 lg:px-16 lg:py-12">
            <div class="pointer-events-none absolute -right-44 -top-44 h-[520px] w-[520px] rounded-full border border-white/5 shadow-[0_0_0_70px_rgba(255,255,255,0.025),0_0_0_140px_rgba(255,255,255,0.02)]" aria-hidden="true"></div>

            <a href="{{ url('/') }}" class="relative self-start">
                <img src="{{ asset('images/benditio-logo-blanco.svg') }}" alt="Benditio" class="h-9 w-auto lg:h-11">
            </a>

            <div class="relative">
                <h1 class="text-4xl font-extrabold leading-[1.02] tracking-[-0.04em] text-white sm:text-5xl lg:text-6xl">
                    Convierte <span class="text-[#3fd394]">mensajes</span><br>en ventas
                </h1>
                <p class="mt-4 max-w-[31em] text-base leading-relaxed text-white/75 lg:text-lg">
                    Benditio lee los pedidos que llegan por WhatsApp y Telegram y los ordena para que tu equipo los confirme, prepare y despache.
                </p>
            </div>

            {{-- Un mensaje que se vuelve pedido: lo que hace Benditio, en un vistazo.
                 Es una ilustración sobre el fondo marino, así que usa colores fijos: se ve igual en claro y en oscuro. --}}
            <div class="relative hidden max-w-[660px] grid-cols-[1fr_34px_1.1fr] items-center gap-2.5 lg:grid" aria-hidden="true">
                <div class="grid gap-2 rounded-[20px_20px_20px_6px] border border-white/15 bg-white/10 px-4 py-4">
                    <span class="flex items-center gap-2 text-xs font-bold text-[#3fd394]">
                        <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        WhatsApp · 22:56
                    </span>
                    <span class="text-base font-semibold leading-snug text-white">“Me manda 2 martillito 903 y 3 sacos de cemento”</span>
                </div>
                <span class="grid h-[34px] w-[34px] place-items-center rounded-full bg-[#3fd394] text-[#062b1c]">
                    <svg viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <div class="grid gap-2 rounded-[20px] bg-[#ffffff] px-4 py-4 text-[#0c1d3c] shadow-[0_30px_60px_-28px_rgba(0,0,0,0.7)]">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <b class="font-display text-lg font-extrabold tracking-tight">Pedido #29</b>
                            <span class="block text-xs font-semibold text-[#5b6b82]">Cliente Auditoría · Central Branch</span>
                        </div>
                        <span class="whitespace-nowrap rounded-full bg-[#fff3e6] px-2.5 py-0.5 text-[0.7rem] font-bold text-[#b45309]">Por revisar</span>
                    </div>
                    <div class="flex justify-between border-t border-[#e6ecf3] pt-2 text-sm text-[#56677b]"><span>Martillo 903</span><b class="text-[#0c1d3c]">× 2</b></div>
                    <div class="flex justify-between border-t border-[#e6ecf3] pt-2 text-sm text-[#56677b]"><span>Saco de cemento</span><b class="text-[#0c1d3c]">× 3</b></div>
                    <div class="flex items-center justify-between text-xs font-semibold text-[#5b6b82]">
                        <span>Detectado en 2 s</span>
                        <span class="text-[#16804a]">✓ Listo para confirmar</span>
                    </div>
                </div>
            </div>

            <dl class="relative hidden gap-9 border-t border-white/10 pt-5 lg:flex">
                <div><dt class="font-display text-xl font-extrabold text-white">WhatsApp y Telegram</dt><dd class="text-sm font-semibold text-white/60">en una sola bandeja</dd></div>
                <div><dt class="font-display text-xl font-extrabold text-white">Revisión</dt><dd class="text-sm font-semibold text-white/60">con nivel de confianza</dd></div>
                <div><dt class="font-display text-xl font-extrabold text-white">Cierres</dt><dd class="text-sm font-semibold text-white/60">diarios por sucursal</dd></div>
            </dl>
        </section>

        {{-- Derecha: el formulario. --}}
        <section class="flex flex-col bg-white px-6 py-8 sm:px-10 lg:px-16">
            <p class="text-right text-sm text-slate-500">
                ¿No tienes una cuenta?
                <a href="mailto:soporte@benditio.com" class="font-bold text-brand-primary hover:underline">Contacta a tu administrador</a>
            </p>

            <div x-data="{ showPassword: false }" class="my-auto w-full max-w-[440px] py-10">
                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-emerald-700">Bienvenido de vuelta</p>
                <h2 class="mt-1.5 text-[2.4rem] font-extrabold leading-tight tracking-[-0.03em] text-brand-navy">Iniciar sesión</h2>
                <p class="mt-2 text-[0.95rem] text-slate-500">Accede a tu cuenta de Benditio para ver los pedidos de hoy.</p>

                @if (session('status'))
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-7 grid gap-5">
                    @csrf

                    <div class="grid gap-2">
                        <label for="email" class="text-sm font-bold text-brand-navy">Correo electrónico</label>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" fill="none" class="pointer-events-none absolute left-4 top-1/2 h-[19px] w-[19px] -translate-y-1/2 text-slate-400" aria-hidden="true"><path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm-1 1 9 6 9-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <input
                                id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@negocio.com"
                                class="brand-input h-[54px] w-full rounded-xl border-[1.5px] pl-12 pr-4 text-[0.95rem]"
                            >
                        </div>
                        @error('email')
                            <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <label for="password" class="text-sm font-bold text-brand-navy">Contraseña</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-bold text-brand-primary hover:underline">¿Olvidaste tu contraseña?</a>
                            @endif
                        </div>
                        <div class="relative">
                            <svg viewBox="0 0 24 24" fill="none" class="pointer-events-none absolute left-4 top-1/2 h-[19px] w-[19px] -translate-y-1/2 text-slate-400" aria-hidden="true"><path d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Zm6 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            <input
                                id="password" name="password" required autocomplete="current-password" placeholder="••••••••••"
                                type="password" x-bind:type="showPassword ? 'text' : 'password'"
                                class="brand-input h-[54px] w-full rounded-xl border-[1.5px] pl-12 pr-12 text-[0.95rem]"
                            >
                            <button
                                type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400 hover:text-brand-navy"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                aria-label="Mostrar contraseña"
                            >
                                <svg x-show="!showPassword" viewBox="0 0 24 24" fill="none" class="h-[19px] w-[19px]" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Zm9.5 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" class="h-[19px] w-[19px]" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 2.8 2.8M9.9 5.1A11 11 0 0 1 12 5c6.7 0 9.8 7 9.8 7a20.6 20.6 0 0 1-4.2 5.2M6.2 6.2C3.8 8 2.2 12 2.2 12s3.1 6 9.8 6c1 0 1.9-.1 2.8-.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-semibold text-slate-600">
                        <input id="remember_me" type="checkbox" name="remember" class="h-5 w-5 rounded-md border-slate-300 text-brand-primary focus:ring-brand-primary">
                        Mantener la sesión abierta en este equipo
                    </label>

                    <button type="submit" class="brand-btn-primary h-14 gap-2.5 rounded-xl text-base">
                        Entrar a Benditio
                        <svg viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                </form>
            </div>

            <p class="text-sm font-semibold text-slate-500"><b class="font-display text-brand-navy">Benditio</b> · parte de la familia OriA</p>
        </section>
    </div>
</x-guest-layout>
