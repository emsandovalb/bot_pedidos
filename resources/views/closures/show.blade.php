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
                <h1 class="text-2xl font-semibold text-slate-900">Closure Detail</h1>
                <p class="mt-1 text-sm text-slate-600">Printable operational summary for this daily closure.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Print</button>
                <a href="{{ route('closures.export', $closure) }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Export CSV</a>
                <a href="{{ route('closures.index') }}" class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-4">
                <div class="print-page rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <div class="text-sm text-slate-500">Organization</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->organization?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Branch</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->branch?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Closure date</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closure_date?->format('Y-m-d') }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Closed by</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closedByUser?->name ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Closed at</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->closed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total amount confirmed</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_amount_confirmed }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total requests</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_requests }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total confirmed</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_confirmed }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total rejected</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_rejected }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-slate-500">Total pending</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $closure->total_pending }}</div>
                        </div>
                        <div class="sm:col-span-2 xl:col-span-3">
                            <div class="text-sm text-slate-500">Notes</div>
                            <div class="mt-1 whitespace-pre-wrap text-sm text-slate-900">{{ $closure->notes ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4 no-print">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Snapshot actions</h2>
                    <p class="mt-2 text-sm text-slate-600">The totals above are immutable. The request list below is queried live for the same branch and closure date.</p>
                </div>
            </div>
        </div>

        <div class="print-page rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">Included requests</h2>
                <span class="text-sm text-slate-500">{{ $requests->count() }} requests</span>
            </div>
            <div class="mt-4 overflow-hidden rounded-md border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">ID</th>
                            <th class="px-3 py-2 text-left font-medium">Customer</th>
                            <th class="px-3 py-2 text-left font-medium">Phone</th>
                            <th class="px-3 py-2 text-left font-medium">Detected #</th>
                            <th class="px-3 py-2 text-left font-medium">Amount</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Confirmed / Rejected</th>
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
                                    <div>Confirmed: {{ $request->confirmed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                    <div>Rejected: {{ $request->rejected_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-slate-500">No requests found for this closure date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
