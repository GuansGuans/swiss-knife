<?php

/** @noinspection AnonymousFunctionStaticInspection */
/** @noinspection ClassConstantCanBeUsedInspection */
/** @noinspection ClassnameLiteralInspection */
/** @noinspection NullPointerExceptionInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection StaticClosureCanBeUsedInspection */
declare(strict_types=1);

/**
 * Copyright (c) 2026 guanguans<ityaozm@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/GuansGuans/swiss-knife
 */

use App\Console\Commands\AbstractCommand;
use App\Listeners\ContextSubscriber;
use App\Models\AbstractModel;
use App\Support\ComposerScripts;

arch()
    ->group(__DIR__, __FILE__)
    ->skip()
    ->preset()->laravel()->ignoring([
        AbstractCommand::class,
        AbstractModel::class,
        ComposerScripts::class,
        ContextSubscriber::class,
    ]);

arch()
    ->group(__DIR__, __FILE__)
    // ->skip()
    ->expect('App\Console\Commands')
    ->classes()
    ->toBeClasses()
    ->toExtend('App\Console\Commands\AbstractCommand')
    ->ignoring([
        'App\Console\Commands\Concerns',
    ]);

arch()
    ->group(__DIR__, __FILE__)
    // ->skip()
    ->expect('App\Models')
    ->classes()
    ->toBeClasses()
    ->toExtend('App\Models\AbstractModel')
    ->ignoring([
        'App\Models\Concerns',
        'App\Models\Pivots',
        'App\Models\Scopes',
        'App\Models\User',
    ]);
