<?php

/** @noinspection D */
/** @noinspection LaravelUnknownConfigKeyInspection */
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

namespace App\Console\Commands;

use App\Models\NetEaseCloudMusicUserHomeSnapshot;
use Guanguans\LaravelExceptionNotify\Facades\ExceptionNotify;
use Guanguans\Notify\Foundation\Client;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Page;
use HeadlessChromium\PageUtils\ResponseWaiter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Illuminate\Console\Attributes\Description('Command description')]
#[\Illuminate\Console\Attributes\Signature(
    <<<'SIGNATURE'
        net-ease-cloud-music
        {--user-id=* : The list of NetEase Cloud Music user id.}
        SIGNATURE
)]
final class NetEaseCloudMusicCommand extends AbstractCommand
{
    public const int PRETTY_JSON_OPTIONS =
        \JSON_PRETTY_PRINT |
        \JSON_UNESCAPED_LINE_TERMINATORS |
        \JSON_UNESCAPED_SLASHES |
        \JSON_UNESCAPED_UNICODE;
    private const int WAIT_MICROSECONDS = 3 * 1000 * 1000; // 3s

    // private const string LOGIN_URL = 'https://music.163.com/#/login';
    private const string USER_HOME_URL_TEMPLATE = 'https://music.163.com/#/user/home?id=%s';
    private ProcessAwareBrowser $processAwareBrowser;

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        if (isset($this->processAwareBrowser)) {
            $this->processAwareBrowser->close();
        }
    }

    /**
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\NoResponseAvailable
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     */
    public function handle(): void
    {
        collect($this->option('user-id'))
            ->tap(function () use (&$page): void {
                $this->components->task(
                    '1. Login',
                    function () use (&$page): void {
                        $page = $this->login();
                    }
                );
            })
            ->map(function (int $userId, int $index) use ($page): array {
                $this->components->task(
                    \sprintf('2-%s. Crawl user [%s] home snapshot', ++$index, $userId),
                    function () use (&$snapshot, $page, $userId): void {
                        $snapshot = $this->crawlUserHomeSnapshot($page, $userId);
                    }
                );

                return $snapshot;
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '3. Print crawled user home snapshots',
                    fn (): null => $this->printCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '4. Create crawled user home snapshots',
                    fn (): null => $this->createCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '5. Update crawled user home snapshots',
                    fn (): null => $this->updateCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '6. Optimize user home screenshots',
                    fn (): null => $this->optimizeUserHomeScreenshots($snapshots)
                );
            })
            ->tap(function (): void {
                $this->components->task(
                    '7. Commit user home snapshots and screenshots',
                    fn (): null => $this->commitUserHomeSnapshotsAndScreenshots()
                );
            });
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->option('user-id'))) {
            $input->setOption('user-id', config('services.net_ease_cloud_music.user_ids'));
        }

        if ($output->isVeryVerbose()) {
            collect(config('exception-notify.channels'))->each(static function (array $configuration, string $name): void {
                if ('notify' !== ($configuration['driver'] ?? $name)) {
                    return;
                }

                Config::set(
                    "exception-notify.channels.$name.client.extender",
                    static fn (Client $client): Client => $client->push(
                        Middleware::log(Log::channel(), new MessageFormatter(MessageFormatter::DEBUG), 'debug')
                    )
                );
            });
        }

        $this->processAwareBrowser = (new BrowserFactory)->createBrowser([
            'headless' => running_in_github_action() || !$output->isDebug(),
            // 'debugLogger' => $output->isDebug() ? $this->consoleLogger() : Log::channel('null'),
            'windowSize' => \PHP_OS_FAMILY === 'Darwin' ? [/* Mac */ 1920, 1080] : [/* Linux */ 1280, 1024],
        ]);
    }

    #[\Override]
    protected function rules(): array
    {
        return [
            'user-id' => 'list',
            'user-id.*' => 'required|integer|min:1|distinct',
        ];
    }

    /**
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\NoResponseAvailable
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     */
    private function login(): Page
    {
        $page = $this->processAwareBrowser->createPage();
        $cookiesFileOrJson = config('services.net_ease_cloud_music.cookies');
        $responseWaiter = $page->setCookies(new CookiesCollection(
            is_file($cookiesFileOrJson)
            ? File::json($cookiesFileOrJson)
            : json_decode($cookiesFileOrJson, true, flags: \JSON_THROW_ON_ERROR)
        ));
        \assert($responseWaiter instanceof ResponseWaiter);
        $responseWaiter->await();
        // $page->navigate(self::LOGIN_URL)->waitForNavigation();

        return $page;
    }

    /**
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\FilesystemException
     * @throws \HeadlessChromium\Exception\ScreenshotFailed
     *
     * @return array<string, scalar>
     */
    private function crawlUserHomeSnapshot(Page $page, int $userId): array
    {
        // $page->navigate(\sprintf(self::USER_HOME_URL_TEMPLATE, $userId))->waitForNavigation();
        $page->navigate(\sprintf(self::USER_HOME_URL_TEMPLATE, $userId));
        // $page->waitUntilContainsElement('#g_iframe');
        Sleep::usleep(self::WAIT_MICROSECONDS);

        $page->screenshot()->saveToFile(net_ease_cloud_music_path($screenshotPath = \sprintf(
            "images/user-id-$userId/home-%s.png",
            now()->format('YmdHis')
        )));

        return collect([
            'name' => "span[class^=\\'tit\\']",
            'level' => "span[class*=\\'u-lev\\']",
            'event_count' => '#event_count',
            'follow_count' => '#follow_count',
            'fan_count' => '#fan_count',
        ])->map(
            static fn (string $selector): ?string => $page
                ->evaluate("document.querySelector('#g_iframe').contentDocument.querySelector('#head-box $selector').textContent.trim()")
                ->getReturnValue()
        )->put('user_id', $userId)->put('screenshot_path', $screenshotPath)->all();
    }

    /**
     * @param Collection<int, array<string, scalar>> $snapshots
     */
    private function printCrawledUserHomeSnapshots(Collection $snapshots): void
    {
        $snapshots = $snapshots->map(
            static fn (array $snapshot): Collection => collect($snapshot)
                ->only($sortedKeys = [
                    /** @see SortListItemOfSameScalarTypeRector */
                    'user_id', 'level', 'event_count', 'follow_count', 'fan_count', 'name',
                ])
                ->sortKeysUsing(
                    static fn (
                        string $a,
                        string $b
                    ): int => (int) array_search($a, $sortedKeys, true) <=> (int) array_search($b, $sortedKeys, true)
                )
                ->mapWithKeys(static fn (mixed $value, string $key): array => [Str::headline($key) => $value])
        );

        $this->newLine();
        $this->table($snapshots->firstOrFail()->keys()->all(), $snapshots);
    }

    /**
     * @param Collection<int, array<string, scalar>> $snapshots
     */
    private function createCrawledUserHomeSnapshots(Collection $snapshots): void
    {
        $this->newLine();
        $this->info(
            $snapshots
                ->map(
                    static fn (array $snapshot): NetEaseCloudMusicUserHomeSnapshot => NetEaseCloudMusicUserHomeSnapshot::query()
                        ->create($snapshot)
                )
                ->toPrettyJson(self::PRETTY_JSON_OPTIONS)
        );
    }

    /**
     * @param Collection<int, array<string, scalar>> $snapshots
     */
    private function updateCrawledUserHomeSnapshots(Collection $snapshots): void
    {
        $snapshots
            ->pluck('user_id')
            ->reduce(
                function (Collection $updatedSnapshots, int $userId): Collection {
                    $latestTwoSnapshots = NetEaseCloudMusicUserHomeSnapshot::query()
                        ->where('user_id', $userId)
                        ->latest('id')
                        ->limit($twoLimit = 2)
                        ->get();

                    if ($latestTwoSnapshots->count() !== $twoLimit) {
                        return $updatedSnapshots;
                    }

                    $diffingAttributes = [
                        /** @see SortListItemOfSameScalarTypeRector */
                        'name', 'level', 'event_count', 'follow_count', 'fan_count',
                    ];
                    $fakedAttributes = [
                        'name' => \sprintf('debug-fake-name:%s', fake()->name()),
                        'level' => fake()->numberBetween(1, 10),
                        'event_count' => fake()->numberBetween(1, 100),
                        'follow_count' => fake()->numberBetween(1, 100),
                        'fan_count' => fake()->numberBetween(1, 100),
                    ];
                    $diffedAttributes = collect($latestTwoSnapshots->firstOrFail()->only($diffingAttributes))
                        ->when(
                            $this->output->isDebug(),
                            static fn (Collection $attributes) => $attributes->merge($fakedAttributes)
                        )
                        ->diffAssoc($latestTwoSnapshots->last()?->only($diffingAttributes));

                    if ($diffedAttributes->isEmpty()) {
                        return $updatedSnapshots;
                    }

                    $latestTwoSnapshots->each(function (NetEaseCloudMusicUserHomeSnapshot $netEaseCloudMusicUserHomeSnapshot) use (
                        $latestTwoSnapshots,
                        $fakedAttributes,
                        $diffedAttributes
                    ): void {
                        $netEaseCloudMusicUserHomeSnapshot->extra_attributes->set($latestTwoSnapshots->map(
                            fn (
                                NetEaseCloudMusicUserHomeSnapshot $netEaseCloudMusicUserHomeSnapshot
                            ): array => $netEaseCloudMusicUserHomeSnapshot->only('id') + (
                                $this->output->isDebug() && $netEaseCloudMusicUserHomeSnapshot->is($latestTwoSnapshots->firstOrFail())
                                    ? $fakedAttributes
                                    : $netEaseCloudMusicUserHomeSnapshot->only($diffedAttributes->keys()->all())
                            )
                        ));
                        $netEaseCloudMusicUserHomeSnapshot->update();
                    });

                    return $updatedSnapshots->push($latestTwoSnapshots->firstOrFail());
                },
                collect()
            )
            ?->whenNotEmpty(function (Collection $updatedSnapshots): void {
                $title = 'Netease cloud music user home snapshots changed';
                $content = $updatedSnapshots->toPrettyJson(self::PRETTY_JSON_OPTIONS);

                $this->newLine();
                $this->info($content);

                $originalConfiguration = collect([
                    /** @see SortListItemOfSameScalarTypeRector */
                    'exception-notify.title',
                    'exception-notify.channels.ntfy.message.options.topic',
                ])->mapWithKeys(static fn (string $key): array => [$key => config($key)])->all();

                try {
                    config([
                        'exception-notify.title' => $title,
                        'exception-notify.channels.ntfy.message.options.topic' => 'swiss-knife-net-ease-cloud-music',
                    ]);
                    ExceptionNotify::reportContent("\n```\n$content\n```\n");
                } catch (\Throwable $throwable) {
                    config($originalConfiguration);

                    throw $throwable;
                } finally {
                    config($originalConfiguration);
                }
            });
    }

    /**
     * @param Collection<int, array<string, scalar>> $snapshots
     */
    private function optimizeUserHomeScreenshots(Collection $snapshots): void
    {
        $this->call('optimize:image', [
            '--path' => $snapshots->pluck('screenshot_path')->map('net_ease_cloud_music_path')->all(),
            '--ansi' => true,
            '--verbose' => true,
        ]);
    }

    private function commitUserHomeSnapshotsAndScreenshots(): void
    {
        if (!running_in_github_action()) {
            return;
        }

        $currentBranch = Process::run('git branch --show-current')->throw()->output();

        if (!str($currentBranch)->trim()->is('main')) {
            return;
        }

        Process::run(implode(' && ', [
            'git config user.name "guanguans"',
            'git config user.email "ityaozm@gmail.com"',
            // 'git config pull.rebase true',
            // 'git config rebase.autoStash true',
            // 'git pull',
            'git pull --rebase --autostash',
            \sprintf('git add --force %s', net_ease_cloud_music_path()),
            'git commit --message "chore: update net ease cloud music storage" --no-verify --signoff',
            'git push',
        ]))->throw();
    }
}
