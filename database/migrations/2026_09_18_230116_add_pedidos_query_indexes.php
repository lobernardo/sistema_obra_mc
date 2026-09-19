<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['obra_id', 'status_id']);
            $table->index('needed_at');
        });

        Schema::table('pedido_events', function (Blueprint $table) {
            $table->index(['pedido_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['obra_id', 'status_id']);
            $table->dropIndex(['needed_at']);
        });

        Schema::table('pedido_events', function (Blueprint $table) {
            $table->dropIndex(['pedido_id', 'created_at']);
        });
    }
};
