<?php

use App\Services\Kb\KbSearch;
use App\Services\Kb\KeywordKbSearch;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests boot the application and start from an empty database.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // RefreshDatabase wraps each test in a transaction, and a MySQL full-text index
        // does not see uncommitted rows. Feature tests therefore always use the keyword
        // search, whatever the database; the full-text search has its own integration tests.
        app()->bind(KbSearch::class, KeywordKbSearch::class);
    })
    ->in('Feature');

// Unit tests that need the framework (enums and services) only boot the application.
pest()->extend(TestCase::class)->in('Unit');

// Integration tests need rows that are really committed (a MySQL full-text index
// does not see the rows of an open transaction), so they migrate the database
// instead of wrapping each test in a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Integration');
