<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notificações internas de `notificacoes-internas` (CT-02, RF-22, RNF-04).
 *
 * Uma linha por (evento de histórico, destinatário), garantida pela unique
 * `(pedido_event_id, recipient_id)`. Tabela append-only exceto `read_at` e o
 * estado do e-mail (sem `updated_at`): o conteúdo exibido é derivado do
 * evento de origem, nunca copiado. O pedido e o evento apagam as
 * notificações em cascata; destinatário e ator são restritos. O índice
 * parcial `internal_notifications_unread_index` atende o contador do sino.
 *
 * Só aditiva, sem backfill: roda no start do container (Railpack).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('internal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('pedido_event_id')->constrained('pedido_events')->cascadeOnDelete();
            $table->string('event_type_slug', 40);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->string('email_status', 10)->default('pendente');
            $table->timestamp('email_status_at')->nullable();

            $table->unique(['pedido_event_id', 'recipient_id']);
            $table->index(['recipient_id', 'created_at']);
        });

        DB::statement("alter table internal_notifications add constraint internal_notifications_email_status_check check (email_status in ('pendente', 'enviado', 'falhou'))");
        DB::statement('create index internal_notifications_unread_index on internal_notifications (recipient_id, created_at desc) where read_at is null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
    }
};
