<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('arazzo_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('definition_id')->index();
            $table->foreign('definition_id')->references('id')->on('arazzo_definitions')->cascadeOnDelete();
            $table->string('workflow_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_executions');
    }
};
