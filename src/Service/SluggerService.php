<?php

declare(strict_types=1);

namespace App\Service;

class SluggerService
{
    public function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }

    public function generateOrderNumber(): string
    {
        return sprintf(
            'CMD-%s-%s',
            (new \DateTimeImmutable())->format('Ymd'),
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))
        );
    }
}
