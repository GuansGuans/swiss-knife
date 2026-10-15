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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('queue')->index();
            $blueprint->longText('payload');
            $blueprint->unsignedSmallInteger('attempts');
            $blueprint->unsignedInteger('reserved_at')->nullable();
            $blueprint->unsignedInteger('available_at');
            $blueprint->unsignedInteger('created_at');
        });

        Schema::create('job_batches', static function (Blueprint $blueprint): void {
            $blueprint->string('id')->primary();
            $blueprint->string('name');
            $blueprint->integer('total_jobs');
            $blueprint->integer('pending_jobs');
            $blueprint->integer('failed_jobs');
            $blueprint->longText('failed_job_ids');
            $blueprint->mediumText('options')->nullable();
            $blueprint->integer('cancelled_at')->nullable();
            $blueprint->integer('created_at');
            $blueprint->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', static function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('uuid')->unique();
            $blueprint->string('connection');
            $blueprint->string('queue');
            $blueprint->longText('payload');
            $blueprint->longText('exception');
            $blueprint->timestamp('failed_at')->useCurrent();

            $blueprint->index(['connection', 'queue', 'failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
