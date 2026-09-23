<?php

namespace App\Console\Commands;

use App\Models\Obra;
use App\Models\Pedido;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
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
 *
 * Convites and the two trails that reference them (RF-35) are removed after
 * the demo pedidos and before the demo obras, also through `DB::table(...)`
 * only, because `obra_invitations`, `obra_admin_events` and
 * `account_registration_events` reference `obras`, `users` and each other
 * with `restrictOnDelete`. A convite is doomed when its obra is demo or any
 * of `created_by`/`revoked_by`/`used_by` is a demo user (mixed rows go,
 * D-05). Then go the `obra_admin_events` of a demo obra, a demo actor or a
 * doomed convite; the `account_registration_events` of a demo user or a
 * doomed convite; and finally the doomed convites themselves. Rows
 * referencing only real data are never touched.
 *
 * Attachments of demo pedidos (RF-19, RF-43): their paths are read through
 * `DB::table('pedido_attachments')` before the demo pedidos are deleted; the
 * rows then go through the `cascadeOnDelete` FK (never through
 * `PedidoAttachment`, so its immutability guard never fires) and the files
 * are removed only after the transaction commits. A failure inside the
 * transaction therefore leaves every file in place, and a surviving row
 * never loses its file. Attachments of real pedidos are never touched; an
 * attachment uploaded by a demo user on a real pedido blocks the reset
 * through the `uploaded_by` restrict FK, exactly like
 * `pedido_events.actor_id`.
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

        $demoAttachmentPaths = DB::transaction(function (): array {
            $demoAttachmentPaths = DB::table('pedido_attachments')
                ->whereIn('pedido_id', DB::table('pedidos')->where('is_demo', true)->select('id'))
                ->pluck('path')
                ->all();

            Pedido::query()->where('is_demo', true)->delete();

            $demoObraIds = Obra::query()->where('is_demo', true)->pluck('id');
            $demoUserIds = User::query()->where('is_demo', true)->pluck('id');

            $doomedInvitations = DB::table('obra_invitations')
                ->where(fn ($query) => $query
                    ->whereIn('obra_id', $demoObraIds)
                    ->orWhereIn('created_by', $demoUserIds)
                    ->orWhereIn('revoked_by', $demoUserIds)
                    ->orWhereIn('used_by', $demoUserIds));
            $doomedInvitationIds = (clone $doomedInvitations)->pluck('id');

            DB::table('obra_admin_events')
                ->where(fn ($query) => $query
                    ->whereIn('obra_id', $demoObraIds)
                    ->orWhereIn('actor_id', $demoUserIds)
                    ->orWhereIn('obra_invitation_id', $doomedInvitationIds))
                ->delete();
            DB::table('account_registration_events')
                ->where(fn ($query) => $query
                    ->whereIn('user_id', $demoUserIds)
                    ->orWhereIn('obra_invitation_id', $doomedInvitationIds))
                ->delete();
            $doomedInvitations->delete();

            Obra::query()->where('is_demo', true)->delete();

            DB::table('user_admin_events')
                ->where(fn ($query) => $query->whereIn('actor_id', $demoUserIds)->orWhereIn('target_id', $demoUserIds))
                ->delete();
            DB::table('authentication_events')->whereIn('user_id', $demoUserIds)->delete();

            User::query()->where('is_demo', true)->delete();

            return $demoAttachmentPaths;
        });

        app(PedidoAttachmentStorage::class)->deleteQuietly($demoAttachmentPaths);

        $this->info('Dados de demonstração removidos.');

        return self::SUCCESS;
    }
}
