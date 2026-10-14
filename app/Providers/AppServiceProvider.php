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

use App\Support\Attribute\Autowired;
use App\Support\Attribute\Mixin;
use App\Support\VarDumper\ServerDumper;
use Carbon\CarbonInterval;
use Composer\XdebugHandler\XdebugHandler;
use Guanguans\LaravelSoar\SoarServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
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
    public function register(): void
    {
        classes(
            static fn (string $class, string $file): bool => str($class)->is('App\\Support\\Mixin\\*')
                && str($file)->is('*/../../app/Support/Mixin/*')
        )
            // ->keys()->dd()
            ->each(static function (\ReflectionClass $reflectionClass, string $class): void {
                $reflectionAttributes = $reflectionClass->getAttributes(Mixin::class);
                \assert(\count($reflectionAttributes) === 1);

                $mixinAttribute = $reflectionAttributes[0]->newInstance();
                \assert($mixinAttribute instanceof Mixin);

                foreach ($mixinAttribute->classes as $targetClass) {
                    $targetClass::mixin(resolve($class), $mixinAttribute->replace);
                }
            });

        $this->whenever($this->app->isLocal(), function (): void {
            $this->app->register(SoarServiceProvider::class);
        });

        $this->unless($this->app->isProduction(), function (): void {
            foreach (
                [
                    'Barryvdh\\LaravelIdeHelper\\IdeHelperServiceProvider',
                    'Dedoc\\Scramble\\ScrambleServiceProvider',
                    'Guanguans\\LaravelSoar\\SoarServiceProvider',
                    'Laravel\\Doctor\\DoctorServiceProvider',
                    'Laravel\\Pail\\PailServiceProvider',
                    'Laravel\\Pao\\Laravel\\ServiceProvider',
                    'Laravel\\Telescope\\TelescopeServiceProvider',
                    'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
                    'Spatie\\LaravelIgnition\\IgnitionServiceProvider',
                ] as $provider
            ) {
                $this->app->register($provider);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->whenever(true, function (): void {
            $this->autowire();
            $this->addDefaultInputDefinition();
            Artisan::whenCommandLifecycleIsLongerThan(CarbonInterval::seconds(3), static function (): void {});
            ServerDumper::register();

            /**
             * @see \Guanguans\LaravelExceptionNotify\Collectors\ApplicationCollector
             * @see \Illuminate\Log\Context\ContextServiceProvider::boot()
             */
            Context::add([
                'php-version' => \PHP_VERSION,
                'php-interface' => \PHP_SAPI,
                'laravel-version' => $this->app->version(),
                'running-in-console' => $this->app->runningInConsole(),
                // 'trace-id' => TRACE_ID,
            ]);

            $this->unless($this->app->runningInConsole(), static function (): void {
                Context::add([
                    'user-id' => request()->user()?->id,
                    'url' => request()->url(),
                    'ip' => request()->ip(),
                    'method' => request()->method(),
                    'action' => request()->route()?->getActionName(),
                ]);
            });
        });
    }

    /**
     * @see https://github.com/spring-projects/spring-framework/blob/main/spring-beans/src/main/java/org/springframework/beans/factory/annotation/Autowired.java
     */
    private function autowire(): void
    {
        $this->app->resolving(static function (mixed $object, Application $app): void {
            if (
                !\is_object($object)
                || !str($object::class)->is(config('services.autowired.only'))
                || str($object::class)->is(config('services.autowired.except'))
            ) {
                return;
            }

            $reflectionObject = new \ReflectionObject($object);

            foreach ($reflectionObject->getProperties() as $reflectionProperty) {
                if (
                    !$reflectionProperty->isDefault()
                    || $reflectionProperty->isStatic()
                    || [] === ($attributes = $reflectionProperty->getAttributes(Autowired::class))
                ) {
                    continue;
                }

                $autowired = $attributes[0]->newInstance();
                \assert($autowired instanceof Autowired);
                $property = "{$reflectionObject->getName()}::\${$reflectionProperty->getName()}";
                $propertyType = value(static function () use ($autowired, $reflectionProperty, $property): string {
                    if ($autowired->propertyType) {
                        return $autowired->propertyType;
                    }

                    $reflectionPropertyType = $reflectionProperty->getType();

                    if ($reflectionPropertyType instanceof \ReflectionNamedType && !$reflectionPropertyType->isBuiltin()) {
                        return $reflectionPropertyType->getName();
                    }

                    throw new \LogicException(\sprintf(
                        "Attribute [%s] of property [$property] miss a argument [propertyType], or property [$property] mustn't be a built-in named type.",
                        Autowired::class,
                    ));
                });
                \assert(\is_string($propertyType));

                try {
                    // \PHP_VERSION_ID >= 80100 or $reflectionProperty->isPublic() or $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($object, $app->make($propertyType, $autowired->parameters));
                } catch (\Throwable $throwable) {
                    throw new \TypeError(
                        "Type [$propertyType] of property [$property] resolve failed [{$throwable->getMessage()}].",
                        $throwable->getCode(),
                        $throwable
                    );
                }
            }
        });
    }

    /**
     * @see \Illuminate\Console\Application::getDefaultInputDefinition()
     */
    private function addDefaultInputDefinition(): void
    {
        $this->app->booted(function (): void {
            collect(Artisan::all())
                ->each(
                    static fn (SymfonyCommand $command): SymfonyCommand => $command
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
                    fn (): null => Event::listen(CommandStarting::class, function (CommandStarting $commandStarting): void {
                        if (
                            class_exists(XdebugHandler::class)
                            && !$commandStarting->input->hasParameterOption('--xdebug')
                            && !$this->app->runningUnitTests()
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
