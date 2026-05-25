<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ProductTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_product_index(): void
    {
        [$user, $product] = $this->makeOwnerAndProduct();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_user_can_create_product_and_normalized_name_is_stored(): void
    {
        [$user] = $this->makeOwner();
        $normalizedName = app(ProductTextNormalizer::class)->normalize('  Bolsas   de  jardin  ');

        $this->actingAs($user)
            ->post(route('products.store'), [
                'name' => '  Bolsas   de  jardin  ',
                'sku' => 'SKU-100',
                'unit_label' => 'bolsa',
                'branch_id' => null,
                'is_active' => 1,
                'sort_order' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'organization_id' => $user->organization_id,
            'name' => 'Bolsas   de  jardin',
            'normalized_name' => $normalizedName,
            'sku' => 'SKU-100',
            'unit_label' => 'bolsa',
            'is_active' => 1,
            'sort_order' => 3,
        ]);
    }

    public function test_user_can_update_product(): void
    {
        [$user, $product] = $this->makeOwnerAndProduct();
        $normalizedName = app(ProductTextNormalizer::class)->normalize('Vasos plasticos');

        $this->actingAs($user)
            ->patch(route('products.update', $product), [
                'name' => 'Vasos plasticos',
                'sku' => 'SKU-200',
                'unit_label' => 'caja',
                'branch_id' => null,
                'is_active' => 0,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('products.edit', $product));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Vasos plasticos',
            'normalized_name' => $normalizedName,
            'sku' => 'SKU-200',
            'unit_label' => 'caja',
            'is_active' => 0,
            'sort_order' => 10,
        ]);
    }

    public function test_user_can_toggle_product_active_status(): void
    {
        [$user, $product] = $this->makeOwnerAndProduct();

        $this->actingAs($user)
            ->post(route('products.toggle', $product))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => 0,
        ]);
    }

    public function test_user_can_add_product_alias_and_normalized_alias_is_stored(): void
    {
        [$user, $product] = $this->makeOwnerAndProduct();
        $normalizedAlias = app(ProductTextNormalizer::class)->normalize('  Bolsa   jardin  ');

        $this->actingAs($user)
            ->post(route('product-aliases.store', $product), [
                'alias' => '  Bolsa   jardin  ',
                'match_weight' => 250,
                'is_active' => 1,
            ])
            ->assertRedirect(route('products.edit', $product));

        $this->assertDatabaseHas('product_aliases', [
            'organization_id' => $user->organization_id,
            'product_id' => $product->id,
            'alias' => 'Bolsa   jardin',
            'normalized_alias' => $normalizedAlias,
            'match_weight' => 250,
            'is_active' => 1,
        ]);
    }

    public function test_user_can_delete_product_alias(): void
    {
        [$user, $product] = $this->makeOwnerAndProduct();
        $alias = ProductAlias::create([
            'organization_id' => $user->organization_id,
            'product_id' => $product->id,
            'alias' => 'bolsa jardin',
            'match_weight' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('product-aliases.destroy', $alias))
            ->assertRedirect(route('products.edit', $product));

        $this->assertDatabaseMissing('product_aliases', [
            'id' => $alias->id,
        ]);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function makeOwnerAndProduct(): array
    {
        [$user] = $this->makeOwner();

        $product = Product::create([
            'organization_id' => $user->organization_id,
            'branch_id' => null,
            'name' => 'Bolsas de jardin',
            'sku' => 'SKU-001',
            'unit_label' => 'bolsa',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return [$user, $product];
    }

    /**
     * @return array{0: User}
     */
    private function makeOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Catalog Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Catalog Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        return [$user];
    }
}
