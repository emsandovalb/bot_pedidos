<x-app-layout>
    @php
        $numberGroups = collect($numbers)->chunk(10)->values();
        $allNumbers = collect($numbers)->pluck('number')->values();
    @endphp

    <div
        class="space-y-6"
        x-data="{
            view: 'grid',
            searchTerm: '',
            modalOpen: {{ $canCreateManualRequests && $selectedBranch && $selectedDraw && $errors->any() ? 'true' : 'false' }},
            selectedNumber: @js(old('number', '')),
            selectedLabel: @js(old('number') ? 'Manual request for number ' . old('number') : 'Select a number'),
            allNumbers: @js($allNumbers),
            openNumber(number) {
                this.selectedNumber = number;
                this.selectedLabel = `Manual request for number ${number}`;
                this.modalOpen = true;
            },
            matchesNumber(number) {
                const term = this.searchTerm.trim();

                if (term === '') {
                    return true;
                }

                return number.includes(term);
            },
            groupHasMatch(numbers) {
                const term = this.searchTerm.trim();

                if (term === '') {
                    return true;
                }

                return numbers.some((number) => number.includes(term));
            },
            hasAnyMatch() {
                return this.allNumbers.some((number) => this.matchesNumber(number));
            },
            clearSearch() {
                this.searchTerm = '';
            }
        }"
    >
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Catálogo de productos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Catálogo operativo</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Revisa el catálogo, abre el formulario rápido desde cualquier tarjeta y mantén la vista amplia.
                </p>
            </div>
            <a href="{{ route('intake-requests.index') }}" class="brand-btn-secondary">Volver a órdenes</a>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-brand-gold/20 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div class="brand-card p-5 sm:p-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    <div class="text-sm font-medium text-slate-700">Branch</div>
                    <form method="GET" action="{{ route('numbers.index') }}" class="mt-1 grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <div>
                            @if ($branches->count() > 1)
                                <select id="branch_id" name="branch_id" class="brand-input block w-full rounded-xl text-sm">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected($selectedBranch?->id === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="hidden" name="branch_id" value="{{ $selectedBranch?->id }}">
                                <div class="rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2.5 text-sm text-slate-700">
                                    {{ $selectedBranch?->name ?? 'No branch available' }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="draw_id" class="block text-sm font-medium text-slate-700">Referencia</label>
                            <select id="draw_id" name="draw_id" class="brand-input mt-1 block w-full rounded-xl text-sm" @disabled($draws->isEmpty())>
                                @foreach ($draws as $draw)
                                    <option value="{{ $draw->id }}" @selected($selectedDraw?->id === $draw->id)>{{ $draw->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="brand-btn-primary w-full">
                                Refresh board
                            </button>
                        </div>
                    </form>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Selected branch</div>
                        <div class="mt-1 text-base font-semibold text-brand-navy">{{ $selectedBranch?->name ?? 'No branch available' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4">
                        <div class="text-sm text-slate-500">Referencia seleccionada</div>
                        <div class="mt-1 text-base font-semibold text-brand-navy">{{ $selectedDraw?->name ?? 'No reference available' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <div class="brand-card border-brand-success/20 p-5">
                <div class="text-sm font-medium text-brand-success">Total confirmed</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['confirmed_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-info/20 p-5">
                <div class="text-sm font-medium text-brand-info">Total pending</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['pending_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-primary/20 p-5">
                <div class="text-sm font-medium text-brand-primary">Total needs review</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['needs_review_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-gold/20 p-5">
                <div class="text-sm font-medium text-brand-gold">Total active</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">&#8353;{{ number_format($summary['active_amount'], 2, '.', ',') }}</div>
            </div>
            <div class="brand-card border-brand-warning/20 p-5">
                <div class="text-sm font-medium text-brand-warning">Numbers near limit</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $summary['near_limit_count'] }}</div>
            </div>
            <div class="brand-card border-brand-danger/20 p-5">
                <div class="text-sm font-medium text-brand-danger">Numbers over limit</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight text-brand-navy">{{ $summary['over_limit_count'] }}</div>
            </div>
        </div>

        <div class="brand-card p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-slate-700">Leyenda de estados</span>
                    <span class="brand-badge bg-green-50 text-green-700">available <span class="font-normal text-slate-500">below 80% or no limit</span></span>
                    <span class="brand-badge bg-amber-50 text-amber-700">warning <span class="font-normal text-slate-500">80% to 99%</span></span>
                    <span class="brand-badge bg-blue-50 text-blue-700">full <span class="font-normal text-slate-500">100%</span></span>
                    <span class="brand-badge bg-red-50 text-red-700">over_limit <span class="font-normal text-slate-500">&gt; 100%</span></span>
                    <span class="brand-badge bg-slate-100 text-slate-700">no_limit <span class="font-normal text-slate-500">no configured limit</span></span>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-100 p-1">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="view === 'grid' ? 'bg-brand-primary text-white shadow-sm' : 'text-slate-600 hover:text-brand-navy'"
                            @click="view = 'grid'"
                        >
                            Grid view (00-99)
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-sm font-medium transition"
                            :class="view === 'table' ? 'bg-brand-primary text-white shadow-sm' : 'text-slate-600 hover:text-brand-navy'"
                            @click="view = 'table'"
                        >
                            Detailed table
                        </button>
                    </div>

                    <div class="relative w-full sm:w-80">
                        <label for="number-search" class="sr-only">Search number</label>
                        <input
                            id="number-search"
                            type="search"
                            x-model="searchTerm"
                            placeholder="Search number..."
                            class="brand-input w-full rounded-2xl py-2.5 pl-4 pr-10 text-sm"
                        >
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 inline-flex items-center justify-center rounded-xl px-2 text-slate-400 transition hover:text-slate-700"
                            @click="clearSearch()"
                            aria-label="Clear search"
                        >
                            <svg viewBox="0 0 24 24" fill="none" class="h-4 w-4" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <section class="space-y-4">
            <div x-show="view === 'grid'" x-cloak class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($numberGroups as $groupIndex => $group)
                    @php
                        $start = $groupIndex * 10;
                        $end = $start + 9;
                        $groupStatuses = collect($group)->pluck('status');
                        $groupTone = match (true) {
                            $groupStatuses->contains('over_limit') => 'over_limit',
                            $groupStatuses->contains('full') => 'full',
                            $groupStatuses->contains('warning') => 'warning',
                            default => 'available',
                        };
                    @endphp

                    <div
                        x-data="{ collapsed: false }"
                        x-show="groupHasMatch(@js($group->pluck('number')->values()))"
                        class="self-start overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left transition hover:bg-slate-50 sm:px-5"
                            @click="collapsed = !collapsed"
                            :aria-expanded="(!collapsed).toString()"
                        >
                            <div class="flex items-center gap-3">
                                <div class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-slate-100 text-slate-900">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $groupTone === 'available' ? 'bg-brand-success' : ($groupTone === 'warning' ? 'bg-brand-warning' : ($groupTone === 'full' ? 'bg-brand-info' : 'bg-brand-danger')) }}"></span>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-brand-navy">{{ sprintf('%02d-%02d', $start, $end) }}</div>
                                    <div class="text-xs text-slate-500">Group {{ $groupIndex + 1 }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="hidden rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500 sm:inline-flex">
                                    10 cards
                                </span>
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    class="h-5 w-5 text-slate-500 transition-transform duration-200"
                                    :class="{ 'rotate-180': collapsed }"
                                    aria-hidden="true"
                                >
                                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </button>

                        <div x-show="!collapsed" x-transition.opacity.duration.150ms class="px-4 pb-4 sm:px-5 sm:pb-5">
                            <div class="grid grid-cols-2 gap-2 xl:grid-cols-5">
                                @foreach ($group as $row)
                                    @php
                                        $hasLimit = $row['max_amount'] !== null;
                                        $statusLabel = $hasLimit ? str_replace('_', ' ', $row['status']) : 'no_limit';
                                        $cardClasses = match ($row['status']) {
                                            'over_limit' => 'border-red-200 bg-red-50/80 text-red-700',
                                            'full' => 'border-blue-200 bg-blue-50/80 text-blue-700',
                                            'warning' => 'border-amber-200 bg-amber-50/80 text-amber-700',
                                            default => $hasLimit ? 'border-brand-success/20 bg-green-50/80 text-green-700' : 'border-slate-200 bg-slate-50 text-slate-600',
                                        };
                                        $badgeClasses = match ($row['status']) {
                                            'over_limit' => 'bg-red-100 text-red-700',
                                            'full' => 'bg-blue-100 text-blue-700',
                                            'warning' => 'bg-amber-100 text-amber-700',
                                            default => $hasLimit ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600',
                                        };
                                    @endphp

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-2xl border px-3 py-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-primary/10"
                                        :class="selectedNumber === @js($row['number']) ? '{{ $cardClasses }} ring-2 ring-brand-primary/10' : '{{ $cardClasses }}'"
                                        @click="openNumber(@js($row['number']))"
                                        x-show="matchesNumber(@js($row['number']))"
                                    >
                                        <div class="flex min-h-[6.5rem] flex-col justify-between gap-3">
                                            <div class="space-y-1 text-center">
                                                <div class="text-2xl font-semibold leading-none tracking-tight text-brand-navy">{{ $row['number'] }}</div>
                                                <div class="text-sm font-medium text-slate-700">&#8353;{{ number_format($row['active_amount'], 0, '.', ',') }}</div>
                                                @if ($hasLimit)
                                                    <div class="text-[11px] leading-none text-slate-500">
                                                        Avail: &#8353;{{ number_format(max((float) $row['available_amount'], 0), 0, '.', ',') }}
                                                    </div>
                                                @endif
                                            </div>

                                            <span class="inline-flex w-full items-center justify-center rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $badgeClasses }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <div
                    x-show="searchTerm.trim() !== '' && !hasAnyMatch()"
                    x-cloak
                    class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 shadow-sm md:col-span-2 xl:col-span-4"
                >
                    No numbers match the current search.
                </div>
            </div>

            <div x-show="view === 'table'" x-cloak class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <h3 class="text-base font-semibold text-brand-navy">Vista detallada</h3>
                    <p class="text-sm text-slate-600">Los mismos datos, mostrados en una tabla operativa compacta.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1220px] divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Number</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmed</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Needs review</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Active total</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Max limit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Available</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">% used</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @foreach ($numbers as $row)
                                @php
                                    $hasLimit = $row['max_amount'] !== null;
                                    $tableRowClasses = match ($row['status']) {
                                        'over_limit' => 'bg-red-50/60',
                                        'full' => 'bg-blue-50/60',
                                        'warning' => 'bg-amber-50/60',
                                        default => '',
                                    };
                                    $statusClasses = match ($row['status']) {
                                        'over_limit' => 'border-red-200 bg-red-100 text-red-700',
                                        'full' => 'border-blue-200 bg-blue-100 text-blue-700',
                                        'warning' => 'border-amber-200 bg-amber-100 text-amber-700',
                                        default => $hasLimit ? 'border-brand-success/20 bg-green-100 text-green-700' : 'border-slate-200 bg-slate-200 text-slate-600',
                                    };
                                    $statusLabel = $hasLimit ? str_replace('_', ' ', $row['status']) : 'no limit';
                                @endphp
                                <tr class="{{ $tableRowClasses }}" x-show="matchesNumber(@js($row['number']))">
                                    <td class="px-4 py-3 text-sm font-semibold text-brand-navy">{{ $row['number'] }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['confirmed_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['pending_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['needs_review_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">&#8353;{{ number_format($row['rejected_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-slate-800">&#8353;{{ number_format($row['active_amount'], 2, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['max_amount'] !== null ? '₡' . number_format((float) $row['max_amount'], 2, '.', ',') : 'No limit' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['available_amount'] !== null ? '₡' . number_format((float) $row['available_amount'], 2, '.', ',') : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-700">{{ $row['percentage_used'] !== null ? number_format($row['percentage_used'], 1) . '%' : '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($canCreateManualRequests && $selectedBranch && $selectedDraw)
                                            <button
                                                type="button"
                                                class="brand-btn-secondary px-3 py-1.5 text-xs"
                                                @click="openNumber(@js($row['number']))"
                                            >
                                                Manual request
                                            </button>
                                        @else
                                            <span class="text-sm text-slate-400">View only</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                x-show="view === 'table' && searchTerm.trim() !== '' && !hasAnyMatch()"
                x-cloak
                class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 shadow-sm"
            >
                No numbers match the current search.
            </div>
        </section>

        @if ($canCreateManualRequests && $selectedBranch && $selectedDraw)
            <div
                x-show="modalOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8 backdrop-blur-sm"
                @keydown.escape.window="modalOpen = false"
            >
                <div class="w-full max-w-2xl rounded-3xl border border-slate-200/80 bg-white shadow-[0_30px_80px_-36px_rgba(8,31,77,0.6)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div>
                            <div class="brand-badge bg-brand-primary/10 text-brand-primary">Manual request</div>
                            <h2 class="mt-2 text-lg font-semibold text-brand-navy">Create manual request</h2>
                            <p class="mt-1 text-sm text-slate-600" x-text="selectedLabel"></p>
                        </div>
                        <button type="button" class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-900" @click="modalOpen = false" aria-label="Close modal">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('numbers.store') }}" class="space-y-5 px-5 py-5 sm:px-6">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $selectedBranch?->id }}">
                        <input type="hidden" name="draw_id" value="{{ $selectedDraw?->id }}">
                        <input type="hidden" name="number" x-bind:value="selectedNumber">

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                            <label class="block text-sm font-medium text-slate-700">Sucursal seleccionada</label>
                                <input type="text" readonly value="{{ $selectedBranch?->name ?? '-' }}" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                            <div>
                            <label class="block text-sm font-medium text-slate-700">Referencia seleccionada</label>
                                <input type="text" readonly value="{{ $selectedDraw?->name ?? '-' }}" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Selected number</label>
                                <input type="text" readonly x-bind:value="selectedNumber" class="brand-input mt-1 block w-full rounded-xl bg-slate-50 text-sm text-slate-700">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700" for="amount">Amount</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="1000">
                            @error('amount')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700" for="customer_name">Customer name</label>
                                <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="Optional">
                                @error('customer_name')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700" for="customer_phone">Customer phone</label>
                                <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone') }}" class="brand-input mt-1 block w-full rounded-xl" placeholder="+50255510001">
                                @error('customer_phone')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700" for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="4" class="brand-input mt-1 block w-full rounded-xl" placeholder="Optional">{{ old('notes') }}</textarea>
                                @error('notes')<p class="mt-2 text-sm text-brand-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                            <button type="button" class="brand-btn-secondary" @click="modalOpen = false">
                                Cancel
                            </button>
                            <button type="submit" class="brand-btn-primary">
                                Save request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
