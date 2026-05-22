<?php

namespace App\Services;

use Illuminate\Support\Str;

class MessageParserService
{
    public function parse(string $rawText): array
    {
        $normalizedText = $this->normalize($rawText);
        $drawReference = $this->detectDrawReference($normalizedText);
        $segments = $this->extractRequestSegments($normalizedText);
        $items = [];

        foreach ($segments as $segment) {
            $amount = $this->detectAmount($segment);
            $numbers = $this->detectNumbers($segment);

            if ($amount === null || $numbers === []) {
                continue;
            }

            foreach ($numbers as $number) {
                $items[] = [
                    'detected_amount' => $amount,
                    'detected_number' => $number,
                ];
            }
        }

        if ($items === []) {
            return [
                'items' => [],
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.1,
                'needs_review' => true,
                'reason' => $drawReference === null
                    ? 'Could not detect a valid amount/number pattern.'
                    : 'Draw schedule is required. Manual review required.',
                'parser_type' => 'invalid',
            ];
        }

        if (count($segments) > 1) {
            return [
                'items' => $items,
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.88,
                'needs_review' => true,
                'reason' => 'Multiple request patterns detected. Manual review required.',
                'parser_type' => 'multiple_request_patterns',
            ];
        }

        if (count($items) > 1) {
            return [
                'items' => $items,
                'detected_amount' => null,
                'detected_number' => null,
                'draw_reference' => $drawReference,
                'confidence' => 0.9,
                'needs_review' => true,
                'reason' => 'Multiple numbers detected for the same amount. Manual review required.',
                'parser_type' => 'multiple_numbers_same_amount',
            ];
        }

        return [
            'items' => $items,
            'detected_amount' => $items[0]['detected_amount'],
            'detected_number' => $items[0]['detected_number'],
            'draw_reference' => $drawReference,
            'confidence' => 0.97,
            'needs_review' => $drawReference === null,
            'reason' => $drawReference === null
                ? 'Draw schedule is required. Manual review required.'
                : null,
            'parser_type' => 'single_request',
        ];
    }

    private function normalize(string $rawText): string
    {
        $normalizedText = Str::ascii(mb_strtolower(trim($rawText)));
        $normalizedText = preg_replace('/(?<=\d)[,\.](?=\d)/u', '', $normalizedText) ?? $normalizedText;
        $normalizedText = preg_replace('/[^\p{L}\p{N}\s#]/u', ' ', $normalizedText) ?? $normalizedText;

        return preg_replace('/\s+/u', ' ', $normalizedText) ?? $normalizedText;
    }

    private function detectDrawReference(string $normalizedText): ?string
    {
        $patterns = [
            '12:00 md' => [
                '/\bmedio\s*dia\b/u',
                '/\bmediodia\b/u',
                '/\b12\s*(?:md|m\s*d)\b/u',
                '/\b12\s*pm\b/u',
                '/\b12pm\b/u',
                '/\b12\b/u',
            ],
            '2:00 pm' => [
                '/\b2\s*pm\b/u',
                '/\b2pm\b/u',
            ],
            '5:00 pm' => [
                '/\b5\s*pm\b/u',
                '/\b5pm\b/u',
            ],
            '7:00 pm' => [
                '/\b7\s*pm\b/u',
                '/\b7pm\b/u',
            ],
        ];

        foreach ($patterns as $reference => $regexList) {
            foreach ($regexList as $regex) {
                if (preg_match($regex, $normalizedText) === 1) {
                    return $reference;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractRequestSegments(string $normalizedText): array
    {
        preg_match_all(
            '/(?:^|\s)((?:\d+\s*mil|\d+|mil)\s*(?:al|numero|num|#)\s*\d{1,2}(?:\s*(?:y|,)\s*\d{1,2})*)(?=\s|$)/u',
            $normalizedText,
            $matches
        );

        return array_values(array_filter(array_map('trim', $matches[1] ?? [])));
    }

    private function detectAmount(string $normalizedText): ?int
    {
        if (preg_match('/(?:^|\s)(\d+)\s*mil\b/u', $normalizedText, $matches)) {
            return (int) $matches[1] * 1000;
        }

        if (preg_match('/\bmil\b/u', $normalizedText)) {
            return 1000;
        }

        if (preg_match('/\b(\d{1,7})\b/u', $normalizedText, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function detectNumbers(string $normalizedText): array
    {
        if (! preg_match('/(?:al|numero|num|#)\s*(.+)$/u', $normalizedText, $matches)) {
            return [];
        }

        $numberText = trim($matches[1]);
        $segments = preg_split('/\s*(?:y|,)\s*/u', $numberText) ?: [];
        $numbers = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (preg_match('/^\d{1,2}$/u', $segment) !== 1) {
                continue;
            }

            $numbers[] = str_pad((string) ((int) $segment), 2, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($numbers));
    }
}
