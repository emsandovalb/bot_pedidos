<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Intake Simulator</h1>
                <p class="mt-1 text-sm text-slate-600">Create a local incoming message and review the parser output.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to dashboard
            </a>
        </div>

        @if (session('simulator_result'))
            @php($result = session('simulator_result'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">
                <div class="font-semibold">Intake saved successfully.</div>
                <div class="mt-2 text-sm">
                    Incoming message #{{ $result['incoming_message_id'] }} · Branch: {{ $result['branch_name'] }} · Phone: {{ $result['customer_phone'] }}
                </div>
                <div class="mt-2 text-sm">
                    Created requests: {{ $result['created_requests_count'] }} · Parser type: {{ $result['parser_result']['parser_type'] }}
                    · Confidence: {{ number_format($result['parser_result']['confidence'] * 100, 0) }}%
                    · Review: {{ $result['parser_result']['needs_review'] ? 'yes' : 'no' }}
                </div>
                @if (! empty($result['parser_result']['draw_reference']))
                    <div class="mt-2 text-sm">Draw detected: {{ $result['parser_result']['draw_reference'] }}</div>
                @endif
                @if ($result['parser_result']['reason'])
                    <div class="mt-2 text-sm">Reason: {{ $result['parser_result']['reason'] }}</div>
                @endif
                <div class="mt-4 rounded-md border border-emerald-200 bg-white/70 p-3">
                    <div class="text-sm font-semibold">Interpreted requests</div>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($result['requests'] as $item)
                            <li>• Número {{ $item['detected_number'] ?? '-' }} → ₡{{ $item['detected_amount'] ?? '-' }} · {{ str_replace('_', ' ', $item['status']) }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-4 rounded-md border border-emerald-200 bg-white/70 p-3">
                    <div class="text-sm font-semibold">Generated customer confirmation text</div>
                    <pre class="mt-2 whitespace-pre-wrap text-sm text-emerald-950">{{ $result['customer_confirmation_text'] }}</pre>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('simulator.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="branch_id" class="block text-sm font-medium text-slate-700">Branch</label>
                        <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Select a branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                    {{ $branch->name }} — {{ $branch->channel_identifier }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="customer_phone" class="block text-sm font-medium text-slate-700">Customer phone</label>
                            <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="+50255510001">
                            @error('customer_phone')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-slate-700">Customer name</label>
                            <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional">
                            @error('customer_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="raw_message" class="block text-sm font-medium text-slate-700">Raw message</label>
                        <textarea id="raw_message" name="raw_message" rows="5" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="1000 al 28">{{ old('raw_message') }}</textarea>
                        @error('raw_message')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Submit simulation
                        </button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Parser examples</h2>
                    <ul class="mt-3 space-y-2 text-sm text-slate-700">
                        <li>1000 al 28 2pm</li>
                        <li>₡1000 al 28 5pm</li>
                        <li>mil al 28 7pm</li>
                        <li>2 mil al 45 12 md</li>
                        <li>500 numero 99 medio dia</li>
                    </ul>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Notes</h2>
                    <p class="mt-3 text-sm text-slate-600">Duplicate exact submissions are blocked. Customers are reused by phone within the current organization.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
