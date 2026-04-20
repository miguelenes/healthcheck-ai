<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi\Tests;

use IllumaLaw\HealthCheckAi\AiChainHealthServiceProvider;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Health\HealthServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            HealthServiceProvider::class,
            AiServiceProvider::class,
            AiChainHealthServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
