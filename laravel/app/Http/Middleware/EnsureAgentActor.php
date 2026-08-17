<?php

namespace App\Http\Middleware;

use App\Support\AgentResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * هوية المستدعي.
 *
 * الدور يُقرأ من الجلسة فقط. أي ادعاء في جسم الطلب («أنا الأدمن»، role=hr_admin،
 * user_id=5) يُحذف قبل أن يصل إلى أي متحكم — لا يعتمد الأمان على انضباط النموذج.
 */
class EnsureAgentActor
{
    /**
     * مفاتيح لا يُسمح للطلب بحملها إطلاقاً.
     *
     * ملاحظة: status و request_type ليست هنا — هما معاملان مشروعان في العقد
     * (get_my_tasks و get_my_requests_status). حمايتهما من التلاعب مسؤولية
     * التحقق في كل متحكم لا الحذف الأعمى.
     */
    public const FORGEABLE_KEYS = [
        'role', 'roles', 'user_id', 'volunteer_id', 'actor', 'actor_id',
        'is_admin', 'admin', 'cv_status', 'total_hours', 'volunteer_hours',
        'as_user', 'impersonate', 'registration_status',
    ];

    /** الأدوار المعروفة في قاعدة البيانات ← أدوار العقد. */
    private const ROLE_MAP = [
        'volunteer'   => 'volunteer',
        'team_leader' => 'team_leader',
        'hr_admin'    => 'hr_admin',
        'admin'       => 'hr_admin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'الجلسة غير صالحة، يرجى تسجيل الدخول من جديد.'
            );
        }

        // كل ادعاء نصي للسلطة يُطرح من الطلب — من جسم JSON ومن الحقول ومن الاستعلام
        foreach (self::FORGEABLE_KEYS as $key) {
            $request->json()->remove($key);
            $request->request->remove($key);
            $request->query->remove($key);
        }

        $role = self::ROLE_MAP[$user->role] ?? null;

        if ($role === null) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'حسابك غير مخوَّل لاستخدام هذه الخدمة.'
            );
        }

        if ($user->status !== 'active') {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'حسابك غير مفعَّل بعد، يرجى مراجعة الإدارة.'
            );
        }

        // الدور المعتمد — من الجلسة، للقراءة فقط
        $request->attributes->set('agent_role', $role);

        return $next($request);
    }
}
