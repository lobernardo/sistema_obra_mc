<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrative audit trail (CT-02, RF-19..RF-25). Names mirror
     * `pedido_events`: append-only (no `updated_at`), both user FKs are
     * `restrictOnDelete` so a user with audit rows can never be deleted
     * (RF-24), and the two indexes serve the expected reads — by target
     * user chronologically and by actor chronologically (RF-25).
     */
    public function up(): void
    {
        Schema::create('user_admin_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_admin_events');
    }
};
