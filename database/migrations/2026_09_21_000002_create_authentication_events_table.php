<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Authentication audit trail (CT-03, RF-26..RF-29), separate from
     * `pedido_events` and from `user_admin_events`. Append-only (no
     * `updated_at`). `user_id` is nullable because `login_failed` for an
     * unknown e-mail has no user to point to, and `restrictOnDelete` so a
     * user with authentication rows can never be deleted (RF-28); only
     * `demo:reset` removes rows, via `DB::table(...)`, before `users`
     * (D-05, D-11). `ip` is 45 chars (IPv6-safe) and `user_agent` is
     * capped at 255 by the writer. The two indexes serve the expected
     * reads: by user chronologically and by e-mail chronologically (the
     * latter covers `login_failed` rows with `user_id = null`).
     */
    public function up(): void
    {
        Schema::create('authentication_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['email', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authentication_events');
    }
};
