<x-app-layout>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Create product</h1>
                <p class="mt-1 text-sm text-slate-600">Create a new catalog product.</p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                Back to catalog
            </a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('products.store') }}" class="space-y-6">
                @csrf
                @include('products._form', ['product' => $product, 'branches' => $branches])

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Create product
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
