<?php

namespace App\Services;

class CustomerConfirmationMessageService
{
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_MANUAL_REVIEW = 'manual_review';

    public function generate(string $rawText, array $parserResult): string
    {
        $items = $parserResult['items'] ?? [];
        $resolvedDraw = $parserResult['resolved_draw'] ?? null;
        $drawReference = $parserResult['draw_reference_label'] ?? $parserResult['draw_reference'] ?? null;
        $availableDraws = $parserResult['available_draws'] ?? [];

        if ($items === []) {
            return implode("\n\n", [
                'Hemos recibido tu solicitud, pero necesitamos revisión manual.',
                'Mensaje recibido:',
                json_encode($rawText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Un operador verificará la información.',
            ]);
        }

        $lines = ['Hemos recibido tu solicitud.', '', 'Interpretamos:'];

        foreach ($items as $item) {
            $amount = $this->formatAmount($item['detected_amount'] ?? null);
            $number = $item['detected_number'] ?? '-';
            $drawLabel = $resolvedDraw['label'] ?? $resolvedDraw['name'] ?? $drawReference;

            if ($drawLabel !== null) {
                $lines[] = sprintf('• Número %s → ₡%s — Sorteo %s', $number, $amount, $drawLabel);
                continue;
            }

            $lines[] = sprintf('• Número %s → ₡%s', $number, $amount);
        }

        $lines[] = '';

        if ($resolvedDraw !== null) {
            $lines[] = 'Draw/schedule: ' . ($resolvedDraw['label'] ?? $resolvedDraw['name'] ?? ($resolvedDraw['draw_time'] ?? '-'));
        } elseif ($drawReference !== null) {
            $lines[] = 'Necesitamos confirmar el sorteo/horario:';
            $lines[] = '• ' . $drawReference;
        } elseif ($availableDraws !== []) {
            $lines[] = 'Necesitamos confirmar el sorteo/horario:';

            foreach ($availableDraws as $draw) {
                $lines[] = '• ' . ($draw['name'] ?? '-');
            }
        } else {
            $lines[] = 'Necesitamos confirmar el sorteo/horario.';
        }

        $lines[] = '';
        $lines[] = 'Pendiente de revisión y confirmación final.';

        return implode("\n", $lines);
    }

    public function typeFor(array $parserResult): string
    {
        return empty($parserResult['items'] ?? []) || empty($parserResult['resolved_draw'] ?? null)
            ? self::TYPE_MANUAL_REVIEW
            : self::TYPE_CONFIRMATION;
    }

    private function formatAmount(mixed $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        if (is_numeric($amount) && (float) $amount === (float) (int) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }
}
