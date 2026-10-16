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

namespace App\Support\Trait;

/**
 * @see \DragonCode\Support\Concerns\Makeable
 */
trait MakeStaticable
{
    /**
     * @noinspection PhpMethodParametersCountMismatchInspection
     * @noinspection OverrideMissingInspection
     */
    public static function make(mixed ...$parameters): static
    {
        return new static(...$parameters);
    }
}
