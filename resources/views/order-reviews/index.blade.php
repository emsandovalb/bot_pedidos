<x-app-layout>
    @php
        $queueOrders = $orders->getCollection();

        $totalPending = $queueOrders->count();
        $classifiedCount = $queueOrders->filter(fn ($order) => (int) ($order->recognized_order_items_count ?? 0) > 0)->count();
        $unclassifiedCount = $totalPending - $classifiedCount;

        $confidenceLabelFor = static function (?float $score): array {
            if ($score === null) {
                return ['label' => 'Sin confianza', 'class' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200'];
            }

            if ($score >= 0.75) {
                return ['label' => 'High', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100'];
            }

            if ($score >= 0.45) {
                return ['label' => 'Medium', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100'];
            }

            return ['label' => 'Low', 'class' => 'bg-red-50 text-red-700 ring-1 ring-red-100'];
        };

        $confidenceCount = static function ($order, callable $predicate): int {
            return $order->filter(function ($candidate) use ($predicate) {
                $score = $candidate->parser_confidence !== null ? (float) $candidate->parser_confidence : null;

                return $predicate($score);
            })->count();
        };

        $highConfidenceCount = $confidenceCount($queueOrders, static fn (?float $score) => $score !== null && $score >= 0.75);
        $mediumConfidenceCount = $confidenceCount($queueOrders, static fn (?float $score) => $score !== null && $score >= 0.45 && $score < 0.75);
        $lowConfidenceCount = $confidenceCount($queueOrders, static fn (?float $score) => $score !== null && $score < 0.45);

        $statusToneFor = static function ($order): array {
            $recognizedCount = (int) ($order->recognized_order_items_count ?? 0);
            $totalItems = (int) $order->orderItems->count();

            if ($recognizedCount > 0 && $totalItems > 0 && $recognizedCount === $totalItems) {
                return [
                    'border' => 'border-emerald-200/80 ring-1 ring-emerald-100',
                    'accent' => 'bg-emerald-500',
                    'badge' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100',
                    'label' => 'Clasificado',
                ];
            }

            if ($recognizedCount > 0) {
                return [
                    'border' => 'border-amber-200/80 ring-1 ring-amber-100',
                    'accent' => 'bg-amber-500',
                    'badge' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-100',
                    'label' => 'Pendiente de validacion',
                ];
            }

            return [
                'border' => 'border-orange-200/80 ring-1 ring-orange-100',
                'accent' => 'bg-orange-500',
                'badge' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-100',
                'label' => 'Pendiente de clasificar',
            ];
        };
    @endphp

    <div class="space-y-6">
        <div class="overflow-hidden rounded-[28px] border border-slate-200/70 bg-[linear-gradient(135deg,rgba(20,110,219,0.08),rgba(22,163,74,0.05)_36%,rgba(255,255,255,1)_78%)] shadow-[0_18px_50px_-28px_rgba(15,23,42,0.45)]">
            <div class="flex flex-col gap-6 p-6 sm:p-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-inset ring-blue-100">
                        Benditio operations inbox
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">
                        Revisión de pedidos
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                        Pedidos que requieren validación antes de continuar el flujo operativo.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('orders.index', ['status' => \App\Models\Order::STATUS_PENDING_REVIEW]) }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                        Ver cola operativa
                    </a>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50">
                        Ir al panel
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-[22px] border border-slate-200/70 border-l-4 border-l-brand-primary bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Total pendientes</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-brand-navy">{{ $totalPending }}</div>
            </div>
            <div class="rounded-[22px] border border-emerald-200/70 border-l-4 border-l-emerald-500 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Clasificados</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-emerald-700">{{ $classifiedCount }}</div>
            </div>
            <div class="rounded-[22px] border border-orange-200/70 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Pendientes de clasificar</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-amber-600">{{ $unclassifiedCount }}</div>
            </div>
            <div class="rounded-[22px] border border-slate-200/70 border-l-4 border-l-emerald-500 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-slate-500">Alta confianza</div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-emerald-600">{{ $highConfidenceCount }}</div>
            </div>
            <div class="rounded-[22px] border border-slate-200/70 border-l-4 border-l-amber-500 bg-white p-5 shadow-sm md:col-span-2 xl:col-span-1">
                <div class="text-sm font-medium text-slate-500">Media / baja confianza</div>
                <div class="mt-2 flex items-end gap-3">
                    <div class="text-3xl font-semibold tracking-tight text-amber-600">{{ $mediumConfidenceCount }}</div>
                    <div class="pb-1 text-sm text-slate-500">Media</div>
                    <div class="text-3xl font-semibold tracking-tight text-red-600">{{ $lowConfidenceCount }}</div>
                    <div class="pb-1 text-sm text-slate-500">Baja</div>
                </div>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">
                    Todos <span class="ml-2 text-xs text-slate-500">{{ $totalPending }}</span>
                </span>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">
                    Clasificados <span class="ml-2 text-xs text-emerald-600">{{ $classifiedCount }}</span>
                </span>
                <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700">
                    Pendientes de clasificar <span class="ml-2 text-xs text-amber-600">{{ $unclassifiedCount }}</span>
                </span>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-sm font-medium text-emerald-700 ring-1 ring-emerald-100">
                    Alta confianza <span class="ml-2 text-xs text-emerald-600">{{ $highConfidenceCount }}</span>
                </span>
                <span class="inline-flex items-center rounded-full border border-amber-200 bg-white px-4 py-2 text-sm font-medium text-amber-700 ring-1 ring-amber-100">
                    Media confianza <span class="ml-2 text-xs text-amber-600">{{ $mediumConfidenceCount }}</span>
                </span>
                <span class="inline-flex items-center rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 ring-1 ring-red-100">
                    Baja confianza <span class="ml-2 text-xs text-red-600">{{ $lowConfidenceCount }}</span>
                </span>
            </div>
        </div>

        <div class="space-y-4">
            @forelse ($orders as $order)
                @php
                    $tone = $statusToneFor($order);
                    $recognizedItems = $order->orderItems->filter(fn ($item) => $item->product_id !== null);
                    $itemCount = $order->orderItems->count();
                    $parserScore = $order->parser_confidence !== null ? (float) $order->parser_confidence : null;
                    $confidence = $confidenceLabelFor($parserScore);
                    $confidencePercent = $parserScore !== null ? number_format($parserScore * 100, 0) . '%' : 'Sin dato';
                    $messagePreview = \Illuminate\Support\Str::of($order->raw_message_text ?? 'Sin mensaje original')
                        ->replaceMatches('/\s+/', ' ')
                        ->toString();
                    $messagePreview = \Illuminate\Support\Str::limit($messagePreview, 240);
                    $itemNotes = $order->orderItems->pluck('notes')->filter()->values();
                    $orderNotes = collect([$order->notes])->filter()->values();
                    $notesToShow = $orderNotes->isNotEmpty() ? $orderNotes : $itemNotes;
                @endphp

                <article class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-[0_18px_50px_-34px_rgba(15,23,42,0.38)] transition hover:-translate-y-0.5 hover:shadow-[0_24px_60px_-34px_rgba(15,23,42,0.45)] {{ $tone['border'] }}">
                    <div class="h-1 {{ $tone['accent'] }}"></div>

                    <div class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0 flex-1 space-y-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pedido</div>
                                        <div class="text-2xl font-semibold tracking-tight text-brand-navy">#{{ $order->id }}</div>
                                    </div>

                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $tone['badge'] }}">
                                        {{ $tone['label'] }}
                                    </span>

                                    @if ($order->possibleDuplicateOf)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-100">
                                            Posible duplicado
                                        </span>
                                    @endif

                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-primary ring-1 ring-blue-100">
                                        {{ $order->source_channel ?? 'Canal no definido' }}
                                    </span>

                                    <span class="text-sm text-slate-500">
                                        {{ $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                                    </span>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Cliente</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $order->customer?->name ?? 'Sin cliente' }}</div>
                                        <div class="mt-1 text-sm text-slate-600">{{ $order->customer?->phone ?? 'Sin telefono' }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Sucursal</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $order->branch?->name ?? 'Sin sucursal' }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Fecha</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $order->created_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</div>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Canal</div>
                                        <div class="mt-1 text-sm font-semibold text-brand-navy">{{ $order->source_channel ?? 'Sin canal' }}</div>
                                    </div>
                                </div>

                                @if ($order->possibleDuplicateOf)
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        Similar al pedido #{{ $order->possibleDuplicateOf->id }}
                                    </div>
                                @endif

                                <div class="grid gap-4 xl:grid-cols-[1.45fr_1fr]">
                                    <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <h2 class="text-sm font-semibold text-brand-navy">Mensaje original</h2>
                                            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Vista previa</span>
                                        </div>
                                        <p
                                            class="mt-3 text-sm leading-6 text-slate-700"
                                            style="display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;"
                                        >
                                            {{ $messagePreview }}
                                        </p>
                                    </section>

                                    <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-amber-500 bg-white p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <h2 class="text-sm font-semibold text-brand-navy">Clasificación</h2>
                                            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $itemCount }} item(s)</span>
                                        </div>

                                        <div class="mt-3 space-y-3">
                                            @if ($recognizedItems->isNotEmpty())
                                                @foreach ($recognizedItems as $item)
                                                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-3">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                                                Producto reconocido
                                                            </span>
                                                            <span class="text-sm font-semibold text-emerald-900">
                                                                Producto reconocido: {{ $item->product?->name ?? 'Producto sin nombre' }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-2 text-xs leading-5 text-emerald-800">
                                                            Texto detectado: {{ $item->raw_text ?? 'Sin texto detectado' }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="rounded-2xl border border-orange-100 bg-orange-50 p-3 text-sm text-orange-800">
                                                    <div class="flex items-center gap-2 font-semibold">
                                                        <span>⚠</span>
                                                        <span>Pendiente de clasificar</span>
                                                    </div>
                                                    <div class="mt-2 text-xs font-medium text-orange-700">Sin producto asociado</div>
                                                    <p class="mt-2 text-sm leading-6 text-orange-700">
                                                        No se identificaron productos automáticamente en este pedido.
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    </section>
                                </div>

                                <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-slate-300 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 class="text-sm font-semibold text-brand-navy">Items detectados</h2>
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $itemCount }} total</span>
                                    </div>

                                    <div class="mt-3 space-y-3">
                                        @forelse ($order->orderItems as $item)
                                            @php
                                                $itemScore = $item->confidence_score !== null ? (float) $item->confidence_score : null;
                                                $itemConfidence = $confidenceLabelFor($itemScore);
                                            @endphp
                                            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 transition hover:bg-white">
                                                <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                                {{ $item->quantity }}{{ $item->unit ? ' ' . $item->unit : '' }}
                                                            </span>
                                                            <span class="text-sm font-semibold text-brand-navy">
                                                                {{ $item->raw_text ?? 'Sin texto detectado' }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-2 text-xs leading-5 text-slate-600">
                                                            Unidad: <span class="font-semibold text-slate-800">{{ $item->unit ?? 'Sin unidad' }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $itemConfidence['class'] }}">
                                                            {{ $itemConfidence['label'] }}
                                                        </span>
                                                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                                            {{ $item->product !== null ? 'Producto: ' . $item->product->name : 'Sin producto asociado' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/70 px-4 py-6 text-sm text-slate-600">
                                                No se detectaron items en este pedido.
                                            </div>
                                        @endforelse
                                    </div>
                                </section>

                                @if ($notesToShow->isNotEmpty())
                                    <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-slate-300 bg-white p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <h2 class="text-sm font-semibold text-brand-navy">Notas</h2>
                                            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Notas detectadas</span>
                                        </div>

                                        <div class="mt-3 space-y-2">
                                            @foreach ($notesToShow as $note)
                                                <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700">
                                                    {{ $note }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            </div>

                            <aside class="flex shrink-0 flex-col gap-4 lg:w-72">
                                <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-brand-primary bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 class="text-sm font-semibold text-brand-navy">Confianza</h2>
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Parser</span>
                                    </div>

                                    <div class="mt-4 flex items-center gap-3">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $confidence['class'] }}">
                                            {{ $confidence['label'] }}
                                        </span>
                                        <span class="text-sm font-semibold text-brand-navy">{{ $confidencePercent }}</span>
                                    </div>

                                    <p class="mt-3 text-sm leading-6 text-slate-600">
                                        Nivel de confianza extraído del parser para priorizar validación operativa.
                                    </p>
                                </section>

                                <section class="rounded-2xl border border-slate-200/80 border-l-4 border-l-slate-300 bg-slate-50 p-4">
                                    <h2 class="text-sm font-semibold text-brand-navy">Acciones</h2>
                                    <div class="mt-4 space-y-3">
                                        <a href="{{ route('orders.show', $order) }}" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700">
                                            Ver pedido
                                        </a>
                                        <a href="{{ route('orders.edit', $order) }}" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                            Editar pedido
                                        </a>
                                    </div>
                                </section>
                            </aside>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[28px] border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm">
                    <div class="mx-auto max-w-md">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 text-3xl text-[#146EDB]">
                            📦
                        </div>
                        <h2 class="mt-5 text-lg font-semibold text-brand-navy">No hay pedidos pendientes de revisión.</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Todo está al día.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="rounded-[24px] border border-slate-200/70 bg-white p-4 shadow-sm sm:p-5">
            {{ $orders->links() }}
        </div>
    </div>
</x-app-layout>
