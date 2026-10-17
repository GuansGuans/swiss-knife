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

    #[\Override]
    protected function casts(): array
    {
        return [
            'event_count' => 'integer',
            'fan_count' => 'integer',
            'follow_count' => 'integer',
            'level' => 'integer',
            'user_id' => 'integer',
        ];
    }
}
