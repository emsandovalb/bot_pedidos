<x-guest-layout>
    <div class="min-h-screen bg-[#eef1f5] flex items-center justify-center p-7">
        <div class="relative w-full max-w-[1280px] overflow-hidden rounded-[26px] bg-[#0a2342] shadow-[0_40px_90px_-50px_rgba(7,20,40,0.55)]">
            {{-- gradient + glows --}}
            <div class="absolute inset-0 bg-[linear-gradient(120deg,#0a2342_0%,#0c3350_42%,#136046_74%,#1f8f3f_100%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_12%_8%,rgba(18,112,223,0.30),transparent_40%),radial-gradient(circle_at_98%_4%,rgba(60,205,104,0.30),transparent_34%),radial-gradient(circle_at_100%_100%,rgba(60,205,104,0.35),transparent_42%)]"></div>

            <div class="relative grid min-h-[720px] grid-cols-1 lg:grid-cols-[1fr_432px]">
                {{-- LEFT --}}
                <section class="flex flex-col justify-between px-7 py-9 text-white sm:px-12 lg:px-[52px] lg:py-11">
                    <div>
                        {{-- logo --}}
                        <div class="flex items-center gap-3">
                            <div class="grid h-[34px] w-[34px] grid-cols-2 grid-rows-2 gap-1">
                                <span class="rounded-[5px] bg-[#1270df]"></span>
                                <span class="rounded-[5px] bg-[#3ccd68]"></span>
                                <span class="rounded-[5px] bg-[#3ccd68]"></span>
                                <span class="rounded-[5px] bg-[#1270df]"></span>
                            </div>
                            <span class="text-[20px] font-bold tracking-[-0.01em]">Benditio</span>
                        </div>

                        {{-- headline --}}
                        <h1 class="mt-[46px] text-[62px] font-black leading-[0.95] tracking-[-0.05em]">
                            Convierte <span class="text-[#3ccd68]">mensajes</span><br>en ventas.
                        </h1>
                        <p class="mt-[22px] max-w-[34ch] text-[17px] leading-[1.5] text-slate-200/85">
                            Gestiona pedidos, clientes y operaciones desde una sola plataforma.
                        </p>

                        {{-- features --}}
                        <div class="mt-[34px] grid max-w-[430px] grid-cols-2 gap-x-[46px] gap-y-[14px]">
                            @foreach (['Pedidos', 'WhatsApp', 'Telegram', 'Analytics', 'Catálogo', 'Clientes', 'Cierres diarios'] as $feature)
                                <div class="flex items-center gap-[11px] text-[16px]">
                                    <span class="flex h-[21px] w-[21px] shrink-0 items-center justify-center rounded-full bg-[#3ccd68] shadow-[0_8px_18px_-10px_rgba(60,205,104,0.9)]">
                                        <svg viewBox="0 0 24 24" class="h-3 w-3 fill-none stroke-white stroke-[3]"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                    <span class="text-white/95">{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- illustration --}}
                    <div class="relative mt-[30px] hidden h-[300px] sm:block">
                        <div class="absolute left-[60px] top-[26px] h-[84px] w-[84px] rounded-full border-2 border-white/15"></div>
                        <div class="absolute left-[128px] top-[64px] h-[54px] w-[54px] rounded-full border-2 border-white/15"></div>
                        <div class="absolute left-[430px] top-[24px] h-[52px] w-[52px] rounded-full border-2 border-white/15"></div>

                        {{-- whatsapp floating badge --}}
                        <div class="absolute left-[474px] top-[118px] flex h-[34px] w-[34px] items-center justify-center rounded-full bg-[#3ccd68] shadow-[0_12px_26px_-10px_rgba(60,205,104,0.7)]">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px] fill-white"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.2 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.1.1.3 0 .5-.1.2-.2.4-.3.5l-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.4.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                        </div>

                        {{-- chart card --}}
                        <div class="absolute left-[178px] top-[8px] h-[268px] w-[300px] overflow-hidden rounded-[18px] border border-white/15 bg-[linear-gradient(180deg,rgba(11,32,58,0.62),rgba(8,22,42,0.5))] p-5 backdrop-blur-sm">
                            <div class="flex items-center justify-between">
                                <span class="block h-[9px] w-[74px] rounded-md bg-white/15"></span>
                                <span class="block h-[12px] w-[66px] rounded-[7px] bg-[#3ccd68]/85"></span>
                            </div>
                            <svg viewBox="0 0 260 118" class="mt-[14px] block h-[118px] w-full" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="ln" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#2aa6e6"/>
                                        <stop offset="1" stop-color="#3ccd68"/>
                                    </linearGradient>
                                </defs>
                                <polyline points="6,96 48,68 90,82 132,52 174,58 216,30 252,12" fill="none" stroke="url(#ln)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="48" cy="68" r="4" fill="#2aa6e6"/>
                                <circle cx="132" cy="52" r="4" fill="#34bf83"/>
                                <circle cx="216" cy="30" r="4" fill="#3ccd68"/>
                                <circle cx="252" cy="12" r="4" fill="#3ccd68"/>
                            </svg>
                            <div class="mt-4 flex gap-3">
                                <div class="flex-1 rounded-xl border border-white/10 bg-white/5 px-3 py-[11px]">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#229ed9]">
                                            <svg viewBox="0 0 24 24" class="h-[11px] w-[11px] fill-white"><path d="M21.9 4.3 2.7 11.6c-1 .4-1 1.8 0 2.1l4.8 1.6 1.9 5.6c.3.8 1.3.9 1.8.3l2.5-2.7 4.8 3.5c.6.4 1.5.1 1.7-.7L23 5.4c.2-.9-.7-1.5-1.1-1.1Z"/></svg>
                                        </span>
                                        <span class="text-xs text-white/85">Telegram</span>
                                    </div>
                                    <span class="mt-[9px] block h-[6px] w-[62%] rounded bg-white/15"></span>
                                </div>
                                <div class="flex-1 rounded-xl border border-white/10 bg-white/5 px-3 py-[11px]">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#3ccd68]">
                                            <svg viewBox="0 0 24 24" class="h-[11px] w-[11px] fill-white"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.2 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.1.1.3 0 .5l-.7 1c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.4.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                        </span>
                                        <span class="text-xs text-white/85">WhatsApp</span>
                                    </div>
                                    <span class="mt-[9px] block h-[6px] w-[62%] rounded bg-white/15"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- trust badge --}}
                    <div class="mt-[30px] inline-flex items-center gap-3 self-start rounded-2xl border border-white/10 bg-slate-950/25 px-4 py-3 text-[13.5px] text-white/90 backdrop-blur-md">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#3ccd68]/25 bg-[#0f233f]/70 text-[#3ccd68]">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current stroke-[1.9]"><path d="M12 2 4.5 5.5v5.9c0 4.9 3.2 9.3 7.5 10.6 4.3-1.3 7.5-5.7 7.5-10.6V5.5L12 2Z"/><path d="M9.4 12.4 11.2 14l3.4-3.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span>Seguro, confiable y siempre disponible.</span>
                    </div>
                </section>

                {{-- RIGHT --}}
                <section class="flex items-center justify-center px-6 py-9 sm:px-11">
                    <div class="w-full max-w-[392px] rounded-3xl bg-white p-8 shadow-[0_30px_60px_-38px_rgba(3,13,31,0.5)]" x-data="{ showPassword: false }">
                        <div class="text-center">
                            <h2 class="text-[30px] font-bold tracking-[-0.03em] text-slate-900">Iniciar sesión</h2>
                            <p class="mt-2 text-sm text-slate-500">Accede a tu cuenta de Benditio</p>
                        </div>

                        <x-auth-session-status class="!mb-0 mt-4" :status="session('status')" />

                        <form method="POST" action="{{ route('login') }}" class="mt-[26px] flex flex-col gap-[18px]">
                            @csrf

                            {{-- email --}}
                            <div>
                                <x-input-label for="email" :value="__('Correo electrónico')" class="!mb-2 text-[12.5px] font-semibold text-slate-700" />
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-[13px] top-1/2 -translate-y-1/2 text-slate-400">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current stroke-[1.8]"><path d="M4 6.5h16a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 20 17.5H4A1.5 1.5 0 0 1 2.5 16V8A1.5 1.5 0 0 1 4 6.5Z"/><path d="m3.5 8 8.2 5.1a1 1 0 0 0 1 0L20.9 8"/></svg>
                                    </span>
                                    <x-text-input id="email" class="mt-0 block w-full rounded-[10px] border-slate-200 bg-[#f1f5fb] py-[11px] pl-[38px] pr-[14px] text-[13.5px] shadow-none placeholder:text-slate-400 focus:border-[#1270df] focus:bg-white focus:ring-[#1270df]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="tu@negocio.com" />
                                </div>
                                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                            </div>

                            {{-- password --}}
                            <div>
                                <x-input-label for="password" :value="__('Contraseña')" class="!mb-2 text-[12.5px] font-semibold text-slate-700" />
                                <div class="relative">
                                    <x-text-input
                                        id="password"
                                        class="mt-0 block w-full rounded-[10px] border-slate-200 bg-[#f1f5fb] py-[11px] pl-[14px] pr-[42px] text-[13.5px] shadow-none placeholder:text-slate-400 focus:border-[#1270df] focus:bg-white focus:ring-[#1270df]"
                                        type="password"
                                        x-bind:type="showPassword ? 'text' : 'password'"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••••"
                                    />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center px-[13px] text-slate-400 transition hover:text-slate-600" :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                                        <svg x-show="!showPassword" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current stroke-[1.8]"><path d="M2.2 12s3.1-6 9.8-6 9.8 6 9.8 6-3.1 6-9.8 6-9.8-6-9.8-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current stroke-[1.8]" stroke-linecap="round"><path d="M3 3l18 18"/><path d="M10.6 10.6A3 3 0 0 0 13.4 13.4"/><path d="M9.9 5.1A11 11 0 0 1 12 5c6.7 0 9.8 7 9.8 7a20.6 20.6 0 0 1-4.2 5.2"/><path d="M6.2 6.2C3.8 8 2.2 12 2.2 12s3.1 6 9.8 6c1 0 1.9-.1 2.8-.4"/></svg>
                                    </button>
                                </div>
                                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                            </div>

                            {{-- remember + forgot --}}
                            <div class="flex items-center justify-between gap-4">
                                <label for="remember_me" class="inline-flex items-center gap-2">
                                    <input id="remember_me" type="checkbox" class="h-[15px] w-[15px] rounded border-slate-300 text-[#1270df] focus:ring-[#1270df]" name="remember">
                                    <span class="text-[12.5px] font-medium text-slate-700">Recordarme</span>
                                </label>
                                @if (Route::has('password.request'))
                                    <a class="text-[12.5px] font-semibold text-[#1270df] hover:text-[#0a2342]" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>

                            {{-- submit --}}
                            <button type="submit" class="flex h-11 w-full items-center justify-center rounded-[10px] bg-[linear-gradient(90deg,#1270df_0%,#3ccd68_100%)] text-sm font-semibold text-white shadow-[0_14px_28px_-16px_rgba(18,112,223,0.8)] transition hover:brightness-105">
                                Entrar
                            </button>
                        </form>

                        {{-- divider --}}
                        <div class="my-[22px] flex items-center gap-[14px]">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <span class="text-xs text-slate-400">o continúa con</span>
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>

                        {{-- google --}}
                        <a href="#" class="flex h-11 w-full items-center justify-center gap-[10px] rounded-[10px] border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]"><path fill="#4285F4" d="M22.5 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2.1-1.9 3.2-4.8 3.2-7.9Z"/><path fill="#34A853" d="M12 23c2.9 0 5.4-1 7.2-2.6l-3.6-2.7c-1 .7-2.3 1.1-3.6 1.1-2.8 0-5.1-1.9-6-4.4H2.3v2.8A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M6 14.4a6.6 6.6 0 0 1 0-4.2V7.4H2.3a11 11 0 0 0 0 9.8L6 14.4Z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3 .5 4.1 1.6l3.1-3.1A11 11 0 0 0 12 1 11 11 0 0 0 2.3 7.4L6 10.2C6.9 7.7 9.2 5.4 12 5.4Z"/></svg>
                            <span>Google</span>
                        </a>

                        <p class="mt-[22px] text-center text-[12.5px] text-slate-500">
                            ¿No tienes una cuenta?
                            <a href="mailto:soporte@benditio.com" class="font-semibold text-[#1270df] hover:text-[#0a2342]">Contacta a tu administrador</a>
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-guest-layout>
