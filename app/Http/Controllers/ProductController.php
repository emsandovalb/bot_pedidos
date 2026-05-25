<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $products = Product::query()
            ->with(['branch'])
            ->withCount('productAliases')
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('products.index', [
            'products' => $products,
            'canManageProducts' => $this->canManageProducts($user),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManageProducts($request->user());

        return view('products.create', [
            'product' => new Product([
                'is_active' => true,
                'sort_order' => 0,
            ]),
            'branches' => $this->visibleBranches($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeManageProducts($user);

        $validated = $this->validateProduct($request, $user?->organization_id);

        $product = Product::create(array_merge($validated, [
            'organization_id' => $user->organization_id,
        ]));

        return redirect()
            ->route('products.edit', $product)
            ->with('status', 'Product created successfully.');
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeManageProduct($request->user(), $product);

        return view('products.edit', [
            'product' => $product->load([
                'branch',
                'productAliases' => fn ($query) => $query->orderByDesc('match_weight')->orderBy('alias'),
            ])->loadCount('productAliases'),
            'branches' => $this->visibleBranches($request->user()),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeManageProduct($request->user(), $product);

        $validated = $this->validateProduct($request, $product->organization_id, $product);

        $product->update($validated);

        return redirect()
            ->route('products.edit', $product)
            ->with('status', 'Product updated successfully.');
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeManageProduct($request->user(), $product);

        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return redirect()
            ->route('products.index')
            ->with('status', $product->is_active ? 'Product activated.' : 'Product deactivated.');
    }

    /**
     * @return array{name:string,sku:?string,unit_label:?string,branch_id:?int,is_active:bool,sort_order:int}
     */
    private function validateProduct(Request $request, ?int $organizationId, ?Product $product = null): array
    {
        $branchIds = $this->visibleBranches($request->user())->pluck('id')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'unit_label' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::in($branchIds ?: [-1])],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'unit_label' => $validated['unit_label'] ?? null,
            'branch_id' => array_key_exists('branch_id', $validated) ? $validated['branch_id'] : null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function canManageProducts(?\App\Models\User $user): bool
    {
        return $user?->organization_id !== null && $user->canViewAllBranches();
    }

    private function authorizeManageProducts(?\App\Models\User $user): void
    {
        abort_unless($this->canManageProducts($user), 403);
    }

    private function authorizeManageProduct(?\App\Models\User $user, Product $product): void
    {
        abort_unless($this->canManageProducts($user) && $user?->organization_id === $product->organization_id, 403);
    }

    private function visibleBranches(?\App\Models\User $user)
    {
        return Branch::query()
            ->when($user?->organization_id, fn ($query) => $query->where('organization_id', $user->organization_id), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();
    }
}
