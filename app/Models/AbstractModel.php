<?php

/** @noinspection LaravelUnknownEloquentFactoryInspection */
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

use App\Models\Concerns\HasSchemalessAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @see https://github.com/kirkbushell/eloquence
 * @see https://github.com/LaravelDaily/laravel-tips
 * @see https://github.com/OussamaMater/Laravel-Tips
 */
abstract class AbstractModel extends Model
{
    // use Watson\Validating\ValidatingTrait;
    // use HasSchemalessAttributes;
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @see self::__callStatic()
     */
    final public static function getTableName(): string
    {
        return (new static)->getTable();
    }

    /**
     * @return array<string, mixed>
     */
    final public function toDotArray(): array
    {
        return collect($this->toArray())->dot()->all();
    }
}
