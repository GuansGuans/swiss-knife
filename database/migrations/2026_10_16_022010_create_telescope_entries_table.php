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
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $builder = Schema::connection($this->getConnection());

        $builder->create('telescope_entries', static function (Blueprint $blueprint): void {
            $blueprint->bigIncrements('sequence');
            $blueprint->uuid('uuid');
            $blueprint->uuid('batch_id');
            $blueprint->string('family_hash')->nullable();
            $blueprint->boolean('should_display_on_index')->default(true);
            $blueprint->string('type', 20);
            $blueprint->longText('content');
            $blueprint->dateTime('created_at')->nullable();

            $blueprint->unique('uuid');
            $blueprint->index('batch_id');
            $blueprint->index('family_hash');
            $blueprint->index('created_at');
            $blueprint->index(['type', 'should_display_on_index']);
        });

        $builder->create('telescope_entries_tags', static function (Blueprint $blueprint): void {
            $blueprint->uuid('entry_uuid');
            $blueprint->string('tag');

            $blueprint->primary(['entry_uuid', 'tag']);
            $blueprint->index('tag');

            $blueprint->foreign('entry_uuid')
                ->references('uuid')
                ->on('telescope_entries')
                ->cascadeOnDelete();
        });

        $builder->create('telescope_monitoring', static function (Blueprint $blueprint): void {
            $blueprint->string('tag')->primary();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $builder = Schema::connection($this->getConnection());

        $builder->dropIfExists('telescope_entries_tags');
        $builder->dropIfExists('telescope_entries');
        $builder->dropIfExists('telescope_monitoring');
    }
};
