<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_does_not_run_when_disabled(): void
    {
        $this->configureTelegram(enabled: false);
        Http::fake();

        $exitCode = Artisan::call('telegram:poll');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_telegram_text_update_creates_order_and_replies(): void
    {
        $branch = $this->makeOrgWithTelegramBranch();
        $messageText = '2 bolsas de jardin y 1 caja de vasos';
        $update = $this->telegramUpdate(9010, 4010, $messageText, 'maria', 'Maria');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        $exitCode = Artisan::call('telegram:poll');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'branch_id' => $branch->id,
            'raw_message_text' => $messageText,
            'status' => Order::STATUS_PENDING_REVIEW,
        ]);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => '4010',
            'to_identifier' => '@pedidosbot',
            'raw_text' => $messageText,
            'external_message_id' => '9010',
            'parse_status' => 'unmatched',
        ]);

        Http::assertSent(function ($request) use ($messageText): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4010'
                && $request->data()['text'] === "Recibimos tu pedido y sera revisado por un operador.\n\nMensaje recibido:\n'".$messageText."'\n\nPronto confirmaremos tu pedido.";
        });

        $this->assertStringContainsString(
            'Processed 1 message(s); skipped 0 duplicate update(s).',
            Artisan::output(),
        );
    }

    public function test_command_runs_without_an_organization_owner(): void
    {
        $branch = $this->makeOrgWithTelegramBranch(withOwner: false);
        $update = $this->telegramUpdate(9011, 4011, '2 bolsas de jardin', 'ana', 'Ana');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        $this->assertSame(0, Artisan::call('telegram:poll'));
        $this->assertDatabaseCount('orders', 1);
    }

    #[DataProvider('telegramCommandProvider')]
    public function test_telegram_commands_send_help_without_creating_orders(string $command): void
    {
        $branch = $this->makeOrgWithTelegramBranch();
        $update = $this->telegramUpdate(9008, 4008, $command, 'botuser', 'Bot User');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('incoming_messages', 0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = $data['text'] ?? null;

            return str_contains($request->url(), '/sendMessage')
                && ($data['chat_id'] ?? null) === '4008'
                && is_string($text)
                && str_contains($text, '2 bolsas de jardin')
                && str_contains($text, 'BotPedidos');
        });
    }

    public static function telegramCommandProvider(): array
    {
        return [
            'start' => ['/start'],
            'help' => ['/help'],
            'menu' => ['/menu'],
        ];
    }

    public function test_telegram_greeting_is_kept_and_does_not_create_an_order(): void
    {
        $branch = $this->makeOrgWithTelegramBranch();
        $update = $this->telegramUpdate(9007, 4007, 'hola', 'sofia', 'Sofia');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        // El mensaje se guarda (nunca se descarta en silencio) pero no se vuelve pedido.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('incoming_messages', [
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'external_message_id' => '9007',
        ]);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4007'
                && str_contains($request->data()['text'], 'la cantidad y el producto');
        });
    }

    public function test_duplicate_update_is_not_processed_twice(): void
    {
        $branch = $this->makeOrgWithTelegramBranch();
        $update = $this->telegramUpdate(9004, 4004, '2 bolsas de jardin', 'carlos', 'Carlos');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update, $update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    private function makeOrgWithTelegramBranch(bool $withOwner = true): Branch
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Telegram Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@pedidosbot',
            'status' => Branch::STATUS_ACTIVE,
        ]);

        if ($withOwner) {
            $user = User::create([
                'organization_id' => $organization->id,
                'branch_id' => null,
                'role' => User::ROLE_OWNER,
                'name' => 'Owner',
                'email' => fake()->unique()->safeEmail(),
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]);

            $organization->update(['owner_user_id' => $user->id]);
        }

        return $branch->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramUpdate(int $updateId, int $chatId, string $text, ?string $username = null, ?string $firstName = null): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId + 100,
                'date' => now()->timestamp,
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => array_filter([
                    'id' => $chatId,
                    'username' => $username,
                    'first_name' => $firstName,
                ], static fn ($value) => $value !== null),
                'text' => $text,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function telegramResponses(array $updates): array
    {
        return [
            'https://api.telegram.org/bot*/getMe' => Http::response([
                'ok' => true,
                'result' => [
                    'id' => 123456789,
                    'is_bot' => true,
                    'first_name' => 'Pedidos Bot',
                    'username' => 'pedidosbot',
                ],
            ], 200),
            'https://api.telegram.org/bot*/getUpdates' => Http::response([
                'ok' => true,
                'result' => $updates,
            ], 200),
            'https://api.telegram.org/bot*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 777,
                ],
            ], 200),
        ];
    }

    private function configureTelegram(bool $enabled, ?int $branchId = null): void
    {
        Config::set('services.telegram.enabled', $enabled);
        Config::set('services.telegram.bot_token', 'test-token');
        Config::set('services.telegram.default_branch_id', $branchId);
    }
}
