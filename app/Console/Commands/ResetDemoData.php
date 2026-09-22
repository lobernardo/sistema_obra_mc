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
 *
 * The two audit trails reference `users` with `restrictOnDelete` (RF-24,
 * RF-28), so their demo-referencing rows are removed before `users`,
 * inside the same transaction, through `DB::table(...)` only — the query
 * builder bypasses the Eloquent `deleting` guard by design; this command
 * is the single exemption to the append-only rule (D-11). A row goes when
 * at least one of its user references (`actor_id` OR `target_id` for
 * `user_admin_events`; `user_id` for `authentication_events`) points to a
 * demo user — mixed rows included (D-05). Rows whose references are all
 * real, and `authentication_events` rows with `user_id = null` (even when
 * `email` matches a demo user), are never touched.
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

            $demoUserIds = User::query()->where('is_demo', true)->pluck('id');

            DB::table('user_admin_events')
                ->where(fn ($query) => $query->whereIn('actor_id', $demoUserIds)->orWhereIn('target_id', $demoUserIds))
                ->delete();
            DB::table('authentication_events')->whereIn('user_id', $demoUserIds)->delete();

            User::query()->where('is_demo', true)->delete();
        });

        $this->info('Dados de demonstração removidos.');

        return self::SUCCESS;
    }
}
