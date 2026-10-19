<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 guanguans<ityaozm@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/GuansGuans/swiss-knife
 */

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathsToScan(
        [
            __DIR__.'/bootstrap/',
            __DIR__.'/config/',
            __DIR__.'/database/',
            __DIR__.'/public/',
            __DIR__.'/resources/',
            __DIR__.'/routes/',
        ],
        false
    )
    ->addPathsToExclude([
        __DIR__.'/app/Support/ComposerScripts.php',
        __DIR__.'/app/Support/Rector/',
    ])
    ->ignoreErrorsOnExtensions(
        /** @see \ShipMonk\ComposerDependencyAnalyser\Analyser::CORE_EXTENSIONS */
        ['ext-pdo', 'ext-pdo_mysql'],
        [ErrorType::SHADOW_DEPENDENCY],
    )
    ->ignoreErrorsOnPackages(
        /** @see vendor/laravel/framework/composer.json */
        [
            'composer/xdebug-handler',
            'guanguans/notify',
            'monolog/monolog',
            'nesbot/carbon',
            'spatie/image-optimizer',
            'symfony/console',
            'symfony/finder',
            'symfony/process',
            'symfony/var-dumper',
        ],
        [ErrorType::SHADOW_DEPENDENCY]
    )
    ->ignoreErrorsOnPackages(
        [
            'laravel/tinker',
            'opcodesio/log-viewer',
            'spatie/laravel-image-optimizer',
            'swiss-devjoy/laravel-optimize-sqlite',
        ],
        [ErrorType::UNUSED_DEPENDENCY]
    )
    ->ignoreErrorsOnPackageAndPaths(
        'laravel/doctor',
        [
            __DIR__.'/bootstrap/app.php',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD]
    )
    ->ignoreErrorsOnPackageAndPaths(
        'laravel/telescope',
        [
            __DIR__.'/app/Providers/TelescopeServiceProvider.php',
            __DIR__.'/config/telescope.php',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD]
    );
