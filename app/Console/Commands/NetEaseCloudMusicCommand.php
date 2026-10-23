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

use App\Models\NetEaseCloudMusicUserHomeLog;
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
     * @throws \Exception
     */
    public function handle(): void
    {
        collect($this->option('user-id'))
            ->tap(function () use (&$page): void {
                $page = $this->processAwareBrowser->createPage();
                $responseWaiter = $page->setCookies(new CookiesCollection($this->cookies()));
                \assert($responseWaiter instanceof ResponseWaiter);
                $responseWaiter->await();
                // $page->navigate(self::LOGIN_URL)->waitForNavigation();
            })
            ->map(static function (int $userId) use ($page): array {
                \assert($page instanceof Page);
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
            })
            ->tap(function (Collection $data): void {
                $data = $data->map(
                    static fn (array $attributes): Collection => collect($attributes)
                        ->only($sortedAttributes = [
                            /** @see SortListItemOfSameScalarTypeRector */
                            'user_id', 'level', 'event_count', 'follow_count', 'fan_count', 'name',
                        ])
                        ->sortKeysUsing(
                            static fn (
                                string $a,
                                string $b
                            ): int => (int) array_search($a, $sortedAttributes, true) <=> (int) array_search($b, $sortedAttributes, true)
                        )
                        ->mapWithKeys(static fn (mixed $value, string $key): array => [Str::headline($key) => $value])
                );

                $this->output->section('NetEase Cloud Music user home logs crawled.');
                $this->table($data->firstOrFail()->keys()->all(), $data);
            })
            // ->dd()
            ->tap(function (Collection $data): void {
                $this->output->section('NetEase Cloud Music user home logs created.');
                $this->info(
                    $data
                        ->map(
                            static fn (array $attributes): NetEaseCloudMusicUserHomeLog => NetEaseCloudMusicUserHomeLog::query()
                                ->create($attributes)
                        )
                        ->toPrettyJson(self::PRETTY_JSON_OPTIONS)
                );
            })
            ->tap(function (Collection $data): void {
                $data
                    ->pluck('user_id')
                    ->reduce(
                        function (Collection $carry, int $userId): Collection {
                            $logs = NetEaseCloudMusicUserHomeLog::query()->where('user_id', $userId)->latest('id')->limit($limit = 2)->get();

                            if ($logs->count() !== $limit) {
                                return $carry;
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
                            $diffedAttributes = collect($logs->firstOrFail()->only($diffingAttributes))
                                ->when($this->output->isDebug(), static fn (Collection $attributes) => $attributes->merge($fakedAttributes))
                                ->diffAssoc($logs->last()?->only($diffingAttributes));

                            if ($diffedAttributes->isEmpty()) {
                                return $carry;
                            }

                            $logs->each(function (NetEaseCloudMusicUserHomeLog $netEaseCloudMusicUserHomeLog) use (
                                $logs,
                                $fakedAttributes,
                                $diffedAttributes
                            ): void {
                                $netEaseCloudMusicUserHomeLog->extra_attributes->set($logs->map(
                                    fn (NetEaseCloudMusicUserHomeLog $netEaseCloudMusicUserHomeLog): array => $netEaseCloudMusicUserHomeLog
                                        ->only('id') + (
                                            $this->output->isDebug() && $netEaseCloudMusicUserHomeLog->is($logs->firstOrFail())
                                            ? $fakedAttributes
                                            : $netEaseCloudMusicUserHomeLog->only($diffedAttributes->keys()->all())
                                        )
                                ));
                                $netEaseCloudMusicUserHomeLog->update();
                            });

                            return $carry->push($logs->firstOrFail());
                        },
                        collect()
                    )
                    ?->whenNotEmpty(function (Collection $logs): void {
                        $title = 'NetEase Cloud Music user home logs changed.';
                        $content = $logs->toPrettyJson(self::PRETTY_JSON_OPTIONS);

                        $this->output->section($title);
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
            })
            ->tap(function (Collection $data): void {
                $this->call('optimize:image', [
                    '--path' => $data->pluck('screenshot_path')->map('net_ease_cloud_music_path')->all(),
                    '--ansi' => true,
                    '--verbose' => true,
                ]);
            })
            ->tap(static function (): void {
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
            });
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->option('user-id') or $this->input->setOption('user-id', config('services.net_ease_cloud_music.user_ids'));

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

        $this->processAwareBrowser = (new BrowserFactory)->createBrowser([
            'headless' => running_in_github_action() || !$this->output->isDebug(),
            // 'debugLogger' => $this->output->isDebug() ? $this->consoleLogger() : Log::channel('null'),
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \JsonException
     *
     * @return list<array<string, mixed>>
     */
    private function cookies(): array
    {
        $cookiesFileOrJson = config('services.net_ease_cloud_music.cookies');

        return is_file($cookiesFileOrJson)
            ? File::json($cookiesFileOrJson)
            : json_decode($cookiesFileOrJson, true, flags: \JSON_THROW_ON_ERROR);
    }
}
