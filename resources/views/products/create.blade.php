<x-app-layout>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-blue-50/50 shadow-sm">
            <div class="grid gap-6 px-6 py-7 lg:grid-cols-[1.15fr_0.85fr] lg:px-8">
                <div class="space-y-4">
                    <div class="inline-flex rounded-full bg-brand-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-brand-primary">
                        Nuevo producto
                    </div>
                    <div class="max-w-3xl space-y-3">
                        <h1 class="text-3xl font-semibold tracking-tight text-brand-navy sm:text-4xl">Nuevo producto</h1>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                            Agrega productos para que Benditio pueda reconocerlos en los mensajes.
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Flujo recomendado</p>
                    <ol class="mt-3 space-y-3 text-sm text-slate-600">
                        <li class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-semibold text-brand-primary">1</span>
                            <span>Completa la informacion basica del producto.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-semibold text-brand-primary">2</span>
                            <span>Guardalo y despues anade alias desde la edicion.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-primary/10 text-xs font-semibold text-brand-primary">3</span>
                            <span>Activalo para que el bot lo reconozca en pedidos.</span>
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('products.store') }}" class="space-y-6">
                    @csrf
                    @include('products._form', ['product' => $product, 'branches' => $branches])

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('products.index') }}" class="brand-btn-secondary justify-center">
                            Volver al catalogo
                        </a>
                        <button type="submit" class="brand-btn-primary justify-center">
                            Crear producto
                        </button>
                    </div>
                </form>
            </div>

            <aside class="space-y-6">
                <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reconocimiento</p>
                    <h2 class="mt-2 text-xl font-semibold text-brand-navy">Como lo vera Benditio</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        El catalogo usa el nombre del producto y, una vez guardado, puedes ampliar el reconocimiento con alias desde la pantalla de edicion.
                    </p>

                    <div class="mt-5 rounded-2xl bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-700">Ejemplo de reconocimiento</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">tomate</span>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">tomates</span>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-brand-navy shadow-sm">kilos de tomate</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-slate-200/80 bg-gradient-to-br from-brand-navy to-slate-900 p-6 text-white shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">Estado</p>
                    <h2 class="mt-2 text-xl font-semibold">Activo desde el inicio</h2>
                    <p class="mt-2 text-sm leading-6 text-white/75">
                        Manten el producto activo si quieres que el bot lo considere en mensajes y flujos de pedidos.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
