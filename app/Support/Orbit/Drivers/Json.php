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

namespace App\Support\Orbit\Drivers;

use App\Console\Commands\NetEaseCloudMusicSnapshotCommand;

class Json extends \Orbit\Drivers\Json
{
    /**
     * @param array<string, mixed> $attributes
     */
    #[\Override]
    public function compile(array $attributes): string
    {
        return json_encode($attributes, NetEaseCloudMusicSnapshotCommand::PRETTY_JSON_OPTIONS);
    }
}
