<?php

declare(strict_types=1);

namespace IllumaLaw\HealthCheckAi;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AiChainHealthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('healthcheck-ai')
            ->hasConfigFile()
            ->hasTranslations();
    }
}
