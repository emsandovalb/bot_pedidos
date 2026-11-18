<?php

namespace App\Services;

use Illuminate\Support\Str;

class ProductTextNormalizer
{
    public function normalize(?string $value): string
    {
        $value = $value ?? '';
        $value = mb_strtolower(trim($value));
        $value = Str::ascii($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Each word of an already normalized text in singular, with Spanish rules: "pinturas blancas" is
     * "pintura blanca", "tubos pvc" is "tubo pvc", "metales" is "metal", "luces" is "luz".
     */
    public function singular(string $normalized): string
    {
        $words = array_map(static function (string $word): string {
            if (mb_strlen($word) <= 3 || ! str_ends_with($word, 's') || preg_match('/\d/', $word) === 1) {
                return $word;
            }
            if (str_ends_with($word, 'ces')) {
                return substr($word, 0, -3).'z';
            }
            // "metales", "tablones": a vowel and l, r, n, d or j take "es" ("cables" only takes "s").
            if (preg_match('/[aeiou][lrndj]es$/', $word) === 1) {
                return substr($word, 0, -2);
            }

            return substr($word, 0, -1);
        }, explode(' ', $normalized));

        return implode(' ', $words);
    }
}
