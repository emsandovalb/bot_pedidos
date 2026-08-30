<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Services\OrderConfirmationReadinessService;
use App\Services\OrderIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PilotMessageCasesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nonEmptyPilotCases')]
    public function test_pilot_message_case_matches_the_real_ingestion_and_confirmation_pipeline(array $case): void
    {
        [$organization, $branch, $customer] = $this->makeOrganizationBranchCustomer();
        $this->seedEphemeralCatalog($organization, $case['expected_products']);

        $order = app(OrderIngestionService::class)->ingest(
            organization: $organization,
            branch: $branch,
            customer: $customer,
            rawMessageText: $case['anonymized_message'],
            sourceChannel: 'pilot_evaluation',
        );

        if ($case['expected_classification'] === 'saludo_o_pregunta') {
            $this->assertNull($order, $case['id'] . ': a greeting or question must not create an order.');
            $this->assertNull($case['expected_status'], $case['id'] . ': greetings/questions have no order status.');
            $this->assertFalse($case['can_confirm'], $case['id'] . ': a missing order cannot be confirmed.');

            return;
        }

        $this->assertNotNull($order, $case['id'] . ': the pipeline must create a reviewable order.');
        $this->assertSame($case['anonymized_message'], $order->raw_message_text, $case['id']);
        $this->assertSame($this->parserStatusFor($case['expected_status']), $order->parsed_payload_json['status'], $case['id']);
        $this->assertSame($case['expected_status'] !== 'resolved', $order->parsed_payload_json['needs_review'], $case['id']);

        $actualProducts = $order->orderItems->map(fn ($item): array => [
            'name' => $order->parsed_payload_json['items'][$item->sort_order]['product_name'],
            'quantity' => $this->numericQuantity($item->quantity),
            'unit' => $item->unit,
        ])->all();
        $expectedProducts = array_map(fn (array $product): array => [
            'name' => $product['name'],
            'quantity' => $this->numericQuantity($product['quantity']),
            'unit' => $product['unit'],
        ], $case['expected_products']);

        $this->assertSame($expectedProducts, $actualProducts, $case['id'] . ': extracted products and quantities.');
        $this->assertSame(
            $case['expected_status'] !== 'unmatched',
            $order->orderItems->every(fn ($item): bool => $item->product_id !== null),
            $case['id'] . ': catalog resolution.',
        );
        $this->assertSame($case['can_confirm'], app(OrderConfirmationReadinessService::class)->canConfirm($order), $case['id']);
    }

    public static function nonEmptyPilotCases(): array
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/docs/pilot/pilot-message-cases.json');
        $dataset = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);

        return collect($dataset['cases'] ?? [])
            ->filter(static fn (array $case): bool => trim((string) ($case['anonymized_message'] ?? '')) !== '')
            ->mapWithKeys(static fn (array $case): array => [$case['id'] => [$case]])
            ->all();
    }

    private function seedEphemeralCatalog(Organization $organization, array $expectedProducts): void
    {
        foreach ($expectedProducts as $index => $expectedProduct) {
            if (! isset($expectedProduct['catalog_alias'])) {
                continue;
            }

            $product = Product::create([
                'organization_id' => $organization->id,
                'name' => 'Pilot evaluation product ' . $index,
                'sku' => 'PILOT-EVAL-' . $index,
                'unit_label' => $expectedProduct['unit'],
                'is_active' => true,
                'sort_order' => $index,
            ]);

            ProductAlias::create([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'alias' => $expectedProduct['catalog_alias'],
                'match_weight' => 100,
                'is_active' => true,
            ]);
        }
    }

    private function parserStatusFor(string $expectedStatus): string
    {
        return match ($expectedStatus) {
            'resolved' => 'parsed',
            'pending_review' => 'pending_review',
            'unmatched' => 'unmatched',
        };
    }

    private function numericQuantity(int|float|string $quantity): int|float
    {
        $number = (float) $quantity;

        return floor($number) === $number ? (int) $number : $number;
    }

    /** @return array{0: Organization, 1: Branch, 2: Customer} */
    private function makeOrganizationBranchCustomer(): array
    {
        $organization = Organization::create(['name' => 'Pilot evaluation', 'status' => Organization::STATUS_ACTIVE]);
        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Pilot evaluation branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => 'pilot-evaluation',
            'status' => Branch::STATUS_ACTIVE,
        ]);
        $customer = Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'name' => 'Pilot evaluation customer',
            'phone' => '5550000',
        ]);

        return [$organization, $branch, $customer];
    }
}
