<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrderParserService
{
    /**
     * @return array{
     *     raw_text:string,
     *     normalized_text:string,
     *     confidence:float,
     *     needs_review:bool,
     *     items:array<int, array{
     *         quantity:int|float,
     *         unit:?string,
     *         raw_text:string,
     *         product_name:string,
     *         matched_text:?string,
     *         confidence_score:float
     *     }>
     * }
     */
    public function parse(string $rawText): array
    {
        $normalizedText = $this->normalize($rawText);
        $candidates = $this->extractCandidatePhrases($normalizedText);
        $items = [];

        foreach ($candidates as $candidate) {
            $parsedItem = $this->parseCandidate($candidate, $rawText);

            if ($parsedItem !== null) {
                $items[] = $parsedItem;
            }
        }

        if ($items === []) {
            $items[] = $this->fallbackItem($rawText, $normalizedText);
        }

        $confidenceValues = array_map(static fn (array $item): float => (float) $item['confidence_score'], $items);
        $confidence = round(array_sum($confidenceValues) / max(count($confidenceValues), 1), 2);
        $needsReview = $confidence < 0.85 || count(array_filter($confidenceValues, static fn (float $value): bool => $value < 0.85)) > 0;

        return [
            'raw_text' => $rawText,
            'normalized_text' => $normalizedText,
            'confidence' => $confidence,
            'needs_review' => $needsReview,
            'items' => $items,
        ];
    }

    private function normalize(string $rawText): string
    {
        $normalized = Str::ascii(mb_strtolower(trim($rawText)));
        $normalized = str_replace(["\r\n", "\r", "\n"], ' , ', $normalized);
        $normalized = str_replace([';', '|', '•'], ',', $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s,.-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array<int, string>
     */
    private function extractCandidatePhrases(string $normalizedText): array
    {
        if ($normalizedText === '') {
            return [];
        }

        $text = preg_replace('/\s*,\s*/u', ' | ', $normalizedText) ?? $normalizedText;
        $text = preg_replace('/\s+\b(?:y|e)\b\s+(?=(?:\d|un|una|uno|unos|unas|ocupo|necesito|quiero|quisiera|me|bolsas|caja|cajas|paquete|paquetes|vasos|servilletas|jardin|basura|negras|grandes))/u', ' | ', $text) ?? $text;

        $phrases = array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/u', $text) ?: [])));

        return $phrases !== [] ? $phrases : [$normalizedText];
    }

    private function parseCandidate(string $candidate, string $rawText): ?array
    {
        $candidate = trim($this->stripLeadInPhrases($candidate));

        if ($candidate === '') {
            return null;
        }

        $quantity = 1;
        $unit = null;
        $productName = $candidate;
        $matchedText = null;
        $confidenceScore = 0.68;

        if (preg_match('/^(?<quantity>\d+(?:[.,]\d+)?)\s+(?<rest>.+)$/u', $candidate, $matches) === 1) {
            $quantity = (int) str_replace(',', '.', $matches['quantity']);
            $rest = trim($matches['rest']);
            $matchedText = $matches[0];
            $confidenceScore = 0.9;

            [$unit, $productName, $matchedText, $confidenceScore] = $this->parseStructuredRest($rest, $quantity, $candidate, $confidenceScore, $matchedText);
        } elseif (preg_match('/^(?<quantity_word>un|una|uno|unos|unas)\s+(?<rest>.+)$/u', $candidate, $matches) === 1) {
            $quantity = 1;
            $rest = trim($matches['rest']);
            $matchedText = $matches[0];
            $confidenceScore = 0.88;

            [$unit, $productName, $matchedText, $confidenceScore] = $this->parseStructuredRest($rest, $quantity, $candidate, $confidenceScore, $matchedText);
        } else {
            $productName = $this->cleanupProductName($candidate);
            $matchedText = null;
            $confidenceScore = max($confidenceScore, $productName !== '' ? 0.72 : 0.45);
        }

        $productName = $this->cleanupProductName($productName);

        if ($productName === '') {
            $productName = $this->cleanupProductName($candidate);
        }

        if ($productName === '') {
            $productName = $this->cleanupProductName($rawText);
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'raw_text' => $candidate,
            'product_name' => $productName,
            'matched_text' => $matchedText !== null ? $this->cleanupProductName($matchedText) : null,
            'confidence_score' => round($confidenceScore, 2),
        ];
    }

    /**
     * @return array{0:?string,1:string,2:?string,3:float}
     */
    private function parseStructuredRest(string $rest, int|float $quantity, string $candidate, float $confidenceScore, ?string $matchedText): array
    {
        $unit = null;
        $productName = $rest;

        if (preg_match('/^(?<unit>bolsas?|cajas?|paquetes?|paqs?|piezas?|rollos?|docenas?|sacos?|botellas?|litros?|galones?|libra?s?|metros?|pares?|docena|cartones?|vasos|servilletas)\b\s*(?<rest>.*)$/u', $rest, $matches) === 1) {
            $unit = $this->normalizeUnit($matches['unit']);
            $productName = trim($matches['rest']);
            $matchedText = trim($matches[0]);
            $confidenceScore = 0.96;
        }

        $productName = preg_replace('/^(?:de|del|da|para|por|con)\s+/u', '', $productName) ?? $productName;
        $productName = $this->cleanupProductName($productName);

        if ($productName === '') {
            $productName = $this->cleanupProductName($candidate);
            $confidenceScore = min($confidenceScore, 0.72);
        }

        return [$unit, $productName, $matchedText, $confidenceScore];
    }

    private function stripLeadInPhrases(string $candidate): string
    {
        $candidate = trim($candidate);

        $phrases = [
            '/^(?:me\s+)?aparta(?:me|nos|n|s)?\b\s*/u',
            '/^ocupo\b\s*/u',
            '/^necesito\b\s*/u',
            '/^quiero\b\s*/u',
            '/^quisiera\b\s*/u',
            '/^favor\s+de\b\s*/u',
            '/^por\s+favor\b\s*/u',
            '/^manda(?:me|nos|n|s)?\b\s*/u',
            '/^me\s+regala\b\s*/u',
            '/^me\s+vende\b\s*/u',
            '/^dame\b\s*/u',
        ];

        foreach ($phrases as $regex) {
            $candidate = preg_replace($regex, '', $candidate) ?? $candidate;
        }

        return trim($candidate);
    }

    private function cleanupProductName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^(?:de|del|para|por|con)\s+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeUnit(string $unit): string
    {
        return match (true) {
            str_starts_with($unit, 'caja') => 'caja',
            str_starts_with($unit, 'paquete') || str_starts_with($unit, 'paq') => 'paquete',
            str_starts_with($unit, 'pieza') => 'pieza',
            str_starts_with($unit, 'rollo') => 'rollo',
            str_starts_with($unit, 'docena') => 'docena',
            str_starts_with($unit, 'saco') => 'saco',
            str_starts_with($unit, 'botella') => 'botella',
            str_starts_with($unit, 'litro') => 'litro',
            str_starts_with($unit, 'galon') => 'galon',
            str_starts_with($unit, 'libra') => 'libra',
            str_starts_with($unit, 'metro') => 'metro',
            str_starts_with($unit, 'par') => 'par',
            str_starts_with($unit, 'carton') => 'carton',
            default => 'bolsa',
        };
    }

    /**
     * @return array{
     *     quantity:int,
     *     unit:?string,
     *     raw_text:string,
     *     product_name:string,
     *     matched_text:?string,
     *     confidence_score:float
     * }
     */
    private function fallbackItem(string $rawText, string $normalizedText): array
    {
        return [
            'quantity' => 1,
            'unit' => null,
            'raw_text' => trim($rawText) !== '' ? trim($rawText) : $normalizedText,
            'product_name' => trim($normalizedText) !== '' ? trim($normalizedText) : trim($rawText),
            'matched_text' => null,
            'confidence_score' => 0.35,
        ];
    }
}
