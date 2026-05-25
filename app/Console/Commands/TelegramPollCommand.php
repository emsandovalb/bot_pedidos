<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\IncomingMessage;
use App\Models\User;
use App\Services\IntakeIntentService;
use App\Services\IntakeMessageService;
use App\Services\OrderIngestionService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--loop : Keep polling until stopped with Ctrl+C} {--sleep=5 : Seconds to wait between polling cycles when --loop is enabled}';

    protected $description = 'Poll Telegram updates and ingest text messages into the intake flow.';

    public function handle(
        TelegramBotService $telegramBotService,
        IntakeIntentService $intakeIntentService,
        IntakeMessageService $intakeMessageService,
        OrderIngestionService $orderIngestionService,
    ): int {
        if (! $telegramBotService->enabled()) {
            $this->info('Telegram polling is disabled. Set TELEGRAM_ENABLED=true to process updates.');

            return self::SUCCESS;
        }

        $defaultBranchId = config('services.telegram.default_branch_id');

        if (blank($defaultBranchId)) {
            $this->error('Telegram polling is enabled, but TELEGRAM_DEFAULT_BRANCH_ID is not configured.');

            return self::FAILURE;
        }

        $branch = Branch::query()
            ->with('organization.ownerUser')
            ->find($defaultBranchId);

        if (! $branch) {
            $this->error('Telegram default branch [' . $defaultBranchId . '] was not found.');

            return self::FAILURE;
        }

        $user = $branch->organization?->ownerUser
            ?? User::query()
                ->where('organization_id', $branch->organization_id)
                ->orderByRaw("CASE WHEN role = ? THEN 0 ELSE 1 END, id ASC", [User::ROLE_OWNER])
                ->first();

        if (! $user) {
            $this->error('Telegram default branch has no available organization user to attribute intake events to.');

            return self::FAILURE;
        }

        $loop = (bool) $this->option('loop');
        $sleepSeconds = max(1, (int) $this->option('sleep'));

        do {
            try {
                $stats = $this->pollOnce(
                    $telegramBotService,
                    $intakeIntentService,
                    $intakeMessageService,
                    $orderIngestionService,
                    $user,
                    $branch,
                    $loop,
                );

                $this->line(sprintf(
                    'Processed %d message(s); skipped %d duplicate update(s).',
                    $stats['processed'],
                    $stats['duplicates'],
                ));
            } catch (\Throwable $e) {
                $this->error('Telegram polling failed: ' . $e->getMessage());

                if (! $loop) {
                    return self::FAILURE;
                }
            }

            if (! $loop) {
                break;
            }

            sleep($sleepSeconds);
        } while (true);

        return self::SUCCESS;
    }

    private function rememberProcessedTelegramUpdateId(int $updateId): void
    {
        $current = $this->lastProcessedTelegramUpdateId();

        if ($current !== null && $updateId <= $current) {
            return;
        }

        Cache::forever('telegram.last_processed_update_id', $updateId);
    }

    /**
     * @return array{processed:int, duplicates:int}
     */
    private function pollOnce(
        TelegramBotService $telegramBotService,
        IntakeIntentService $intakeIntentService,
        IntakeMessageService $intakeMessageService,
        OrderIngestionService $orderIngestionService,
        User $user,
        Branch $branch,
        bool $continueOnError,
    ): array {
        $offset = $this->lastProcessedTelegramUpdateId();
        $updates = $telegramBotService->getUpdates($offset !== null ? $offset + 1 : null);

        $processedCount = 0;
        $duplicateCount = 0;

        foreach ($updates as $update) {
            if (! is_array($update)) {
                continue;
            }

            $updateId = $telegramBotService->extractUpdateId($update);
            $chatId = $telegramBotService->extractChatId($update);
            $messageText = $telegramBotService->extractMessageText($update);

            if ($updateId === null || $chatId === null || $messageText === null) {
                continue;
            }

            $alreadyProcessed = IncomingMessage::query()
                ->where('channel_type', Branch::CHANNEL_TYPE_TELEGRAM)
                ->where('external_message_id', $updateId)
                ->exists();

            if ($alreadyProcessed) {
                $duplicateCount++;
                $this->rememberProcessedTelegramUpdateId($updateId);
                continue;
            }

            try {
                if (config('services.order_ingestion.enabled')) {
                    if ($this->isTelegramCommandMessage($messageText)) {
                        $intent = $intakeIntentService->detect($messageText);

                        $this->rememberProcessedTelegramUpdateId($updateId);
                        $telegramBotService->sendMessage(
                            $chatId,
                            match ($intent['command']) {
                                'start' => $intakeIntentService->startReply(),
                                'help', 'menu' => $intakeIntentService->helpReply(),
                                default => $intakeIntentService->helpReply(),
                            }
                        );
                        $processedCount++;

                        continue;
                    }

                    $customer = $this->resolveTelegramCustomer(
                        branch: $branch,
                        customerPhone: (string) $chatId,
                        customerName: $telegramBotService->extractSenderName($update),
                    );

                    $incomingMessage = IncomingMessage::create([
                        'organization_id' => $branch->organization_id,
                        'branch_id' => $branch->id,
                        'customer_id' => $customer->id,
                        'channel_type' => Branch::CHANNEL_TYPE_TELEGRAM,
                        'from_identifier' => (string) $chatId,
                        'to_identifier' => $telegramBotService->getBotIdentifier(),
                        'raw_text' => $messageText,
                        'payload_json' => [
                            'source' => Branch::CHANNEL_TYPE_TELEGRAM,
                            'payload' => $update,
                        ],
                        'external_message_id' => (string) $updateId,
                        'status' => IncomingMessage::STATUS_RECEIVED,
                        'received_at' => now(),
                    ]);

                    $orderIngestionService->ingest(
                        organization: $branch->organization,
                        branch: $branch,
                        customer: $customer,
                        rawMessageText: $messageText,
                        sourceChannel: Branch::CHANNEL_TYPE_TELEGRAM,
                        externalMessageId: (string) $updateId,
                        incomingMessage: $incomingMessage,
                    );

                    $this->rememberProcessedTelegramUpdateId($updateId);
                    $telegramBotService->sendMessage($chatId, $this->orderIngestionReply($messageText));
                    $processedCount++;

                    continue;
                }

                $intent = $intakeIntentService->detect($messageText);

                if ($intent['type'] === IntakeIntentService::TYPE_COMMAND) {
                    $this->rememberProcessedTelegramUpdateId($updateId);
                    $telegramBotService->sendMessage(
                        $chatId,
                        match ($intent['command']) {
                            'start' => $intakeIntentService->startReply(),
                            'help', 'menu' => $intakeIntentService->helpReply(),
                            default => $intakeIntentService->helpReply(),
                        }
                    );
                    $processedCount++;

                    continue;
                }

                if ($intent['type'] === IntakeIntentService::TYPE_GREETING) {
                    $this->rememberProcessedTelegramUpdateId($updateId);
                    $telegramBotService->sendMessage($chatId, $intakeIntentService->greetingReply());
                    $processedCount++;

                    continue;
                }

                $result = $intakeMessageService->create(
                    user: $user,
                    branch: $branch,
                    customerPhone: $chatId,
                    customerName: $telegramBotService->extractSenderName($update),
                    rawText: $messageText,
                    payload: $update,
                    externalMessageId: $updateId,
                    channelType: Branch::CHANNEL_TYPE_TELEGRAM,
                    fromIdentifier: $chatId,
                    toIdentifier: $telegramBotService->getBotIdentifier(),
                );

                $this->rememberProcessedTelegramUpdateId($updateId);
                $telegramBotService->sendMessage($chatId, $result['customer_confirmation_text']);
                $processedCount++;
            } catch (\Throwable $e) {
                $this->error(sprintf('Telegram update %s failed: %s', $updateId, $e->getMessage()));

                if (! $continueOnError) {
                    throw $e;
                }
            }
        }

        return [
            'processed' => $processedCount,
            'duplicates' => $duplicateCount,
        ];
    }

    private function lastProcessedTelegramUpdateId(): ?int
    {
        $updateIds = IncomingMessage::query()
            ->where('channel_type', Branch::CHANNEL_TYPE_TELEGRAM)
            ->whereNotNull('external_message_id')
            ->pluck('external_message_id')
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0);

        $cachedUpdateId = Cache::get('telegram.last_processed_update_id');
        if (is_numeric($cachedUpdateId)) {
            $updateIds->push((int) $cachedUpdateId);
        }

        if ($updateIds->isEmpty()) {
            return null;
        }

        return $updateIds->max();
    }

    private function resolveTelegramCustomer(Branch $branch, string $customerPhone, ?string $customerName): Customer
    {
        $customer = Customer::query()
            ->where('organization_id', $branch->organization_id)
            ->where('phone', $customerPhone)
            ->first();

        if (! $customer) {
            $customer = Customer::create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'name' => $customerName,
                'phone' => $customerPhone,
                'external_id' => null,
            ]);

            return $customer;
        }

        $updates = [];

        if ($customer->branch_id !== $branch->id) {
            $updates['branch_id'] = $branch->id;
        }

        if ($customerName && blank($customer->name)) {
            $updates['name'] = $customerName;
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer->fresh();
    }

    private function isTelegramCommandMessage(string $messageText): bool
    {
        return str_starts_with($messageText, '/');
    }

    private function orderIngestionReply(string $messageText): string
    {
        return sprintf(
            "Recibimos tu pedido y sera revisado por un operador.\n\nMensaje recibido:\n'%s'\n\nPronto confirmaremos tu pedido.",
            $messageText,
        );
    }
}
