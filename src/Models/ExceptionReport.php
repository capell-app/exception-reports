<?php

declare(strict_types=1);

namespace Capell\ExceptionReports\Models;

use Capell\ExceptionReports\Actions\ReportExceptionByEmailAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * A durable, queryable record of a single exception reported through
 * {@see ReportExceptionByEmailAction}, kept
 * alongside the existing email/webhook notifications so an incident can be
 * investigated with `DB::table(...)` or an artisan command instead of inbox
 * or log-file access.
 *
 * @property int $id
 * @property string $exception_class
 * @property string $message
 * @property string|null $job_class
 * @property string|null $job_id
 * @property string|null $url
 * @property Carbon $reported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ExceptionReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'exception_class',
        'message',
        'job_class',
        'job_id',
        'url',
        'reported_at',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getCasts(): array
    {
        return [
            ...$this->casts,
            'reported_at' => 'datetime',
        ];
    }

    #[Override]
    public function getTable(): string
    {
        $table = config('capell-exception-reports.persistence.table');

        return is_string($table) && $table !== '' ? $table : parent::getTable();
    }
}
