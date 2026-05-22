<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Pilot Script</h1>
            <p class="mt-1 text-sm text-slate-600">Sample messages grouped by expected parser result.</p>
        </div>

        @php
            $groups = [
                'Pending' => [
                    '1000 al 28',
                    '₡1000 al 28',
                    '2 mil al 45',
                    'quiero echarle 1000 al 45',
                ],
                'Needs review' => [
                    'hola',
                    '1000',
                    '28',
                    'mil al cien',
                    '1000 al 28 y 2000 al 45',
                ],
            ];
        @endphp

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($groups as $title => $messages)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        @foreach ($messages as $message)
                            <li class="rounded-md border border-slate-200 px-3 py-2">{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
