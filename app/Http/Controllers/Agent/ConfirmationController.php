<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAgentActor;
use App\Models\AgentConfirmation;
use App\Support\AgentResponse;
use App\Support\ConfirmationFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إصدار معرّفات التأكيد.
 *
 * ليست أداة من الخمس عشرة — بنية تحتية تستدعيها طبقة المحادثة بعد أن يقول
 * المستخدم «نعم» صراحةً، ثم تمرّر المعرّف في ترويسة X-Confirmation-Id.
 *
 * المعرّف مربوط بالمستخدم وبالأداة وببصمة معاملاتها، ويُستهلك مرة واحدة.
 */
class ConfirmationController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool'      => 'required|string|in:' . implode(',', config('agent.mutating_tools')),
            'arguments' => 'sometimes|array',
        ]);

        // المفاتيح المزوَّرة تُحذف من الطلب الفعلي، فيجب حذفها من البصمة أيضاً
        // وإلا لم يتطابق التأكيد مع الاستدعاء الذي صدر من أجله.
        $arguments = array_diff_key(
            $data['arguments'] ?? [],
            array_flip(EnsureAgentActor::FORGEABLE_KEYS)
        );

        $confirmation = AgentConfirmation::create([
            'user_id'     => $request->user()->id,
            'tool'        => $data['tool'],
            'fingerprint' => ConfirmationFingerprint::make($data['tool'], $arguments),
            'expires_at'  => now()->addMinutes((int) config('agent.confirmation_ttl_minutes')),
        ]);

        return AgentResponse::ok([
            'confirmation_id' => $confirmation->id,
            'tool'            => $confirmation->tool,
            'expires_at'      => $confirmation->expires_at->toDateTimeString(),
        ], 201);
    }
}
