<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\IncomingMessage;
use App\Services\Messaging\DTO\IncomingMessageDTO;
use App\Services\Messaging\MessagingIngestionService;
use App\Services\OrderParserService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--loop : Keep polling until stopped with Ctrl+C} {--sleep=5 : Seconds to wait between polling cycles when --loop is enabled}';

    protected $description = 'Poll Telegram updates and ingest orders from Telegram messages.';

    public function handle(
        TelegramBotService $telegramBotService,
        MessagingIngestionService $messagingIngestionService,
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
            ->with('organization')
            ->find($defaultBranchId);

        if (! $branch) {
            $this->error('Telegram default branch ['.$defaultBranchId.'] was not found.');

            return self::FAILURE;
        }

        $loop = (bool) $this->option('loop');
        $sleepSeconds = max(1, (int) $this->option('sleep'));

        do {
            try {
                $stats = $this->pollOnce(
                    $telegramBotService,
                    $messagingIngestionService,
                    $branch,
                    $loop,
                );

                $this->line(sprintf(
                    'Processed %d message(s); skipped %d duplicate update(s).',
                    $stats['processed'],
                    $stats['duplicates'],
                ));
            } catch (\Throwable $e) {
                $this->error('Telegram polling failed: '.$e->getMessage());

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
        MessagingIngestionService $messagingIngestionService,
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
                ->where('provider', Branch::CHANNEL_TYPE_TELEGRAM)
                ->where('external_message_id', $updateId)
                ->exists();

            if ($alreadyProcessed) {
                $duplicateCount++;
                $this->rememberProcessedTelegramUpdateId($updateId);

                continue;
            }

            try {
                $command = $this->detectOrderCommand($messageText);

                if ($command !== null) {
                    $this->rememberProcessedTelegramUpdateId($updateId);
                    $telegramBotService->sendMessage(
                        $chatId,
                        $command === 'start'
                            ? $this->orderStartReply()
                            : $this->orderHelpReply()
                    );
                    $processedCount++;

                    continue;
                }

                $result = $messagingIngestionService->ingest(
                    organization: $branch->organization,
                    branch: $branch,
                    message: new IncomingMessageDTO(
                        provider: Branch::CHANNEL_TYPE_TELEGRAM,
                        external_message_id: (string) $updateId,
                        external_chat_id: (string) $chatId,
                        customer_name: $telegramBotService->extractSenderName($update),
                        customer_phone: (string) $chatId,
                        message: $messageText,
                        received_at: now(),
                        raw_payload: [
                            'source' => Branch::CHANNEL_TYPE_TELEGRAM,
                            'payload' => $update,
                        ],
                        attachments: [],
                    ),
                );

                $this->rememberProcessedTelegramUpdateId($updateId);
                if ($result['duplicate']) {
                    $duplicateCount++;

                    continue;
                }

                if ($result['status'] === IncomingMessage::STATUS_FAILED) {
                    throw new \RuntimeException($result['failure_reason'] ?? 'Telegram order ingestion failed.');
                }

                $telegramBotService->sendMessage(
                    $chatId,
                    $result['order'] === null
                        ? $this->nonOrderIngestionReply((string) ($result['incoming_message']->parse_status ?? ''))
                        : $this->orderIngestionReply($messageText),
                );
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

    private function detectOrderCommand(string $messageText): ?string
    {
        if (! preg_match('/^\/(start|help|menu)(?:@\w+)?(?:\s|$)/u', $messageText, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function orderIngestionReply(string $messageText): string
    {
        return sprintf(
            "Recibimos tu pedido y sera revisado por un operador.\n\nMensaje recibido:\n'%s'\n\nPronto confirmaremos tu pedido.",
            $messageText,
        );
    }

    private function nonOrderIngestionReply(string $parseStatus): string
    {
        return match ($parseStatus) {
            OrderParserService::STATUS_INVALID => 'No pude identificar un pedido en ese mensaje. Envíame la cantidad y el producto para que el equipo pueda revisarlo.',
            default => 'Ese mensaje no parece ser un pedido. Puedes enviarme la cantidad y el producto que necesitas.',
        };
    }

    private function orderStartReply(): string
    {
        return implode("\n\n", [
            'Bienvenido a BotPedidos.',
            'Envia mensajes como:',
            "• 2 bolsas de jardin\n• 1 caja de vasos\n• 5 bolsas de apretados",
            'Tu pedido sera revisado y preparado por el equipo.',
        ]);
    }

    private function orderHelpReply(): string
    {
        return implode("\n\n", [
            'BotPedidos recibe pedidos automaticos por Telegram.',
            'Envia mensajes como:',
            "• 2 bolsas de jardin\n• 1 caja de vasos\n• 5 bolsas de apretados",
            'Tambien puedes revisar pedidos, productos y seguimiento desde el panel.',
        ]);
    }
}
