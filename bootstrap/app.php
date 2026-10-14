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

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

return Application::configure(basePath: \dirname(__DIR__))
    ->booting(static function (Application $app): void {
        File::ensureDirectoryExists(base_path('vendor/_laravel_ide_helper/'));
        $app->make(Repository::class)->set([
            'ide-helper.filename' => base_path('vendor/_laravel_ide_helper/_ide_helper.php'),
            'ide-helper.meta_filename' => base_path('vendor/_laravel_ide_helper/.phpstorm.meta.php'),
            'ide-helper.models_filename' => base_path('vendor/_laravel_ide_helper/_ide_helper_models.php'),
        ]);
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {})
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
