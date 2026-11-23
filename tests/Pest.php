<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests boot the application and start from an empty database.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests that need the framework (enums and services) only boot the application.
pest()->extend(TestCase::class)->in('Unit');

// Integration tests need rows that are really committed (a MySQL full-text index
// does not see the rows of an open transaction), so they migrate the database
// instead of wrapping each test in a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Integration');
