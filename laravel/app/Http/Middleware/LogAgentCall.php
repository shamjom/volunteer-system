<?php

namespace App\Http\Middleware;

use App\Models\AgentAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * طبقة تدقيق: كل استدعاء أداة يُسجَّل بمعاملاته ونتيجته.
 */
class LogAgentCall
{
    private const REDACTED = ['password', 'password_confirmation', 'token'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $payload = json_decode($response->getContent() ?: '[]', true);

            AgentAuditLog::create([
                'user_id'         => $request->user()?->id,
                'tool'            => $this->toolName($request),
                'arguments'       => array_diff_key($request->all(), array_flip(self::REDACTED)),
                'ok'              => (bool) ($payload['ok'] ?? false),
                'error_code'      => $payload['error']['code'] ?? null,
                'confirmation_id' => $request->attributes->get('confirmation_id'),
                'ip'              => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // التدقيق لا يُسقِط الطلب
            Log::warning('agent audit log failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function toolName(Request $request): string
    {
        $segments = explode('/', trim($request->path(), '/'));

        return (string) end($segments);
    }
}
