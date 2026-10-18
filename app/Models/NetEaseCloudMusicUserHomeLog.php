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

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;
use Orbit\Concerns\Orbital;
use Orbit\Concerns\SoftDeletes;
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
final class NetEaseCloudMusicUserHomeLog extends AbstractModel
{
    use HasFactory;
    use Orbital;
    use SoftDeletes;

    /**
     * Orbit 在 bootOrbital() 迁移时用 newInstanceWithoutConstructor() 创建模型，跳过了初始化钩子，
     * 所以需要在这里手动设置 $casts 属性，不能用 casts() 方法，因为 casts() 方法在 bootOrbital() 迁移时不会被调用。
     *
     * @see \Orbit\Concerns\Orbital::bootOrbital()
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::initializeHasAttributes()
     * @see \App\Models\Concerns\HasSchemalessAttributes::initializeHasSchemalessAttributes()
     */
    #[\Override]
    protected $casts = [
        'event_count' => 'integer',
        'extra_attributes' => SchemalessAttributes::class,
        'fan_count' => 'integer',
        'follow_count' => 'integer',
        'level' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * @noinspection OverrideMissingInspection
     */
    public static function schema(Blueprint $table): void
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
}
