<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests boot the application and start from an empty database.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests that need the framework (enums and services) only boot the application.
pest()->extend(TestCase::class)->in('Unit');
