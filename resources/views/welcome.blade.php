<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'BotPedidos') }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-white">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(10,61,145,0.45),transparent_40%),linear-gradient(180deg,#081F4D_0%,#0B1220_100%)]"></div>
            <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-6 py-16">
                <div class="max-w-3xl space-y-8">
                    <div class="brand-badge bg-white/10 text-white">Bot de pedidos</div>
                    <div class="space-y-4">
                        <h1 class="text-5xl font-semibold tracking-tight sm:text-6xl">BotPedidos</h1>
                        <p class="max-w-2xl text-lg text-slate-200">
                            RecepciÃ³n automÃ¡tica de pedidos por chat. Telegram primero. WhatsApp prÃ³ximamente.
                        </p>
                    </div>

                    <div class="grid gap-3 text-sm text-slate-200 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">Bandeja de mensajes</div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">RevisiÃ³n de pedidos</div>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">Cierres diarios</div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="brand-btn-primary">Ir al panel</a>
                        @else
                            <a href="{{ route('login') }}" class="brand-btn-primary">Entrar</a>
                        @endauth
                    </div>
                </div>
            </div>

            <section class="tech-section relative mx-auto max-w-6xl px-6 pb-4" id="tecnologias">
                <div class="w-full">
                    <span class="section-kicker inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-slate-300">
                        Tecnolog&iacute;as que utilizamos
                    </span>

                    <div class="tech-logos mt-6 flex flex-wrap items-center gap-x-10 gap-y-6">
                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <defs>
                                    <linearGradient id="flutter-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#47C5FB" />
                                        <stop offset="100%" stop-color="#00569E" />
                                    </linearGradient>
                                </defs>
                                <path d="M6 17.5 17.2 6.3h8.4L14.4 17.5l11.2 11.2h-8.4L6 17.5Z" fill="url(#flutter-grad)" />
                                <path d="M14.4 17.5 21 10.9h4.6l-8.4 8.4-2.8-1.8Z" fill="#47C5FB" opacity="0.9" />
                            </svg>
                            <span>Flutter</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <path d="M8 25.5 16 6.5l8 19h-4l-1.7-4H13.7l-1.7 4H8Zm7.2-7.3h1.6L16 14.3l-.8 3.9Z" fill="#FF2D20" />
                                <path d="M11.7 11.3c1.2-1.7 3.1-2.8 5.1-2.8 1.3 0 2.5.4 3.6 1.1l-1.5 2.8c-.7-.4-1.4-.6-2.2-.6-1.1 0-2.1.5-2.8 1.4-.7.9-1.2 2.1-1.2 3.6 0 1.5.5 2.7 1.2 3.6.7.9 1.7 1.4 2.8 1.4.8 0 1.5-.2 2.2-.6l1.5 2.8c-1.1.7-2.3 1.1-3.6 1.1-2 0-3.9-1.1-5.1-2.8-1.2-1.7-1.9-4-1.9-5.5s.7-3.8 1.9-5.5Z" fill="#FF2D20" opacity="0.92" />
                            </svg>
                            <span>Laravel</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <rect x="6.5" y="6.5" width="19" height="19" rx="5.5" fill="#512BD4" />
                                <path d="M11.2 12.2h2.5v7.6h-2.5v-7.6Zm4.2 0h2.4l2.5 4.1v-4.1h2.5v7.6h-2.3l-2.6-4.2v4.2h-2.5v-7.6Z" fill="#fff" />
                            </svg>
                            <span>.NET</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <path d="M6.5 23.5 16 8.5l9.5 15h-19Z" fill="#0078D4" />
                                <path d="M16 8.5h9.5L19.5 16 16 8.5Z" fill="#50E6FF" opacity="0.9" />
                            </svg>
                            <span>Azure</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <circle cx="16" cy="16" r="11" fill="#4479A1" />
                                <path d="M11 12.2h2.3l1.4 5.1 1.4-5.1h2.3l-2.7 7.6h-2L11 12.2Z" fill="#fff" />
                                <path d="M20.9 12.2h2.1v7.6h-2.1v-7.6Z" fill="#fff" />
                            </svg>
                            <span>MySQL</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <path d="M16 5.6c5.7 0 10.4 4.3 10.4 9.6S21.7 24.8 16 24.8 5.6 20.5 5.6 15.2 10.3 5.6 16 5.6Z" fill="#336791" />
                                <path d="M12.3 20.3c1 .7 2.2 1.1 3.8 1.1 2.2 0 4-.6 5.3-1.8-.9 2.2-3.3 3.8-6.2 3.8-2.9 0-5.3-1.6-6.2-3.8.9.5 1.9.7 3.3.7Z" fill="#fff" opacity="0.9" />
                            </svg>
                            <span>PostgreSQL</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <defs>
                                    <linearGradient id="openai-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#7AD7C8" />
                                        <stop offset="100%" stop-color="#10A37F" />
                                    </linearGradient>
                                </defs>
                                <path d="M16 5.6c5.8 0 10.4 4.6 10.4 10.4S21.8 26.4 16 26.4 5.6 21.8 5.6 16 10.2 5.6 16 5.6Z" fill="url(#openai-grad)" />
                                <path d="M12.2 11.5a4.4 4.4 0 0 1 7.6 2.1 4.4 4.4 0 0 1-1.5 8.5h-3.6a4.4 4.4 0 0 1-2.5-8.1 4.4 4.4 0 0 1 0-2.5Z" fill="#fff" opacity="0.95" />
                            </svg>
                            <span>OpenAI</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <path d="M6.5 9.5h14a5 5 0 0 1 5 5v3a5 5 0 0 1-5 5H14l-5.5 4v-4.2a5 5 0 0 1-2-4V14.5a5 5 0 0 1 5-5Z" fill="#25D366" />
                                <path d="M11.8 12.8c.5-.2 1-.2 1.2.2l.8 1.9c.1.3 0 .6-.2.8l-.6.6c-.2.2-.2.5-.1.7.5.9 1.4 1.8 2.3 2.3.2.1.5.1.7-.1l.6-.6c.2-.2.5-.3.8-.2l1.9.8c.4.2.4.7.2 1.2-.2.5-.6 1-1.2 1-4.3 0-7.8-3.5-7.8-7.8 0-.6.5-1 1-1.2Z" fill="#fff" />
                            </svg>
                            <span>WhatsApp</span>
                        </div>

                        <div class="tech-logo-item inline-flex items-center gap-3 text-[18px] font-bold text-[#DCE8FB] opacity-95">
                            <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true">
                                <path d="M6.3 15.8 25 8.5c.8-.3 1.5.4 1.2 1.2l-3 13.8c-.2.8-1.2 1.1-1.8.6l-4.2-3.2-2.2 2.2c-.4.4-1 .4-1.4 0l.2-4.7 8.5-7.7c.2-.2 0-.5-.3-.4l-10.4 6.5-4.2-1.4c-.8-.3-.8-1.4 0-1.7Z" fill="#229ED9" />
                            </svg>
                            <span>Telegram</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="final-cta relative mx-auto max-w-6xl px-6 pb-12 pt-2">
                <div class="w-full">
                    <div class="final-cta-card relative grid min-h-[145px] grid-cols-1 items-center gap-6 overflow-hidden rounded-[20px] border border-sky-400/25 bg-[linear-gradient(135deg,rgba(255,255,255,0.045),rgba(37,99,235,0.06))] px-6 py-8 shadow-[0_24px_60px_-42px_rgba(37,99,235,0.55)] backdrop-blur-md sm:px-10 lg:grid-cols-[1.1fr_1fr_auto] lg:px-11">
                        <div>
                            <h2 class="m-0 text-[clamp(2rem,4vw,2.25rem)] font-semibold leading-[1.05] tracking-[-0.06em] text-white">
                                &iquest;Tienes una idea?<br>
                                <span class="text-[#2F82FF]">Conversemos.</span>
                            </h2>
                        </div>

                        <p class="m-0 max-w-xl text-base leading-7 text-[#B8C5DC]">
                            Cu&eacute;ntanos tu proyecto y te ayudaremos a hacerlo realidad.
                        </p>

                        <a href="#contacto" class="brand-btn-primary justify-self-center px-5 py-3 text-sm font-semibold text-white">
                            Agendar reuni&oacute;n
                        </a>

                        <div class="cta-brand-glow pointer-events-none absolute -bottom-14 -right-10 h-[180px] w-[280px] rounded-full bg-[radial-gradient(circle,rgba(37,99,235,0.42),transparent_65%)]"></div>
                        <div class="pointer-events-none absolute right-6 top-1/2 hidden h-24 w-24 -translate-y-1/2 rounded-full border border-sky-400/20 bg-sky-500/10 blur-[1px] lg:block"></div>
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
