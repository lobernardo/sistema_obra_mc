<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Linhas de lookup de `solicitacao-historico-finalizacao` (RF-33, RF-42,
 * CT-09).
 *
 * Numa única transação, `up()` insere o status `finalizado` (sort_order 7) e
 * os tipos de evento `observacao`, `romaneio_anexado` e `finalizacao`, cada um
 * apenas quando o slug ainda não existe — rodar duas vezes mantém uma linha de
 * cada, e o `DemoSeeder` (`firstOrCreate`) convive com as linhas inseridas
 * aqui. Nenhuma linha existente é atualizada nem apagada. Se `sort_order = 7`
 * já pertencer a outro slug, a migration aborta com `RuntimeException` em
 * PT-BR antes de qualquer escrita.
 *
 * Os slugs são literais congelados, sem referência a `App\Enums`: a migration
 * descreve o banco no momento em que foi escrita.
 *
 * `down()` é um rollback CONDICIONALMENTE DESTRUTIVO (F-14b): remove as 4
 * linhas de lookup acima quando nenhum `pedidos.status_id` nem
 * `pedido_events.event_type_id` as referencia; se qualquer uma estiver
 * referenciada, lança `RuntimeException` em PT-BR sem alterar nada.
 */
return new class extends Migration
{
    private const FINALIZADO_SLUG = 'finalizado';

    private const FINALIZADO_SORT_ORDER = 7;

    /**
     * @var array<string, string>
     */
    private const EVENT_TYPES = [
        'observacao' => 'Observação adicionada',
        'romaneio_anexado' => 'Romaneio anexado',
        'finalizacao' => 'Pedido finalizado',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->insertFinalizadoStatus();
            $this->insertHistoryEventTypes();
        });
    }

    /**
     * Rollback condicionalmente destrutivo: apaga as linhas de lookup apenas
     * quando não referenciadas; caso contrário lança sem mudar nada.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $statusIds = DB::table('statuses')
                ->where('slug', self::FINALIZADO_SLUG)
                ->pluck('id');

            $eventTypeIds = DB::table('event_types')
                ->whereIn('slug', array_keys(self::EVENT_TYPES))
                ->pluck('id');

            $statusReferenced = $statusIds->isNotEmpty()
                && DB::table('pedidos')->whereIn('status_id', $statusIds)->exists();

            $eventTypeReferenced = $eventTypeIds->isNotEmpty()
                && DB::table('pedido_events')->whereIn('event_type_id', $eventTypeIds)->exists();

            if ($statusReferenced || $eventTypeReferenced) {
                throw new RuntimeException(
                    'Não é possível reverter: há pedidos com status "finalizado" ou eventos que usam '
                    .'"observacao", "romaneio_anexado" ou "finalizacao". Nenhuma linha foi removida.'
                );
            }

            DB::table('statuses')->whereIn('id', $statusIds)->delete();
            DB::table('event_types')->whereIn('id', $eventTypeIds)->delete();
        });
    }

    private function insertFinalizadoStatus(): void
    {
        if (DB::table('statuses')->where('slug', self::FINALIZADO_SLUG)->exists()) {
            return;
        }

        $conflictingSlug = DB::table('statuses')
            ->where('sort_order', self::FINALIZADO_SORT_ORDER)
            ->value('slug');

        if ($conflictingSlug !== null) {
            throw new RuntimeException(sprintf(
                'Não é possível inserir o status "finalizado": sort_order %d já pertence ao status "%s". Nenhuma linha foi inserida.',
                self::FINALIZADO_SORT_ORDER,
                $conflictingSlug,
            ));
        }

        DB::table('statuses')->insert([
            'name' => 'Finalizado',
            'slug' => self::FINALIZADO_SLUG,
            'description' => 'Pedido concluído operacionalmente por Suprimentos após o romaneio.',
            'sort_order' => self::FINALIZADO_SORT_ORDER,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertHistoryEventTypes(): void
    {
        foreach (self::EVENT_TYPES as $slug => $name) {
            if (DB::table('event_types')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('event_types')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
