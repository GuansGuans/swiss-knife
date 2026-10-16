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

namespace App\Rules;

final class IntegerBooleanRule extends AbstractRule
{
    #[\Override]
    public function passes(string $attribute, mixed $value): bool
    {
        return \in_array($value, [0, 1], true);
    }
}
