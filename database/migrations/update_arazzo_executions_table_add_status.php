<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('arazzo_executions', function (Blueprint $table) {
            $table->string('status')->default('running')->after('workflow_id');
            $table->timestamp('completed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('arazzo_executions', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};
