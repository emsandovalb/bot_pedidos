<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.10),rgba(22,163,74,0.08)_42%,rgba(255,255,255,1)_84%)] shadow-sm">
            <div class="relative px-6 py-7 lg:px-8">
                <div class="max-w-4xl space-y-4">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-primary ring-1 ring-inset ring-blue-100">
                        Configuración
                    </div>
                    <div class="space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Oria · Pago de servicios</h1>
                        <p class="max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">
                            Conecta {{ $organization->name }} con su cuenta de Oria. Con el plan Plus, el menú muestra
                            <span class="font-semibold text-brand-navy">Pagar servicios</span> y abre Oria sin volver a iniciar sesión.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        <section class="rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-sm">
            @if (! $configured && ! $connection)
                <h2 class="text-xl font-semibold text-brand-navy">Oria no está configurada</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Falta <code>ORIA_API_URL</code> u <code>ORIA_PARTNER_KEY</code> en el entorno. La llave se genera en Oria con
                    <code>php artisan oria:partner-key bot_pedidos</code>.
                </p>
            @elseif (! $connection)
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-2">
                        <h2 class="text-xl font-semibold text-brand-navy">Sin conectar</h2>
                        <p class="max-w-3xl text-sm leading-6 text-slate-600">
                            Te llevaremos a Oria para que entres con la cuenta del negocio y apruebes la conexión. Oria decide el plan.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.oria.connect') }}">
                        @csrf
                        <button type="submit" class="rounded-full bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-navy">
                            Conectar con Oria
                        </button>
                    </form>
                </div>
            @else
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div @class([
                            'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-inset',
                            'bg-emerald-50 text-emerald-700 ring-emerald-100' => $connection->isPlus(),
                            'bg-slate-100 text-slate-600 ring-slate-200' => ! $connection->isPlus(),
                        ])>
                            {{ $connection->isPlus() ? 'Plan Plus' : 'Plan Básico' }}
                        </div>
                        <h2 class="text-xl font-semibold text-brand-navy">Conectado con Oria</h2>
                        <p class="text-sm leading-6 text-slate-600">
                            Cuenta: <span class="font-semibold text-brand-navy">{{ $connection->account_name ?? '—' }}</span> · {{ $connection->account_email }}
                        </p>
                        <p class="max-w-3xl text-sm leading-6 text-slate-500">
                            @if ($connection->isPlus())
                                "Pagar servicios" abre Oria con esta cuenta para pagar luz, agua, internet y telefonía.
                            @else
                                Con el plan Básico no se muestra "Pagar servicios". El plan Plus lo activa el equipo de Oria.
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($connection->isPlus())
                            <a href="{{ route('settings.oria.pay-services') }}" class="rounded-full bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-navy">
                                Pagar servicios
                            </a>
                        @endif
                        <form method="POST" action="{{ route('settings.oria.disconnect') }}" onsubmit="return confirm('¿Desconectar el negocio de Oria?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-rose-200 hover:text-rose-700">
                                Desconectar
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
