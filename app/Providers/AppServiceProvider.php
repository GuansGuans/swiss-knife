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

namespace App\Providers;

use Composer\XdebugHandler\XdebugHandler;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Lottery;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Traits\Conditionable;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputOption;

final class AppServiceProvider extends ServiceProvider
{
    use Conditionable {
        Conditionable::when as whenever;
    }

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(Application $application): void
    {
        $application->booted(static function (): void {
            collect(Artisan::all())
                ->each(
                    static fn (SymfonyCommand $symfonyCommand): SymfonyCommand => $symfonyCommand
                        ->addOption('xdebug', null, InputOption::VALUE_NONE, 'Display xdebug output')
                        ->addOption(
                            'configuration',
                            null,
                            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                            'Used to dynamically pass one or more configuration key-value pairs(e.g. `--configuration=app.name=guanguans` or `--configuration app.name=guanguans`).',
                        )
                )
                ->tap(
                    /**
                     * @see \Illuminate\Foundation\Console\Kernel::rerouteSymfonyCommandEvents()
                     * @see \Rector\Console\ConsoleApplication::doRun()
                     * @see https://github.com/infection/infection/blob/master/src/Command/RunCommand.php#L586
                     * @see https://github.com/infection/infection/blob/master/src/Console/XdebugHandler.php
                     * @see https://github.com/phan/phan/blob/v6/src/Phan/CLI.php#L3156
                     * @see https://github.com/phan/phan/blob/v6/src/Phan/Library/Restarter.php
                     * @see https://github.com/vimeo/psalm/blob/6.x/src/Psalm/Internal/Cli/Psalm.php#L948
                     * @see https://github.com/vimeo/psalm/blob/6.x/src/Psalm/Internal/Fork/PsalmRestarter.php
                     */
                    static fn (): null => Event::listen(CommandStarting::class, static function (CommandStarting $commandStarting): void {
                        if (
                            class_exists(XdebugHandler::class)
                            && !$commandStarting->input->hasParameterOption('--xdebug')
                            && !app()->runningUnitTests()
                        ) {
                            $xdebugHandler = tap(
                                new XdebugHandler(config('app.name')),
                                static function (XdebugHandler $xdebugHandler): void {
                                    // If Xdebug is enabled, restart without it
                                    $xdebugHandler
                                        // ->setLogger(Log::channel('stderr'))
                                        ->setLogger(Log::channel(
                                            Lottery::odds(1, 100)
                                                ->winner(static fn (): null => null)
                                                ->loser(static fn (): string => 'null')
                                                ->choose()
                                        ))
                                        ->setPersistent()
                                        ->check();
                                }
                            );
                            unset($xdebugHandler);
                        }

                        collect($commandStarting->input->getOption('configuration'))
                            // ->dump()
                            ->mapWithKeys(static function (string $configuration): array {
                                // Assert::contains($configuration, '=', "The configurable option [$configuration] must be formatted as key=value.");
                                \assert(str_contains($configuration, '='), "The configurable option [$configuration] must be formatted as key=value.");
                                [$key, $value] = str($configuration)->explode('=', 2)->all();

                                return [$key => $value];
                            })
                            ->tap(static fn (Collection $configuration): mixed => config($configuration->all()));
                    })
                );
        });
    }
}
