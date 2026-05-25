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
}
