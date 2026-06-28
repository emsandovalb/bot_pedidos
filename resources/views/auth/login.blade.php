<x-guest-layout>
    <div style="position:relative;min-height:100vh;width:100%;overflow:hidden;background:#0a2342;display:flex;align-items:center;justify-content:center;box-sizing:border-box;font-family:'Inter',system-ui,-apple-system,sans-serif;">
        <div style="position:fixed;inset:0;background:linear-gradient(120deg,#0a2342 0%,#0c3350 42%,#136046 74%,#1f8f3f 100%);"></div>
        <div style="position:fixed;inset:0;background:radial-gradient(circle at 12% 8%, rgba(18,112,223,.30), transparent 40%),radial-gradient(circle at 98% 4%, rgba(60,205,104,.30), transparent 34%),radial-gradient(circle at 100% 100%, rgba(60,205,104,.35), transparent 42%);"></div>

        <div style="position:relative;width:100%;max-width:1280px;">
            <div style="position:relative;display:grid;grid-template-columns:1fr 432px;min-height:720px;">
                {{-- LEFT --}}
                <section style="padding:44px 52px;color:#fff;display:flex;flex-direction:column;justify-content:space-between;box-sizing:border-box;">
                    <div>
                        <div style="display:flex;align-items:center;">
                            <a href="{{ url('/') }}" style="display:inline-flex;align-items:center;text-decoration:none;">
                                <x-application-logo class="h-14 w-auto" />
                            </a>
                        </div>

                        <h1 style="margin:46px 0 0;font-size:62px;line-height:.95;font-weight:900;letter-spacing:-.05em;">Convierte <span style="color:#3ccd68;">mensajes</span><br>en ventas.</h1>
                        <p style="margin:22px 0 0;font-size:17px;line-height:1.5;color:rgba(226,232,240,.86);max-width:34ch;">Gestiona pedidos, clientes y operaciones desde una sola plataforma.</p>

                        <div style="margin:34px 0 0;display:grid;grid-template-columns:1fr 1fr;column-gap:46px;row-gap:14px;max-width:430px;">
                            @foreach (['Pedidos', 'WhatsApp', 'Telegram', 'Analytics', 'Catálogo', 'Clientes', 'Cierres diarios'] as $feature)
                                <div style="display:flex;align-items:center;gap:11px;font-size:16px;">
                                    <span style="width:21px;height:21px;flex:none;border-radius:50%;background:#3ccd68;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 18px -10px rgba(60,205,104,.9);">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </span>
                                    <span style="color:rgba(255,255,255,.96);">{{ $feature }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div style="position:relative;height:300px;margin:30px 0 0;">
                        <div style="position:absolute;left:60px;top:26px;width:84px;height:84px;border:2px solid rgba(255,255,255,.16);border-radius:50%;"></div>
                        <div style="position:absolute;left:128px;top:64px;width:54px;height:54px;border:2px solid rgba(255,255,255,.16);border-radius:50%;"></div>
                        <div style="position:absolute;left:430px;top:24px;width:52px;height:52px;border:2px solid rgba(255,255,255,.16);border-radius:50%;"></div>

                        <div style="position:absolute;left:474px;top:118px;width:34px;height:34px;border-radius:50%;background:#3ccd68;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 26px -10px rgba(60,205,104,.7);">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.2 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.1.1.3 0 .5-.1.2-.2.4-.3.5l-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.4.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                        </div>

                        <div style="position:absolute;left:178px;top:8px;width:300px;height:268px;border-radius:18px;border:1px solid rgba(255,255,255,.14);background:linear-gradient(180deg,rgba(11,32,58,.62),rgba(8,22,42,.5));padding:20px;overflow:hidden;box-sizing:border-box;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="display:block;width:74px;height:9px;border-radius:6px;background:rgba(255,255,255,.16);"></span>
                                <span style="display:block;width:66px;height:12px;border-radius:7px;background:rgba(60,205,104,.85);"></span>
                            </div>
                            <svg viewBox="0 0 260 118" width="100%" height="118" style="margin-top:14px;display:block;" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="lnchart" x1="0" y1="0" x2="1" y2="0">
                                        <stop offset="0" stop-color="#2aa6e6"/>
                                        <stop offset="1" stop-color="#3ccd68"/>
                                    </linearGradient>
                                </defs>
                                <polyline points="6,96 48,68 90,82 132,52 174,58 216,30 252,12" fill="none" stroke="url(#lnchart)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="48" cy="68" r="4" fill="#2aa6e6"/>
                                <circle cx="132" cy="52" r="4" fill="#34bf83"/>
                                <circle cx="216" cy="30" r="4" fill="#3ccd68"/>
                                <circle cx="252" cy="12" r="4" fill="#3ccd68"/>
                            </svg>
                            <div style="display:flex;gap:12px;margin-top:16px;">
                                <div style="flex:1;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);padding:11px 12px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="width:18px;height:18px;border-radius:50%;background:#229ed9;display:flex;align-items:center;justify-content:center;">
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="#fff"><path d="M21.9 4.3 2.7 11.6c-1 .4-1 1.8 0 2.1l4.8 1.6 1.9 5.6c.3.8 1.3.9 1.8.3l2.5-2.7 4.8 3.5c.6.4 1.5.1 1.7-.7L23 5.4c.2-.9-.7-1.5-1.1-1.1Z"/></svg>
                                        </span>
                                        <span style="font-size:12px;color:rgba(255,255,255,.85);">Telegram</span>
                                    </div>
                                    <span style="display:block;width:62%;height:6px;border-radius:4px;background:rgba(255,255,255,.13);margin-top:9px;"></span>
                                </div>
                                <div style="flex:1;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);padding:11px 12px;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="width:18px;height:18px;border-radius:50%;background:#3ccd68;display:flex;align-items:center;justify-content:center;">
                                            <svg viewBox="0 0 24 24" width="11" height="11" fill="#fff"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.2 14.2c-.2.6-1.2 1.2-1.7 1.2-.4 0-1 .1-3.4-.9-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5.2.5.7 1.8.8 1.9.1.1.1.3 0 .5l-.7 1c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.4.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1l1.8.9c.2.1.4.2.4.3.1.2.1.6-.1 1.1Z"/></svg>
                                        </span>
                                        <span style="font-size:12px;color:rgba(255,255,255,.85);">WhatsApp</span>
                                    </div>
                                    <span style="display:block;width:62%;height:6px;border-radius:4px;background:rgba(255,255,255,.13);margin-top:9px;"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:inline-flex;align-items:center;gap:12px;align-self:flex-start;margin-top:30px;border-radius:16px;border:1px solid rgba(255,255,255,.1);background:rgba(2,6,23,.24);padding:12px 16px;font-size:13.5px;color:rgba(255,255,255,.9);">
                        <span style="width:32px;height:32px;flex:none;border-radius:50%;border:1px solid rgba(60,205,104,.25);background:rgba(15,35,63,.7);color:#3ccd68;display:flex;align-items:center;justify-content:center;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 2 4.5 5.5v5.9c0 4.9 3.2 9.3 7.5 10.6 4.3-1.3 7.5-5.7 7.5-10.6V5.5L12 2Z"/><path d="M9.4 12.4 11.2 14l3.4-3.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <span>Seguro, confiable y siempre disponible.</span>
                    </div>
                </section>

                <section style="display:flex;align-items:center;justify-content:center;padding:36px 44px;box-sizing:border-box;">
                    <div x-data="{ showPassword: false }" style="width:100%;max-width:392px;background:#fff;border-radius:24px;box-shadow:0 30px 60px -38px rgba(3,13,31,.5);padding:34px 32px;box-sizing:border-box;">
                        <div style="text-align:center;">
                            <h2 style="margin:0;font-size:30px;font-weight:700;letter-spacing:-.03em;color:#0f172a;">Iniciar sesión</h2>
                            <p style="margin:8px 0 0;font-size:14px;color:#64748b;">Accede a tu cuenta de Benditio</p>
                        </div>

                        @if (session('status'))
                            <div style="margin-top:16px;font-size:13px;font-weight:600;color:#16a34a;text-align:center;">{{ session('status') }}</div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" style="margin-top:26px;display:flex;flex-direction:column;gap:18px;">
                            @csrf

                            <div>
                                <label for="email" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:8px;">Correo electrónico</label>
                                <div style="position:relative;">
                                    <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;display:flex;">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6.5h16a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 20 17.5H4A1.5 1.5 0 0 1 2.5 16V8A1.5 1.5 0 0 1 4 6.5Z"/><path d="m3.5 8 8.2 5.1a1 1 0 0 0 1 0L20.9 8"/></svg>
                                    </span>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@negocio.com"
                                        style="width:100%;border:1px solid #e2e8f0;border-radius:10px;background:#f1f5fb;padding:11px 14px 11px 38px;font-size:13.5px;color:#0f172a;box-sizing:border-box;outline:none;" />
                                </div>
                                @error('email')
                                    <p style="margin:6px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:8px;">Contraseña</label>
                                <div style="position:relative;">
                                    <input id="password" name="password" required autocomplete="current-password" placeholder="••••••••••"
                                        type="password" x-bind:type="showPassword ? 'text' : 'password'"
                                        style="width:100%;border:1px solid #e2e8f0;border-radius:10px;background:#f1f5fb;padding:11px 42px 11px 14px;font-size:13.5px;color:#0f172a;box-sizing:border-box;outline:none;" />
                                    <button type="button" @click="showPassword = !showPassword" aria-label="Mostrar contraseña"
                                        style="position:absolute;right:0;top:0;height:100%;padding:0 13px;border:0;background:transparent;color:#94a3b8;cursor:pointer;display:flex;align-items:center;">
                                        <svg x-show="!showPassword" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.2 12s3.1-6 9.8-6 9.8 6 9.8 6-3.1 6-9.8 6-9.8-6-9.8-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 3l18 18"/><path d="M10.6 10.6A3 3 0 0 0 13.4 13.4"/><path d="M9.9 5.1A11 11 0 0 1 12 5c6.7 0 9.8 7 9.8 7a20.6 20.6 0 0 1-4.2 5.2"/><path d="M6.2 6.2C3.8 8 2.2 12 2.2 12s3.1 6 9.8 6c1 0 1.9-.1 2.8-.4"/></svg>
                                    </button>
                                </div>
                                @error('password')
                                    <p style="margin:6px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>
                                @enderror
                            </div>

                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
                                <label for="remember_me" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input id="remember_me" type="checkbox" name="remember" style="width:15px;height:15px;accent-color:#1270df;">
                                    <span style="font-size:12.5px;font-weight:500;color:#334155;">Recordarme</span>
                                </label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" style="font-size:12.5px;font-weight:600;color:#1270df;text-decoration:none;">¿Olvidaste tu contraseña?</a>
                                @endif
                            </div>

                            <button type="submit" style="height:44px;width:100%;border:0;border-radius:10px;background:linear-gradient(90deg,#1270df 0%,#3ccd68 100%);color:#fff;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 14px 28px -16px rgba(18,112,223,.8);">Entrar</button>
                        </form>

                        <div style="display:flex;align-items:center;gap:14px;margin:22px 0;">
                            <span style="height:1px;flex:1;background:#e2e8f0;"></span>
                            <span style="font-size:12px;color:#94a3b8;">o continúa con</span>
                            <span style="height:1px;flex:1;background:#e2e8f0;"></span>
                        </div>

                        <a href="#" style="height:44px;width:100%;display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#334155;font-size:14px;font-weight:600;text-decoration:none;box-sizing:border-box;">
                            <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.5 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.9a5 5 0 0 1-2.2 3.3v2.7h3.6c2.1-1.9 3.2-4.8 3.2-7.9Z"/><path fill="#34A853" d="M12 23c2.9 0 5.4-1 7.2-2.6l-3.6-2.7c-1 .7-2.3 1.1-3.6 1.1-2.8 0-5.1-1.9-6-4.4H2.3v2.8A11 11 0 0 0 12 23Z"/><path fill="#FBBC05" d="M6 14.4a6.6 6.6 0 0 1 0-4.2V7.4H2.3a11 11 0 0 0 0 9.8L6 14.4Z"/><path fill="#EA4335" d="M12 5.4c1.6 0 3 .5 4.1 1.6l3.1-3.1A11 11 0 0 0 12 1 11 11 0 0 0 2.3 7.4L6 10.2C6.9 7.7 9.2 5.4 12 5.4Z"/></svg>
                            <span>Google</span>
                        </a>

                        <p style="margin:22px 0 0;text-align:center;font-size:12.5px;color:#64748b;">¿No tienes una cuenta? <a href="mailto:soporte@benditio.com" style="font-weight:600;color:#1270df;text-decoration:none;">Contacta a tu administrador</a></p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-guest-layout>
