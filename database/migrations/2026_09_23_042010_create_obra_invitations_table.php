<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CHECK_NAME = 'obra_invitations_revoked_or_used_check';

    /**
     * Convite record (CT-02, RF-26, RF-27, RNF-01). Only the SHA-256 hex
     * digest of the token is stored (`token_hash char(64)` unique; the
     * plaintext never reaches the database). Append-only in its identity
     * columns (no `updated_at`): `revoked_*`/`used_*` are written only by
     * the conditional UPDATEs of revocation and consumption. Every user/obra
     * FK is `restrictOnDelete` so the convite history survives. The check
     * guarantees a convite is never both revoked and used, and the
     * `(obra_id, created_at)` index serves the per-obra listing (RF-26).
     */
    public function up(): void
    {
        Schema::create('obra_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('used_at')->nullable();

            $table->index(['obra_id', 'created_at']);
        });

        DB::statement('alter table obra_invitations add constraint '.self::CHECK_NAME.' check (revoked_at is null or used_at is null)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obra_invitations');
    }
};
