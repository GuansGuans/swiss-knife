<?php

/** @noinspection LaravelUnknownConfigKeyInspection */
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
use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
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
    private const string LOGIN_URL = 'https://music.163.com/#/login';
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
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\CommunicationException\CannotReadResponse
     * @throws \HeadlessChromium\Exception\CommunicationException\InvalidResponse
     * @throws \HeadlessChromium\Exception\CommunicationException\ResponseHasError
     * @throws \HeadlessChromium\Exception\EvaluationFailed
     * @throws \HeadlessChromium\Exception\FilesystemException
     * @throws \HeadlessChromium\Exception\JavascriptException
     * @throws \HeadlessChromium\Exception\NavigationExpired
     * @throws \HeadlessChromium\Exception\NoResponseAvailable
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     * @throws \HeadlessChromium\Exception\ScreenshotFailed
     */
    public function handle(): void
    {
        collect($this->option('user-id'))
            ->tap(function () use (&$page): void {
                $page = $this->processAwareBrowser->createPage();
                $page
                    ->setCookies(array_map(
                        static fn (array $cookie): Cookie => new Cookie($cookie),
                        File::json(net_ease_cloud_music_path('cookie.json'))
                    ))
                    ->await();
                $page->navigate(self::LOGIN_URL)->waitForNavigation();
            })
            ->map(static function (int $userId) use ($page): array {
                /** @var \HeadlessChromium\Page $page */
                $page->navigate(\sprintf(self::USER_HOME_URL_TEMPLATE, $userId));
                $page->waitUntilContainsElement('#g_iframe');
                Sleep::sleep(3);

                $page->screenshot()->saveToFile(net_ease_cloud_music_path($screenshotPath = \sprintf(
                    "user-id-$userId/home-%s.png",
                    now()->format('Y-m-d-H-i-s')
                )));

                return collect([
                    'name' => "span[class^=\\'tit\\']",
                    'level' => "span[class*=\\'u-lev\\']",
                    'event_count' => '#event_count',
                    'follow_count' => '#follow_count',
                    'fan_count' => '#fan_count',
                ])
                    ->map(
                        static fn (string $selector): ?string => $page
                            ->evaluate("document.querySelector('#g_iframe')?.contentDocument?.querySelector('#head-box $selector')?.textContent?.trim() ?? null")
                            ->getReturnValue()
                    )
                    ->put('user_id', $userId)
                    ->put('screenshot_path', $screenshotPath)
                    ->sortKeysUsing(
                        static fn (string $a, string $b): int => (int) array_search(
                            $a,
                            $sorted = [
                                /** @see \Guanguans\RectorRules\Rector\Array_\SortListItemOfSameScalarTypeRector */
                                'user_id',
                                'level',
                                'event_count',
                                'fan_count',
                                'follow_count',
                                'screenshot_path',
                                'name',
                            ],
                            true
                        ) <=> (int) array_search($b, $sorted, true)
                    )
                    ->all();
            })
            ->tap(function (Collection $data): void {
                $data
                    ->tap(function (Collection $data): void {
                        $this->table(
                            collect($data->first())->keys()->map(static fn (string $key) => str($key)->headline())->all(),
                            $data
                        );
                    })
                    ->tap(static function (Collection $data): void {
                        $data->each(static function (array $item): void {
                            NetEaseCloudMusicUserHomeLog::query()->create($item);
                        });
                    })
                    ->tap(static function (Collection $data): void {
                        NetEaseCloudMusicUserHomeLog::query()
                            ->latest()
                            ->limit($data->count())
                            ->get()
                            ->map(static fn (NetEaseCloudMusicUserHomeLog $netEaseCloudMusicUserHomeLog): array => $netEaseCloudMusicUserHomeLog->toArray())
                            ->dump();

                        // notice.
                        // optimize image
                    });
            });
    }

    /**
     * @throws \Throwable
     *
     * @noinspection StaticInvocationViaThisInspection
     */
    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->option('user-id') or $this->input->setOption('user-id', config('services.net_ease_cloud_music.user_ids'));
        throw_if(
            collect($this->option('user-id'))->duplicates()->isNotEmpty(),
            \InvalidArgumentException::class,
            'The --user-id option must be unique.'
        );

        $this->processAwareBrowser = (new BrowserFactory)->createBrowser([
            'headless' => $headless = (!$this->output->isDebug() && !$this->option('xdebug')),
            'debugLogger' => $headless ? Log::channel('null') : $this->consoleLogger(),
            'windowSize' => [
                /** @see \Guanguans\RectorRules\Rector\Array_\SortListItemOfSameScalarTypeRector */
                1920,
                1080,
            ],
        ]);
    }

    #[\Override]
    protected function rules(): array
    {
        return [
            'user-id' => 'list',
            'user-id.*' => 'required|integer',
        ];
    }
}
