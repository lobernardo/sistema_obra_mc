<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status da obra de `obras-associacoes-cadastro-convites` (RF-36, RF-36b,
 * CT-01, RNF-06).
 *
 * Numa única transação e nesta ordem:
 *  1. colisões de `lower(btrim(name))` em `obras` são detectadas; qualquer
 *     colisão aborta com `RuntimeException` em PT-BR antes de qualquer
 *     escrita, então `migrate` sai com código ≠ 0 e o banco fica exatamente
 *     como estava (RF-36b). Nenhuma obra é renomeada automaticamente;
 *  2. as colunas `status varchar(20)` e `responsavel varchar(255)` são
 *     adicionadas, ambas anuláveis;
 *  3. o status é preenchido a partir de `is_active`: `true` → `em_andamento`,
 *     `false` → `concluido` (NC-02);
 *  4. `status` passa a `NOT NULL DEFAULT 'a_iniciar'` com o check
 *     `obras_status_check` restrito aos três valores de CT-01;
 *  5. `obras.is_active` é removida — a única remoção de coluna autorizada
 *     pela SPEC;
 *  6. o índice único funcional `obras_name_normalized_unique` sobre
 *     `lower(btrim(name))` é criado.
 *
 * Nenhuma linha de `users`, `obras`, `obra_profile`, `pedidos`,
 * `pedido_events`, `user_admin_events` ou `authentication_events` é removida
 * (RF-36).
 *
 * `down()` é um rollback COM PERDA DE DADOS (F-14a): recria `is_active` como
 * `status <> 'concluido'`, de modo que as obras "A iniciar" e "Em andamento"
 * voltam ambas a `is_active = true` e a distinção entre elas se perde; a
 * coluna `responsavel` é removida e todos os seus valores são descartados.
 * Faça um `pg_dump` de `obras` antes de reverter em produção.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'obras_name_normalized_unique';

    private const CHECK_NAME = 'obras_status_check';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->guardAgainstNameCollisions();

            DB::statement('alter table obras add column status varchar(20) null');
            DB::statement('alter table obras add column responsavel varchar(255) null');

            DB::statement("update obras set status = case when is_active then 'em_andamento' else 'concluido' end");

            DB::statement("alter table obras alter column status set default 'a_iniciar'");
            DB::statement('alter table obras alter column status set not null');
            DB::statement(sprintf(
                "alter table obras add constraint %s check (status in ('a_iniciar', 'em_andamento', 'concluido'))",
                self::CHECK_NAME,
            ));

            DB::statement('alter table obras drop column is_active');

            DB::statement(sprintf(
                'create unique index %s on obras (lower(btrim(name)))',
                self::INDEX_NAME,
            ));
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement('alter table obras add column is_active boolean not null default true');
            DB::statement("update obras set is_active = (status <> 'concluido')");

            DB::statement(sprintf('drop index if exists %s', self::INDEX_NAME));
            DB::statement(sprintf('alter table obras drop constraint if exists %s', self::CHECK_NAME));
            DB::statement('alter table obras drop column status');
            DB::statement('alter table obras drop column responsavel');
        });
    }

    /**
     * Aborta antes de qualquer escrita quando duas ou mais obras compartilham
     * o mesmo `lower(btrim(name))`, listando os nomes em colisão para que o
     * operador renomeie as obras manualmente (RF-36b).
     */
    private function guardAgainstNameCollisions(): void
    {
        $groups = DB::select(
            'select lower(btrim(name)) as normalized, count(*) as total, string_agg(name, \' | \' order by id) as names '
            .'from obras group by lower(btrim(name)) having count(*) > 1 order by normalized',
        );

        if ($groups === []) {
            return;
        }

        $details = array_map(
            fn (object $group): string => sprintf('"%s" (%d obras: %s)', $group->normalized, (int) $group->total, $group->names),
            $groups,
        );

        throw new RuntimeException(sprintf(
            'Conversão do status das obras abortada: %d grupo(s) de obras colidem sob lower(btrim(name)). '
            .'Renomeie as obras manualmente antes de migrar (nenhuma linha foi alterada). Grupos: %s.',
            count($groups),
            implode('; ', $details),
        ));
    }
};
