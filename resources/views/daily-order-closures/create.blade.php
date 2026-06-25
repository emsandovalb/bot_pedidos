<x-app-layout>
    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-emerald-50 shadow-[0_24px_80px_-40px_rgba(15,23,42,0.35)]">
            <div class="px-6 py-8 sm:px-8 lg:px-10">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <div class="inline-flex items-center rounded-full border border-brand-primary/10 bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-brand-primary">
                            Cierres diarios
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                            Nuevo cierre diario
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Genera una fotografía operativa de los pedidos de una sucursal en una fecha específica.
                        </p>
                    </div>

                    <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                        Volver
                    </a>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
            <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                <div class="border-b border-slate-200/70 px-6 py-5">
                    <h2 class="text-lg font-semibold text-brand-navy">Datos del cierre</h2>
                    <p class="mt-1 text-sm text-slate-600">Completa la sucursal, la fecha y las notas opcionales.</p>
                </div>

                <form method="POST" action="{{ route('daily-order-closures.store') }}" class="space-y-6 px-6 py-6 sm:px-8">
                    @csrf

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                            <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-brand-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-primary/10">
                                <option value="">Seleccione una sucursal</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="closure_date" class="block text-sm font-medium text-slate-700">Fecha</label>
                            <input id="closure_date" name="closure_date" type="date" value="{{ old('closure_date', $selectedClosureDate) }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-brand-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-primary/10">
                            @error('closure_date')
                                <p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notas</label>
                        <textarea id="notes" name="notes" rows="6" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-brand-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-primary/10" placeholder="Observaciones, eventos operativos o contexto útil para revisar después.">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-primary px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-primary/20 transition hover:-translate-y-0.5 hover:bg-brand-primary/90">
                            Crear cierre
                        </button>
                        <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-brand-primary/30 hover:text-brand-primary">
                            Volver
                        </a>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                    <div class="border-b border-slate-200/70 px-6 py-5">
                        <h2 class="text-lg font-semibold text-brand-navy">Importante</h2>
                    </div>
                    <div class="px-6 py-6">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-sm leading-6 text-emerald-900">
                            Este cierre no bloquea pedidos ni modifica estados. Solo guarda un resumen operativo del día seleccionado.
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-slate-50 to-white shadow-[0_18px_55px_-35px_rgba(15,23,42,0.45)]">
                    <div class="border-b border-slate-200/70 px-6 py-5">
                        <h2 class="text-lg font-semibold text-brand-navy">Consejo</h2>
                    </div>
                    <div class="px-6 py-6 text-sm leading-6 text-slate-600">
                        Usa una fecha cerrada solo cuando quieras congelar una fotografía operativa del día. Puedes revisar o exportar el resultado más tarde sin afectar el flujo de pedidos.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
