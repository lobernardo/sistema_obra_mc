<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado `ignorado` do e-mail de notificação interna, de
 * `email-notificacoes-enxutas` (RF-03, CT-03).
 *
 * `up()` recria `internal_notifications_email_status_check` aceitando
 * `pendente`, `enviado`, `falhou` e `ignorado`.
 *
 * `down()` restaura os 3 valores originais. Se existir alguma linha
 * `ignorado`, aborta com `RuntimeException` em PT-BR antes de qualquer
 * escrita: converta as linhas antes (procedimento de rollback em
 * `.spec/features/email-notificacoes-enxutas/PLAN.md`).
 */
return new class extends Migration
{
    private const CHECK = 'internal_notifications_email_status_check';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(sprintf('alter table internal_notifications drop constraint %s', self::CHECK));
            DB::statement(sprintf(
                "alter table internal_notifications add constraint %s check (email_status in ('pendente', 'enviado', 'falhou', 'ignorado'))",
                self::CHECK,
            ));
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $ignored = (int) DB::scalar("select count(*) from internal_notifications where email_status = 'ignorado'");

            if ($ignored > 0) {
                throw new RuntimeException(sprintf(
                    'Rollback abortado: existem %d notificação(ões) com email_status = ignorado em internal_notifications. '
                    .'Converta essas linhas antes de restaurar a check; nenhuma linha foi alterada.',
                    $ignored,
                ));
            }

            DB::statement(sprintf('alter table internal_notifications drop constraint %s', self::CHECK));
            DB::statement(sprintf(
                "alter table internal_notifications add constraint %s check (email_status in ('pendente', 'enviado', 'falhou'))",
                self::CHECK,
            ));
        });
    }
};
