<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->createPartitionedTable();

            return;
        }

        Schema::create('arazzo_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('execution_id')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arazzo_events');
    }

    private function createPartitionedTable(): void
    {
        // Postgres native RANGE partitioning requires the partition key (created_at) to be
        // part of the primary key, so this can't reuse the portable Schema::create() shape
        // above -- and a partitioned table can't carry a single-column FK, matching the
        // no-FK decision on the portable path (see plan's Global Constraints).
        DB::statement(<<<'SQL'
            CREATE TABLE arazzo_events (
                id BIGSERIAL,
                execution_id CHAR(26) NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        DB::statement('CREATE INDEX arazzo_events_execution_id_index ON arazzo_events (execution_id)');
        DB::statement('CREATE TABLE arazzo_events_default PARTITION OF arazzo_events DEFAULT');
    }
};
