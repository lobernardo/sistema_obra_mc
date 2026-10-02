<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the `passwords.invites` broker (72 h) its own token table so a
 * `passwords.users` token (60 min) can never be redeemed on the first-access
 * page, and vice-versa.
 *
 * Pending invites already stored in `password_reset_tokens` are moved, in the
 * same transaction, so a link that was e-mailed before the deploy keeps
 * working. A row is an invite when it is older than the 60-minute reset
 * window (it can no longer be a usable reset token) or when an
 * `access_link_sent` / `access_link_resent` audit record of its user was
 * written at or after the token was created. Every other row stays a reset
 * token. The rule is a frozen copy: it reads only tables, never application
 * classes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Schema::create('password_invite_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });

            $resetWindowStart = Carbon::now()->subMinutes(60)->toDateTimeString();

            $inviteEmails = DB::table('password_reset_tokens as t')
                ->whereNotNull('t.created_at')
                ->where(function ($query) use ($resetWindowStart): void {
                    $query->where('t.created_at', '<', $resetWindowStart)
                        ->orWhereExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('user_admin_events as e')
                                ->join('users as u', 'u.id', '=', 'e.target_id')
                                ->whereColumn(DB::raw('lower(u.email)'), 't.email')
                                ->whereIn('e.action', ['access_link_sent', 'access_link_resent'])
                                ->whereRaw("e.created_at >= t.created_at - interval '5 seconds'");
                        });
                })
                ->pluck('t.email');

            if ($inviteEmails->isEmpty()) {
                return;
            }

            DB::table('password_invite_tokens')->insertUsing(
                ['email', 'token', 'created_at'],
                DB::table('password_reset_tokens')->whereIn('email', $inviteEmails)->select('email', 'token', 'created_at'),
            );

            DB::table('password_reset_tokens')->whereIn('email', $inviteEmails)->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                insert into password_reset_tokens (email, token, created_at)
                select email, token, created_at from password_invite_tokens
                on conflict (email) do update
                    set token = excluded.token, created_at = excluded.created_at
                    where password_reset_tokens.created_at is null
                       or excluded.created_at > password_reset_tokens.created_at
                SQL);

            Schema::drop('password_invite_tokens');
        });
    }
};
