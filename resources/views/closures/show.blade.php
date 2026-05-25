<x-app-layout>
    <div class="space-y-6">
        <style>
            @media print {
                .no-print { display: none !important; }
                body { background: white !important; }
                .print-break { break-inside: avoid; }
                .print-page { box-shadow: none !important; border-color: #e5e7eb !important; }
            }
        </style>

        <div class="flex items-start justify-between gap-4 no-print">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Detalle de cierre</h1>
                <p class="mt-1 text-sm text-slate-600">Resumen operativo imprimible para este cierre diario.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Imprimir</button>
                <a href="{{ route('closures.export', $closure) }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Exportar CSV</a>
                <a href="{{ route('closures.index') }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Volver</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">
                <div class="print-page rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <div class="text-sm text-slate-500">Organización</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->organization?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Sucursal</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->branch?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Fecha de cierre</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Cerrado por</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closedByUser?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Cerrado a las</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Monto total confirmado</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_amount_confirmed }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total de pedidos</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_requests }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total confirmados</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_confirmed }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total rechazados</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_rejected }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total pendientes</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_pending }}</div>
                        </div>
                        <div class="sm:col-span-2 xl:col-span-3">
                            <div class="text-sm text-slate-500">Notas</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $closure->notes ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 no-print">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Acciones del cierre</h2>
                    <p class="mt-2 text-sm text-slate-600">Los totales anteriores son inmutables. La lista de pedidos de abajo se consulta en vivo para la misma sucursal y fecha.</p>
                </div>
            </div>
        </div>

        <div class="print-page rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Pedidos incluidos</h2>
                <span class="text-sm text-slate-500">{{ $requests->count() }} pedidos</span>
            </div>
            <div class="mt-4 overflow-hidden rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">ID</th>
                            <th class="px-3 py-2 text-left font-medium">Cliente</th>
                            <th class="px-3 py-2 text-left font-medium">Teléfono</th>
                            <th class="px-3 py-2 text-left font-medium">Detalle</th>
                            <th class="px-3 py-2 text-left font-medium">Monto</th>
                            <th class="px-3 py-2 text-left font-medium">Estado</th>
                            <th class="px-3 py-2 text-left font-medium">Confirmado / Rechazado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($requests as $request)
                            <tr>
                                <td class="px-3 py-2 text-slate-900">{{ $request->id }}</td>
                                <td class="px-3 py-2 text-slate-900">{{ $request->customer?->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $request->customer?->phone ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $request->detected_number ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $request->detected_amount ?? '-' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ str_replace('_', ' ', $request->status) }}</td>
                                <td class="px-3 py-2 text-slate-600">
                                    <div>Confirmado: {{ $request->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                    <div>Rechazado: {{ $request->rejected_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-slate-500">No se encontraron pedidos para esta fecha de cierre.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
