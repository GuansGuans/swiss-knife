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

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Context;

final class ContextSubscriber
{
    /**
     * @see \Symfony\Component\Console\DataCollector\CommandDataCollector
     * @see \Symfony\Component\Console\Debug\CliRequest
     * @see \Symfony\Component\Console\Command\TraceableCommand
     *
     * @return array<class-string, callable(object $event): void>
     */
    public function subscribe(): array
    {
        return [
            RouteMatched::class => static function (RouteMatched $routeMatched): void {
                Context::add([
                    'action' => $routeMatched->route->getActionName(),
                ]);
            },
            CommandStarting::class => static function (CommandStarting $commandStarting): void {
                Context::add([
                    'command' => $commandStarting->command ?? $commandStarting->input->getArguments()['command'] ?? 'default',
                ]);
            },
            ScheduledTaskStarting::class => static function (ScheduledTaskStarting $scheduledTaskStarting): void {
                /** @noinspection NestedTernaryOperatorInspection */
                Context::add([
                    'command' => ($scheduledTaskStarting->task->command ?: $scheduledTaskStarting->task->description) ?: 'Closure',
                ]);
            },
        ];
    }
}
