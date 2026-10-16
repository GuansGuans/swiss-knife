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

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
    private const string USER_HOME_URL = 'https://music.163.com/#/user/home?id=%s';
    private ProcessAwareBrowser $processAwareBrowser;
    private Filesystem $filesystem;

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
                        File::json($this->filesystem->path('cookie.json'))
                    ))
                    ->await();
                $page->navigate(self::LOGIN_URL)->waitForNavigation();
            })
            ->map(function (int $userId) use ($page): array {
                /** @var \HeadlessChromium\Page $page */
                $page->navigate(\sprintf(self::USER_HOME_URL, $userId));
                $page->waitUntilContainsElement('#g_iframe');
                Sleep::sleep(3);

                $page->screenshot()->saveToFile($this->filesystem->path(\sprintf(
                    "$userId/home-%s.png",
                    now()->format('Y-m-d-H-i-s')
                )));

                $eventCount = $page->evaluate("document.querySelector('#g_iframe')?.contentDocument?.querySelector('#head-box #event_count')?.textContent?.trim() ?? null")->getReturnValue();
                $followCount = $page->evaluate("document.querySelector('#g_iframe')?.contentDocument?.querySelector('#head-box #follow_count')?.textContent?.trim() ?? null")->getReturnValue();
                $fanCount = $page->evaluate("document.querySelector('#g_iframe')?.contentDocument?.querySelector('#head-box #fan_count')?.textContent?.trim() ?? null")->getReturnValue();

                return [
                    'user_id' => $userId,
                    'event_count' => $eventCount,
                    'follow_count' => $followCount,
                    'fan_count' => $fanCount,
                ];
            })
            ->tap(static function (Collection $data): void {
                $data->dump();
            });
    }

    /**
     * @noinspection StaticInvocationViaThisInspection
     */
    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->option('user-id') or $this->input->setOption('user-id', config('services.net_ease_cloud_music.user_ids'));

        $this->processAwareBrowser = (new BrowserFactory)->createBrowser([
            'headless' => $headless = (!$this->output->isDebug() && !$this->option('xdebug')),
            'debugLogger' => $headless ? Log::channel('null') : $this->consoleLogger(),
            'windowSize' => [1920, 1080],
        ]);

        $this->filesystem = Storage::build(
            Config::collection('filesystems.disks.local')
                ->put('root', storage_path('app/net-ease-cloud-music'))
                ->all()
        );
    }

    #[\Override]
    protected function rules(): array
    {
        return [
            '--user-id' => 'list',
            '--user-id.*' => 'required|integer',
        ];
    }
}
