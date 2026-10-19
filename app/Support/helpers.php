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

use Carbon\CarbonInterval;
use Carbon\CarbonTimeZone;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Symfony\Component\VarDumper\VarDumper;
use function Illuminate\Filesystem\join_paths;

if (!\function_exists('classes')) {
    /**
     * @see https://github.com/illuminate/collections
     * @see https://github.com/alekitto/class-finder
     * @see https://github.com/ergebnis/classy
     * @see https://gitlab.com/hpierce1102/ClassFinder
     * @see https://packagist.org/packages/haydenpierce/class-finder
     * @see \get_declared_classes()
     * @see \get_declared_interfaces()
     * @see \get_declared_traits()
     * @see \Composer\Util\ErrorHandler
     * @see \Composer\Util\Silencer::call()
     * @see \DG\BypassFinals::enable()
     * @see \Illuminate\Foundation\Bootstrap\HandleExceptions::bootstrap()
     * @see \Monolog\ErrorHandler
     * @see \PhpCsFixer\ExecutorWithoutErrorHandler
     * @see \Phrity\Util\ErrorHandler
     *
     * @template TObject of object
     *
     * @internal
     *
     * @param null|(callable(class-string<TObject>, string): bool) $filter
     *
     * @throws \ErrorException
     * @throws \ReflectionException
     *
     * @return \Illuminate\Support\Collection<class-string<TObject>, \ReflectionClass<TObject>>
     *
     * @noinspection PhpUndefinedNamespaceInspection
     */
    function classes(?callable $filter = null): Collection
    {
        $func = __FUNCTION__;
        $errorMessenger = static fn (
            string $file,
            string $class
        ): string => "Failed to reflect the class [$class] in the file [$file]. "
            ."You may need to filter out the class or file using the callback parameter of the function [$func()].";

        /** @var null|array{file: string, class: class-string<TObject>, line: int} $context */
        static $context = null;
        static $registered = false;

        if (!$registered) {
            register_shutdown_function(
                static function () use (&$context, $errorMessenger): void {
                    // @codeCoverageIgnoreStart
                    if (
                        null === $context
                        || null === ($error = error_get_last())
                        || !\in_array($error['type'], [\E_COMPILE_ERROR, \E_CORE_ERROR, \E_ERROR, \E_PARSE], true)
                    ) {
                        return;
                    }

                    // trigger_error($errorMessenger($context['file'], $context['class']), \E_USER_ERROR);
                    throw new ErrorException(
                        $errorMessenger($context['file'], $context['class']),
                        0,
                        $error['type'],
                        __FILE__,
                        $context['line'],
                        new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
                    );
                    // @codeCoverageIgnoreEnd
                }
            );
            $registered = true;
        }

        /** @var null|\Illuminate\Support\Collection<string, class-string> $classes */
        static $classes;
        $classes ??= collect(spl_autoload_functions())->flatMap(
            static fn (callable $loader): array => \is_array($loader) && $loader[0] instanceof ClassLoader
                ? $loader[0]->getClassMap()
                : []
        );
        $filter ??= static fn (string $_, string $__): bool => true;

        return $classes
            ->filter(static fn (string $file, string $class): bool => $filter($class, $file))
            ->mapWithKeys(static function (string $file, string $class) use (&$context, $errorMessenger): array {
                try {
                    $context = ['class' => $class, 'file' => $file, 'line' => __LINE__ + 2];

                    return [$class => new ReflectionClass($class)];
                } catch (Throwable $throwable) {
                    // return [$class => $throwable];
                    throw new ReflectionException($errorMessenger($file, $class), 0, $throwable);
                } finally {
                    $context = null;
                }
            });
    }
}

if (!\function_exists('dump_to_server')) {
    /**
     * @see \Symfony\Component\VarDumper\VarDumper::register()
     *
     * ```
     * ./vendor/bin/var-dump-server
     * ```.
     *
     * @noinspection ForgottenDebugOutputInspection
     * @noinspection DebugFunctionUsageInspection
     */
    function dump_to_server(mixed ...$vars): mixed
    {
        $_SERVER['VAR_DUMPER_FORMAT'] = null;
        VarDumper::setHandler(null);
        $_SERVER['VAR_DUMPER_FORMAT'] = 'server';
        // $_SERVER['VAR_DUMPER_SERVER'] = '0.0.0.0:9912';

        return dump(...$vars);
    }
}

if (!\function_exists('env_explode')) {
    /**
     * @noinspection LaravelFunctionsInspection
     */
    function env_explode(string $key, mixed $default = null, string $delimiter = ',', int $limit = \PHP_INT_MAX): mixed
    {
        $env = env($key, $default);

        if (\is_string($env)) {
            return $env ? explode($delimiter, $env, $limit) : [];
        }

        return $env;
    }
}

if (!\function_exists('env_json_decode')) {
    /**
     * @noinspection LaravelFunctionsInspection
     */
    function env_json_decode(
        string $key,
        mixed $default,
        int $depth = 512,
        int $options = \JSON_THROW_ON_ERROR
    ): mixed {
        $env = env($key, $default);

        if (\is_string($env)) {
            return $env ? json_decode($env, true, $depth, $options) : [];
        }

        return $env;
    }
}

if (!\function_exists('humans_milliseconds')) {
    /**
     * @param array<string, mixed> $syntax
     *
     * @throws \Exception
     */
    function humans_milliseconds(float|int $milliseconds, array $syntax = []): string
    {
        return CarbonInterval::microseconds($milliseconds * 1000)
            ->cascade()
            ->forHumans($syntax + [
                'join' => ', ',
                'locale' => 'en',
                // 'locale' => 'zh_CN',
                'minimumUnit' => 'us',
                'short' => true,
            ]);
    }
}

if (!\function_exists('make')) {
    /**
     * @see https://github.com/laravel/framework/blob/12.x/src/Illuminate/Foundation/helpers.php
     * @see https://github.com/yiisoft/yii2/blob/master/framework/BaseYii.php
     *
     * @param array<string, mixed>|string $name
     * @param array<string, mixed> $parameters
     */
    function make(array|string $name, array $parameters = []): mixed
    {
        if (\is_string($name)) {
            return resolve($name, $parameters);
        }

        foreach (
            $keys ??= [
                '__abstract', '__class', '__name',
                '_abstract', '_class', '_name',
                'abstract', 'class', 'name',
            ] as $key
        ) {
            if (isset($name[$key])) {
                return make($name[$key], $parameters + Arr::except($name, $key));
            }
        }

        throw new InvalidArgumentException(
            \sprintf('The argument of name must be an array containing a `%s` element.', implode('` or `', $keys))
        );
    }
}

if (!\function_exists('net_ease_cloud_music_path')) {
    function net_ease_cloud_music_path(string $path = ''): string
    {
        return join_paths('app', 'net-ease-cloud-music')
                |> storage_path(...)
                |> (static fn (string $basePath): string => join_paths($basePath, $path));
    }
}

if (!\function_exists('resolve_callback')) {
    /**
     * @see https://github.com/PHP-DI/Invoker/blob/master/src/CallableResolver.php
     * @see \Illuminate\Container\Container::call()
     *
     * @throws \Throwable
     *
     * @noinspection DebugFunctionUsageInspection
     */
    function resolve_callback(callable|string $callback): callable
    {
        if (\is_callable($callback)) {
            return $callback;
        }

        if (\is_callable($segments = explode('@', $callback, 2))) {
            return $segments;
        }

        $exportedCallback = var_export($callback, true);

        throw_if(
            \count($segments) !== 2 || !method_exists($segments[0], $segments[1]),
            InvalidArgumentException::class,
            "Invalid callback: $exportedCallback."
        );

        try {
            return [resolve($segments[0]), $segments[1]];
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException(
                "Invalid callback: $exportedCallback, Error message: {$throwable->getMessage()}.",
                $throwable->getCode(),
                $throwable
            );
        }
    }
}

if (!\function_exists('running_in_github_action')) {
    function running_in_github_action(): bool
    {
        return 'true' === getenv('GITHUB_ACTIONS');
    }
}

if (!\function_exists('running_in_octane')) {
    function running_in_octane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) || Env::get('OCTANE_DATABASE_SESSION_TTL');
    }
}

if (!\function_exists('timezone_offset_name')) {
    /**
     * Gets the time offset from the provided timezone relative to UTC as a number. This
     * is used in the database configuration since we can't always rely on there being support
     * for named timezones in MySQL.
     *
     * Returns the timezone as a string like +08:00 or -05:00 depending on the app timezone.
     *
     * @see https://github.com/pelican-dev/panel/blob/3.x/app/Helpers/Time.php
     */
    function timezone_offset_name(null|DateTimeZone|false|int|string $timezone = null): string
    {
        // return \Carbon\CarbonImmutable::now($timezone ?: config('app.timezone'))->getTimezone()->toOffsetName();
        // return \Carbon\CarbonImmutable::now($timezone ?? config('app.timezone'))->format('P');
        return CarbonTimeZone::instance($timezone ?? config('app.timezone'))?->toOffsetName();
    }
}
