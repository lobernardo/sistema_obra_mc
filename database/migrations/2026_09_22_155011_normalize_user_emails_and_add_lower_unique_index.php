<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 0 of `paridade-demo-v0` (RF-05, RF-06, RF-08, RF-09, CT-07).
 *
 * In one transaction and in this order:
 *  1. collisions under `lower(email)` are detected in `users` **and**
 *     `password_reset_tokens`; any collision aborts with a PT-BR
 *     `RuntimeException` before a single row is written, so `migrate` exits
 *     non-zero and the database is left exactly as it was (RF-06). No
 *     account is deactivated, merged, renamed or reassigned — resolving a
 *     collision is a manual operation, guided by
 *     `php artisan users:email-case-report`;
 *  2. both e-mail columns are rewritten to `lower(btrim(email))` (RF-05);
 *  3. the functional unique index `users_email_lower_unique` is created, so
 *     the database — not only the application layer — refuses a second
 *     account differing from an existing one by case alone (RF-08, CT-07).
 *
 * No PostgreSQL extension is installed and `users.email` stays
 * `varchar(255)`: `citext` is prohibited by CT-07. Not one row of
 * `pedido_events`, `user_admin_events` or `authentication_events` is read for
 * writing or touched — historical e-mails keep the casing they were written
 * with (RF-09).
 *
 * `down()` drops only the index. The backfill is schema-reversible but not
 * data-reversible: the original casing of a rewritten address is lost for
 * good, which is why RNF-09 scopes reversibility to the schema and to an
 * already-normalized dataset, and why a database snapshot is taken before
 * the first production run.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'users_email_lower_unique';

    /**
     * Tables whose e-mail column is inspected and rewritten, mapped to the
     * column name.
     *
     * @var array<string, string>
     */
    private const NORMALIZED_COLUMNS = [
        'users' => 'email',
        'password_reset_tokens' => 'email',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::NORMALIZED_COLUMNS as $table => $column) {
                $this->guardAgainstCollisions($table, $column);
            }

            foreach (self::NORMALIZED_COLUMNS as $table => $column) {
                DB::statement(sprintf(
                    'update %s set %s = lower(btrim(%s)) where %s <> lower(btrim(%s))',
                    $table,
                    $column,
                    $column,
                    $column,
                    $column,
                ));
            }

            DB::statement(sprintf(
                'create unique index %s on users (lower(email))',
                self::INDEX_NAME,
            ));
        });
    }

    public function down(): void
    {
        DB::statement(sprintf('drop index if exists %s', self::INDEX_NAME));
    }

    /**
     * Aborts before any write when two or more rows of one table would share
     * the same `lower(btrim(email))`, listing every colliding address so the
     * operator can resolve them manually (RF-06).
     */
    private function guardAgainstCollisions(string $table, string $column): void
    {
        $groups = DB::select(sprintf(
            'select lower(btrim(%1$s)) as normalized, count(*) as total, string_agg(%1$s, \', \' order by %1$s) as addresses '
            .'from %2$s group by lower(btrim(%1$s)) having count(*) > 1 order by normalized',
            $column,
            $table,
        ));

        if ($groups === []) {
            return;
        }

        $details = array_map(
            fn (object $group): string => sprintf('%s (%d linhas: %s)', $group->normalized, (int) $group->total, $group->addresses),
            $groups,
        );

        throw new RuntimeException(sprintf(
            'Normalização de e-mail abortada: a tabela %s possui %d grupo(s) de endereços que colidem sob lower(email). '
            .'Resolva manualmente antes de migrar (nenhuma linha foi alterada). Grupos: %s. '
            .'Use "php artisan users:email-case-report" para o relatório completo.',
            $table,
            count($groups),
            implode(' | ', $details),
        ));
    }
};
