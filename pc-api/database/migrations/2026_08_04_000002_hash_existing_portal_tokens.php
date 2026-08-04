<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tender_portal_invites')->orderBy('id')->each(function ($invite) {
            if (!preg_match('/^[a-f0-9]{64}$/', (string) $invite->token)) {
                DB::table('tender_portal_invites')->where('id', $invite->id)->update([
                    'token' => hash('sha256', (string) $invite->token),
                    'used_at' => $invite->used_at ?? now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Token hashing is intentionally irreversible.
    }
};
