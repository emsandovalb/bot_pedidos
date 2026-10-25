<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrderParserService
{
    public const STATUS_PARSED = 'parsed';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_NOT_ORDER = 'not_order';

    public const STATUS_INVALID = 'invalid';

    private const UNIT_REGEX = '(?:unidad(?:es)?|paquete(?:s)?|caja(?:s)?|bolsa(?:s)?|docena(?:s)?|rollo(?:s)?|kilo(?:s)?|libra(?:s)?|litro(?:s)?)';

    private const NOTE_PATTERNS = [
        ['regex' => '/\bpara\s+manana\b/u', 'note' => 'para manana'],
        ['regex' => '/\bpara\s+hoy\b/u', 'note' => 'para hoy'],
        ['regex' => '/\bpara\s+las?\s+\d{1,2}(?::\d{2})?(?:\s*(?:am|pm|a\.m\.|p\.m\.|md))?\b/u', 'note' => null],
        ['regex' => '/\burgente\b/u', 'note' => 'urgente'],
        ['regex' => '/\blo\s+paso\s+recogiendo\b/u', 'note' => 'lo paso recogiendo'],
        ['regex' => '/\bme\s+lo\s+env(?:ia|ias|ie|ien)\b/u', 'note' => 'me lo envia'],
        ['regex' => '/\bme\s+lo\s+llev(?:an|a|e|en)\b/u', 'note' => 'me lo llevan'],
        ['regex' => '/\bme\s+lo\s+tra(?:en|e)\b/u', 'note' => 'me lo traen'],
        ['regex' => '/\byo\s+paso\s+por\s+ell(?:o|a|os|as)\b/u', 'note' => 'yo paso por ellos'],
        ['regex' => '/\bpaso\s+por\s+ell(?:o|a|os|as)\b/u', 'note' => 'paso por ellos'],
        ['regex' => '/\bpago\s+por\s+sinpe\b/u', 'note' => 'pago por sinpe'],
        ['regex' => '/\bpago\s+en\s+efectivo\b/u', 'note' => 'pago en efectivo'],
        ['regex' => '/\bpago\s+por\s+transferencia\b/u', 'note' => 'pago por transferencia'],
        ['regex' => '/\btransferencia\b/u', 'note' => 'transferencia'],
        ['regex' => '/\benviar\s+a\s+domicilio\b/u', 'note' => 'enviar a domicilio'],
        ['regex' => '/\brecoger\s+en\s+tienda\b/u', 'note' => 'recoger en tienda'],
        ['regex' => '/\bretiro\s+en\s+tienda\b/u', 'note' => 'retiro en tienda'],
        ['regex' => '/\bsin\s+falta\b/u', 'note' => 'sin falta'],
    ];

    /**
     * @return array{
     *     raw_text:string,
     *     normalized_text:string,
     *     confidence:float,
     *     text_confidence:float,
     *     structure_confidence:float,
     *     final_confidence:float,
     *     status:string,
     *     review_reason:?string,
     *     warnings:array<int, string>,
     *     needs_review:bool,
     *     notes:array<int, string>,
     *     notes_text:?string,
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
        $notes = $this->extractNotes($normalizedText);

        if ($normalizedText === '') {
            return $this->emptyResult($rawText, $normalizedText, $notes, self::STATUS_INVALID, 'The message is empty or contains no interpretable text.');
        }

        if ($this->isClearlyNotOrder($normalizedText)) {
            return $this->emptyResult($rawText, $normalizedText, $notes, self::STATUS_NOT_ORDER, 'The message is a known greeting or command, not an order.');
        }

        $candidates = $this->extractCandidatePhrases($rawText, $normalizedText);
        $items = [];

        foreach ($candidates as $candidate) {
            $parsedItem = $this->parseCandidate($candidate['raw'], $candidate['normalized'], $rawText);

            if ($parsedItem !== null) {
                $items[] = $parsedItem;
            }
        }

        if ($items === [] && ! $this->isIntentOnlyMessage($normalizedText)) {
            $items[] = $this->fallbackItem($rawText, $normalizedText);
        }

        $confidenceValues = array_map(static fn (array $item): float => (float) $item['confidence_score'], $items);
        $confidence = round(array_sum($confidenceValues) / max(count($confidenceValues), 1), 2);
        $needsReview = $confidence < 0.85 || count(array_filter($confidenceValues, static fn (float $value): bool => $value < 0.85)) > 0;
        $status = $needsReview ? self::STATUS_PENDING_REVIEW : self::STATUS_PARSED;
        $reviewReason = $needsReview ? 'The order structure is incomplete or ambiguous.' : null;

        return [
            'raw_text' => $rawText,
            'normalized_text' => $normalizedText,
            'confidence' => $confidence,
            'text_confidence' => $confidence,
            'structure_confidence' => $confidence,
            'final_confidence' => $confidence,
            'status' => $status,
            'needs_review' => $needsReview,
            'review_reason' => $reviewReason,
            'warnings' => $needsReview ? ['Manual review is required before this interpretation can be acted on.'] : [],
            'notes' => $notes,
            'notes_text' => $notes !== [] ? implode(', ', $notes) : null,
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, string>  $notes
     * @return array<string, mixed>
     */
    private function emptyResult(string $rawText, string $normalizedText, array $notes, string $status, string $reason): array
    {
        return [
            'raw_text' => $rawText,
            'normalized_text' => $normalizedText,
            'confidence' => 0.0,
            'text_confidence' => 0.0,
            'structure_confidence' => 0.0,
            'final_confidence' => 0.0,
            'status' => $status,
            'needs_review' => false,
            'review_reason' => $reason,
            'warnings' => [$reason],
            'notes' => $notes,
            'notes_text' => $notes !== [] ? implode(', ', $notes) : null,
            'items' => [],
        ];
    }

    private function isClearlyNotOrder(string $normalizedText): bool
    {
        return in_array($normalizedText, [
            'hola',
            'buenos dias',
            'buenas',
            'hello',
            'start',
            'help',
            'menu',
        ], true);
    }

    private function normalize(string $rawText): string
    {
        // Las viñetas se cambian antes de Str::ascii, que las borra.
        $normalized = str_replace('•', ',', mb_strtolower(trim($rawText)));
        $normalized = Str::ascii($normalized);
        $normalized = str_replace(["\r\n", "\r", "\n"], ' , ', $normalized);
        $normalized = str_replace([';', '|'], ',', $normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}\s,.-]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array<int, array{raw:string, normalized:string}>
     */
    private function extractCandidatePhrases(string $rawText, string $normalizedText): array
    {
        if (trim($rawText) === '' && $normalizedText === '') {
            return [];
        }

        $segments = [];

        foreach ($this->splitCompositeText($rawText) as $rawSegment) {
            $rawSegment = trim($rawSegment);
            $normalizedSegment = $this->normalize($rawSegment);

            if ($rawSegment === '' && $normalizedSegment === '') {
                continue;
            }

            $segments[] = [
                'raw' => $rawSegment,
                'normalized' => $normalizedSegment,
            ];
        }

        return $segments !== [] ? $segments : [[
            'raw' => trim($rawText),
            'normalized' => $normalizedText,
        ]];
    }

    /**
     * @return array<int, string>
     */
    private function splitCompositeText(string $text): array
    {
        $text = preg_replace('/\s*(?:\r\n|\r|\n)\s*/u', ',', $text) ?? $text;
        $text = preg_replace('/\s*;\s*/u', ',', $text) ?? $text;
        $text = preg_replace('/\s*\|\s*/u', ',', $text) ?? $text;
        $text = preg_replace('/\s*•\s*/u', ',', $text) ?? $text;
        $text = preg_replace('/\s+\b(?:y|e)\b\s+/u', ',', $text) ?? $text;

        $segments = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/u', $text) ?: [])));

        return $segments !== [] ? $segments : [trim($text)];
    }

    private function parseCandidate(string $rawCandidate, string $normalizedCandidate, string $rawText): ?array
    {
        $candidate = trim($this->stripLeadInPhrases($this->stripTrailingNotes($normalizedCandidate)));
        $rawCandidate = trim($rawCandidate);

        if ($candidate === '' && $rawCandidate === '') {
            return null;
        }

        if ($candidate === '' || $this->isNoteOnlyCandidate($candidate)) {
            return null;
        }

        $quantity = 1;
        $unit = null;
        $productName = $candidate !== '' ? $candidate : $rawCandidate;
        $matchedText = $candidate !== '' ? $candidate : null;
        $confidenceScore = 0.68;

        if (($match = $this->matchPrefixQuantity($candidate)) !== null) {
            $quantity = $match['quantity'];
            $matchedText = trim($match['rest']);
            $confidenceScore = 0.9;

            [$unit, $productName, $matchedText, $confidenceScore] = $this->parseStructuredRest($match['rest'], $quantity, $candidate, $confidenceScore, $matchedText);
        } elseif (($match = $this->matchSuffixQuantityWithUnit($candidate)) !== null) {
            $quantity = $match['quantity'];
            $matchedText = trim($match['product']);
            $unit = $this->normalizeUnit($match['unit']);
            $productName = $this->cleanupProductName($match['product']);
            $confidenceScore = 0.94;
        } elseif (($match = $this->matchSuffixQuantity($candidate)) !== null) {
            $quantity = $match['quantity'];
            $matchedText = trim($match['product']);
            $confidenceScore = 0.88;

            [$unit, $productName, $matchedText, $confidenceScore] = $this->parseStructuredRest($match['product'], $quantity, $candidate, $confidenceScore, $matchedText);
        } else {
            $productName = $this->cleanupProductName($candidate);
            $matchedText = $productName !== '' ? $productName : null;
            $confidenceScore = $productName !== '' ? 0.72 : 0.45;
        }

        $productName = $this->cleanupProductName($productName);
        $matchedText = $matchedText !== null ? $this->cleanupProductName($matchedText) : null;

        if ($productName === '') {
            $productName = $this->cleanupProductName($rawCandidate);
        }

        if ($productName === '') {
            $productName = $this->cleanupProductName($rawText);
        }

        if ($matchedText === null && $productName !== '') {
            $matchedText = $productName;
        }

        return [
            'quantity' => $quantity,
            'unit' => $unit,
            'raw_text' => $candidate !== '' ? $candidate : trim($rawText),
            'product_name' => $productName,
            'matched_text' => $matchedText,
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

        if (preg_match('/^(?<unit>'.$this->unitRegex().')\b\s*(?:de\s+)?(?<rest>.*)$/u', $rest, $matches) === 1) {
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

    /**
     * @return array{quantity:int|float,rest:string}|null
     */
    private function matchPrefixQuantity(string $candidate): ?array
    {
        $patterns = [
            '/^(?<quantity>\d+(?:[.,]\d+)?)\s*x\s+(?<rest>.+)$/u',
            '/^x\s*(?<quantity>\d+(?:[.,]\d+)?)\s+(?<rest>.+)$/u',
            '/^x(?<quantity>\d+(?:[.,]\d+)?)\s+(?<rest>.+)$/u',
            '/^(?<quantity>\d+(?:[.,]\d+)?)\s+(?<rest>.+)$/u',
            '/^(?<quantity_word>un|una|uno|unos|unas)\s+(?<rest>.+)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $candidate, $matches) !== 1) {
                continue;
            }

            $quantity = isset($matches['quantity_word']) ? 1 : $this->parseQuantityValue($matches['quantity']);
            $rest = trim($matches['rest']);

            if ($rest === '') {
                continue;
            }

            return [
                'quantity' => $quantity,
                'rest' => $rest,
            ];
        }

        return null;
    }

    /**
     * @return array{quantity:int|float,product:string,unit:string}|null
     */
    private function matchSuffixQuantityWithUnit(string $candidate): ?array
    {
        $pattern = '/^(?<product>.+?)\s+(?<quantity>\d+(?:[.,]\d+)?)\s+(?<unit>'.$this->unitRegex().')\s*$/u';

        if (preg_match($pattern, $candidate, $matches) !== 1) {
            return null;
        }

        $product = trim($matches['product']);

        if ($product === '') {
            return null;
        }

        return [
            'quantity' => $this->parseQuantityValue($matches['quantity']),
            'product' => $product,
            'unit' => $matches['unit'],
        ];
    }

    /**
     * @return array{quantity:int|float,product:string}|null
     */
    private function matchSuffixQuantity(string $candidate): ?array
    {
        $patterns = [
            '/^(?<product>.+?)\s+(?<quantity>\d+(?:[.,]\d+)?)\s*x\s*$/u',
            '/^(?<product>.+?)\s+x\s*(?<quantity>\d+(?:[.,]\d+)?)\s*$/u',
            '/^(?<product>.+?)\s+(?<quantity>\d+(?:[.,]\d+)?)\s*$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $candidate, $matches) !== 1) {
                continue;
            }

            $product = trim($matches['product']);

            if ($product === '') {
                continue;
            }

            return [
                'quantity' => $this->parseQuantityValue($matches['quantity']),
                'product' => $product,
            ];
        }

        return null;
    }

    private function stripLeadInPhrases(string $candidate): string
    {
        $candidate = trim($candidate);

        $phrases = [
            '/^(?:me\s+)?aparta(?:me|nos|n|s)?\b\s*/u',
            '/^ocupo\b\s*/u',
            '/^necesito\b\s*/u',
            '/^requiero\b\s*/u',
            '/^quiero\s+pedir\b\s*/u',
            '/^quiero\b\s*/u',
            '/^quisiera\b\s*/u',
            '/^solicito\b\s*/u',
            '/^favor\s+de\b\s*/u',
            '/^por\s+favor\b\s*/u',
            '/^mande(?:me|nos|n|s)?\b\s*/u',
            '/^manda(?:me|nos|n|s)?\b\s*/u',
            '/^me\s+regala\b\s*/u',
            '/^me\s+regalas?\b\s*/u',
            '/^me\s+vende(?:me|nos|n|s)?\b\s*/u',
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
        $value = preg_replace('/\s+(?:para\s+(?:manana|hoy)|urgente|sin\s+falta|lo\s+paso\s+recogiendo|me\s+lo\s+env(?:ia|ias|ie|ien)|enviar\s+a\s+domicilio|recoger\s+en\s+tienda)\b.*$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    private function extractNotes(string $normalizedText): array
    {
        if ($normalizedText === '') {
            return [];
        }

        $matches = [];

        foreach (self::NOTE_PATTERNS as $definition) {
            if (preg_match_all($definition['regex'], $normalizedText, $found, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            foreach ($found[0] as $match) {
                $note = $definition['note'] ?? $this->normalizeNotePhrase($match[0]);
                $matches[] = [
                    'note' => $note,
                    'offset' => $match[1],
                ];
            }
        }

        usort($matches, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);

        $notes = [];

        foreach ($matches as $match) {
            if (in_array($match['note'], $notes, true)) {
                continue;
            }

            $notes[] = $match['note'];
        }

        return $notes;
    }

    private function stripTrailingNotes(string $candidate): string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return '';
        }

        if ($this->isIntentOnlyMessage($candidate)) {
            return '';
        }

        $patterns = [
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpara\s+manana\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpara\s+hoy\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpara\s+las?\s+\d{1,2}(?::\d{2})?(?:\s*(?:am|pm|a\.m\.|p\.m\.|md))?\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\burgente\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\blo\s+paso\s+recogiendo\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bme\s+lo\s+env(?:ia|ias|ie|ien)\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bme\s+lo\s+llev(?:an|a|e|en)\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bme\s+lo\s+tra(?:en|e)\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\byo\s+paso\s+por\s+ell(?:o|a|os|as)\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpaso\s+por\s+ell(?:o|a|os|as)\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpago\s+por\s+sinpe\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpago\s+en\s+efectivo\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bpago\s+por\s+transferencia\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\btransferencia\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\benviar\s+a\s+domicilio\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\brecoger\s+en\s+tienda\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bretiro\s+en\s+tienda\b\s*$/u',
            '/(?:\s*(?:,|;|\.)\s*|\s+)\bsin\s+falta\b\s*$/u',
        ];

        do {
            $before = $candidate;

            foreach ($patterns as $pattern) {
                $candidate = preg_replace($pattern, '', $candidate) ?? $candidate;
            }

            $candidate = trim(preg_replace('/\s+/u', ' ', $candidate) ?? $candidate, " \t\n\r\0\x0B,.;:-");
        } while ($candidate !== $before);

        return $candidate;
    }

    private function isNoteOnlyCandidate(string $candidate): bool
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return true;
        }

        return $this->stripTrailingNotes($candidate) === '';
    }

    private function isIntentOnlyMessage(string $normalizedText): bool
    {
        $normalizedText = trim($normalizedText);

        if ($normalizedText === '') {
            return false;
        }

        $stripped = $this->stripIntentPhrases($normalizedText);

        if ($stripped === $normalizedText) {
            return false;
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $stripped) === '';
    }

    private function stripIntentPhrases(string $value): string
    {
        $patterns = array_map(static fn (array $definition): string => $definition['regex'], self::NOTE_PATTERNS);

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, ' ', $value) ?? $value;
        }

        $value = preg_replace('/\s+\b(?:y|e)\b\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[,\.;:-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeNotePhrase(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;

        return trim($value);
    }

    private function normalizeUnit(string $unit): string
    {
        return match (true) {
            str_starts_with($unit, 'unidad') => 'unidad',
            str_starts_with($unit, 'paquete') => 'paquete',
            str_starts_with($unit, 'caja') => 'caja',
            str_starts_with($unit, 'bolsa') => 'bolsa',
            str_starts_with($unit, 'docena') => 'docena',
            str_starts_with($unit, 'rollo') => 'rollo',
            str_starts_with($unit, 'kilo') => 'kilo',
            str_starts_with($unit, 'libra') => 'libra',
            str_starts_with($unit, 'litro') => 'litro',
            default => 'bolsa',
        };
    }

    private function unitRegex(): string
    {
        return self::UNIT_REGEX;
    }

    private function parseQuantityValue(string $value): int|float
    {
        $normalized = str_replace(',', '.', trim($value));

        return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
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
