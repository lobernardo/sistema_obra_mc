<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos de pedido de `solicitacao-historico-finalizacao` (CT-03, RF-19,
 * RF-30, RF-42).
 *
 * Tabela append-only (sem `updated_at`): `kind` classifica explicitamente o
 * arquivo como `anexo` ou `romaneio` (check `pedido_attachments_kind_check`),
 * `path` é o nome gerado pelo servidor (único), `size_bytes` é positivo
 * (check `pedido_attachments_size_bytes_check`). A exclusão do pedido apaga
 * os anexos em cascata, como `pedido_events`; `uploaded_by` é restrito.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pedido_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pedido_id', 'kind']);
        });

        DB::statement("alter table pedido_attachments add constraint pedido_attachments_kind_check check (kind in ('anexo', 'romaneio'))");
        DB::statement('alter table pedido_attachments add constraint pedido_attachments_size_bytes_check check (size_bytes > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedido_attachments');
    }
};
