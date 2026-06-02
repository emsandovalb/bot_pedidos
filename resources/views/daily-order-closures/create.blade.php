<x-app-layout>
    <div class="space-y-6">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,#F8FAFC_0%,#EEF4FF_52%,#F8FAFC_100%)] shadow-sm">
            <div class="relative px-6 py-8 sm:px-8 lg:px-10">
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="inline-flex items-center rounded-full border border-[#146EDB]/10 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-[#146EDB] shadow-sm">
                            Benditio · Cierres diarios
                        </div>
                        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-[#0F172A] sm:text-4xl">Nuevo cierre diario</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-[#64748B] sm:text-base">
                            Genera una fotografía operativa de los pedidos de una sucursal en una fecha específica.
                        </p>
                    </div>

                    <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#146EDB]/30 hover:text-[#146EDB]">
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.8fr)]">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('daily-order-closures.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-slate-700">Sucursal</label>
                            <select id="branch_id" name="branch_id" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#146EDB] focus:ring-4 focus:ring-[#146EDB]/10">
                                <option value="">Seleccione una sucursal</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId) === (string) $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="closure_date" class="block text-sm font-medium text-slate-700">Fecha del cierre</label>
                            <input id="closure_date" name="closure_date" type="date" value="{{ old('closure_date', $selectedClosureDate) }}" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-[#146EDB] focus:ring-4 focus:ring-[#146EDB]/10">
                            @error('closure_date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notas</label>
                        <textarea id="notes" name="notes" rows="6" class="mt-2 block w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-[#146EDB] focus:ring-4 focus:ring-[#146EDB]/10">{{ old('notes') }}</textarea>
                        @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#146EDB] px-5 py-3 text-sm font-semibold text-white shadow-[0_10px_30px_rgba(20,110,219,0.22)] transition hover:bg-blue-700">
                            Crear cierre
                        </button>
                        <a href="{{ route('daily-order-closures.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-[#146EDB]/30 hover:text-[#146EDB]">
                            Volver
                        </a>
                    </div>
                </form>
            </div>

            <aside class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#EFF6FF] text-xl text-[#146EDB]">i</div>
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-[#0F172A]">Antes de crear el cierre</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Este cierre no bloquea pedidos ni modifica estados. Solo guarda un resumen operativo del día seleccionado.
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-3 rounded-3xl bg-slate-50 p-5">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-2.5 w-2.5 rounded-full bg-[#146EDB]"></span>
                        <p class="text-sm text-slate-600">Elige la sucursal que quieres analizar.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-2.5 w-2.5 rounded-full bg-[#16A34A]"></span>
                        <p class="text-sm text-slate-600">Selecciona la fecha exacta del resumen.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-2.5 w-2.5 rounded-full bg-slate-500"></span>
                        <p class="text-sm text-slate-600">Agrega notas si hubo incidencias operativas.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
