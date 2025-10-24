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
        Schema::create('dedupler_unique_files', function (Blueprint $table) {
            $table->comment('Table for storing unique deduplicated files.');
            $table->char('id', 40)
                ->primary()
                ->comment('SHA1');
            $table->char('sha1_hash', 40)
                ->comment('SHA1');
            $table->char('md5_hash', 32)
                ->nullable()
                ->comment('MD5');
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type', 130)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('status', 20)
                ->nullable()
                ->default('pending')
                ->index('uploaded_files_status_index');
            $table->string('disk')
                ->nullable()
                ->default('public');
            $table->string('original_name')->nullable();
            $table->timestamp('created_at')->nullable()
                ->index('uploaded_files_created_at_index');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dedupler_unique_files');
    }
};
