<?php

/** @noinspection ClassConstantCanBeUsedInspection */
/** @noinspection ClassnameLiteralInspection */
/** @noinspection D */
/** @noinspection LaravelFunctionsInspection */
/** @noinspection LaravelUnknownConfigKeyInspection */
/** @noinspection PhpInternalEntityUsedInspection */
/** @noinspection PhpUnusedAliasInspection */
declare(strict_types=1);

/**
 * Copyright (c) 2026 guanguans<ityaozm@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/GuansGuans/swiss-knife
 */

use App\Console\Commands\NetEaseCloudMusicFreshCommand;
use App\Console\Commands\NetEaseCloudMusicSnapshotCommand;
use App\Listeners\ContextSubscriber;
use App\Rules\AbstractRule;
use App\Rules\MaxUploadSizeRule;
use App\Support\Attribute\Autowired;
use App\Support\Attribute\Mixin;
use App\Support\VarDumper\ServerDumper;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Guanguans\LaravelExceptionNotify\Facades\ExceptionNotify;
use Guanguans\LaravelExceptionNotify\Pipes;
use Guanguans\LaravelExceptionNotify\Template;
use Guanguans\Notify;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\DatabaseBusy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Lottery;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\File as FileRule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Laravel\Doctor\Diagnostics\ComposerAuditPasses;
use Laravel\Doctor\Diagnostics\ComposerAutoloadIsValid;
use Orbit\Drivers\Json as JsonDriver;
use Orbit\Facades\Orbit;
use Pdo\Mysql;
use function Illuminate\Filesystem\join_paths;

\defined('TRACE_ID') or \define('TRACE_ID', (string) Str::uuid7());

return Application::configure(\dirname(__DIR__))
    ->registered(static function (): void {
        classes(
            static fn (string $class, string $file): bool => str($class)->is('App\\Support\\Mixin\\*')
                && str($file)->is('*/../../app/Support/Mixin/*')
        )
            // ->keys()->dd()
            ->each(static function (ReflectionClass $reflectionClass, string $class): void {
                $reflectionAttributes = $reflectionClass->getAttributes(Mixin::class);
                \assert(1 === \count($reflectionAttributes));

                $mixinAttribute = $reflectionAttributes[0]->newInstance();
                \assert($mixinAttribute instanceof Mixin);

                foreach ($mixinAttribute->classes as $targetClass) {
                    $targetClass::mixin(resolve($class), $mixinAttribute->replace);
                }
            });
    })
    ->registered(static function (Application $application): void {
        if ($application->isLocal()) {
            foreach (
                [
                    'App\\Providers\\TelescopeServiceProvider',
                    'Guanguans\\LaravelSoar\\SoarServiceProvider',
                    'Laravel\\Telescope\\TelescopeServiceProvider',
                ] as $provider
            ) {
                $application->register($provider);
            }
        }

        if (!$application->isProduction()) {
            foreach (
                [
                    'Barryvdh\\LaravelIdeHelper\\IdeHelperServiceProvider',
                    'Dedoc\\Scramble\\ScrambleServiceProvider',
                    // 'Guanguans\\LaravelSoar\\SoarServiceProvider',
                    'KitLoong\\MigrationsGenerator\\MigrationsGeneratorServiceProvider',
                    'Laravel\\Boost\\BoostServiceProvider',
                    'Laravel\\Doctor\\DoctorServiceProvider',
                    'Laravel\\Pail\\PailServiceProvider',
                    'Laravel\\Pao\\Laravel\\ServiceProvider',
                    // 'Laravel\\Telescope\\TelescopeServiceProvider',
                    'Laravel\\Tinker\\TinkerServiceProvider',
                    'Msamgan\\LaravelEnvKeysChecker\\LaravelEnvKeysCheckerServiceProvider',
                    'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
                    'Opcodes\\LogViewer\\LogViewerServiceProvider',
                    'Orangehill\\Iseed\\IseedServiceProvider',
                    'Pest\\Laravel\\PestServiceProvider',
                    'Reliese\\Coders\\CodersServiceProvider',
                    'Spatie\\LaravelIgnition\\IgnitionServiceProvider',
                    'TheDoctor0\\LaravelFactoryGenerator\\FactoryGeneratorServiceProvider',
                    'Worksome\\Envy\\EnvyServiceProvider',
                ] as $provider
            ) {
                $application->register($provider);
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
                'only' => ['App\*'],
                'except' => ['App\Support\Mixin\*'],
            ],
            'services.net_ease_cloud_music' => [
                'cookies' => env('NET_EASE_CLOUD_MUSIC_COOKIES'),
                'user_ids' => env_explode('NET_EASE_CLOUD_MUSIC_USER_IDS', [/* 284761234, */ 13655983778]),
            ],
        ]);

        config([
            'doctor.except' => [
                ComposerAuditPasses::class,
                ComposerAutoloadIsValid::class,
            ],
        ]);

        File::ensureDirectoryExists($directory = base_path('vendor/_laravel_ide_helper/'));
        config([
            'ide-helper.filename' => join_paths($directory, '_ide_helper.php'),
            'ide-helper.meta_filename' => join_paths($directory, '.phpstorm.meta.php'),
            'ide-helper.models_filename' => join_paths($directory, '_ide_helper_models.php'),
        ]);

        config([
            'localization.align' => false,
        ]);

        Orbit::extend('json', static fn (): JsonDriver => new class extends JsonDriver {
            /**
             * @noinspection MissingParentCallInspection
             * @noinspection KeysFragmentationWithArrayFunctionsInspection
             */
            #[Override]
            protected function dumpContent(Model $model): string
            {
                return json_encode(
                    array_filter($this->getModelAttributes($model), static fn (mixed $value): bool => null !== $value),
                    NetEaseCloudMusicSnapshotCommand::PRETTY_JSON_OPTIONS
                );
            }
        });
        config([
            'orbit.default' => 'json',
            'orbit.paths.content' => net_ease_cloud_music_path('database/'),
        ]);

        config([
            'exception-notify.environments' => ['production'],
            'exception-notify.job.connection' => 'sync',
            'exception-notify.channels.stack.channels' => ['log', 'ntfy', 'wPush'],
            'exception-notify.channels.ntfy.message.options.topic' => env('EXCEPTION_NOTIFY_NTFY_TOPIC'),
            'exception-notify.channels.ntfy.message.options.markdown' => true,
            'exception-notify.channels.ntfy.pipes' => [
                Pipes\SprintfMarkdownPipe::class,
                Pipes\LimitLengthPipe::with(4096),
            ],
            'exception-notify.channels.wPush' => [
                'driver' => 'notify',
                'authenticator' => [
                    'class' => Notify\WPush\Authenticator::class,
                    'apikey' => env('EXCEPTION_NOTIFY_WPUSH_APIKEY'),
                ],
                'client' => [
                    'class' => Notify\WPush\Client::class,
                ],
                'message' => [
                    'class' => Notify\WPush\Messages\Message::class,
                    'options' => [
                        'title' => Template::TITLE,
                        'content' => Template::CONTENT,
                        'channel' => 'wechat',
                    ],
                ],
                'pipes' => [
                    Pipes\SprintfMarkdownPipe::class,
                    Pipes\LimitLengthPipe::with(50000),
                ],
            ],
        ]);
        Event::listen(ScheduledTaskFailed::class, static function (ScheduledTaskFailed $scheduledTaskFailed): void {
            /** @see \Illuminate\Console\Scheduling\ScheduleRunCommand::runEvent() */
            ExceptionNotify::skipWhen(
                static fn (Throwable $throwable): bool => $throwable === $scheduledTaskFailed->exception
            );
        });
    })
    ->booting(static function (Application $application): void {
        if (running_in_github_action() && !$application->isProduction()) {
            return;
        }

        /**
         * @see https://masteringlaravel.io/daily/2024-11-13-how-can-you-make-sure-the-environment-is-configured-correctly
         */
        Env::getOrFail('EXCEPTION_NOTIFY_NTFY_TOPIC');
        Env::getOrFail('EXCEPTION_NOTIFY_WPUSH_APIKEY');
        Env::getOrFail('NET_EASE_CLOUD_MUSIC_COOKIES');
        // Env::getOrFail('NET_EASE_CLOUD_MUSIC_USER_IDS');
    })
    ->booting(static function (Application $application): void {
        if (!$application->runningUnitTests()) {
            return;
        }

        CarbonImmutable::setTestNow();
        CarbonImmutable::setTestNowAndTimezone();
        Date::setTestNow();
        Date::setTestNowAndTimezone();
        Http::preventStrayRequests();
        Mail::alwaysTo('example@example.com');
        Mail::fake();
        Queue::fake();
        ParallelTesting::setUpTestDatabase(static function (): void {
            Artisan::call('db:seed');
        });
    })
    ->booting(static function (Application $application): void {
        /**
         * @see https://github.com/laravel/octane/issues/990
         * @see https://medium.com/@raymondlor/migrating-to-laravel-octane-almost-cost-me-a-client-forever-59b0162e74e2
         * @see https://learnku.com/laravel/t/89636
         */
        Queue::looping(static function (Looping $looping): void {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
                Log::error('Transaction have not been committed or rolled back.', (array) $looping);
            }
        });

        Artisan::whenCommandLifecycleIsLongerThan(CarbonInterval::seconds(3), static function (): void {});
        Event::subscribe(ContextSubscriber::class);
        Route::pattern('id', '[0-9]+');
        Route::patterns(['id' => '[0-9]+']);
        ServerDumper::register();
        NetEaseCloudMusicFreshCommand::prohibit($application->isProduction());
    })
    ->booting(static function (): void {
        config(['session.secure' => true]);
        request()->server->set('HTTPS', 'on');
        request()->server->set('SERVER_PORT', 443);
        URL::forceHttps();
    })
    ->booting(static function (Application $application): void {
        /**
         * @see \Guanguans\LaravelExceptionNotify\Collectors\ApplicationCollector
         * @see \Guanguans\LaravelExceptionNotify\Collectors\RequestBasicCollector
         * @see \Illuminate\Log\Context\ContextServiceProvider::boot()
         */
        Context::add([
            'Trace Id' => TRACE_ID,
            // 'Time' => Date::now()->format('Y-m-d H:i:s'),
            'Name' => config('app.name'),
            'Version' => $application->version(),
            'PHP Version' => \PHP_VERSION,
            'Php Interface' => \PHP_SAPI,
            'Environment' => $application->environment(),
            'In Console' => $application->runningInConsole(),
            'Debug Mode' => $application->hasDebugModeEnabled(),
            'Maintenance Mode' => $application->isDownForMaintenance(),
            'Timezone' => config('app.timezone'),
            'Locale' => config('app.locale'),
        ]);

        $application->runningInConsole() or Context::add([
            'User Id' => request()->user()?->id,
            'URL' => request()->url(),
            'IP' => request()->ip(),
            'Method' => request()->method(),
            'Controller Action' => request()->route()?->getActionName(),
            // 'Command' => null,
            // 'Memory' => Number::fileSize(memory_get_peak_usage(true)),
            // 'Duration' => blank(
            //     $startTime = \defined('LARAVEL_START') ? LARAVEL_START : request()->server('REQUEST_TIME_FLOAT')
            // ) ? 'Unknown' : humans_milliseconds((microtime(true) - (float) $startTime) * 1000),
        ]);
    })
    ->booting(static function (Application $application): void {
        /**
         * @see \Carbon\Laravel\ServiceProvider
         * @see https://dyrynda.com.au/blog/laravel-immutable-dates
         */
        Date::use(CarbonImmutable::class);
        Date::useCallable(
            static fn (mixed $result): mixed => $result instanceof CarbonInterface
                // ? $result->inAppTimezone()
                ? $result->setTimezone(Config::string('app.timezone'))
                : $result
        );

        /**
         * @see https://www.php.net/manual/zh/numberformatter.parsecurrency.php
         * @see https://zh.wikipedia.org/wiki/ISO_4217
         */
        Number::useCurrency('CNY');
        Date::setLocale($application->getLocale());
        Number::useLocale($application->getLocale());
    })
    ->booting(static function (Application $application): void {
        Email::defaults(static fn (): Email => Email::default()->when(
            $application->isProduction(),
            static fn (Email $email) => $email
                // ->rfcCompliant()
                ->strict()
                ->validateMxRecord()
                ->preventSpoofing()
                ->withNativeValidation()
        ));

        FileRule::defaults(static fn (): FileRule => FileRule::default()->when(
            $application->isProduction(),
            static fn (FileRule $fileRule) => $fileRule->rules([MaxUploadSizeRule::make()])
        ));

        Password::defaults(static fn (): Password => Password::min(8)->max(64)->when(
            $application->isProduction(),
            static fn (#[SensitiveParameter] Password $password) => $password
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()
        ));

        classes(
            static fn (string $class, string $file): bool => str($class)->is('App\\Rules\\*')
                && str($file)->is('*/../../app/Rules/*')
        )
            ->filter(
                static fn (ReflectionClass $reflectionClass): bool => $reflectionClass->isSubclassOf(AbstractRule::class)
                    && $reflectionClass->isInstantiable()
            )
            // ->keys()->dd()
            ->each(
                /**
                 * @param class-string<\App\Rules\AbstractRule> $ruleClass
                 */
                static function (ReflectionClass $_, string $ruleClass): void {
                    ValidatorFacade::{$ruleClass::extendMethod()}(
                        $ruleClass::name(),
                        static fn (string $attribute, mixed $value, array $parameters, Validator $validator): bool => tap(
                            new $ruleClass(...$parameters),
                            static function (AbstractRule $rule) use ($validator): void {
                                $rule instanceof DataAwareRule and $rule->setData($validator->getData());
                                $rule instanceof ValidatorAwareRule and $rule->setValidator($validator);
                            }
                        )->passes($attribute, $value),
                        $ruleClass::message()
                    );
                }
            );
    })
    ->booting(static function (Application $application): void {
        Event::listen(StatementPrepared::class, static function (StatementPrepared $statementPrepared): void {
            $statementPrepared->statement->setFetchMode(PDO::FETCH_ASSOC);
        });

        Event::listen(ConnectionEstablished::class, static function (ConnectionEstablished $connectionEstablished): void {
            if ($connectionEstablished->connection instanceof SQLiteConnection) {
                // Enable on delete cascade for sqlite connections.
                DB::statement(DB::raw('PRAGMA foreign_keys = ON')->getValue(DB::getQueryGrammar()));
            }
        });

        Json::encodeUsing(static fn (mixed $value): string => json_encode(
            $value,
            // \JSON_INVALID_UTF8_IGNORE |
            \JSON_INVALID_UTF8_SUBSTITUTE |
            // \JSON_PARTIAL_OUTPUT_ON_ERROR |
            \JSON_PRESERVE_ZERO_FRACTION |
            // \JSON_PRETTY_PRINT |
            \JSON_THROW_ON_ERROR |
            \JSON_UNESCAPED_LINE_TERMINATORS |
            \JSON_UNESCAPED_SLASHES |
            \JSON_UNESCAPED_UNICODE
        ));

        if ($application->isProduction()) {
            /**
             * 此设置将仅报告 1% 的耗时超过 2 秒的查询，从而帮助您监控性能而不会使日志记录系统不堪重负。
             *
             * @see https://www.harrisrafto.eu/harnessing-controlled-randomness-with-laravels-lottery/
             */
            DB::whenQueryingForLongerThan(
                CarbonInterval::seconds(2),
                Lottery::odds(1, 100)->winner(static function (Connection $connection, QueryExecuted $queryExecuted): void {}),
            );

            DB::whenQueryingForLongerThan(
                300000,
                static function (Connection $connection, QueryExecuted $queryExecuted): void {}
            );

            DB::prohibitDestructiveCommands($application->isProduction());
            Event::listen(DatabaseBusy::class, static function (DatabaseBusy $databaseBusy): void {});
        }

        if (!$application->isProduction()) {
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
                info(\sprintf('Attempted to lazy load [%s] on model [%s].', $relation, $model::class));
            });
            Model::automaticallyEagerLoadRelationships(); // 避免 N+1 查询问题
            Model::preventAccessingMissingAttributes(); // Trigger MissingAttributeException
            Model::preventLazyLoading(); // 预防 N+1 查询问题
            Model::preventSilentlyDiscardingAttributes(); // 防止模型静默丢弃不在 fillable 中的字段
            Model::shouldBeStrict(); // Eloquent 严格模式
        }
    })
    ->booting(static function (Application $application): void {
        $application->resolving(static function (mixed $object, Application $application): void {
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
                    $reflectionProperty->setValue($object, $application->make($propertyType, $autowired->parameters));
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
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(static function (Schedule $schedule): void {
        // $schedule->command('inspire')->daily()/* ->atRandom('07:15', '11:42') */ /* ->doNotMonitor() */ /* ->ddExpression() */ ->dailyAppendOutputTo()->runInBackground()->withoutOverlapping(60);
        $schedule->command('telescope:prune')->everyThirtyMinutes()->skip(app()->isProduction())->dailyAppendOutputTo()->withoutOverlapping();
        $schedule->command(NetEaseCloudMusicSnapshotCommand::class)->everyThirtyMinutes()->when(running_in_github_action() && app()->isProduction())->dailyAppendOutputTo()->withoutOverlapping();
    })
    ->withMiddleware(static function (Middleware $middleware): void {})
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
