<?php

return [

    /*
    |--------------------------------------------------------------------------
    | وضع التسجيل في الفعاليات
    |--------------------------------------------------------------------------
    |
    | direct  : register_for_event ينجح فوراً ويصبح التسجيل approved.
    | pending : register_for_event يُنشئ طلباً pending ينتظر موافقة الإدارة.
    |
    | في الوضعين يُحجز المقعد فوراً (slots_taken)، فرمز EVENT_FULL يبقى صادقاً.
    |
    */
    'registration_mode' => env('AGENT_REGISTRATION_MODE', 'direct'),

    /*
    | المهلة قبل بداية الفعالية التي يُمنع بعدها الانسحاب (بالساعات).
    */
    'withdraw_deadline_hours' => (int) env('AGENT_WITHDRAW_DEADLINE_HOURS', 24),

    /*
    | صلاحية معرّف التأكيد بالدقائق.
    */
    'confirmation_ttl_minutes' => (int) env('AGENT_CONFIRMATION_TTL', 10),

    /*
    | بداية الأسبوع لحساب this_week / next_week (0 = الأحد، 6 = السبت).
    */
    'week_starts_on' => (int) env('AGENT_WEEK_STARTS_ON', 0),

    /*
    | الحد الأدنى لدرجة التطابق التي تُرجعها find_event، وعدد المرشحين.
    */
    'find_event_min_score' => 0.30,
    'find_event_limit'     => 3,

    /*
    | الأدوات المُغيِّرة — تتطلب معرّف تأكيد صالحاً في ترويسة X-Confirmation-Id.
    */
    'mutating_tools' => [
        'register_for_event',
        'withdraw_from_event',
        'update_my_profile',
        'request_join_team',
        'submit_help_request',
        'update_task_status',
    ],

];
