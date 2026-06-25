<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Organization;
use App\Services\CustomerIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_customer_and_identity_for_new_provider_identity(): void
    {
        $organization = $this->makeOrganization();

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'telegram',
            externalUserId: 'telegram-user-1',
            externalChatId: 'telegram-chat-1',
            providerUsername: '@maria',
            phone: '8888-7777',
            displayName: 'Maria',
            email: 'maria@example.com',
            metadata: ['source' => 'telegram'],
        );

        $this->assertSame('new_customer', $result['match_type']);
        $this->assertSame(100, $result['confidence_score']);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_identities', 1);
        $this->assertSame($organization->id, $result['customer']->organization_id);
        $this->assertSame($result['customer']->id, $result['customer_identity']->customer_id);
        $this->assertSame('telegram', $result['customer_identity']->provider);
        $this->assertSame('telegram-user-1', $result['customer_identity']->external_user_id);
        $this->assertSame('telegram-chat-1', $result['customer_identity']->external_chat_id);
        $this->assertSame('+50688887777', $result['customer_identity']->normalized_phone);
        $this->assertNotNull($result['customer_identity']->first_seen_at);
        $this->assertNotNull($result['customer_identity']->last_seen_at);
    }

    public function test_reuses_identity_by_same_provider_and_external_user_id(): void
    {
        $organization = $this->makeOrganization();
        $customer = $this->makeCustomer($organization, 'Ana', '+50255550001');
        $identity = $this->makeIdentity($organization, $customer, [
            'provider' => 'telegram',
            'external_user_id' => 'user-123',
            'external_chat_id' => 'chat-a',
            'display_name' => 'Ana',
        ]);

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'telegram',
            externalUserId: 'user-123',
            externalChatId: 'chat-b',
            providerUsername: '@ana2',
            phone: '+50255550001',
            displayName: 'Ana Updated',
            email: 'ana@example.com',
            metadata: ['incoming' => true],
        );

        $this->assertSame('exact_provider_match', $result['match_type']);
        $this->assertSame($identity->id, $result['customer_identity']->id);
        $this->assertSame($customer->id, $result['customer']->id);
        $this->assertDatabaseCount('customer_identities', 1);
    }

    public function test_reuses_identity_by_same_provider_and_external_chat_id(): void
    {
        $organization = $this->makeOrganization();
        $customer = $this->makeCustomer($organization, 'Luis', '+50255550002');
        $identity = $this->makeIdentity($organization, $customer, [
            'provider' => 'instagram',
            'external_chat_id' => 'chat-789',
            'display_name' => 'Luis',
        ]);

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'instagram',
            externalChatId: 'chat-789',
            providerUsername: 'luis.ig',
            phone: '+50255550002',
            displayName: 'Luis Updated',
            email: 'luis@example.com',
            metadata: ['incoming' => true],
        );

        $this->assertSame('exact_provider_match', $result['match_type']);
        $this->assertSame($identity->id, $result['customer_identity']->id);
        $this->assertSame($customer->id, $result['customer']->id);
        $this->assertDatabaseCount('customer_identities', 1);
    }

    public function test_links_to_same_customer_by_normalized_phone_with_confidence_ninety(): void
    {
        $organization = $this->makeOrganization();
        $customer = $this->makeCustomer($organization, 'Carla', '+50688887777');
        $this->makeIdentity($organization, $customer, [
            'provider' => 'telegram',
            'external_chat_id' => 'chat-900',
            'phone' => '+50688887777',
            'normalized_phone' => '+50688887777',
            'display_name' => 'Carla',
        ]);

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'whatsapp',
            externalUserId: 'wa-user-1',
            externalChatId: 'wa-chat-1',
            providerUsername: 'carla.wa',
            phone: '8888-7777',
            displayName: 'Carla WhatsApp',
            metadata: ['source' => 'whatsapp'],
        );

        $this->assertSame('phone_match', $result['match_type']);
        $this->assertSame(90, $result['confidence_score']);
        $this->assertSame($customer->id, $result['customer']->id);
        $this->assertDatabaseCount('customer_identities', 2);
        $this->assertSame($customer->id, $result['customer_identity']->customer_id);
        $this->assertSame('+50688887777', $result['customer_identity']->normalized_phone);
    }

    public function test_does_not_auto_merge_when_phone_is_ambiguous(): void
    {
        $organization = $this->makeOrganization();
        $firstCustomer = $this->makeCustomer($organization, 'One', '+50688880001');
        $secondCustomer = $this->makeCustomer($organization, 'Two', '+50688880002');

        $this->makeIdentity($organization, $firstCustomer, [
            'provider' => 'telegram',
            'external_chat_id' => 'chat-1',
            'phone' => '+50688880099',
            'normalized_phone' => '+50688880099',
        ]);
        $this->makeIdentity($organization, $secondCustomer, [
            'provider' => 'instagram',
            'external_chat_id' => 'chat-2',
            'phone' => '+50688880099',
            'normalized_phone' => '+50688880099',
        ]);

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'messenger',
            externalUserId: 'messenger-user-1',
            externalChatId: 'messenger-chat-1',
            providerUsername: 'one.more',
            phone: '8888-0099',
            displayName: 'Ambiguous Person',
            metadata: ['source' => 'messenger'],
        );

        $this->assertSame('ambiguous_phone', $result['match_type']);
        $this->assertSame(50, $result['confidence_score']);
        $this->assertNotSame($firstCustomer->id, $result['customer']->id);
        $this->assertNotSame($secondCustomer->id, $result['customer']->id);
        $this->assertDatabaseCount('customer_identities', 3);
    }

    public function test_normalizes_costa_rica_phone_numbers(): void
    {
        $organization = $this->makeOrganization();

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'telegram',
            externalUserId: 'user-900',
            externalChatId: 'chat-900',
            providerUsername: 'normalizer',
            phone: '(506) 8888-7777',
            displayName: 'Normalizer',
        );

        $this->assertSame('+50688887777', $result['customer_identity']->normalized_phone);
        $this->assertSame('(506) 8888-7777', $result['customer_identity']->phone);
    }

    public function test_updates_last_seen_at_on_every_resolution(): void
    {
        $organization = $this->makeOrganization();
        $customer = $this->makeCustomer($organization, 'Seen', '+50255550003');
        $identity = $this->makeIdentity($organization, $customer, [
            'provider' => 'telegram',
            'external_user_id' => 'seen-user',
            'external_chat_id' => 'seen-chat',
        ]);

        $initialLastSeen = $identity->last_seen_at;

        Carbon::setTestNow(now()->addMinutes(10));

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'telegram',
            externalUserId: 'seen-user',
            externalChatId: 'new-chat',
            providerUsername: '@seen',
            phone: '+50255550003',
            displayName: 'Seen Updated',
        );

        Carbon::setTestNow();

        $this->assertSame($identity->id, $result['customer_identity']->id);
        $this->assertTrue($result['customer_identity']->last_seen_at->greaterThan($initialLastSeen));
    }

    public function test_preserves_metadata_json(): void
    {
        $organization = $this->makeOrganization();
        $customer = $this->makeCustomer($organization, 'Meta', '+50255550004');
        $identity = $this->makeIdentity($organization, $customer, [
            'provider' => 'telegram',
            'external_user_id' => 'meta-user',
            'external_chat_id' => 'meta-chat',
            'metadata_json' => [
                'source' => 'telegram',
                'existing' => true,
            ],
        ]);

        $result = app(CustomerIdentityResolver::class)->resolve(
            organizationId: $organization->id,
            provider: 'telegram',
            externalUserId: 'meta-user',
            externalChatId: 'meta-chat',
            providerUsername: '@meta',
            phone: '+50255550004',
            displayName: 'Meta Updated',
            metadata: [
                'source' => 'webhook',
                'incoming' => true,
            ],
        );

        $this->assertSame($identity->id, $result['customer_identity']->id);
        $this->assertSame('telegram', $result['customer_identity']->metadata_json['source']);
        $this->assertTrue($result['customer_identity']->metadata_json['existing']);
        $this->assertTrue($result['customer_identity']->metadata_json['incoming']);
    }

    private function makeOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Identity Org',
            'status' => Organization::STATUS_ACTIVE,
        ]);
    }

    private function makeCustomer(Organization $organization, string $name, string $phone): Customer
    {
        return Customer::create([
            'organization_id' => $organization->id,
            'branch_id' => null,
            'name' => $name,
            'phone' => $phone,
            'external_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeIdentity(Organization $organization, Customer $customer, array $attributes): CustomerIdentity
    {
        return CustomerIdentity::create(array_merge([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'provider' => 'telegram',
            'external_user_id' => null,
            'external_chat_id' => null,
            'provider_username' => null,
            'phone' => null,
            'normalized_phone' => null,
            'email' => null,
            'display_name' => null,
            'confidence_score' => 100,
            'is_primary' => false,
            'metadata_json' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ], $attributes));
    }
}
