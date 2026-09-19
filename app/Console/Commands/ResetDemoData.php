<?php

namespace App\Console\Commands;

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes only demo rows (`is_demo = true`) from `pedidos`, `obras` and
 * `users`. `pedido_events` (cascadeOnDelete on `pedido_id`) and
 * `obra_profile` (cascadeOnDelete on both FKs) are removed by the database
 * as a side effect, never through Eloquent, so `PedidoEvent`'s
 * immutability guard never fires. Pedidos are deleted before obras/users
 * because `pedidos.obra_id`/`requester_id` `restrictOnDelete()` would
 * otherwise block their removal.
 */
class ResetDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove todos os dados de demonstração (is_demo = true), preservando os dados reais.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Remover todos os dados de demonstração (is_demo = true)?')) {
            $this->info('Operação cancelada.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            Pedido::query()->where('is_demo', true)->delete();
            Obra::query()->where('is_demo', true)->delete();
            User::query()->where('is_demo', true)->delete();
        });

        $this->info('Dados de demonstração removidos.');

        return self::SUCCESS;
    }
}
