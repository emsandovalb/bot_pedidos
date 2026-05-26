<x-app-layout>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="brand-badge bg-brand-primary/10 text-brand-primary">Importar productos</div>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-brand-navy">Importar productos</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Pega una linea por producto. El importador actualiza productos y alias por nombre normalizado.
                </p>
            </div>
            <a href="{{ route('products.index') }}" class="brand-btn-secondary">Volver al catalogo</a>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Revisa el formulario.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
            <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('products.import.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="content" class="block text-sm font-medium text-slate-700">Texto de importacion</label>
                        <textarea
                            id="content"
                            name="content"
                            rows="16"
                            class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20"
                            placeholder="Tomate | TOM-001 | kilo | tomate, tomates, kilos de tomate"
                        >{{ old('content') }}</textarea>
                        @error('content')
                            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="brand-btn-primary">Importar</button>
                </form>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200/80 bg-slate-50 p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Formato</h2>
                    <p class="mt-3 text-sm text-slate-600">Una linea por producto con campos separados por pipe.</p>
                    <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-900 p-4 text-xs leading-6 text-slate-100">Name | SKU | Unit | Aliases
Tomate | TOM-001 | kilo | tomate, tomates, kilos de tomate
Carne | CAR-001 | kilo | carne, kilos de carne
Tortillas | TOR-001 | paquete | tortillas, paquetes de tortillas
Bolsas negras | BN-001 | bolsa | bolsas negras, bolsa negra</pre>
                    <ul class="mt-4 space-y-2 text-sm text-slate-600">
                        <li>Name es obligatorio.</li>
                        <li>SKU, Unit y Aliases son opcionales.</li>
                        <li>Los alias se separan con coma.</li>
                        <li>Las lineas vacias se ignoran.</li>
                        <li>Las lineas invalidas se saltan sin detener el import.</li>
                    </ul>
                </div>

                @if (session('import_summary'))
                    @php($summary = session('import_summary'))
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Resultado</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-slate-500">Products created</dt>
                                <dd class="text-lg font-semibold text-brand-navy">{{ $summary['products_created'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Products updated</dt>
                                <dd class="text-lg font-semibold text-brand-navy">{{ $summary['products_updated'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Aliases created</dt>
                                <dd class="text-lg font-semibold text-brand-navy">{{ $summary['aliases_created'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Aliases updated</dt>
                                <dd class="text-lg font-semibold text-brand-navy">{{ $summary['aliases_updated'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Skipped lines</dt>
                                <dd class="text-lg font-semibold text-brand-navy">{{ $summary['skipped_lines'] }}</dd>
                            </div>
                        </dl>

                        @if (!empty($summary['errors']))
                            <div class="mt-6">
                                <h3 class="text-sm font-semibold text-slate-700">Errors</h3>
                                <ul class="mt-3 space-y-3 text-sm text-slate-600">
                                    @foreach ($summary['errors'] as $error)
                                        <li class="rounded-2xl bg-slate-50 px-4 py-3">
                                            <div class="font-medium text-slate-800">Line {{ $error['line'] }}</div>
                                            <div class="mt-1">{{ $error['error'] }}</div>
                                            <div class="mt-1 font-mono text-xs text-slate-500">{{ $error['content'] }}</div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
