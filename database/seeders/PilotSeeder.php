<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use App\Services\ProductTextNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PilotSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['name' => config('pilot.organization_name')],
            ['status' => Organization::STATUS_ACTIVE],
        );

        $owner = User::query()->updateOrCreate(
            ['email' => config('pilot.owner_email')],
            [
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => config('pilot.owner_name'),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $organization->forceFill([
            'owner_user_id' => $owner->id,
        ])->save();

        foreach ([
            [
                'name' => 'Pilot WhatsApp Branch',
                'channel_type' => Branch::CHANNEL_TYPE_WHATSAPP,
                'channel_identifier' => 'pilot-whatsapp-' . $organization->id,
            ],
            [
                'name' => 'Pilot Telegram Branch',
                'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                'channel_identifier' => '@pilot-telegram-' . $organization->id,
            ],
        ] as $branchData) {
            Branch::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'channel_identifier' => $branchData['channel_identifier'],
                ],
                [
                    'name' => $branchData['name'],
                    'channel_type' => $branchData['channel_type'],
                    'status' => Branch::STATUS_ACTIVE,
                ],
            );
        }

        foreach (config('pilot.demo_products', []) as $productData) {
            $product = Product::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'sku' => $productData['sku'],
                ],
                [
                    'branch_id' => null,
                    'name' => $productData['name'],
                    'normalized_name' => app(ProductTextNormalizer::class)->normalize($productData['name']),
                    'unit_label' => $productData['unit_label'],
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );

            foreach ($productData['aliases'] as $alias) {
                ProductAlias::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'normalized_alias' => app(ProductTextNormalizer::class)->normalize($alias),
                    ],
                    [
                        'product_id' => $product->id,
                        'alias' => $alias,
                        'match_weight' => 100,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
