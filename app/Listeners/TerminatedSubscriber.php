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

namespace App\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\Process;

final class TerminatedSubscriber
{
    /**
     * @see \Illuminate\Foundation\Application::handleCommand()
     * @see \Illuminate\Foundation\Application::handleRequest()
     *
     * @return array<class-string, callable(object $event): void>
     */
    public function subscribe(): array
    {
        return [
            CommandFinished::class => $listeners = static function (): void {
                if (!running_in_github_action()) {
                    return;
                }

                /**
                 * @see https://github.com/guanguans/music-dl/blob/master/.github/workflows/publish-phar.yml#L70-L75
                 */
                Process::command(\sprintf(
                    'git add %s',
                    str(net_ease_cloud_music_path())->chopStart(base_path(\DIRECTORY_SEPARATOR))
                ))
                    ->path(base_path())
                    ->run()
                    ->throw();
                Process::command('git commit -m "chore: update net ease cloud music storage"')
                    ->path(base_path())
                    ->run()
                    ->throw();
                Process::command('git push')->path(base_path())->run()->throw();
            },
            ScheduledTaskFinished::class => $listeners,
        ];
    }
}
