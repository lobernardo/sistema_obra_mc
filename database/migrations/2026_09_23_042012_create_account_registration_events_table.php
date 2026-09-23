<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account-creation audit trail (CT-07 c, RF-22). Append-only (no
     * `updated_at`); holds the created user, the origin (`novo_cadastro` or
     * `convite`), the convite when applicable and the client IP (45 chars,
     * IPv6-safe) — never a password or an e-mail. Both FKs are
     * `restrictOnDelete`; only `demo:reset` removes rows, via
     * `DB::table(...)`.
     */
    public function up(): void
    {
        Schema::create('account_registration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('origin', 20);
            $table->foreignId('obra_invitation_id')->nullable()->constrained('obra_invitations')->restrictOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_registration_events');
    }
};
