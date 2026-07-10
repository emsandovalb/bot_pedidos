<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Services\Fulfillment\DTO\FulfillmentIntent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class FulfillmentIntentParser
{
    public function parse(Order $order, string $message): FulfillmentIntent
    {
        $timezone = $this->timezone();
        $normalizedMessage = $this->normalize($message);
        $matchedPhrases = [];
        $metadata = [
            'normalized_message' => $normalizedMessage,
            'timezone' => $timezone,
        ];

        $dateIntent = $this->detectDateIntent($message, $normalizedMessage, $timezone);
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $dateIntent['matched_phrases']);
        $metadata = array_merge($metadata, $dateIntent['metadata']);

        $timeWindowIntent = $this->detectTimeWindowIntent($message, $normalizedMessage);
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $timeWindowIntent['matched_phrases']);
        $metadata = array_merge($metadata, $timeWindowIntent['metadata']);

        $deliveryIntent = $this->detectDeliveryMethodIntent($message, $normalizedMessage);
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $deliveryIntent['matched_phrases']);
        $metadata = array_merge($metadata, $deliveryIntent['metadata']);

        $paymentIntent = $this->detectPaymentMethodIntent($message, $normalizedMessage);
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $paymentIntent['matched_phrases']);
        $metadata = array_merge($metadata, $paymentIntent['metadata']);

        $priorityIntent = $this->detectPriorityIntent(
            $normalizedMessage,
            $dateIntent['requested_date'],
            $dateIntent['metadata']['date_intent'] ?? null,
        );
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $priorityIntent['matched_phrases']);
        $metadata = array_merge($metadata, $priorityIntent['metadata']);

        $specificTimes = $this->detectSpecificTimeMentions($message, $normalizedMessage);
        $matchedPhrases = $this->mergeMatches($matchedPhrases, $specificTimes['matched_phrases']);
        $metadata = array_merge($metadata, $specificTimes['metadata']);

        $confidence = $this->calculateConfidence([
            $dateIntent,
            $timeWindowIntent,
            $deliveryIntent,
            $paymentIntent,
            $priorityIntent,
            $specificTimes,
        ]);

        return new FulfillmentIntent(
            requested_date: $dateIntent['requested_date'],
            requested_time_window: $timeWindowIntent['requested_time_window'],
            delivery_method: $deliveryIntent['delivery_method'],
            payment_method: $paymentIntent['payment_method'],
            delivery_address: $deliveryIntent['delivery_address'],
            delivery_notes: $deliveryIntent['delivery_notes'],
            priority_level: $priorityIntent['priority_level'],
            priority_reason: $priorityIntent['priority_reason'],
            confidence: $confidence,
            matched_phrases: array_values(array_unique(array_filter($matchedPhrases, static fn ($value): bool => is_string($value) && $value !== ''))),
            metadata: $metadata,
        );
    }

    private function timezone(): string
    {
        $configured = config('fulfillment.timezone');

        return is_string($configured) && $configured !== '' ? $configured : (string) config('app.timezone', 'UTC');
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = preg_replace('/[^\p{L}\p{N}\s:\/\-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array{requested_date:?string, matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectDateIntent(string $message, string $normalizedMessage, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $phraseMaps = (array) config('fulfillment.phrase_maps.date', []);
        $matched = [];
        $metadata = [
            'date_matches' => [],
            'date_intent' => null,
        ];

        foreach (['day_after_tomorrow', 'tomorrow', 'today'] as $intent) {
            $phrases = $this->matchConfiguredPhrases($normalizedMessage, (array) ($phraseMaps[$intent] ?? []));

            if ($phrases === []) {
                continue;
            }

            $matched = $this->mergeMatches($matched, $phrases);

            $resolved = match ($intent) {
                'today' => $today,
                'tomorrow' => $today->addDay(),
                'day_after_tomorrow' => $today->addDays(2),
            };

            $metadata['date_matches'] = array_values(array_unique(array_merge($metadata['date_matches'], $phrases)));
            $metadata['date_intent'] = $intent;

            return [
                'requested_date' => $resolved->toDateString(),
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        foreach ((array) ($phraseMaps['weekdays'] ?? []) as $weekday => $phrasesList) {
            $phrases = $this->matchConfiguredPhrases($normalizedMessage, (array) $phrasesList);

            if ($phrases === []) {
                continue;
            }

            $matched = $this->mergeMatches($matched, $phrases);
            $resolved = $this->resolveWeekdayDate($today, (string) $weekday);

            if ($resolved === null) {
                continue;
            }

            $metadata['date_matches'] = array_values(array_unique(array_merge($metadata['date_matches'], $phrases)));
            $metadata['date_intent'] = 'weekday:' . $weekday;

            return [
                'requested_date' => $resolved->toDateString(),
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        $explicitDate = $this->detectExplicitDate($message, $timezone);

        if ($explicitDate !== null) {
            $matched[] = $explicitDate['matched_phrase'];
            $metadata['date_matches'] = [$explicitDate['matched_phrase']];
            $metadata['date_intent'] = 'explicit';

            return [
                'requested_date' => $explicitDate['value']->toDateString(),
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        return [
            'requested_date' => null,
            'matched_phrases' => [],
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{value:CarbonImmutable,matched_phrase:string}|null
     */
    private function detectExplicitDate(string $message, string $timezone): ?array
    {
        $patterns = [
            '/\b(?<date>\d{4}-\d{2}-\d{2})\b/u',
            '/\b(?<date>\d{1,2}[\/-]\d{1,2}[\/-]\d{4})\b/u',
        ];
        $today = CarbonImmutable::now($timezone)->startOfDay();

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) !== 1) {
                continue;
            }

            $candidate = trim($matches['date']);
            $resolved = $this->parseExplicitDateCandidate($candidate, $timezone);

            if ($resolved === null || $resolved->startOfDay()->lt($today)) {
                continue;
            }

            return [
                'value' => $resolved->startOfDay(),
                'matched_phrase' => $this->normalize($candidate),
            ];
        }

        return null;
    }

    private function parseExplicitDateCandidate(string $candidate, string $timezone): ?CarbonImmutable
    {
        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            try {
                $resolved = CarbonImmutable::createFromFormat('!' . $format, $candidate, $timezone);
            } catch (\Throwable) {
                continue;
            }

            if ($resolved !== false && $resolved->format($format) === $candidate) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveWeekdayDate(CarbonImmutable $today, string $weekday): ?CarbonImmutable
    {
        $weekdayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        if (! isset($weekdayMap[$weekday])) {
            return null;
        }

        $target = $weekdayMap[$weekday];
        $current = (int) $today->dayOfWeekIso;
        $daysUntil = ($target - $current + 7) % 7;

        return $today->addDays($daysUntil);
    }

    /**
     * @return array{requested_time_window:?string, matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectTimeWindowIntent(string $message, string $normalizedMessage): array
    {
        $maps = (array) config('fulfillment.phrase_maps.time_windows', []);
        $matched = [];
        $metadata = [
            'time_window_matches' => [],
        ];

        foreach ($maps as $window => $phrases) {
            $phrasesMatched = $this->matchConfiguredPhrases($normalizedMessage, (array) $phrases);

            if ($phrasesMatched === []) {
                continue;
            }

            $matched = $this->mergeMatches($matched, $phrasesMatched);
            $metadata['time_window_matches'] = array_values(array_unique(array_merge($metadata['time_window_matches'], $phrasesMatched)));

            return [
                'requested_time_window' => (string) $window,
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        return [
            'requested_time_window' => null,
            'matched_phrases' => [],
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{delivery_method:?string, delivery_address:?string, delivery_notes:?string, matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectDeliveryMethodIntent(string $message, string $normalizedMessage): array
    {
        $maps = (array) config('fulfillment.phrase_maps.delivery_methods', []);
        $matchedByMethod = [];
        $allMatches = [];
        $metadata = [
            'delivery_method_matches' => [],
        ];

        foreach ($maps as $method => $phrases) {
            $phrasesMatched = $this->matchConfiguredPhrases($normalizedMessage, (array) $phrases);

            if ($phrasesMatched === []) {
                continue;
            }

            $matchedByMethod[$method] = $phrasesMatched;
            $allMatches = $this->mergeMatches($allMatches, $phrasesMatched);
        }

        if (isset($matchedByMethod['express'])) {
            $metadata['delivery_method_matches'] = $matchedByMethod['express'];

            return [
                'delivery_method' => 'express',
                'delivery_address' => null,
                'delivery_notes' => null,
                'matched_phrases' => $allMatches,
                'metadata' => $metadata,
            ];
        }

        if (isset($matchedByMethod['third_party'])) {
            $metadata['delivery_method_matches'] = $matchedByMethod['third_party'];

            return [
                'delivery_method' => 'third_party',
                'delivery_address' => null,
                'delivery_notes' => null,
                'matched_phrases' => $allMatches,
                'metadata' => $metadata,
            ];
        }

        $pickupMatches = $matchedByMethod['pickup'] ?? [];
        $deliveryMatches = $matchedByMethod['delivery'] ?? [];
        $metadata['delivery_method_matches'] = array_values(array_unique(array_merge($pickupMatches, $deliveryMatches)));

        if ($pickupMatches !== [] && $deliveryMatches !== []) {
            return [
                'delivery_method' => 'unknown',
                'delivery_address' => null,
                'delivery_notes' => null,
                'matched_phrases' => $allMatches,
                'metadata' => $metadata,
            ];
        }

        if ($pickupMatches !== []) {
            return [
                'delivery_method' => 'pickup',
                'delivery_address' => null,
                'delivery_notes' => null,
                'matched_phrases' => $allMatches,
                'metadata' => $metadata,
            ];
        }

        if ($deliveryMatches !== []) {
            return [
                'delivery_method' => 'delivery',
                'delivery_address' => null,
                'delivery_notes' => null,
                'matched_phrases' => $allMatches,
                'metadata' => $metadata,
            ];
        }

        return [
            'delivery_method' => null,
            'delivery_address' => null,
            'delivery_notes' => null,
            'matched_phrases' => [],
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{payment_method:?string, matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectPaymentMethodIntent(string $message, string $normalizedMessage): array
    {
        $maps = (array) config('fulfillment.phrase_maps.payment_methods', []);
        $matches = [];
        $metadata = [
            'payment_method_matches' => [],
        ];

        foreach ($maps as $method => $phrases) {
            foreach ((array) $phrases as $phrase) {
                $normalizedPhrase = $this->normalize((string) $phrase);

                if ($normalizedPhrase === '' || ! $this->containsPhrase($normalizedMessage, $normalizedPhrase)) {
                    continue;
                }

                $matches[] = [
                    'method' => (string) $method,
                    'phrase' => $normalizedPhrase,
                    'length' => mb_strlen($normalizedPhrase),
                ];
            }
        }

        if ($matches === []) {
            return [
                'payment_method' => null,
                'matched_phrases' => [],
                'metadata' => $metadata,
            ];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left['length'] !== $right['length']) {
                return $right['length'] <=> $left['length'];
            }

            return $left['method'] <=> $right['method'];
        });

        $chosen = $matches[0];
        $metadata['payment_method_matches'] = array_values(array_unique(array_map(static fn (array $match): string => $match['phrase'], $matches)));

        return [
            'payment_method' => $chosen['method'],
            'matched_phrases' => array_values(array_unique(array_map(static fn (array $match): string => $match['phrase'], $matches))),
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{priority_level:?string, priority_reason:?string, matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectPriorityIntent(string $normalizedMessage, ?string $requestedDate, ?string $dateIntent): array
    {
        $maps = (array) config('fulfillment.phrase_maps.priority', []);
        $urgentMatches = $this->matchConfiguredPhrases($normalizedMessage, (array) ($maps['urgent'] ?? []));
        $highMatches = $this->matchConfiguredPhrases($normalizedMessage, (array) ($maps['high'] ?? []));
        $matched = $this->mergeMatches($urgentMatches, $highMatches);
        $metadata = [
            'priority_matches' => $matched,
        ];

        if ($urgentMatches !== []) {
            return [
                'priority_level' => 'urgent',
                'priority_reason' => 'Urgencia explicita detectada.',
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        if ($requestedDate !== null) {
            $today = CarbonImmutable::now($this->timezone())->startOfDay();
            $resolvedDate = CarbonImmutable::parse($requestedDate, $this->timezone())->startOfDay();

            if ($resolvedDate->equalTo($today)) {
                return [
                    'priority_level' => 'high',
                    'priority_reason' => 'Solicitud para hoy.',
                    'matched_phrases' => $matched,
                    'metadata' => $metadata,
                ];
            }
        }

        if ($highMatches !== []) {
            $reason = 'Solicitud marcada como prioridad alta.';

            if ($dateIntent === 'explicit') {
                $reason = 'Fecha especifica cercana detectada.';
            }

            return [
                'priority_level' => 'high',
                'priority_reason' => $reason,
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        if ($dateIntent === 'tomorrow') {
            return [
                'priority_level' => 'normal',
                'priority_reason' => 'Solicitud para manana sin urgencia explicita.',
                'matched_phrases' => $matched,
                'metadata' => $metadata,
            ];
        }

        return [
            'priority_level' => 'normal',
            'priority_reason' => 'Prioridad base.',
            'matched_phrases' => $matched,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array{matched_phrases:array<int, string>, metadata:array<string, mixed>}
     */
    private function detectSpecificTimeMentions(string $message, string $normalizedMessage): array
    {
        $matches = [];
        $metadata = [
            'specific_time_mentions' => [],
        ];

        $patterns = [
            '/\bantes de las? \d{1,2}(?::\d{2})?\s*(?:am|pm)?\b/u',
            '/\b(?:a|para) las? \d{1,2}(?::\d{2})?\s*(?:am|pm)?\b/u',
            '/\bantes de medio dia\b/u',
            '/\bantes del mediodia\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $normalizedMessage, $found) < 1) {
                continue;
            }

            foreach ($found[0] as $match) {
                $matches[] = trim($match);
            }
        }

        $matches = array_values(array_unique(array_filter($matches, static fn (string $value): bool => $value !== '')));
        $metadata['specific_time_mentions'] = $matches;

        return [
            'matched_phrases' => $matches,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<int, string> $phrases
     * @return array<int, string>
     */
    private function matchConfiguredPhrases(string $normalizedMessage, array $phrases): array
    {
        $matches = [];

        foreach ($phrases as $phrase) {
            $normalizedPhrase = $this->normalize((string) $phrase);

            if ($normalizedPhrase === '' || ! $this->containsPhrase($normalizedMessage, $normalizedPhrase)) {
                continue;
            }

            $matches[] = $normalizedPhrase;
        }

        return array_values(array_unique($matches));
    }

    private function containsPhrase(string $normalizedMessage, string $normalizedPhrase): bool
    {
        $pattern = '/(?<![a-z0-9])' . str_replace('\ ', '\s+', preg_quote($normalizedPhrase, '/')) . '(?![a-z0-9])/u';

        return preg_match($pattern, $normalizedMessage) === 1;
    }

    /**
     * @param array<int, string> $matchedPhrases
     * @return array<int, string>
     */
    private function mergeMatches(array ...$matchedPhrases): array
    {
        $merged = [];

        foreach ($matchedPhrases as $phrases) {
            foreach ($phrases as $phrase) {
                if (! is_string($phrase) || $phrase === '') {
                    continue;
                }

                $merged[] = $phrase;
            }
        }

        return array_values(array_unique($merged));
    }

    /**
     * @param array<int, array<string, mixed>> $intents
     */
    private function calculateConfidence(array $intents): int
    {
        $score = 40;

        foreach ($intents as $intent) {
            if (($intent['requested_date'] ?? null) !== null) {
                $score += 20;
            }

            if (($intent['requested_time_window'] ?? null) !== null) {
                $score += 15;
            }

            if (($intent['delivery_method'] ?? null) !== null) {
                $score += 15;
            }

            if (($intent['payment_method'] ?? null) !== null) {
                $score += 15;
            }

            if (($intent['priority_level'] ?? null) !== null) {
                $score += 10;
            }

            if (($intent['metadata']['specific_time_mentions'] ?? []) !== []) {
                $score += 5;
            }
        }

        if (($intents[2]['metadata']['delivery_method_matches'] ?? []) !== [] && ($intents[2]['delivery_method'] ?? null) === 'unknown') {
            $score -= 20;
        }

        if (count((array) ($intents[3]['metadata']['payment_method_matches'] ?? [])) > 1) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }
}
