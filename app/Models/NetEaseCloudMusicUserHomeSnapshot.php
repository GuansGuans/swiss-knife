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

namespace App\Models;

use App\Support\Orbit\Drivers\FlatJson;
use App\Support\Orbit\Drivers\Json;
use Illuminate\Database\Schema\Blueprint;
use Orbit\Concerns\Orbital;
use Orbit\Contracts\Orbit;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;

#[\Illuminate\Database\Eloquent\Attributes\Fillable([
    'event_count',
    'fan_count',
    'follow_count',
    'level',
    'name',
    'screenshot_path',
    'user_id',
])]
final class NetEaseCloudMusicUserHomeSnapshot extends AbstractModel implements Orbit
{
    use Orbital;

    public static function orbitContentPath(): string
    {
        self::query(); // 触发 booting() 方法，设置 orbit.paths.content 配置项

        return config('orbit.paths.content');
    }

    /**
     * @see https://github.com/ryangjchandler/orbit/blob/1.x/src/Concerns/Orbital.php#L53
     * @see https://github.com/ryangjchandler/orbit/blob/1.x/src/Drivers/FileDriver.php#L86
     * @see self::toOrbitAttributes()
     */
    #[\Override]
    public function getAttributes(): array
    {
        if ('toOrbitAttributes' === (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? null)) {
            // We need to refresh the model so that we can get all of the columns
            // and default values from the SQLite cache.
            $this->refresh();
        }

        return parent::getAttributes();
    }

    #[\Override]
    public function schema(Blueprint $table): void
    {
        $table->id();
        $table->unsignedInteger('user_id');
        $table->string('name');
        $table->unsignedTinyInteger('level');
        $table->unsignedInteger('event_count');
        $table->unsignedInteger('follow_count');
        $table->unsignedInteger('fan_count');
        $table->string('screenshot_path');
        $table->schemalessAttributes('extra_attributes');
    }

    #[\Override]
    public function getOrbitDriver(): string
    {
        // return FlatJson::class;
        return Json::class;
    }

    #[\Override]
    protected static function booting(): void
    {
        config([
            'orbit.paths.content' => net_ease_cloud_music_path('content/'),
        ]);
    }

    /**
     * Orbit 在 bootOrbital() 迁移时用 newInstanceWithoutConstructor() 创建模型，跳过了初始化钩子，
     * 所以需要在这里手动设置 $casts 属性，不能用 casts() 方法，因为 casts() 方法在 bootOrbital() 迁移时不会被调用。
     *
     * @see \Orbit\Concerns\Orbital::bootOrbital()
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::initializeHasAttributes()
     * @see \App\Models\Concerns\HasSchemalessAttributes::initializeHasSchemalessAttributes()
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'event_count' => 'integer',
            // 'extra_attributes' => SchemalessAttributes::class,
            'fan_count' => 'integer',
            'follow_count' => 'integer',
            'level' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
