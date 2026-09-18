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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('obra_id')->constrained('obras')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->date('needed_at');
            $table->text('items_description');
            $table->foreignId('status_id')->constrained('statuses')->restrictOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('priorities')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_delivery_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
