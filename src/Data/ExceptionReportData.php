<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Data;

use Spatie\LaravelData\Data;

final class ExceptionReportData extends Data
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>|null  $console
     * @param  array<string, mixed>|null  $user
     * @param  array<string, mixed>|null  $digest
     * @param  array<string, mixed>  $unsafe
     */
    public function __construct(
        public string $subject,
        public string $source,
        public array $summary,
        public array $request,
        public ?array $console,
        public ?array $user,
        public string $trace,
        public ?array $digest = null,
        public array $unsafe = [],
    ) {}
}
