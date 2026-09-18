<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `migrate:fresh` (used by RefreshDatabase in tests) drops tables but
        // never touches a standalone sequence, so guard creation and reset
        // the counter explicitly to keep repeated fresh-migrations idempotent.
        DB::statement('create sequence if not exists pedido_code_sequence as bigint start with 1 increment by 1');
        DB::statement('alter sequence pedido_code_sequence restart with 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop sequence if exists pedido_code_sequence');
    }
};
