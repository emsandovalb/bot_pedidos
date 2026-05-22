<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit Request</h1>
                <p class="mt-1 text-sm text-slate-600">Update the detected fields before confirming.</p>
            </div>
            <a href="{{ route('intake-requests.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to requests
            </a>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('intake-requests.update', $request) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="draw_id">Draw / schedule</label>
                        <select id="draw_id" name="draw_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Select a draw</option>
                            @foreach ($draws as $draw)
                                <option value="{{ $draw->id }}" @selected(old('draw_id', $request->draw_id) == $draw->id)>{{ $draw->name }}</option>
                            @endforeach
                        </select>
                        @error('draw_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="detected_number">Detected number</label>
                        <input id="detected_number" name="detected_number" value="{{ old('detected_number', $request->detected_number) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="00">
                        @error('detected_number')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="detected_amount">Detected amount</label>
                        <input id="detected_amount" name="detected_amount" type="number" step="0.01" min="0" value="{{ old('detected_amount', $request->detected_amount) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="1000">
                        @error('detected_amount')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('notes', $request->notes) }}</textarea>
                        @error('notes')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Save changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Request summary</h2>
                    <dl class="mt-3 space-y-2 text-sm text-slate-700">
                        <div class="flex justify-between gap-4"><dt>Status</dt><dd>{{ $request->status }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Branch</dt><dd>{{ $request->branch?->name ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Draw</dt><dd>{{ $request->draw?->name ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Customer</dt><dd>{{ $request->customer?->phone ?? '-' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Raw text</h2>
                    <p class="mt-3 text-sm text-slate-700">{{ $request->raw_text }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
