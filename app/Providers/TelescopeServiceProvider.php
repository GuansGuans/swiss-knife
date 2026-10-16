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

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

final class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(static fn (IncomingEntry $incomingEntry): bool => $isLocal
                   || $incomingEntry->isReportableException()
                   || $incomingEntry->isFailedRequest()
                   || $incomingEntry->isFailedJob()
                   || $incomingEntry->isScheduledTask()
                   || $incomingEntry->hasMonitoredTag());
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', static fn (User $user): bool => \in_array(
            $user->email,
            [
                'example1@example.com',
                'example2@example.com',
            ],
            true
        ));
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    private function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }
}
