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
            ->create('dedupler_deduplicatables', function (Blueprint $table) {
                $table->comment('Table for morph relations with unique files');
                $table->bigIncrements('id');
                $table->string('sha1_hash', 40)
                    ->index('dedupler_uploaded_files_hash_unique')
                    ->comment('SHA1 file_hash (dedupler_unique_files.id)');
                $table->string('deduplable_type');
                $table->char('deduplable_id', 36);
                $table->char('status', 20)
                    ->nullable()
                    ->default('pending')
                    ->index('dedupler_uploaded_deduplicates_files_status_index');
                $table->string('original_name')->nullable();
                $table->timestamp('created_at')->nullable()
                    ->index('dedupler_deduplicated_uploaded_files_created_at_index');
                $table->timestamp('updated_at')->nullable();
                $table->index(
                    ['deduplable_type', 'deduplable_id'],
                    'dedupler_uploaded_files_uploadable_type_uploadable_id_index'
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('dedupler.db_connection'))
            ->dropIfExists('dedupler_deduplicatables');
    }
};
