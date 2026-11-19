<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // SAFETY GUARD: tests must NEVER run against the live database.
        // phpunit.xml sets DB_DATABASE=traffic_demerit_test; this reads the
        // raw environment (available before the app boots) and aborts loudly
        // if the suite is ever pointed back at the live `traffic_demerit`.
        $database = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');

        if ($database === 'traffic_demerit') {
            throw new RuntimeException(
                'ABORTING TESTS: the configured database is traffic_demerit (the LIVE database). ' .
                'Tests must run against traffic_demerit_test. Check phpunit.xml / DB_DATABASE.'
            );
        }

        if ($database === '') {
            throw new RuntimeException(
                'ABORTING TESTS: no test database configured (DB_DATABASE is empty).'
            );
        }

        parent::setUp();
    }
}
