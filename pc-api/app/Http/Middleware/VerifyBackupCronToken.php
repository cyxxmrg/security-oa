<?php

namespace AppHttpMiddleware;

use Closure;
use IlluminateHttpRequest;
use IlluminateSupportFacadesDB;
use SymfonyComponentHttpFoundationResponse;

class VerifyBackupCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = DB::table('system_settings')->where('key', 'backup_cron_token')->value('value');
        $decoded = json_decode((string) $raw, true);
        $expected = is_string($decoded) ? $decoded : (is_scalar($raw) ? (string) $raw : '');
        $provided = (string) ($request->header('X-Backup-Cron-Token') ?: $request->query('token', ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['code' => 403, 'message' => '备份任务 token 不正确'], 403);
        }

        return $next($request);
    }
}
