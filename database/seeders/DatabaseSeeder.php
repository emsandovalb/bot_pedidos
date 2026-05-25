<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::create([
            'name' => 'Local Demo Organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branches = collect([
            ['name' => 'Central Branch', 'channel_identifier' => '+50255500001'],
            ['name' => 'North Branch', 'channel_identifier' => '+50255500002'],
            ['name' => 'South Branch', 'channel_identifier' => '+50255500003'],
        ])->map(function (array $branchData) use ($organization) {
            return Branch::create([
                'organization_id' => $organization->id,
                'name' => $branchData['name'],
                'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
                'channel_identifier' => $branchData['channel_identifier'],
            'status' => Branch::STATUS_ACTIVE,
            ]);
        });

        $draws = collect([
            ['name' => '12:00 md', 'draw_time' => '12:00:00'],
            ['name' => '2:00 pm', 'draw_time' => '14:00:00'],
            ['name' => '5:00 pm', 'draw_time' => '17:00:00'],
            ['name' => '7:00 pm', 'draw_time' => '19:00:00'],
        ])->map(function (array $drawData) use ($organization) {
            return Draw::create([
                'organization_id' => $organization->id,
                'name' => $drawData['name'],
                'draw_time' => $drawData['draw_time'],
                'status' => Draw::STATUS_ACTIVE,
            ]);
        });

        $owner = User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_OWNER,
            'name' => 'Owner User',
            'email' => 'owner@local.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $organization->update(['owner_user_id' => $owner->id]);

        User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_ADMIN,
            'name' => 'Admin User',
            'email' => 'admin@local.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        User::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'role' => User::ROLE_VIEWER,
            'name' => 'Viewer User',
            'email' => 'viewer@local.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $branches->values()->each(function (Branch $branch, int $index) use ($organization, $owner, $draws) {
            $seller = User::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'role' => User::ROLE_SELLER,
                'name' => sprintf('Seller %d', $index + 1),
                'email' => sprintf('seller-%d@local.test', $index + 1),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            $customer = Customer::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Sample Customer ' . ($index + 1),
                'phone' => '+502555100' . ($index + 1),
                'external_id' => 'cust-' . ($index + 1),
            ]);

            $incomingMessage = IncomingMessage::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'channel_type' => Branch::CHANNEL_TYPE_SIMULATED,
                'from_identifier' => $customer->phone,
                'to_identifier' => $branch->channel_identifier,
                'raw_text' => 'Sample request message for ' . $branch->name,
                'payload_json' => [
                    'branch' => $branch->name,
                    'seller_email' => $seller->email,
                ],
                'external_message_id' => 'msg-' . ($index + 1),
                'status' => IncomingMessage::STATUS_RECEIVED,
                'received_at' => now()->subHours($index + 1),
            ]);

            IntakeRequest::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'draw_id' => $draws->get($index)->id,
                'customer_id' => $customer->id,
                'incoming_message_id' => $incomingMessage->id,
                'detected_number' => '7' . ($index + 1) . '23',
                'detected_amount' => 50 + ($index * 25),
                'raw_text' => $incomingMessage->raw_text . ' ' . $draws->get($index)->name,
                'status' => match ($index) {
                    0 => IntakeRequest::STATUS_PENDING,
                    1 => IntakeRequest::STATUS_NEEDS_REVIEW,
                    default => IntakeRequest::STATUS_CONFIRMED,
                },
                'confirmed_by' => $index === 2 ? $owner->id : null,
                'confirmed_at' => $index === 2 ? now()->subHours(1) : null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => $index === 1 ? 'Needs amount clarification.' : null,
            ]);
        });

        $products = collect([
            [
                'name' => 'Bolsas de jardÃ­n',
                'sku' => 'JARDIN-01',
                'unit_label' => 'bolsa',
                'branch_id' => null,
                'aliases' => ['bolsas de jardin', 'bolsa jardin'],
            ],
            [
                'name' => 'Bolsas de apretados',
                'sku' => 'APRET-01',
                'unit_label' => 'bolsa',
                'branch_id' => $branches->first()?->id,
                'aliases' => ['bolsas de apretados', 'apretados'],
            ],
            [
                'name' => 'Vasos plÃ¡sticos',
                'sku' => 'VASOS-01',
                'unit_label' => 'caja',
                'branch_id' => null,
                'aliases' => ['vasos', 'caja de vasos'],
            ],
        ]);

        $products->each(function (array $productData) use ($organization): void {
            $product = Product::create([
                'organization_id' => $organization->id,
                'branch_id' => $productData['branch_id'],
                'name' => $productData['name'],
                'sku' => $productData['sku'],
                'unit_label' => $productData['unit_label'],
                'is_active' => true,
                'sort_order' => 0,
            ]);

            foreach ($productData['aliases'] as $alias) {
                ProductAlias::create([
                    'organization_id' => $organization->id,
                    'product_id' => $product->id,
                    'alias' => $alias,
                    'match_weight' => 100,
                    'is_active' => true,
                ]);
            }
        });
    }
}
