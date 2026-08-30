<?php

namespace App\Services\ParserQuality;

class DatasetAnonymizer
{
    /**
     * Removes direct identifiers from a message before it can enter the evaluation dataset.
     * It is deliberately conservative: messages that still require human judgment must not
     * be exported until the reviewer has removed the remaining identifying context.
     */
    public function anonymize(string $text): string
    {
        $text = preg_replace('/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', '[EMAIL]', $text) ?? $text;
        $text = preg_replace('/(?<!\w)(?:\+?\d[\d\s().-]{6,}\d)(?!\w)/u', '[PHONE]', $text) ?? $text;
        $text = preg_replace('/(?<!\w)@(?!here\b)[A-Za-z0-9_]{3,32}\b/u', '[TELEGRAM_USERNAME]', $text) ?? $text;
        $text = preg_replace('/\b(?:telegram\s*)?(?:user|chat|id)\s*[:#-]?\s*\d+\b/iu', '[TELEGRAM_ID]', $text) ?? $text;
        $text = preg_replace('/\b(?:pedido|orden|order)\s*(?:n[.º°o]?\s*)?[:#-]?\s*\d+\b/iu', '[ORDER_NUMBER]', $text) ?? $text;
        $text = preg_replace('/\b(?:calle|avenida|av\.?|zona|colonia|residencial|km)\s+[^,;\n]+/iu', '[ADDRESS]', $text) ?? $text;
        // Names following explicit identity phrases are direct identifiers. This pass
        // keeps ordinary product wording intact because it only acts on those phrases.
        $text = preg_replace('/\b(soy|mi nombre es|a nombre de)\s+[\p{L}][\p{L}\s\'-]{1,80}(?=[,.;!\n]|$)/iu', '$1 [PERSON]', $text) ?? $text;

        return trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
    }

    /** @return list<string> */
    public function obviousSensitiveFindings(string $text): array
    {
        $patterns = [
            'email' => '/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u',
            'phone' => '/(?<!\w)(?:\+?\d[\d\s().-]{6,}\d)(?!\w)/u',
            'telegram_username' => '/(?<!\w)@[A-Za-z0-9_]{3,32}\b/u',
            'telegram_id' => '/\b(?:telegram\s*)?(?:user|chat|id)\s*[:#-]?\s*\d+\b/iu',
            'order_number' => '/\b(?:pedido|orden|order)\s*(?:n[.º°o]?\s*)?[:#-]?\s*\d+\b/iu',
            'address' => '/\b(?:calle|avenida|av\.?|zona|colonia|residencial|km)\s+[^,;\n]+/iu',
        ];

        return array_keys(array_filter($patterns, static fn (string $pattern): bool => preg_match($pattern, $text) === 1));
    }
}
