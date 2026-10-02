<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atividade da obra independente do Status, de `obras-ativacao-exclusao`
 * (RF-01, RF-19, CT-01, RNF-05).
 *
 * Numa única transação e nesta ordem:
 *  1. confere em `pg_constraint` que as FKs
 *     `obra_admin_events_obra_id_foreign` e
 *     `obra_admin_events_obra_invitation_id_foreign` existem; se faltar
 *     alguma, aborta com `RuntimeException` em PT-BR antes de qualquer
 *     escrita, para que o banco nunca fique com as FKs meio relaxadas;
 *  2. adiciona `obras.is_active boolean not null default true`;
 *  3. backfill único: `is_active = (status <> 'concluido')`. Obras
 *     "Concluído" nascem inativas e as demais ativas; a partir daqui Status
 *     e `is_active` são independentes e este é o único lugar que deriva a
 *     atividade do Status (RF-01, RF-03);
 *  4. adiciona `obra_admin_events.subject_obra_id bigint`, sem FK,
 *     preenchida com `obra_id` em toda linha existente e depois `NOT NULL`,
 *     para que a trilha continue agrupável por obra depois que a obra for
 *     excluída (RF-19);
 *  5. `obra_admin_events.obra_id` passa a anulável e sua FK é recriada com
 *     `on delete set null`;
 *  6. a FK de `obra_admin_events.obra_invitation_id` é recriada com
 *     `on delete set null`.
 *
 * Deploy: o script de start do Railpack roda `migrate` a cada início de
 * container. Durante a sobreposição entre o container antigo e o novo, o
 * código antigo grava `obra_admin_events` sem `subject_obra_id` e essa
 * escrita falha pelo `NOT NULL` até o container antigo sair.
 *
 * `down()` é um rollback COM PERDA DE DADOS: se existir linha de
 * `obra_admin_events` com `obra_id` nulo (auditoria de obra excluída), ele
 * aborta em PT-BR sem escrever nada; senão restaura as FKs RESTRICT e o
 * `NOT NULL` de `obra_id`, remove `subject_obra_id` e remove
 * `obras.is_active` — a escolha manual de Desativar/Reativar se perde e só
 * volta a ser derivada do Status.
 */
return new class extends Migration
{
    private const OBRA_FK = 'obra_admin_events_obra_id_foreign';

    private const INVITATION_FK = 'obra_admin_events_obra_invitation_id_foreign';

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->guardAgainstMissingForeignKeys();

            DB::statement('alter table obras add column is_active boolean not null default true');
            DB::statement("update obras set is_active = (status <> 'concluido')");

            DB::statement('alter table obra_admin_events add column subject_obra_id bigint null');
            DB::statement('update obra_admin_events set subject_obra_id = obra_id');
            DB::statement('alter table obra_admin_events alter column subject_obra_id set not null');

            DB::statement(sprintf('alter table obra_admin_events drop constraint %s', self::OBRA_FK));
            DB::statement('alter table obra_admin_events alter column obra_id drop not null');
            DB::statement(sprintf(
                'alter table obra_admin_events add constraint %s foreign key (obra_id) references obras (id) on delete set null',
                self::OBRA_FK,
            ));

            DB::statement(sprintf('alter table obra_admin_events drop constraint %s', self::INVITATION_FK));
            DB::statement(sprintf(
                'alter table obra_admin_events add constraint %s foreign key (obra_invitation_id) references obra_invitations (id) on delete set null',
                self::INVITATION_FK,
            ));
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $orphans = (int) DB::scalar('select count(*) from obra_admin_events where obra_id is null');

            if ($orphans > 0) {
                throw new RuntimeException(sprintf(
                    'Rollback abortado: existem %d registro(s) de auditoria de obras excluídas (obra_id nulo) em obra_admin_events. '
                    .'Restaurar a FK RESTRICT exigiria apagá-los; nenhuma linha foi alterada.',
                    $orphans,
                ));
            }

            DB::statement(sprintf('alter table obra_admin_events drop constraint %s', self::INVITATION_FK));
            DB::statement(sprintf(
                'alter table obra_admin_events add constraint %s foreign key (obra_invitation_id) references obra_invitations (id) on delete restrict',
                self::INVITATION_FK,
            ));

            DB::statement(sprintf('alter table obra_admin_events drop constraint %s', self::OBRA_FK));
            DB::statement('alter table obra_admin_events alter column obra_id set not null');
            DB::statement(sprintf(
                'alter table obra_admin_events add constraint %s foreign key (obra_id) references obras (id) on delete restrict',
                self::OBRA_FK,
            ));

            DB::statement('alter table obra_admin_events drop column subject_obra_id');
            DB::statement('alter table obras drop column is_active');
        });
    }

    /**
     * Aborta antes de qualquer escrita quando uma das duas FKs que esta
     * migration recria não existe com o nome esperado.
     */
    private function guardAgainstMissingForeignKeys(): void
    {
        $missing = array_values(array_filter(
            [self::OBRA_FK, self::INVITATION_FK],
            fn (string $name): bool => (int) DB::scalar(
                "select count(*) from pg_constraint where conname = ? and conrelid = 'obra_admin_events'::regclass and contype = 'f'",
                [$name],
            ) !== 1,
        ));

        if ($missing === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Migração da atividade das obras abortada: FK(s) esperada(s) não encontrada(s) em obra_admin_events: %s. '
            .'Nenhuma linha foi alterada.',
            implode(', ', $missing),
        ));
    }
};
