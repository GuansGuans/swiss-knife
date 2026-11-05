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

namespace App\Console\Commands;

use App\Models\NetEaseCloudMusicUserHomeSnapshot;
use Illuminate\Console\Prohibitable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Orbit\Commands\CacheCommand;
use Orbit\Commands\ClearCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Illuminate\Console\Attributes\Description('Fresh the NetEase CloudMusic storage directory')]
#[\Illuminate\Console\Attributes\Signature('net-ease-cloud-music:fresh {--force : Force the operation to run when in production}')]
final class NetEaseCloudMusicFreshCommand extends AbstractCommand
{
    use Prohibitable;

    /**
     * @see \Illuminate\Database\Console\Migrations\FreshCommand
     * @see \Illuminate\Database\Console\Migrations\RefreshCommand
     * @see \Illuminate\Database\Console\Migrations\ResetCommand
     * @see \Orbit\Commands\ClearCommand
     * @see \Orbit\Commands\FreshCommand
     * @see \Illuminate\Support\Str::flushCache()
     */
    public function handle(): int
    {
        if ($this->isProhibited() || !$this->confirmToProceed()) {
            return self::FAILURE;
        }

        collect([config('orbit.paths.content'), net_ease_cloud_music_path('images/')])
            ->filter(static fn (string $directory): bool => File::isDirectory($directory))
            ->when($this->output->isVeryVerbose(), function (Collection $directories): void {
                $this->components->bulletList(
                    $directories->flatMap(static fn (string $directory): array => File::allFiles($directory))
                );
            })
            ->each(static function (string $directory): void {
                File::deleteDirectory($directory);
            })
            ->tap(function (): void {
                $this->call(ClearCommand::class);
                // $this->call(CacheCommand::class);
            });

        $this->components->info('Freshened.');

        return self::SUCCESS;
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        /**
         * @see NetEaseCloudMusicUserHomeSnapshot::booting()
         */
        NetEaseCloudMusicUserHomeSnapshot::query();
    }

    #[\Override]
    protected function rules(): array
    {
        return [
            'force' => 'sometimes|boolean',
        ];
    }
}
