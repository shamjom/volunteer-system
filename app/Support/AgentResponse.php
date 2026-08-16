<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * غلاف الاستجابة المجمَّد.
 *
 *   {"ok": true,  "data": ..., "error": null}
 *   {"ok": false, "data": null, "error": {"code": "...", "message_ar": "..."}}
 *
 * ثلاثة مفاتيح لا أكثر. أي حقل إضافي يوضع داخل data لا بجانبه.
 */
class AgentResponse
{
    public const PERMISSION_DENIED  = 'PERMISSION_DENIED';
    public const NOT_FOUND          = 'NOT_FOUND';
    public const ALREADY_REGISTERED = 'ALREADY_REGISTERED';
    public const EVENT_FULL         = 'EVENT_FULL';
    public const DEADLINE_PASSED    = 'DEADLINE_PASSED';
    public const VALIDATION_ERROR   = 'VALIDATION_ERROR';
    public const UPSTREAM_DOWN      = 'UPSTREAM_DOWN';

    /** الرسالة العربية الافتراضية لكل رمز — تُعرض للمستخدم كما هي. */
    private const MESSAGES = [
        self::PERMISSION_DENIED  => 'لا تملك صلاحية تنفيذ هذه العملية.',
        self::NOT_FOUND          => 'لم يتم العثور على المطلوب.',
        self::ALREADY_REGISTERED => 'أنت مسجَّل مسبقاً.',
        self::EVENT_FULL         => 'اكتمل عدد المقاعد في هذه الفعالية.',
        self::DEADLINE_PASSED    => 'انتهت المهلة المحددة لهذه العملية.',
        self::VALIDATION_ERROR   => 'البيانات المُرسلة غير صالحة.',
        self::UPSTREAM_DOWN      => 'تعذّر إتمام العملية حالياً، يرجى المحاولة لاحقاً.',
    ];

    private const HTTP_STATUS = [
        self::PERMISSION_DENIED  => 403,
        self::NOT_FOUND          => 404,
        self::ALREADY_REGISTERED => 409,
        self::EVENT_FULL         => 409,
        self::DEADLINE_PASSED    => 422,
        self::VALIDATION_ERROR   => 422,
        self::UPSTREAM_DOWN      => 503,
    ];

    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok'    => true,
            'data'  => $data,
            'error' => null,
        ], $status);
    }

    public static function fail(string $code, ?string $messageAr = null, ?int $status = null): JsonResponse
    {
        if (! isset(self::MESSAGES[$code])) {
            $code = self::UPSTREAM_DOWN;
        }

        return response()->json([
            'ok'    => false,
            'data'  => null,
            'error' => [
                'code'       => $code,
                'message_ar' => $messageAr ?: self::MESSAGES[$code],
            ],
        ], $status ?: self::HTTP_STATUS[$code]);
    }

    public static function isKnownCode(string $code): bool
    {
        return isset(self::MESSAGES[$code]);
    }
}
