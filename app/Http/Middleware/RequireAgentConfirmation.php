<?php

namespace App\Http\Middleware;

use App\Models\AgentConfirmation;
use App\Support\AgentResponse;
use App\Support\ConfirmationFingerprint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة التأكيد للأدوات المُغيِّرة.
 *
 * أي استدعاء لأداة تغيّر البيانات بلا معرّف تأكيد صالح مخزَّن في الخادم يُرفض.
 * المعرّف يُصدَر عبر POST /api/agent/confirmations بعد موافقة المستخدم الصريحة،
 * ويُربَط ببصمة (الأداة + معاملاتها) فلا يصلح لعملية أخرى، ويُستهلك مرة واحدة.
 *
 * المعرّف ينتقل في ترويسة X-Confirmation-Id لا في معاملات الأداة — عقد الأدوات
 * مجمَّد ولا يُضاف إليه حقل.
 */
class RequireAgentConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $tool = $this->toolName($request);
        $id   = $request->header('X-Confirmation-Id');

        if (! $id) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'هذه العملية تحتاج تأكيداً صريحاً من المستخدم قبل تنفيذها.'
            );
        }

        $fingerprint = ConfirmationFingerprint::make($tool, $request->all());

        $confirmation = DB::transaction(function () use ($id, $request, $tool, $fingerprint) {

            $record = AgentConfirmation::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $record
                || ! $record->isUsable()
                || $record->tool !== $tool
                || ! hash_equals($record->fingerprint, $fingerprint)) {
                return null;
            }

            // الاستهلاك داخل القفل — لا يُعاد استخدام المعرّف مرتين
            $record->forceFill(['used_at' => now()])->save();

            return $record;
        });

        if (! $confirmation) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'معرّف التأكيد غير صالح أو منتهي الصلاحية أو لا يطابق العملية المطلوبة.'
            );
        }

        $request->attributes->set('confirmation_id', $confirmation->id);

        return $next($request);
    }

    private function toolName(Request $request): string
    {
        $segments = explode('/', trim($request->path(), '/'));

        return (string) end($segments);
    }
}
