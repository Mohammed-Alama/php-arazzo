<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arazzo_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('document_identity');
            $table->string('content_hash', 64);
            $table->json('raw_document');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['document_identity', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_definitions');
    }
};
