<?php

namespace App\Services;

use Illuminate\Support\Str;

class IntakeIntentService
{
    public const TYPE_COMMAND = 'command';
    public const TYPE_GREETING = 'greeting';
    public const TYPE_INTAKE = 'intake';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * @return array{type:string, normalized_text:string, command:?string}
     */
    public function detect(string $rawText): array
    {
        $normalizedText = $this->normalize($rawText);

        if ($normalizedText === '') {
            return $this->result(self::TYPE_UNKNOWN, $normalizedText);
        }

        $command = $this->detectCommand($normalizedText);

        if ($command !== null) {
            return $this->result(self::TYPE_COMMAND, $normalizedText, $command);
        }

        if ($this->isGreeting($normalizedText)) {
            return $this->result(self::TYPE_GREETING, $normalizedText);
        }

        return $this->result($this->looksLikeIntake($normalizedText) ? self::TYPE_INTAKE : self::TYPE_UNKNOWN, $normalizedText);
    }

    public function startReply(): string
    {
        return implode("\n\n", [
            'Bienvenido a BotPedidos.',
            'Puedes enviar mensajes como:',
            "• 2 bolsas de jardin\n• 1 caja de vasos\n• 5 bolsas de apretados",
            'Tu pedido sera revisado y preparado por el equipo.',
        ]);
    }

    public function helpReply(): string
    {
        return implode("\n\n", [
            'BotPedidos recibe pedidos automaticos por Telegram.',
            'Envia mensajes como:',
            "• 2 bolsas de jardin\n• 1 caja de vasos\n• 5 bolsas de apretados",
            'Tambien puedes revisar pedidos, productos y seguimiento desde el panel.',
        ]);
    }

    public function greetingReply(): string
    {
        return implode("\n\n", [
            'Hola.',
            'Puedes enviar mensajes como:',
            "• 2 bolsas de jardin\n• 1 caja de vasos\n• 5 bolsas de apretados",
            'Tu pedido sera revisado y preparado por el equipo.',
        ]);
    }

    private function detectCommand(string $normalizedText): ?string
    {
        if (! preg_match('/^\/(start|help|menu)(?:@\w+)?(?:\s|$)/u', $normalizedText, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function isGreeting(string $normalizedText): bool
    {
        return in_array($normalizedText, [
            'hola',
            'buenos dias',
            'buenas',
            'hello',
        ], true);
    }

    private function looksLikeIntake(string $normalizedText): bool
    {
        return preg_match(
            '/(?:^|\s)(?:\d+\s*mil|\d+|mil)\s*(?:al|numero|num|#)\s*\d{1,2}(?:\s*(?:y|,)\s*\d{1,2})*(?:\s*(?:12\s*pm|12pm|2\s*pm|2pm|5\s*pm|5pm|7\s*pm|7pm|medio\s*dia))?(?:\s|$)/u',
            $normalizedText
        ) === 1;
    }

    private function normalize(string $rawText): string
    {
        $normalizedText = Str::ascii(mb_strtolower(trim($rawText)));
        $normalizedText = preg_replace('/[^\p{L}\p{N}\s#\/@-]/u', ' ', $normalizedText) ?? $normalizedText;

        return trim(preg_replace('/\s+/u', ' ', $normalizedText) ?? $normalizedText);
    }

    /**
     * @return array{type:string, normalized_text:string, command:?string}
     */
    private function result(string $type, string $normalizedText, ?string $command = null): array
    {
        return [
            'type' => $type,
            'normalized_text' => $normalizedText,
            'command' => $command,
        ];
    }
}
