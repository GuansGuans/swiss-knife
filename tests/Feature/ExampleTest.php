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

use App\Console\Commands\CheckServiceProviderCommand;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

it('is console', function (): void {
    classes(
        static fn (string $class, string $file): bool => str($class)->is('App\\Console\\Commands\\*')
            && str($file)->is('*/../../app/Console/Commands/*')
    )
        ->filter(fn (ReflectionClass $reflectionClass): bool => $reflectionClass->isInstantiable())
        ->reject(fn (ReflectionClass $reflectionClass): bool => str($reflectionClass->getName())->is([]))
        ->keys()
        ->merge([RouteListCommand::class])
        // ->dd()
        ->each(function (string $class): void {
            $parameters = ['--no-interaction' => true];
            CheckServiceProviderCommand::class === $class and $parameters += ['--ignore-indirect' => true];

            $this->artisan($class, $parameters)->assertOk();
        });
})->group(__DIR__, __FILE__);

it('is http', function (): void {
    $this->get('/')->assertOk();
})->group(__DIR__, __FILE__);

it('is all routes', function (): void {
    collect(resolve(Router::class)->getRoutes())
        ->reject(fn (Route $route) => (fn () => $this->isVendorRoute($route))->call(app()->make(RouteListCommand::class)))
        ->each(fn (Route $route): array => array_map(fn (string $method) => $this->{$method}($route->uri()), $route->methods()));
})->throwsNoExceptions()->group(__DIR__, __FILE__);
