<?php

namespace App\Jobs;

use App\Models\IncomingMessage;
use App\Services\Messaging\MessagingIngestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un mensaje entrante ya persistido (parser + creación de pedido).
 * Se encola desde el webhook para que este devuelva 200 de inmediato y no
 * dependa del tiempo del parser ni del pipeline de pedidos.
 */
class ProcessInboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> segundos de espera entre reintentos */
    public array $backoff = [10, 60, 300];

    public function __construct(public IncomingMessage $incomingMessage) {}

    public function handle(MessagingIngestionService $ingestionService): void
    {
        $ingestionService->processInbound($this->incomingMessage);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessInboundMessage agotó los reintentos.', [
            'incoming_message_id' => $this->incomingMessage->id ?? null,
            'exception' => $exception?->getMessage(),
        ]);

        $incomingMessage = IncomingMessage::find($this->incomingMessage->id);

        if ($incomingMessage !== null && $incomingMessage->status !== IncomingMessage::STATUS_PROCESSED) {
            $incomingMessage->forceFill([
                'status' => IncomingMessage::STATUS_FAILED,
                'status_reason' => 'Job de procesamiento agotó los reintentos: '.($exception?->getMessage() ?? 'error desconocido'),
                'processed_at' => now(),
            ])->save();
        }
    }
}
