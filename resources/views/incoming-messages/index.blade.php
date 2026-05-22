<x-app-layout>
    <div class="space-y-6">
        <div>
            <div class="brand-badge bg-brand-primary/10 text-brand-primary">Message feed</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Incoming Messages</h1>
            <p class="mt-1 text-sm text-slate-600">Latest captured messages for the visible scope.</p>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Received</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">From</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">To</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Text</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($messages as $message)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $message->received_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $message->from_identifier }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $message->to_identifier }}</td>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $message->raw_text }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <span class="brand-badge bg-slate-100 text-slate-700">{{ $message->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No messages available for this account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
