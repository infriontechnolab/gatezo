<?php

namespace Tests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SubscriptionPlan::flush(); // the catalogue is cached statically; each test gets a fresh database
    }

    /** Set a plan's caps for a test, e.g. a tiny Free cap so cap tests stay quick. */
    protected function capPlan(string $slug, array $caps): void
    {
        SubscriptionPlan::where('slug', $slug)->firstOrFail()->update($caps);
    }
}
