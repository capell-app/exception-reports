<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Support;

use Throwable;

/**
 * Stable grouping key for an exception. Variable fragments of the message
 * (identifiers, quoted values, numbers) are masked so repeats of the same
 * failure share one signature and the per-signature throttle can work.
 */
final class ExceptionSignature
{
    public static function for(Throwable $exception): string
    {
        return hash('sha256', implode('|', [
            $exception::class,
            self::normalise($exception->getMessage()),
            $exception->getFile(),
            $exception->getLine(),
        ]));
    }

    public static function normalise(string $message): string
    {
        $patterns = [
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '?',
            '/\'[^\']*\'/' => '?',
            '/\b[0-9a-f]{16,}\b/i' => '?',
            '/\d+/' => '?',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message) ?? $message;
    }
}
