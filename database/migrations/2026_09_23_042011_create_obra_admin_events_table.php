<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Obra/convite administrative audit trail (CT-07 b, RF-02, RF-34).
     * Mirrors `user_admin_events`: append-only (no `updated_at`), every FK
     * is `restrictOnDelete` so an actor, obra or convite with audit rows can
     * never be deleted; only `demo:reset` removes rows, via `DB::table(...)`.
     * The two indexes serve the reads by obra and by actor chronologically.
     */
    public function up(): void
    {
        Schema::create('obra_admin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('obra_invitation_id')->nullable()->constrained('obra_invitations')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['obra_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obra_admin_events');
    }
};
