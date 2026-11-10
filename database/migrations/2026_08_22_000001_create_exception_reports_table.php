<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = $this->tableName();

        if (TableName::externalExistsAndIsOwned($tableName, basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('exception_class');
            $table->text('message');
            $table->string('job_class')->nullable();
            $table->string('job_id')->nullable();
            $table->text('url')->nullable();
            $table->dateTime('reported_at');
            $table->timestamps();

            $table->index('exception_class');
            $table->index('reported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        return TableName::resolve(Config::string('capell-exception-reports.persistence.table', 'exception_reports'));
    }
};
