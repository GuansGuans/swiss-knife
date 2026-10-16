<?php

/** @noinspection AnonymousFunctionStaticInspection */
/** @noinspection ClassConstantCanBeUsedInspection */
/** @noinspection ClassnameLiteralInspection */
/** @noinspection NullPointerExceptionInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection StaticClosureCanBeUsedInspection */
declare(strict_types=1);

/**
 * Copyright (c) 2026 guanguans<ityaozm@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/GuansGuans/swiss-knife
 */

use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

it('is command naming', function (): void {
    collect(Artisan::all())
        ->filter(fn (SymfonyCommand $symfonyCommand): bool => str($symfonyCommand::class)->startsWith('App\\Console\\Commands'))
        ->reject(fn (SymfonyCommand $symfonyCommand): bool => str($symfonyCommand->getName())->is([]))
        ->each(
            fn (SymfonyCommand $symfonyCommand) => expect(str($symfonyCommand->getName())->replaceMatches('/\d/', '')->explode(':'))
                ->each
                ->toBeKebabCase(\sprintf('The command [%s] name [%s] should be kebab case.', $symfonyCommand::class, $symfonyCommand->getName()))
        );
})->group(__DIR__, __FILE__);

it('is route naming', function (): void {
    Artisan::call(RouteListCommand::class, ['--except-vendor' => true, '--json' => true]);

    Collection::fromJson(Artisan::output())
        // ->dd()
        // ->whereNotNull('name')
        ->whereNotInStrict('name', [])
        ->whereNotInStrict('uri', [])
        ->each(function (array $route): void {
            expect($route['name'])->not->toBeNull("The route [{$route['method']} {$route['uri']}] name should not be null.");

            expect(str($route['name'])->explode('.'))->each->toBeKebabCase(
                "The route [{$route['method']} {$route['uri']}] name [{$route['name']}] should be kebab case."
            );

            expect(
                str($route['uri'])
                    ->explode('/')
                    ->reject(static fn (string $segment): bool => str($segment)->isMatch([
                        '/^$/',
                        '/^\{.*\}$/',
                        '/^v[1-9]$/',
                        // '/^_.*$/',
                    ]))
            )->each->toBeKebabCase(
                "The route [{$route['method']} {$route['uri']}] uri [{$route['uri']}] should be kebab case."
            );
        });
})->group(__DIR__, __FILE__);
