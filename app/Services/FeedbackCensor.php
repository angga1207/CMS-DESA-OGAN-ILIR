<?php

declare(strict_types=1);

namespace App\Services;

final class FeedbackCensor
{
    public function censor(string $message): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', strip_tags($message)));
        $words = collect(config('feedback.blocked_words', []))
            ->filter(fn (mixed $word): bool => is_string($word) && trim($word) !== '')
            ->map(fn (string $word): string => preg_quote(trim($word), '/'))
            ->values()
            ->all();

        if ($words === []) {
            return $normalized;
        }

        return (string) preg_replace_callback(
            '/(?<![\pL\pN])('.implode('|', $words).')(?![\pL\pN])/iu',
            fn (array $match): string => str_repeat('*', max(mb_strlen($match[0]), 3)),
            $normalized,
        );
    }
}
