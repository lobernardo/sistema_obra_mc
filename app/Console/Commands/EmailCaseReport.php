<?php

namespace App\Console\Commands;

use App\Support\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic for the Fase 0 e-mail normalization (RF-07, CT-04).
 *
 * Inspects the same two tables the Fase 0 migration rewrites and aborts on
 * — `users` and `password_reset_tokens` — and reports, per table, every row
 * whose e-mail differs from its canonical form (`EmailNormalizer`) and every
 * group that would collide under `lower(email)`.
 *
 * The verdict is the union of the two tables: a collision in
 * `password_reset_tokens` alone is reported and exits non-zero, so an
 * operator can never read "Nenhuma colisão encontrada." and still watch the
 * migration abort during deploy. Exit `0` only when no row needs
 * normalization and no collision exists (CT-04).
 *
 * Nothing is written: only `select` statements run, and every address goes
 * to stdout through `$this->table()`/`$this->line()` — never to a log
 * channel (RF-10).
 */
class EmailCaseReport extends Command
{
    /**
     * Every table inspected, mapped to the column holding the e-mail.
     *
     * @var array<string, string>
     */
    private const INSPECTED_TABLES = [
        'users' => 'email',
        'password_reset_tokens' => 'email',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:email-case-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relatório somente leitura de e-mails fora da forma canônica e de colisões sob lower(email) em users e password_reset_tokens.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Diagnóstico de caixa de e-mail (somente leitura).');

        $totalNotNormalized = 0;
        $totalCollisions = 0;

        foreach (self::INSPECTED_TABLES as $table => $column) {
            $notNormalized = $this->notNormalizedRows($table, $column);
            $collisions = $this->collisionGroups($table, $column);

            $totalNotNormalized += count($notNormalized);
            $totalCollisions += count($collisions);

            $this->newLine();
            $this->line(sprintf('Tabela: %s', $table));
            $this->reportNotNormalized($table, $notNormalized);
            $this->reportCollisions($table, $collisions);
        }

        $this->newLine();

        if ($totalCollisions === 0) {
            $this->info('Nenhuma colisão encontrada.');
        } else {
            $this->error(sprintf('Colisões sob lower(email): %d grupo(s). A migration de normalização abortará até que sejam resolvidas manualmente.', $totalCollisions));
        }

        if ($totalNotNormalized > 0) {
            $this->warn(sprintf('E-mails fora da forma canônica: %d linha(s). A migration de normalização reescreverá essas linhas.', $totalNotNormalized));
        }

        if ($totalCollisions === 0 && $totalNotNormalized === 0) {
            $this->info('Nenhuma linha precisa de normalização.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * Rows whose stored e-mail differs from its canonical form. The
     * comparison is done in PHP against `EmailNormalizer` so the report and
     * the application share one definition of "canonical".
     *
     * @return list<array{identificador: string, atual: string, normalizado: string}>
     */
    private function notNormalizedRows(string $table, string $column): array
    {
        $rows = [];

        foreach ($this->allRows($table, $column) as $row) {
            $current = (string) $row->email_value;
            $normalized = EmailNormalizer::normalize($current);

            if ($current !== $normalized) {
                $rows[] = [
                    'identificador' => (string) $row->row_identifier,
                    'atual' => $current,
                    'normalizado' => $normalized,
                ];
            }
        }

        return $rows;
    }

    /**
     * Canonical values shared by more than one row — exactly what the unique
     * functional index `users_email_lower_unique` would reject and what the
     * Fase 0 migration aborts on.
     *
     * @return list<array{normalizado: string, linhas: int, enderecos: string}>
     */
    private function collisionGroups(string $table, string $column): array
    {
        $groups = [];

        foreach ($this->allRows($table, $column) as $row) {
            $groups[EmailNormalizer::normalize((string) $row->email_value)][] = (string) $row->email_value;
        }

        $collisions = [];

        foreach ($groups as $normalized => $addresses) {
            if (count($addresses) < 2) {
                continue;
            }

            sort($addresses);

            $collisions[] = [
                'normalizado' => (string) $normalized,
                'linhas' => count($addresses),
                'enderecos' => implode(', ', $addresses),
            ];
        }

        usort($collisions, fn (array $a, array $b): int => strcmp($a['normalizado'], $b['normalizado']));

        return $collisions;
    }

    /**
     * Every row of one inspected table, as `row_identifier` + `email_value`.
     * `password_reset_tokens` has no surrogate key — its e-mail is the
     * primary key — so the address doubles as the identifier there.
     *
     * @return list<object{row_identifier: mixed, email_value: mixed}>
     */
    private function allRows(string $table, string $column): array
    {
        $identifier = $table === 'users' ? 'id' : $column;

        return DB::select(sprintf(
            'select %s as row_identifier, %s as email_value from %s order by %s',
            $identifier,
            $column,
            $table,
            $identifier,
        ));
    }

    /**
     * @param  list<array{identificador: string, atual: string, normalizado: string}>  $rows
     */
    private function reportNotNormalized(string $table, array $rows): void
    {
        if ($rows === []) {
            $this->line(sprintf('  %s: nenhum e-mail fora da forma canônica.', $table));

            return;
        }

        $this->warn(sprintf('  %s: %d linha(s) fora da forma canônica.', $table, count($rows)));
        $this->table(['Identificador', 'E-mail atual', 'Forma normalizada'], $rows);
    }

    /**
     * @param  list<array{normalizado: string, linhas: int, enderecos: string}>  $groups
     */
    private function reportCollisions(string $table, array $groups): void
    {
        if ($groups === []) {
            $this->line(sprintf('  %s: nenhuma colisão sob lower(email).', $table));

            return;
        }

        $this->error(sprintf('  %s: %d grupo(s) colidem sob lower(email).', $table, count($groups)));
        $this->table(['Forma normalizada', 'Linhas', 'Endereços'], $groups);
    }
}
