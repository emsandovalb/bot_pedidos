<x-app-layout>
    <div class="space-y-6">
        <div>
            <div class="brand-badge bg-brand-primary/10 text-brand-primary">Bandeja de mensajes</div>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Mensajes entrantes</h1>
            <p class="mt-1 text-sm text-slate-600">Últimos mensajes capturados para el ámbito visible.</p>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Recibido</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Desde</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Hacia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Texto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($messages as $message)
                        <tr id="incoming-message-{{ $message->id }}">
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
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No hay mensajes disponibles para esta cuenta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
