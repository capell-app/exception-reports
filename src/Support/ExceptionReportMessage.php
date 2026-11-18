<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * The one exception message every report channel uses: the stored row, the email, the digest,
 * the webhook and the reporter's own failure logs. Database driver messages embed the
 * statement, its bound values, quoted literals and whole rows, in driver-specific and
 * multi-line layouts. They cannot be scrubbed reliably, so only the SQLSTATE code is kept.
 * The message is sanitised before it is shortened.
 */
final class ExceptionReportMessage
{
    public static function of(Throwable $exception, int $limit): string
    {
        // Sanitise the whole message first: shortening first could cut an address or token in
        // half, and a fragment no longer matches the patterns that redact it.
        $message = self::withoutQueryData($exception->getMessage());
        $sanitized = resolve(ExceptionReportMailSanitizer::class)->sanitizeLogContext(['message' => $message])['message'] ?? '';

        return Str::limit(is_string($sanitized) ? $sanitized : '', $limit, '...');
    }

    /**
     * The stack trace without call arguments. getTraceAsString() prints argument values (strings
     * cut to 15 characters, still enough for a short password) unless
     * zend.exception_ignore_args is on, so each frame keeps only its location and callee.
     */
    public static function traceOf(Throwable $exception): string
    {
        $lines = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $location = isset($frame['file']) ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')' : '[internal function]';
            $callee = (isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '->') : '') . $frame['function'] . '()';
            $lines[] = '#' . $index . ' ' . $location . ': ' . $callee;
        }

        $lines[] = '#' . count($lines) . ' {main}';

        return implode("\n", $lines);
    }

    public static function withoutQueryData(string $message): string
    {
        if (preg_match('/SQLSTATE\[([0-9A-Za-z]{1,10})\]/', $message, $matches) === 1) {
            return sprintf('SQLSTATE[%s]: database error (details withheld)', $matches[1]);
        }

        return $message;
    }
}
