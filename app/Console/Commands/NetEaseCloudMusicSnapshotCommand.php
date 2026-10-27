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
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Illuminate\Console\Attributes\Description('Snapshot the NetEase CloudMusic user home')]
#[\Illuminate\Console\Attributes\Signature(
    <<<'SIGNATURE'
        net-ease-cloud-music:snapshot
        {--user-id=* : The list of NetEase CloudMusic user id.}
        SIGNATURE
)]
final class NetEaseCloudMusicSnapshotCommand extends AbstractCommand
{
    public const int PRETTY_JSON_OPTIONS =
        \JSON_PRETTY_PRINT |
        \JSON_UNESCAPED_LINE_TERMINATORS |
        \JSON_UNESCAPED_SLASHES |
        \JSON_UNESCAPED_UNICODE;
    private const int RETRY_TIMES = 3;
    private const int WAIT_DEFAULT_LOAD_MILLISECONDS = 3 * 1000;
    private const int WAIT_FIRST_LOAD_MILLISECONDS = 5 * 1000;

    // private const string LOGIN_URL = 'https://music.163.com/#/login';
    private const string USER_HOME_URL_TEMPLATE = 'https://music.163.com/#/user/home?id=%s';
    private ProcessAwareBrowser $processAwareBrowser;

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        $this->closeProcessAwareBrowser();
    }

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        collect($this->option('user-id'))
            ->tap(function (): void {
                $this->newLine();
            })
            ->tap(function (): void {
                $this->components->task(
                    '<comment>1. Create process aware browser</comment>',
                    function (): void {
                        retry(self::RETRY_TIMES, function (int $attempts): void {
                            $this->warnRetry($attempts, self::RETRY_TIMES);
                            $this->createProcessAwareBrowser();
                        });
                    }
                );
            })
            ->tap(function () use (&$page): void {
                $this->components->task(
                    '<comment>2. Login NetEase CloudMusic</comment>',
                    function () use (&$page): void {
                        $page = $this->loginNetEaseCloudMusic();
                    }
                );
            })
            ->map(function (int $userId, int $index) use ($page): array {
                $this->components->task(
                    \sprintf('<comment>3-%s. Crawl user(%s) home snapshot</comment>', ++$index, $userId),
                    function () use (&$snapshot, $page, $userId): void {
                        $snapshot = retry(self::RETRY_TIMES, function (int $attempts) use ($page, $userId): array {
                            $this->warnRetry($attempts, self::RETRY_TIMES);

                            return $this->crawlUserHomeSnapshot($page, $userId);
                        });
                    }
                );

                return $snapshot;
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '<comment>4. Print crawled user home snapshots</comment>',
                    fn (): null => $this->printCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '<comment>5. Create crawled user home snapshots</comment>',
                    fn (): null => $this->createCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '<comment>6. Update crawled user home snapshots</comment>',
                    fn (): null => $this->updateCrawledUserHomeSnapshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '<comment>7. Optimize user home screenshots</comment>',
                    fn (): null => $this->optimizeUserHomeScreenshots($snapshots)
                );
            })
            ->tap(function (Collection $snapshots): void {
                $this->components->task(
                    '<comment>8. Commit user home snapshots and screenshots</comment>',
                    fn (): null => $this->commitUserHomeSnapshotsAndScreenshots($snapshots)
                );
            })
            ->tap(function (): void {
                $this->components->task(
                    '<comment>9. Close process aware browser</comment>',
                    fn (): null => $this->closeProcessAwareBrowser()
                );
            })
            ->tap(function (): void {
                $this->components->success((new ResourceUsageFormatter)->resourceUsageSinceStartOfRequest());
            });
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if (empty($this->option('user-id'))) {
            $this->input->setOption('user-id', config('services.net_ease_cloud_music.user_ids'));
        }

        if ($this->output->isVeryVerbose()) {
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

        $this->validator()->validate();
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
     * @noinspection StaticInvocationViaThisInspection
     */
    private function createProcessAwareBrowser(): void
    {
        $this->processAwareBrowser ??= (new BrowserFactory)->createBrowser([
            'headless' => running_in_github_action() || !$this->output->isVeryVerbose(),
            'debugLogger' => $this->consoleLogger(),
            'disableNotifications' => true,
            'windowSize' => \PHP_OS_FAMILY === 'Darwin' ? [/* Mac */ 1920, 1080] : [/* Linux */ 1280, 1024],
            'excludedSwitches' => [
                '--disable-features=ChromeWhatsNewUI',
            ],
            'customFlags' => [
                '--disable-component-extensions-with-background-pages',
                '--disable-component-update',
                '--disable-default-apps',
                '--disable-extensions',
                '--disable-features=ChromeWhatsNewUI,MediaRouter',
            ],
        ]);
    }

    /**
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\NoResponseAvailable
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     */
    private function loginNetEaseCloudMusic(): Page
    {
        $page = $this->processAwareBrowser->createPage();
        $responseWaiter = $page->setCookies(new CookiesCollection(json_decode(
            config('services.net_ease_cloud_music.cookies'),
            true,
            flags: \JSON_THROW_ON_ERROR
        )));
        \assert($responseWaiter instanceof ResponseWaiter);
        $responseWaiter->await();
        // $page->navigate(self::LOGIN_URL)->waitForNavigation();

        return $page;
    }

    /**
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\EvaluationFailed
     * @throws \HeadlessChromium\Exception\FilesystemException
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     * @throws \HeadlessChromium\Exception\ScreenshotFailed
     *
     * @return array<string, scalar>
     */
    private function crawlUserHomeSnapshot(Page $page, int $userId): array
    {
        // $page->navigate(\sprintf(self::USER_HOME_URL_TEMPLATE, $userId))->waitForNavigation();
        $page->navigate(\sprintf(self::USER_HOME_URL_TEMPLATE, $userId));
        // $page->waitUntilContainsElement('#g_iframe');
        array_first($this->option('user-id')) === $userId
            ? $this->waitLoad(self::WAIT_FIRST_LOAD_MILLISECONDS)
            : $this->waitLoad();

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
                $this->newLine();
                $this->info($content = $updatedSnapshots->toPrettyJson(self::PRETTY_JSON_OPTIONS));

                $originalConfiguration = collect([
                    /** @see SortListItemOfSameScalarTypeRector */
                    'exception-notify.title',
                    'exception-notify.channels.ntfy.message.options.topic',
                ])->mapWithKeys(static fn (string $key): array => [$key => config($key)])->all();

                try {
                    config([
                        'exception-notify.title' => 'NetEase CloudMusic user home snapshots changed',
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
        $this->{$this->output->isVeryVerbose() ? 'call' : 'callSilent'}('optimize:image', [
            '--path' => $snapshots->pluck('screenshot_path')->map('net_ease_cloud_music_path')->all(),
            '--ansi' => true,
            // '--silent' => true,
            // '--quiet' => true,
            '--verbose' => true,
        ]);
    }

    /**
     * @param Collection<int, array<string, scalar>> $snapshots
     *
     * @noinspection StaticInvocationViaThisInspection
     * @noinspection PhpVoidFunctionResultUsedInspection
     */
    private function commitUserHomeSnapshotsAndScreenshots(Collection $snapshots): void
    {
        if (!running_in_github_action()) {
            return;
        }

        // $currentBranch = Process::run('git branch --show-current')->throw()->output();
        $output = $this->output->isVeryVerbose()
            ? tap(new OutputStyle($this->input, clone $this->output->getOutput()))->setVerbosity(OutputInterface::VERBOSITY_DEBUG)
            : null;
        $this->output->isVeryVerbose() and $this->newLine();
        $currentBranch = $this->processHelperMustRun('git branch --show-current', output: $output)->getOutput();

        if (!str($currentBranch)->trim()->is('main')) {
            return;
        }

        // Process::run(implode(' && ', [
        //     'git config user.name "guanguans"',
        //     'git config user.email "ityaozm@gmail.com"',
        //     'git pull --rebase --autostash',
        //     \sprintf(
        //         'git add --force %s',
        //         $snapshots
        //             ->pluck('screenshot_path')->map('net_ease_cloud_music_path')
        //             ->push(net_ease_cloud_music_path('database/'))
        //             ->implode(' ')
        //     ),
        //     'git status',
        //     'git commit --message "chore: update NetEase CloudMusic storage" --no-verify --signoff',
        //     'git push',
        // ]))->throw();
        $this->output->isVeryVerbose() and $this->newLine();
        $this->processHelperMustRun(implode(" &&\n", [
            'git config user.name "guanguans"',
            'git config user.email "ityaozm@gmail.com"',
            'git pull --rebase --autostash',
            \sprintf(
                'git add --force %s',
                $snapshots
                    ->pluck('screenshot_path')->map('net_ease_cloud_music_path')
                    ->push(net_ease_cloud_music_path('database/'))
                    ->implode(' ')
            ),
            'git status',
            'git diff --cached',
            'git commit --message "chore: update NetEase CloudMusic storage" --no-verify --signoff',
            'git push',
        ]), output: $output);
    }

    /**
     * @throws \Exception
     */
    private function closeProcessAwareBrowser(): void
    {
        if (isset($this->processAwareBrowser)) {
            $this->processAwareBrowser->close();
            // unset($this->processAwareBrowser);
        }
    }

    private function warnRetry(int $attempts, int $times): void
    {
        1 < $attempts and $this->components->warn(\sprintf(
            "Attempt %d/$times failed; retrying attempt $attempts/$times...",
            $attempts - 1,
        ));
    }

    private function waitLoad(int $milliseconds = self::WAIT_DEFAULT_LOAD_MILLISECONDS): void
    {
        Sleep::usleep($milliseconds * 1000);
    }
}
