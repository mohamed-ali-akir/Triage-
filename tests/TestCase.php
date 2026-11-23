<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on compiled front-end assets.
        $this->withoutVite();

        // No test may reach a real API: any HTTP request that is not faked fails the test.
        Http::preventStrayRequests();
    }
}
