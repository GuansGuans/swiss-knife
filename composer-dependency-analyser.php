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
        __DIR__.'/app/Support/Rector/',
        __DIR__.'/app/Support/ComposerScripts.php',
    ])
    ->ignoreErrors([ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackages(
        [
            'guanguans/laravel-exception-notify',
            'laravel/tinker',
            'chrome-php/chrome',
            'opcodesio/log-viewer',
            'spatie/laravel-activitylog',
            // 'spatie/laravel-schemaless-attributes',
            'spatie/laravel-backup',
            'swiss-devjoy/laravel-optimize-sqlite',
        ],
        [ErrorType::UNUSED_DEPENDENCY]
    )
    ->ignoreErrorsOnPaths(
        [
            __DIR__.'/app/Providers/AppServiceProvider.php',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD]
    );
