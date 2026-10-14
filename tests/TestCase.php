<?php

/** @noinspection AnonymousFunctionStaticInspection */
/** @noinspection NullPointerExceptionInspection */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpVoidFunctionResultUsedInspection */
/** @noinspection StaticClosureCanBeUsedInspection */
/** @noinspection PhpAttributeCanBeAddedToOverriddenMemberInspection */
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

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;

abstract class TestCase extends BaseTestCase
{
    // use DatabaseMigrations;
    // use DatabaseTruncation;
    // use LazilyRefreshDatabase;
    // use RefreshDatabase;
    // use WithCachedConfig;
    // use WithCachedRoutes;
    use RefreshDatabase;

    // /** 在测试前指定要运行的 seeder. */
    // protected string $seeder = OrderStatusSeeder::class;

    // /** 指示默认 seeder 是否应在每次测试之前运行. */
    // protected bool $seed = false;
}
