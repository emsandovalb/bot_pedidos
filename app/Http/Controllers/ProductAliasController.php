<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\ProductTextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductAliasController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeManageProduct($request->user(), $product);

        $validated = $request->validate([
            'alias' => ['required', 'string', 'max:255'],
            'match_weight' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $normalizedAlias = app(ProductTextNormalizer::class)->normalize($validated['alias']);

        if ($normalizedAlias === '') {
            throw ValidationException::withMessages([
                'alias' => 'Alias is required.',
            ]);
        }

        $duplicateExists = ProductAlias::query()
            ->where('organization_id', $product->organization_id)
            ->where('normalized_alias', $normalizedAlias)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'alias' => 'The alias has already been taken.',
            ]);
        }

        $alias = ProductAlias::create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'alias' => $validated['alias'],
            'match_weight' => (int) ($validated['match_weight'] ?? 100),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('products.edit', $product)
            ->with('status', sprintf('Alias "%s" added.', $alias->alias));
    }

    public function destroy(Request $request, ProductAlias $productAlias): RedirectResponse
    {
        $productAlias->loadMissing('product');
        $product = $productAlias->product;

        abort_unless($product !== null, 404);
        $this->authorizeManageProduct($request->user(), $product);

        $alias = $productAlias->alias;
        $productAlias->delete();

        return redirect()
            ->route('products.edit', $product)
            ->with('status', sprintf('Alias "%s" deleted.', $alias));
    }

    private function authorizeManageProduct(?\App\Models\User $user, Product $product): void
    {
        abort_unless($user?->organization_id !== null && $user->canViewAllBranches() && $user->organization_id === $product->organization_id, 403);
    }
}
