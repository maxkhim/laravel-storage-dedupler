<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(config('dedupler.db_connection'))
            ->create('dedupler_legacy_file_migrations', function (Blueprint $table) {
                $table->integer('id', true);
                $table->string('original_path');
                $table->string('original_filename');
                $table->char('sha1_hash', 40)->index('idx_dedupler_file_hash');
                $table->bigInteger('file_size');
                $table->string('mime_type', 127)->nullable();
                $table->char('status', 20)->nullable()->default('pending')
                    ->index('idx_dedupler_status');
                $table->tinyInteger('has_duplicates')
                    ->default(0)
                    ->index('idx_dedupler_legacy_file_migrations_has_duplicates');
                $table->timestamp('migrated_at')->nullable();
                $table->text('error_message')->nullable();
                $table->char('migration_strategy', 20)->nullable()->default('link');
                $table->timestamp('file_modificated_at')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrent();

                $table->index(
                    ['original_path', 'original_filename'],
                    'idx_dedupler_legacy_file_migrations_original_path'
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('dedupler.db_connection'))
            ->dropIfExists('dedupler_legacy_file_migrations');
    }
};
