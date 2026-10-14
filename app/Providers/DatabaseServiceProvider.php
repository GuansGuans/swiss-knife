<?php

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

namespace App\Providers;

use Carbon\CarbonInterval;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\DatabaseBusy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Lottery;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Traits\Conditionable;

final class DatabaseServiceProvider extends ServiceProvider
{
    use Conditionable {
        Conditionable::when as whenever;
    }

    /**
     * @throws \JsonException
     */
    public function boot(): void
    {
        $this->whenever(true, function (): void {
            $this->whenProduction();
            $this->whenSQLiteConnection();
            $this->unlessProduction();
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
        });
    }

    private function whenProduction(): void
    {
        $this->whenever($this->app->isProduction(), function (): void {
            DB::prohibitDestructiveCommands($this->app->isProduction());

            /**
             * 此设置将仅报告 1% 的耗时超过 2 秒的查询，从而帮助您监控性能而不会使日志记录系统不堪重负。
             *
             * @see https://www.harrisrafto.eu/harnessing-controlled-randomness-with-laravels-lottery/
             */
            DB::whenQueryingForLongerThan(
                CarbonInterval::seconds(2),
                Lottery::odds(1, 100)->winner(static fn () => report('Querying > 2 seconds.')),
            );

            DB::whenQueryingForLongerThan(
                300000,
                static function (Connection $connection, QueryExecuted $queryExecuted): void {}
            );

            Event::listen(static function (DatabaseBusy $event): void {});
        });
    }

    private function unlessProduction(): void
    {
        $this->unless($this->app->isProduction(), static function (): void {
            Model::automaticallyEagerLoadRelationships(); // 避免 N+1 查询问题
            Model::preventAccessingMissingAttributes(); // Trigger MissingAttributeException
            Model::preventLazyLoading(); // 预防 N+1 查询问题
            Model::preventSilentlyDiscardingAttributes(); // 防止模型静默丢弃不在 fillable 中的字段
            Model::shouldBeStrict(); // Eloquent 严格模式
        });
    }

    private function whenSQLiteConnection(): void
    {
        $this->whenever(DB::connection() instanceof SQLiteConnection, static function (): void {
            /** Enable on delete cascade for sqlite connections. */
            DB::statement(DB::raw('PRAGMA foreign_keys = ON')->getValue(DB::getQueryGrammar()));
        });
    }
}
