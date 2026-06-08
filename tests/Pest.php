<?php

declare(strict_types=1);

use Capell\ExceptionReports\Tests\ExceptionReportsTestCase;

pest()->extend(ExceptionReportsTestCase::class)->group('exception-reports')->in(__DIR__);
