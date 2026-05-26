<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ProductMatchingService;
use App\Services\ProductTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_import_page(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->get(route('products.import'))
            ->assertOk()
            ->assertSee('Importar productos');
    }

    public function test_import_creates_products(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Tomate | TOM-001 | kilo | tomate, tomates\nCarne | CAR-001 | kilo | carne",
        ])->assertRedirect(route('products.import'));

        $this->assertDatabaseHas('products', [
            'organization_id' => $user->organization_id,
            'name' => 'Tomate',
            'sku' => 'TOM-001',
            'unit_label' => 'kilo',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('products', [
            'organization_id' => $user->organization_id,
            'name' => 'Carne',
            'sku' => 'CAR-001',
            'unit_label' => 'kilo',
            'is_active' => 1,
        ]);
    }

    public function test_import_creates_aliases(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Tortillas | TOR-001 | paquete | tortillas, paquetes de tortillas",
        ])->assertRedirect(route('products.import'));

        $this->assertDatabaseHas('product_aliases', [
            'organization_id' => $user->organization_id,
            'alias' => 'tortillas',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('product_aliases', [
            'organization_id' => $user->organization_id,
            'alias' => 'paquetes de tortillas',
            'is_active' => 1,
        ]);
    }

    public function test_import_updates_existing_product_instead_of_duplicating(): void
    {
        [$user] = $this->makeOwner();
        $product = Product::create([
            'organization_id' => $user->organization_id,
            'branch_id' => null,
            'name' => 'Tomate',
            'sku' => 'OLD-SKU',
            'unit_label' => 'unidad',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Tomate | TOM-001 | kilo | tomate",
        ])->assertRedirect(route('products.import'));

        $this->assertSame(1, Product::query()->where('organization_id', $user->organization_id)->where('normalized_name', app(ProductTextNormalizer::class)->normalize('Tomate'))->count());
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Tomate',
            'sku' => 'TOM-001',
            'unit_label' => 'kilo',
            'is_active' => 1,
        ]);
    }

    public function test_import_updates_existing_alias_instead_of_duplicating(): void
    {
        [$user] = $this->makeOwner();
        $firstProduct = Product::create([
            'organization_id' => $user->organization_id,
            'branch_id' => null,
            'name' => 'Tomate',
            'sku' => 'TOM-OLD',
            'unit_label' => 'kilo',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $secondProduct = Product::create([
            'organization_id' => $user->organization_id,
            'branch_id' => null,
            'name' => 'Carne',
            'sku' => 'CAR-001',
            'unit_label' => 'kilo',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ProductAlias::create([
            'organization_id' => $user->organization_id,
            'product_id' => $firstProduct->id,
            'alias' => 'tomates',
            'match_weight' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Carne | CAR-001 | kilo | tomates",
        ])->assertRedirect(route('products.import'));

        $this->assertSame(1, ProductAlias::query()->where('organization_id', $user->organization_id)->where('normalized_alias', app(ProductTextNormalizer::class)->normalize('tomates'))->count());
        $this->assertDatabaseHas('product_aliases', [
            'organization_id' => $user->organization_id,
            'product_id' => $secondProduct->id,
            'alias' => 'tomates',
            'is_active' => 1,
        ]);
    }

    public function test_invalid_lines_are_skipped_and_reported(): void
    {
        [$user] = $this->makeOwner();

        $response = $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Tomate | TOM-001 | kilo | tomate\n | bad |\nCarne | CAR-001 | kilo | carne",
        ]);

        $response->assertRedirect(route('products.import'));

        $this->assertDatabaseHas('products', [
            'organization_id' => $user->organization_id,
            'name' => 'Tomate',
        ]);
        $this->assertDatabaseHas('products', [
            'organization_id' => $user->organization_id,
            'name' => 'Carne',
        ]);

        $summary = session('import_summary');
        $this->assertIsArray($summary);
        $this->assertSame(1, $summary['skipped_lines']);
        $this->assertCount(1, $summary['errors']);
    }

    public function test_empty_lines_are_ignored(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "\n\nTomate | TOM-001 | kilo | tomate\n\n",
        ])->assertRedirect(route('products.import'));

        $this->assertDatabaseCount('products', 1);
    }

    public function test_import_result_summary_is_displayed(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)
            ->post(route('products.import.store'), [
                'content' => "Tomate | TOM-001 | kilo | tomate",
            ])
            ->assertRedirect(route('products.import'));

        $this->actingAs($user)->get(route('products.import'))
            ->assertOk()
            ->assertSee('Resultado');
    }

    public function test_product_matching_works_after_bulk_import(): void
    {
        [$user] = $this->makeOwner();

        $this->actingAs($user)->post(route('products.import.store'), [
            'content' => "Bolsas negras | BN-001 | bolsa | bolsas negras, bolsa negra",
        ])->assertRedirect(route('products.import'));

        $organization = $user->organization;
        $result = app(ProductMatchingService::class)->match(
            organization: $organization,
            productName: 'bolsa negra',
            rawText: '1 bolsa negra',
        );

        $this->assertNotNull($result['product']);
        $this->assertSame('bolsa negra', $result['matched_text']);
    }

    /**
     * @return array{0: User}
     */
    private function makeOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Import Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Import Owner',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $user->id]);

        return [$user];
    }
}
