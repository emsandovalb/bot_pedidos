<x-guest-layout>
    <style>
        .bl-page{position:relative;min-height:100vh;width:100%;overflow:hidden;box-sizing:border-box;font-family:'Poppins','Inter',system-ui,-apple-system,sans-serif;color:#fff;
            background:radial-gradient(ellipse 55% 60% at 100% 0%,rgba(52,199,89,.85),transparent 70%),radial-gradient(ellipse 50% 55% at 100% 100%,rgba(22,110,170,.75),transparent 70%),radial-gradient(ellipse 45% 50% at 62% 8%,rgba(20,130,120,.45),transparent 70%),linear-gradient(110deg,#061a33 0%,#082544 40%,#0b3a55 65%,#0f5a5c 100%);}
        .bl-wrap{position:relative;max-width:1340px;min-height:100vh;margin:0 auto;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,556px);gap:64px;padding:30px 104px 30px 50px;box-sizing:border-box;align-items:stretch;}
        .bl-left{position:relative;display:flex;flex-direction:column;padding:18px 0 0;min-height:600px;}
        .bl-brand{display:inline-flex;align-items:center;gap:12px;text-decoration:none;color:#fff;font-size:28px;font-weight:600;letter-spacing:-.01em;}
        .bl-title{margin:52px 0 0;font-size:46px;line-height:1.08;font-weight:700;letter-spacing:-.02em;}
        .bl-title span{color:#3ccd68;}
        .bl-sub{margin:20px 0 0;font-size:18px;line-height:1.6;color:rgba(255,255,255,.88);max-width:23em;}
        .bl-features{margin:42px 0 0;display:grid;grid-template-columns:150px 1fr;column-gap:2px;max-width:340px;}
        .bl-features ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;}
        .bl-features li{display:flex;align-items:center;gap:11px;font-size:17px;font-weight:500;}
        .bl-check{width:22px;height:22px;flex:none;border-radius:50%;background:#3ccd68;display:flex;align-items:center;justify-content:center;}
        .bl-secure{margin-top:auto;display:inline-flex;align-items:center;gap:14px;align-self:flex-start;min-width:310px;box-sizing:border-box;border-radius:8px;border:1px solid rgba(255,255,255,.14);background:rgba(2,10,25,.35);padding:14px 22px;font-size:14px;color:rgba(255,255,255,.92);}
        .bl-deco{position:absolute;inset:0;pointer-events:none;}
        .bl-bubble{position:absolute;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.08);box-shadow:0 0 0 1px rgba(255,255,255,.04),0 20px 40px -18px rgba(0,0,0,.5);}
        .bl-chart{position:absolute;right:0;top:345px;width:230px;height:190px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(18,48,82,.55);backdrop-filter:blur(2px);padding:14px 16px;box-sizing:border-box;}
        .bl-mini{flex:1;height:52px;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;padding:0 14px;}
        .bl-mini i{display:block;height:4px;border-radius:3px;background:rgba(255,255,255,.3);}
        .bl-card{align-self:stretch;background:#fff;border-radius:18px;box-shadow:0 30px 70px -40px rgba(3,13,31,.6);padding:44px 48px 36px;box-sizing:border-box;color:#0f172a;display:flex;flex-direction:column;justify-content:center;position:relative;z-index:1;}
        .bl-card h2{margin:0;text-align:center;font-size:30px;font-weight:600;letter-spacing:-.01em;}
        .bl-card .bl-lead{margin:10px 0 0;text-align:center;font-size:15px;color:#475569;}
        .bl-label{display:block;font-size:14px;font-weight:500;color:#1e293b;margin-bottom:9px;}
        .bl-input{width:100%;height:46px;border:1px solid #d7dde6;border-radius:7px;background:#fff;font-size:14px;color:#0f172a;box-sizing:border-box;outline:none;font-family:inherit;}
        .bl-input:focus{border-color:#1270df;box-shadow:0 0 0 3px rgba(18,112,223,.15);}
        .bl-input::placeholder{color:#9aa4b2;}
        .bl-link{font-size:13px;font-weight:500;color:#1270df;text-decoration:none;}
        .bl-link:hover{text-decoration:underline;}
        .bl-submit{height:47px;width:100%;border:0;border-radius:6px;background:linear-gradient(90deg,#1565c0 0%,#1d7fd0 35%,#3ccd68 100%);color:#fff;font-size:15px;font-weight:500;cursor:pointer;font-family:inherit;}
        .bl-submit:hover{filter:brightness(1.05);}
        .bl-divider{display:flex;align-items:center;gap:16px;margin:26px 0 0;font-size:13px;color:#64748b;}
        .bl-divider::before,.bl-divider::after{content:"";flex:1;height:1px;background:#e2e8f0;}
        .bl-google{margin-top:16px;height:45px;width:100%;border:1px solid #d7dde6;border-radius:6px;background:#fff;display:flex;align-items:center;justify-content:center;gap:12px;font-size:15px;font-weight:500;color:#1e293b;text-decoration:none;box-sizing:border-box;}
        .bl-google:hover{background:#f8fafc;}
        .bl-form{margin-top:34px;display:flex;flex-direction:column;gap:24px;}
        @media (max-width:1300px){
            .bl-wrap{grid-template-columns:minmax(0,1fr) minmax(0,460px);gap:48px;padding:30px 40px;}
            .bl-deco{display:none;}
            .bl-left{min-height:0;}
            .bl-secure{min-width:0;white-space:nowrap;}
            .bl-card{padding:40px 36px 32px;}
        }
        @media (max-width:960px){
            .bl-wrap{grid-template-columns:minmax(0,460px);justify-content:center;align-content:center;gap:32px;padding:40px 24px;}
            .bl-left{align-items:center;text-align:center;padding:0;}
            .bl-title{margin-top:28px;font-size:34px;line-height:1.15;}
            .bl-title br{display:none;}
            .bl-sub{margin-top:12px;font-size:16px;}
            .bl-features,.bl-secure{display:none;}
            .bl-card{align-self:auto;}
        }
        @media (max-width:520px){
            .bl-wrap{align-content:start;gap:22px;padding:24px 16px;}
            .bl-brand{font-size:24px;}
            .bl-brand img{width:28px;height:34px;}
            .bl-title{margin-top:18px;font-size:27px;}
            .bl-sub{display:none;}
            .bl-card{padding:28px 20px 24px;border-radius:16px;}
            .bl-card h2{font-size:26px;}
            .bl-form{margin-top:24px;gap:18px;}
        }
    </style>

    <div class="bl-page">
        <div class="bl-wrap">
            {{-- LEFT --}}
            <section class="bl-left">
                <a href="{{ url('/') }}" class="bl-brand" aria-label="Benditio">
                    <img src="{{ asset('images/benditio-mark.svg') }}" alt="" width="34" height="41" style="display:block;">

                    <span>Benditio</span>
                </a>

                <h1 class="bl-title">Convierte <br><span>mensajes</span> en <br>ventas.</h1>
                <p class="bl-sub">Gestiona pedidos, clientes y operaciones desde una sola plataforma.</p>

                <div class="bl-features">
                    @foreach ([['Pedidos', 'WhatsApp', 'Telegram'], ['Analytics', 'Catálogo', 'Clientes', 'Cierres diarios']] as $column)
                        <ul>
                            @foreach ($column as $feature)
                                <li>
                                    <span class="bl-check">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                    </span>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>

                <div class="bl-deco" aria-hidden="true">
                    <svg style="position:absolute;right:0;top:120px;" width="280" height="260" viewBox="0 0 320 300" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="1.6" stroke-dasharray="4 6">
                        <path d="M150 10C70 20 40 90 70 150s80 70 100 120"/>
                        <path d="M120 40c-50 20-70 80-40 120"/>
                        <path d="M300 110c-20 60-60 90-110 100"/>
                        <circle cx="210" cy="200" r="14"/>
                    </svg>

                    <div class="bl-bubble" style="right:150px;top:76px;width:76px;height:76px;">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="rgba(255,255,255,.75)"><path d="M21.9 4.3 2.7 11.6c-1 .4-1 1.8 0 2.1l4.8 1.6 1.9 5.6c.3.8 1.3.9 1.8.3l2.5-2.7 4.8 3.5c.6.4 1.5.1 1.7-.7L23 5.4c.2-.9-.7-1.5-1.1-1.1ZM9.6 15l-.4 3.6-1.3-3.9L18 8.6Z"/></svg>
                    </div>
                    <div class="bl-bubble" style="right:40px;top:164px;width:76px;height:76px;background:rgba(40,170,140,.22);">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="rgba(255,255,255,.85)" stroke-width="1.6" stroke-linejoin="round"><path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3Z"/><path d="M9 8.2c.3-.4.8-.4 1 0l.8 1.7c.1.3 0 .6-.2.8l-.5.5a5.6 5.6 0 0 0 2.7 2.7l.5-.5c.2-.2.5-.3.8-.2l1.7.8c.4.2.4.7 0 1-.9.9-2 1.1-3.3.5a8.6 8.6 0 0 1-4-4c-.6-1.3-.4-2.4.5-3.3Z" fill="rgba(255,255,255,.85)" stroke="none"/></svg>
                    </div>

                    <div class="bl-chart">
                        <svg viewBox="0 0 280 110" width="100%" height="110" preserveAspectRatio="none">
                            <g stroke="rgba(255,255,255,.07)" stroke-width="1">
                                <line x1="18" y1="10" x2="280" y2="10"/><line x1="18" y1="34" x2="280" y2="34"/><line x1="18" y1="58" x2="280" y2="58"/><line x1="18" y1="82" x2="280" y2="82"/><line x1="18" y1="104" x2="280" y2="104"/>
                            </g>
                            <polyline points="24,104 64,82 96,94 128,72 154,52 184,66 214,44 246,48 274,36" fill="none" stroke="#3ccd68" stroke-width="2.4" stroke-linejoin="round"/>
                            <polyline points="24,104 64,82 96,94" fill="none" stroke="#1d7fd0" stroke-width="2.4" stroke-linejoin="round"/>
                            <g fill="#3ccd68"><circle cx="154" cy="52" r="3.6"/><circle cx="214" cy="44" r="3.6"/><circle cx="274" cy="36" r="3.6"/></g>
                            <circle cx="64" cy="82" r="3.6" fill="#1d7fd0"/>
                        </svg>
                        <div style="display:flex;gap:12px;margin-top:22px;">
                            <div class="bl-mini">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#38bdf8" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3C7 3 3 6.7 3 11.3c0 2.6 1.3 4.9 3.3 6.4V21l3-1.7c.9.3 1.8.4 2.7.4 5 0 9-3.7 9-8.4S17 3 12 3Z"/><path d="m7.5 13.5 3-3 2 2 3.5-3.5" stroke-linecap="round"/></svg>
                                <div style="display:flex;flex-direction:column;gap:6px;flex:1;"><i style="width:70%"></i><i style="width:45%"></i></div>
                            </div>
                            <div class="bl-mini">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#3ccd68" stroke-width="1.8" stroke-linejoin="round"><path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3Z"/></svg>
                                <div style="display:flex;flex-direction:column;gap:6px;flex:1;"><i style="width:70%"></i><i style="width:45%"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bl-secure">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#3ccd68" stroke-width="1.8"><path d="M12 2.5 4.5 5.5v5.9c0 4.9 3.2 9.3 7.5 10.6 4.3-1.3 7.5-5.7 7.5-10.6V5.5L12 2.5Z"/><path d="M9.2 12.2 11.2 14l3.6-3.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Seguro, confiable y siempre disponible.</span>
                </div>
            </section>

            {{-- RIGHT --}}
            <section class="bl-card" x-data="{ showPassword: false }">
                <h2>Iniciar sesión</h2>
                <p class="bl-lead">Accede a tu cuenta de Benditio</p>

                @if (session('status'))
                    <div style="margin-top:16px;font-size:13px;font-weight:600;color:#16a34a;text-align:center;">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="bl-form">
                    @csrf

                    <div>
                        <label for="email" class="bl-label">Correo electrónico</label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#64748b;display:flex;">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5.5" width="18" height="13" rx="1.8"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@negocio.com"
                                class="bl-input" style="padding:0 16px 0 48px;" />
                        </div>
                        @error('email')
                            <p style="margin:6px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="bl-label">Contraseña</label>
                        <div style="position:relative;">
                            <input id="password" name="password" required autocomplete="current-password" placeholder="•••••••••"
                                type="password" x-bind:type="showPassword ? 'text' : 'password'"
                                class="bl-input" style="padding:0 48px 0 16px;" />
                            <button type="button" @click="showPassword = !showPassword" aria-label="Mostrar contraseña"
                                style="position:absolute;right:0;top:0;height:100%;padding:0 16px;border:0;background:transparent;color:#64748b;cursor:pointer;display:flex;align-items:center;">
                                <svg x-show="!showPassword" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2.2 12s3.1-6 9.8-6 9.8 6 9.8 6-3.1 6-9.8 6-9.8-6-9.8-6Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 3l18 18"/><path d="M10.6 10.6A3 3 0 0 0 13.4 13.4"/><path d="M9.9 5.1A11 11 0 0 1 12 5c6.7 0 9.8 7 9.8 7a20.6 20.6 0 0 1-4.2 5.2"/><path d="M6.2 6.2C3.8 8 2.2 12 2.2 12s3.1 6 9.8 6c1 0 1.9-.1 2.8-.4"/></svg>
                            </button>
                        </div>
                        @error('password')
                            <p style="margin:6px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <label for="remember_me" style="display:inline-flex;align-items:center;gap:12px;cursor:pointer;">
                            <input id="remember_me" type="checkbox" name="remember" style="width:18px;height:18px;margin:0;accent-color:#1270df;">
                            <span style="font-size:14px;color:#1e293b;">Recordarme</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="bl-link">¿Olvidaste tu contraseña?</a>
                        @endif
                    </div>

                    <button type="submit" class="bl-submit" style="margin-top:6px;">Entrar</button>
                </form>

                @if (Route::has('auth.google'))
                    <div class="bl-divider">o continúa con</div>
                    <a href="{{ route('auth.google') }}" class="bl-google">
                        <svg width="22" height="22" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2A11.9 11.9 0 0 1 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3a12 12 0 0 1-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
                        Google
                    </a>
                @endif

                <p style="margin:30px 0 0;text-align:center;font-size:14px;color:#475569;">¿No tienes una cuenta? <a href="mailto:soporte@benditio.com" class="bl-link" style="font-size:14px;">Contacta a tu administrador</a></p>
            </section>
        </div>
    </div>
</x-guest-layout>
