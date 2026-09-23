<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos "Outra" e Data prevista de `solicitacao-historico-finalizacao`
 * (RF-06, RF-12, RF-42, CT-02, CT-06, RNF-06).
 *
 * Numa única transação e nesta ordem:
 *  1. `pedidos.obra_id` passa a aceitar NULL; a FK `pedidos_obra_id_foreign`
 *     (`on delete restrict`) não é tocada;
 *  2. a coluna `obra_reference varchar(255) NULL` é adicionada com os checks
 *     `pedidos_obra_reference_only_without_obra` (referência só existe em
 *     pedido sem obra, RF-06) e `pedidos_obra_reference_not_blank`;
 *  3. a coluna `data_prevista date NULL` é adicionada;
 *  4. todo pedido existente recebe a Data prevista calculada a partir de
 *     `requested_at` (via query builder, sem hooks de modelo);
 *  5. `data_prevista` passa a `NOT NULL`;
 *  6. o índice `pedidos_data_prevista_index` é criado.
 *
 * Nenhuma linha é removida; `expected_delivery_at` e `pedido_events` ficam
 * intactos.
 *
 * REGRA CONGELADA (RF-12, F-14c): o backfill usa uma cópia desta própria
 * migration da regra de dias úteis (3 dias úteis estritamente depois da data
 * de `requested_at` em `America/Sao_Paulo`, 9 feriados nacionais fixos e
 * Sexta-feira da Paixão pelo algoritmo de Meeus/Jones/Butcher). A cópia é
 * deliberada: nenhuma classe da aplicação é importada, de modo que uma
 * mudança futura na regra viva nunca altera o que `migrate`/`migrate:fresh`
 * preenche. Um teste de paridade compara as duas regras.
 *
 * `down()` recusa com `RuntimeException` em PT-BR, antes de qualquer
 * alteração, quando existe pedido sem obra: restaurar `obra_id NOT NULL`
 * exigiria apagar dados.
 */
return new class extends Migration
{
    private const DIAS_UTEIS = 3;

    private const TIMEZONE = 'America/Sao_Paulo';

    /**
     * @var list<string>
     */
    private const FIXED_HOLIDAYS = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];

    private const ONLY_WITHOUT_OBRA_CHECK = 'pedidos_obra_reference_only_without_obra';

    private const NOT_BLANK_CHECK = 'pedidos_obra_reference_not_blank';

    private const INDEX_NAME = 'pedidos_data_prevista_index';

    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement('alter table pedidos alter column obra_id drop not null');

            DB::statement('alter table pedidos add column obra_reference varchar(255) null');
            DB::statement(sprintf(
                'alter table pedidos add constraint %s check (obra_id is null or obra_reference is null)',
                self::ONLY_WITHOUT_OBRA_CHECK,
            ));
            DB::statement(sprintf(
                "alter table pedidos add constraint %s check (obra_reference is null or btrim(obra_reference) <> '')",
                self::NOT_BLANK_CHECK,
            ));

            DB::statement('alter table pedidos add column data_prevista date null');

            DB::table('pedidos')
                ->select('id', 'requested_at')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('pedidos')
                            ->where('id', $row->id)
                            ->update(['data_prevista' => $this->frozenDataPrevista((string) $row->requested_at)]);
                    }
                });

            DB::statement('alter table pedidos alter column data_prevista set not null');
            DB::statement(sprintf('create index %s on pedidos (data_prevista)', self::INDEX_NAME));
        });
    }

    public function down(): void
    {
        if (DB::table('pedidos')->whereNull('obra_id')->exists()) {
            throw new RuntimeException('Existem pedidos "Outra" sem obra; a reversão exigiria apagar dados.');
        }

        DB::transaction(function (): void {
            DB::statement(sprintf('drop index if exists %s', self::INDEX_NAME));
            DB::statement('alter table pedidos drop column data_prevista');
            DB::statement(sprintf('alter table pedidos drop constraint if exists %s', self::NOT_BLANK_CHECK));
            DB::statement(sprintf('alter table pedidos drop constraint if exists %s', self::ONLY_WITHOUT_OBRA_CHECK));
            DB::statement('alter table pedidos drop column obra_reference');
            DB::statement('alter table pedidos alter column obra_id set not null');
        });
    }

    /**
     * Frozen Data prevista rule: `Y-m-d` of the 3rd business day strictly
     * after the local calendar date of a UTC `requested_at`. Public only so
     * the parity test can compare it with the live rule.
     */
    public function frozenDataPrevista(string $requestedAtUtc): string
    {
        $local = (new DateTimeImmutable($requestedAtUtc, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(self::TIMEZONE));

        $date = new DateTimeImmutable($local->format('Y-m-d'), new DateTimeZone('UTC'));
        $counted = 0;

        while ($counted < self::DIAS_UTEIS) {
            $date = $date->modify('+1 day');

            if ((int) $date->format('N') <= 5 && ! $this->frozenIsHoliday($date)) {
                $counted++;
            }
        }

        return $date->format('Y-m-d');
    }

    private function frozenIsHoliday(DateTimeImmutable $date): bool
    {
        if (in_array($date->format('m-d'), self::FIXED_HOLIDAYS, true)) {
            return true;
        }

        return $date->format('Y-m-d') === $this->frozenGoodFriday((int) $date->format('Y'));
    }

    /**
     * Sexta-feira da Paixão (Easter Sunday − 2 days), with Easter by the
     * anonymous Gregorian algorithm (Meeus/Jones/Butcher) in integer arithmetic.
     */
    private function frozenGoodFriday(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC')))
            ->modify('-2 days')
            ->format('Y-m-d');
    }
};
