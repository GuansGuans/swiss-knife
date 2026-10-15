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

use App\Listeners\ContextSubscriber;
use App\Support\Attribute\Autowired;
use App\Support\Attribute\Mixin;
use App\Support\VarDumper\ServerDumper;
use Carbon\CarbonInterval;
use Composer\XdebugHandler\XdebugHandler;
use Guanguans\LaravelSoar\SoarServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Lottery;
use Pdo\Mysql;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputOption;

return Application::configure(basePath: \dirname(__DIR__))
    ->registered(function (): void {
        classes(
            static fn (string $class, string $file): bool => str($class)->is('App\\Support\\Mixin\\*')
                && str($file)->is('*/../../app/Support/Mixin/*')
        )
            // ->keys()->dd()
            ->each(static function (ReflectionClass $reflectionClass, string $class): void {
                $reflectionAttributes = $reflectionClass->getAttributes(Mixin::class);
                \assert(\count($reflectionAttributes) === 1);

                $mixinAttribute = $reflectionAttributes[0]->newInstance();
                \assert($mixinAttribute instanceof Mixin);

                foreach ($mixinAttribute->classes as $targetClass) {
                    $targetClass::mixin(resolve($class), $mixinAttribute->replace);
                }
            });
    })
    ->registered(function (Application $app): void {
        if ($app->isLocal()) {
            $app->register(SoarServiceProvider::class);
        }

        if (!$app->isProduction()) {
            foreach (
                [
                    'Barryvdh\\LaravelIdeHelper\\IdeHelperServiceProvider',
                    'Dedoc\\Scramble\\ScrambleServiceProvider',
                    'Guanguans\\LaravelSoar\\SoarServiceProvider',
                    'KitLoong\\MigrationsGenerator\\MigrationsGeneratorServiceProvider',
                    'Laravel\\Doctor\\DoctorServiceProvider',
                    'Laravel\\Pail\\PailServiceProvider',
                    'Laravel\\Pao\\Laravel\\ServiceProvider',
                    'Laravel\\Telescope\\TelescopeServiceProvider',
                    'Msamgan\\LaravelEnvKeysChecker\\LaravelEnvKeysCheckerServiceProvider',
                    'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
                    'Orangehill\\Iseed\\IseedServiceProvider',
                    'Pest\\Laravel\\PestServiceProvider',
                    'Reliese\\Coders\\CodersServiceProvider',
                    'Spatie\\LaravelIgnition\\IgnitionServiceProvider',
                    'TheDoctor0\\LaravelFactoryGenerator\\FactoryGeneratorServiceProvider',
                    'Worksome\\Envy\\EnvyServiceProvider',
                ] as $provider
            ) {
                $app->register($provider);
            }
        }
    })
    ->booting(static function (): void {
        config([
            'app.timezone' => 'Asia/Shanghai',
            'app.locale' => 'zh_CN',
            'app.faker_locale' => 'zh_CN',
        ]);

        config([
            'database.connections.mysql.options' => $mysqlOptions = \extension_loaded('pdo_mysql') ? array_filter([
                // Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                Mysql::ATTR_SSL_CA => Config::collection('database.connections.mysql.options')->get(Mysql::ATTR_SSL_CA),
                // PDO::ATTR_CASE => PDO::CASE_LOWER, // @see https://www.yiichina.com/tutorial/65543
                Mysql::ATTR_INIT_COMMAND => \sprintf("SET time_zone = '%s';", timezone_offset_name()),
            ]) : [],
            'database.connections.mariadb.options' => $mysqlOptions,
        ]);

        config([
            'logging.channels.stack.channels' => ['daily'],
        ]);

        config([
            'services.autowired' => [
                'only' => [
                    'App\*',
                ],
                'except' => [
                    'App\Support\Mixin\*',
                ],
            ],
        ]);

        File::ensureDirectoryExists(base_path('vendor/_laravel_ide_helper/'));
        config([
            'ide-helper.filename' => base_path('vendor/_laravel_ide_helper/_ide_helper.php'),
            'ide-helper.meta_filename' => base_path('vendor/_laravel_ide_helper/.phpstorm.meta.php'),
            'ide-helper.models_filename' => base_path('vendor/_laravel_ide_helper/_ide_helper_models.php'),
        ]);

        config([
            'localization.align' => false,
        ]);
    })
    ->booting(static function (): void {
        Artisan::whenCommandLifecycleIsLongerThan(
            CarbonInterval::seconds(3),
            static function (): void {}
        );
        Event::subscribe(ContextSubscriber::class);
        ServerDumper::register();
    })
    ->booting(static function (Application $app): void {
        /**
         * @see \Guanguans\LaravelExceptionNotify\Collectors\ApplicationCollector
         * @see \Illuminate\Log\Context\ContextServiceProvider::boot()
         */
        Context::add([
            'php-version' => \PHP_VERSION,
            'php-interface' => \PHP_SAPI,
            'laravel-version' => $app->version(),
            'running-in-console' => $app->runningInConsole(),
            // 'trace-id' => TRACE_ID,
        ]);

        $app->runningInConsole() or Context::add([
            'user-id' => request()->user()?->id,
            'url' => request()->url(),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'action' => request()->route()?->getActionName(),
        ]);
    })
    ->booting(function (Application $app): void {
        $app->resolving(static function (mixed $object, Application $app): void {
            if (
                !\is_object($object)
                || !str($object::class)->is(config('services.autowired.only'))
                || str($object::class)->is(config('services.autowired.except'))
            ) {
                return;
            }

            $reflectionObject = new ReflectionObject($object);

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

                    if ($reflectionPropertyType instanceof ReflectionNamedType && !$reflectionPropertyType->isBuiltin()) {
                        return $reflectionPropertyType->getName();
                    }

                    throw new LogicException(\sprintf(
                        "Attribute [%s] of property [$property] miss a argument [propertyType], or property [$property] mustn't be a built-in named type.",
                        Autowired::class,
                    ));
                });
                \assert(\is_string($propertyType));

                try {
                    // \PHP_VERSION_ID >= 80100 or $reflectionProperty->isPublic() or $reflectionProperty->setAccessible(true);
                    $reflectionProperty->setValue($object, $app->make($propertyType, $autowired->parameters));
                } catch (Throwable $throwable) {
                    throw new TypeError(
                        "Type [$propertyType] of property [$property] resolve failed [{$throwable->getMessage()}].",
                        $throwable->getCode(),
                        $throwable
                    );
                }
            }
        });
    })
    ->booted(function (Application $app): void {
        $app->booted(function (): void {
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
