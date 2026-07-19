<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Throwable;

final class InactivePostmarkRecipientFailure
{
    private const ERROR_CODE = 406;

    public static function matches(Throwable $exception): bool
    {
        if (! $exception instanceof HttpTransportException) {
            return false;
        }

        try {
            $content = $exception->getResponse()->getContent(false);

            if (! is_string($content)) {
                return false;
            }

            $payload = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        $errorCode = $payload['ErrorCode'] ?? null;

        return is_int($errorCode) && $errorCode === self::ERROR_CODE;
    }

    public static function logNoticeIfEnabled(): void
    {
        if (! (bool) config('capell-exception-reports.suppressed_mail_failures.logging.enabled', false)) {
            return;
        }

        $channel = config('capell-exception-reports.suppressed_mail_failures.logging.channel');

        if (! is_string($channel) || $channel === '') {
            return;
        }

        try {
            Log::channel($channel)->notice('Suppressed a non-actionable Postmark delivery failure.', [
                'provider' => 'postmark',
                'error_code' => self::ERROR_CODE,
                'reason' => 'inactive_recipient',
            ]);
        } catch (Throwable) {
        }
    }
}
