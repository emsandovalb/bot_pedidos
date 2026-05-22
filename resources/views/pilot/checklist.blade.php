<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Pilot Checklist</h1>
            <p class="mt-1 text-sm text-slate-600">Use this as a quick readiness check before a controlled pilot.</p>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($items as $item)
                        @php
                            $statusClass = match ($item['status']) {
                                'done' => 'bg-emerald-100 text-emerald-800',
                                'not applicable' => 'bg-slate-100 text-slate-700',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $item['label'] }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $item['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">No checklist items available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
