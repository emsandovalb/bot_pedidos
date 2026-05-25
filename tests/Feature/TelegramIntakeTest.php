<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\Order;
use App\Models\IntakeRequest;
use App\Models\MessageResponse;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
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
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('message_responses', 0);
    }

    public function test_telegram_text_update_creates_incoming_message(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9001, 4001, '1000 al 28 2pm', 'maria', 'Maria');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        $exitCode = Artisan::call('telegram:poll');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => '4001',
            'to_identifier' => '@loteriabot',
            'raw_text' => '1000 al 28 2pm',
            'external_message_id' => '9001',
        ]);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4001'
                && str_contains($request->data()['text'], 'Sorteo 2:00 pm');
        });
    }

    public function test_telegram_text_update_uses_order_ingestion_when_enabled(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $messageText = '2 bolsas de jardin y 1 caja de vasos';
        $update = $this->telegramUpdate(9010, 4010, $messageText, 'maria', 'Maria');

        $this->configureTelegram(enabled: true, branchId: $branch->id, orderIngestionEnabled: true);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'branch_id' => $branch->id,
            'raw_message_text' => $messageText,
            'status' => Order::STATUS_PENDING_REVIEW,
        ]);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('message_responses', 0);
        $this->assertDatabaseHas('incoming_messages', [
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'from_identifier' => '4010',
            'to_identifier' => '@loteriabot',
            'raw_text' => $messageText,
            'external_message_id' => '9010',
            'parse_status' => Order::STATUS_PENDING_REVIEW,
        ]);

        Http::assertSent(function ($request) use ($messageText): bool {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $text = $request->data()['text'];

            return $request->data()['chat_id'] === '4010'
                && $text === "Recibimos tu pedido y sera revisado por un operador.\n\nMensaje recibido:\n'" . $messageText . "'\n\nPronto confirmaremos tu pedido."
                && str_contains($text, 'pedido')
                && str_contains($text, $messageText)
                && ! str_contains($text, 'Sorteo')
                && ! str_contains($text, 'amount')
                && ! str_contains($text, 'number');
        });
    }

    public function test_telegram_valid_intake_creates_request_and_replies(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9002, 4002, '1000 al 25 y 28 5pm', 'jose', 'Jose');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('requests', 2);
        $this->assertDatabaseHas('requests', [
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 5pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'detected_amount' => 1000,
            'detected_number' => '25',
        ]);
        $this->assertDatabaseHas('requests', [
            'branch_id' => $branch->id,
            'raw_text' => '1000 al 25 y 28 5pm',
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'detected_amount' => 1000,
            'detected_number' => '28',
        ]);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4002'
                && str_contains($request->data()['text'], 'Sorteo 5:00 pm');
        });

        $this->assertStringContainsString(
            'Processed 1 message(s); skipped 0 duplicate update(s).',
            Artisan::output(),
        );
    }

    public function test_generated_confirmation_response_is_stored(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9003, 4003, '1000 al 28 12 md', 'ana', 'Ana');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('message_responses', 1);

        $response = MessageResponse::query()->firstOrFail();

        $this->assertSame('confirmation', $response->response_type);
        $this->assertStringContainsString('Sorteo 12:00 md', $response->response_text);
    }

    #[DataProvider('telegramCommandProvider')]
    public function test_telegram_commands_do_not_create_requests_and_send_help(string $command, string $expectedText): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9006, 4006, $command, 'botuser', 'Bot User');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('message_responses', 0);

        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4006'
            && $request->data()['text'] === $expectedText;
        });
    }

    #[DataProvider('telegramCommandProvider')]
    public function test_telegram_commands_still_work_when_order_ingestion_is_enabled(string $command, string $expectedText): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9008, 4008, $command, 'botuser', 'Bot User');

        $this->configureTelegram(enabled: true, branchId: $branch->id, orderIngestionEnabled: true);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('message_responses', 0);

        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4008'
                && $request->data()['text'] === $expectedText;
        });
    }

    public static function telegramCommandProvider(): array
    {
        $startHelpText = "Bienvenido al sistema de recepción de solicitudes.\n\nPuedes enviar mensajes como:\n\n• 1000 al 28 2pm\n• 1000 al 25 y 28 5pm\n\nTu solicitud será revisada y confirmada.";
        $helpText = "Puedo ayudarte a registrar solicitudes de forma simple.\n\nEnvíame mensajes como:\n\n• 1000 al 28 2pm\n• 1000 al 25 y 28 5pm\n\nSi tu mensaje trae una solicitud válida, la procesaré de inmediato.";

        return [
            'start' => ['/start', $startHelpText],
            'help' => ['/help', $helpText],
            'menu' => ['/menu', $helpText],
        ];
    }

    public function test_telegram_greeting_does_not_create_request_and_replies_helpfully(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9007, 4007, 'hola', 'sofia', 'Sofia');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('incoming_messages', 0);
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('message_responses', 0);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/sendMessage')
                && $request->data()['chat_id'] === '4007'
                && str_contains($request->data()['text'], 'Hola.')
                && str_contains($request->data()['text'], '1000 al 28 2pm');
        });
    }

    public function test_duplicate_update_is_not_processed_twice(): void
    {
        [, $branch] = $this->makeOrgWithTelegramBranchAndOwner();
        $update = $this->telegramUpdate(9004, 4004, '1000 al 28 2pm', 'carlos', 'Carlos');

        $this->configureTelegram(enabled: true, branchId: $branch->id);
        Http::fake($this->telegramResponses([$update, $update]));

        Artisan::call('telegram:poll');

        $this->assertDatabaseCount('incoming_messages', 1);
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('incoming_messages', [
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'external_message_id' => '9004',
        ]);
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function makeOrgWithTelegramBranchAndOwner(): array
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $branch = Branch::create([
            'organization_id' => $organization->id,
            'name' => 'Telegram Branch',
            'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
            'channel_identifier' => '@loteriabot',
            'status' => Branch::STATUS_ACTIVE,
        ]);

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

        collect([
            ['name' => '12:00 md', 'draw_time' => '12:00:00'],
            ['name' => '2:00 pm', 'draw_time' => '14:00:00'],
            ['name' => '5:00 pm', 'draw_time' => '17:00:00'],
            ['name' => '7:00 pm', 'draw_time' => '19:00:00'],
        ])->each(function (array $drawData) use ($organization): void {
            Draw::create([
                'organization_id' => $organization->id,
                'name' => $drawData['name'],
                'draw_time' => $drawData['draw_time'],
                'status' => Draw::STATUS_ACTIVE,
            ]);
        });

        return [$user->fresh(), $branch->fresh()];
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
                    'first_name' => 'Loteria Bot',
                    'username' => 'loteriabot',
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

    private function configureTelegram(bool $enabled, ?int $branchId = null, bool $orderIngestionEnabled = false): void
    {
        Config::set('services.telegram.enabled', $enabled);
        Config::set('services.telegram.bot_token', 'test-token');
        Config::set('services.telegram.default_branch_id', $branchId);
        Config::set('services.order_ingestion.enabled', $orderIngestionEnabled);
    }
}
