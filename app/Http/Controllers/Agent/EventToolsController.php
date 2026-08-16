<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\RegistrationSeats;
use App\Support\AgentResponse;
use App\Support\ArabicText;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventToolsController extends Controller
{
    /**
     * list_upcoming_events
     */
    public function listUpcoming(Request $request): JsonResponse
    {
        $data = $request->validate([
            'range' => 'sometimes|nullable|in:today,tomorrow,this_week,next_week,this_month,next_month',
            'date_from' => 'sometimes|nullable|date_format:Y-m-d',
            'date_to'   => 'sometimes|nullable|date_format:Y-m-d',
        ]);

        $query = Event::query()
            ->where('status', 'open')
            ->where('start_date', '>=', now());

        [$from, $to] = $this->resolveWindow(
            $data['range'] ?? null,
            $data['date_from'] ?? null,
            $data['date_to'] ?? null
        );

        if ($from) {
            $query->where('start_date', '>=', $from);
        }

        if ($to) {
            $query->where('start_date', '<=', $to);
        }

        $events = $query->orderBy('start_date')->limit(20)->get();

        return AgentResponse::ok([
            'count'  => $events->count(),
            'range'  => $data['range'] ?? null,
            'events' => $events->map(fn (Event $event) => $this->present($event))->all(),
        ]);
    }

    /**
     * find_event — مطابقة ضبابية عربية.
     *
     * ترجع أفضل ثلاثة مرشحين مع match_score. لا تخمّن نيابةً عن النموذج:
     * الدرجات المتقاربة تُعرض كما هي ليسأل النموذج المستخدم.
     */
    public function find(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $events = Event::query()
            ->whereIn('status', ['open', 'closed'])
            ->orderByDesc('start_date')
            ->limit(300)
            ->get();

        $scored = $events
            ->map(function (Event $event) use ($data) {
                $score = max(
                    ArabicText::similarity($data['query'], $event->title),
                    // العنوان مع الموقع يساعد حين يقول المستخدم «الحملة في جدة»
                    ArabicText::similarity($data['query'], $event->title . ' ' . $event->location) * 0.95
                );

                return ['event' => $event, 'score' => round($score, 3)];
            })
            ->filter(fn ($row) => $row['score'] >= config('agent.find_event_min_score'))
            ->sortByDesc('score')
            ->take(config('agent.find_event_limit'))
            ->values();

        if ($scored->isEmpty()) {
            return AgentResponse::fail(
                AgentResponse::NOT_FOUND,
                'لم أجد فعالية بهذا الاسم.'
            );
        }

        return AgentResponse::ok([
            'query'      => $data['query'],
            'count'      => $scored->count(),
            'candidates' => $scored->map(fn ($row) => $this->present($row['event'], [
                'match_score' => $row['score'],
            ]))->all(),
        ]);
    }

    /**
     * register_for_event
     *
     * الوضع يُضبط من config('agent.registration_mode'):
     *   direct  → التسجيل يصبح approved فوراً
     *   pending → يُنشأ طلب ينتظر موافقة الإدارة
     *
     * المقعد يُحجز في الوضعين، فـ EVENT_FULL يعكس امتلاءً حقيقياً.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => 'required|integer',
        ]);

        $user  = $request->user();
        $event = Event::find($data['event_id']);

        if (! $event) {
            return AgentResponse::fail(
                AgentResponse::NOT_FOUND,
                'لم أجد هذه الفعالية.'
            );
        }

        if ($event->status !== 'open') {
            return AgentResponse::fail(
                AgentResponse::DEADLINE_PASSED,
                'التسجيل في هذه الفعالية مغلق.'
            );
        }

        if (Carbon::parse($event->start_date)->isPast()) {
            return AgentResponse::fail(
                AgentResponse::DEADLINE_PASSED,
                'بدأت هذه الفعالية بالفعل ولم يعد التسجيل ممكناً.'
            );
        }

        $pendingMode = config('agent.registration_mode') === 'pending';

        return DB::transaction(function () use ($event, $user, $pendingMode) {

            $existing = EventRegistration::where('event_id', $event->id)
                ->where('volunteer_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && RegistrationSeats::holds($existing->registration_status)) {
                return AgentResponse::fail(
                    AgentResponse::ALREADY_REGISTERED,
                    $existing->registration_status === 'approved'
                        ? 'أنت مسجَّل في هذه الفعالية بالفعل.'
                        : 'لديك طلب تسجيل معلّق في هذه الفعالية.'
                );
            }

            if (! RegistrationSeats::reserve($event->id)) {
                return AgentResponse::fail(
                    AgentResponse::EVENT_FULL,
                    'اكتمل عدد المقاعد في هذه الفعالية.'
                );
            }

            $status = $pendingMode ? 'pending' : 'approved';

            $attributes = [
                'registration_status' => $status,
                'attendance_status'   => 'pending',
                'registered_at'       => now(),
                'cancellation_reason' => null,
                'cancelled_at'        => null,
            ];

            if ($existing) {
                // تسجيل ملغى أو مرفوض سابقاً — يُعاد تفعيله بدل إنشاء صف مكرر
                $existing->update($attributes);
                $registration = $existing;
            } else {
                $registration = EventRegistration::create($attributes + [
                    'event_id'        => $event->id,
                    'volunteer_id'    => $user->id,
                    'volunteer_hours' => 0,
                ]);
            }

            return AgentResponse::ok([
                'registration_id'     => $registration->id,
                'registration_status' => $registration->registration_status,
                'confirmed'           => ! $pendingMode,
                'event'               => $this->present($event->fresh()),
            ], 201);
        });
    }

    /**
     * withdraw_from_event
     */
    public function withdraw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id' => 'required|integer',
            'reason'   => 'sometimes|nullable|string|max:1000',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {

            $registration = EventRegistration::with('event')
                ->where('event_id', $data['event_id'])
                ->where('volunteer_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                return AgentResponse::fail(
                    AgentResponse::NOT_FOUND,
                    'لا يوجد تسجيل لك في هذه الفعالية.'
                );
            }

            if (! RegistrationSeats::holds($registration->registration_status)) {
                return AgentResponse::fail(
                    AgentResponse::VALIDATION_ERROR,
                    'تسجيلك في هذه الفعالية ملغى أو مرفوض أصلاً.'
                );
            }

            $event    = $registration->event;
            $deadline = Carbon::parse($event->start_date)
                ->subHours((int) config('agent.withdraw_deadline_hours'));

            if (now()->greaterThanOrEqualTo($deadline)) {
                return AgentResponse::fail(
                    AgentResponse::DEADLINE_PASSED,
                    'انتهت مهلة الانسحاب من هذه الفعالية، يرجى التواصل مع الإدارة.'
                );
            }

            $registration->update([
                'registration_status' => 'cancelled',
                'cancellation_reason' => $data['reason'] ?? null,
                'cancelled_at'        => now(),
            ]);

            RegistrationSeats::release($event->id);

            return AgentResponse::ok([
                'registration_id'     => $registration->id,
                'registration_status' => 'cancelled',
                'event'               => $this->present($event->fresh()),
            ]);
        });
    }

    /**
     * get_my_registrations
     */
    public function myRegistrations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'include_past' => 'sometimes|nullable|boolean',
        ]);

        $includePast = (bool) ($data['include_past'] ?? false);

        $query = EventRegistration::with('event')
            ->where('volunteer_id', $request->user()->id);

        if (! $includePast) {
            $query->whereHas('event', fn ($q) => $q->where('start_date', '>=', now()));
        }

        $registrations = $query->get()
            ->sortBy(fn ($registration) => $registration->event?->start_date)
            ->values();

        return AgentResponse::ok([
            'count'         => $registrations->count(),
            'include_past'  => $includePast,
            'registrations' => $registrations->map(fn (EventRegistration $registration) => [
                'registration_id'     => $registration->id,
                'registration_status' => $registration->registration_status,
                'attendance_status'   => $registration->attendance_status,
                'volunteer_hours'     => (int) $registration->volunteer_hours,
                'registered_at'       => optional($registration->registered_at)->toDateTimeString(),
                'event'               => $registration->event
                    ? $this->present($registration->event)
                    : null,
            ])->all(),
        ]);
    }

    /**
     * تمثيل الفعالية بأسماء العقد.
     */
    private function present(Event $event, array $extra = []): array
    {
        $start = Carbon::parse($event->start_date);
        $taken = (int) $event->slots_taken;
        $total = (int) $event->max_volunteers;

        return $extra + [
            'event_id'          => $event->id,
            'title_ar'          => $event->title,
            'description'       => $event->description,
            'location'          => $event->location,
            'date'              => $start->toDateTimeString(),
            'end_date'          => optional($event->end_date ? Carbon::parse($event->end_date) : null)->toDateTimeString(),
            'slots_total'       => $total,
            'slots_taken'       => $taken,
            'slots_available'   => max(0, $total - $taken),
            'registration_open' => $event->status === 'open' && $start->isFuture(),
        ];
    }

    /**
     * ترجمة المدى الزمني النسبي إلى نافذة تاريخين.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveWindow(?string $range, ?string $from, ?string $to): array
    {
        if ($range) {
            $weekStart = (int) config('agent.week_starts_on');
            $now       = now();

            return match ($range) {
                'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'tomorrow'   => [$now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()],
                'this_week'  => [$now->copy()->startOfWeek($weekStart), $now->copy()->endOfWeek($weekStart)],
                'next_week'  => [
                    $now->copy()->addWeek()->startOfWeek($weekStart),
                    $now->copy()->addWeek()->endOfWeek($weekStart),
                ],
                'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
                'next_month' => [
                    $now->copy()->addMonthNoOverflow()->startOfMonth(),
                    $now->copy()->addMonthNoOverflow()->endOfMonth(),
                ],
            };
        }

        return [
            $from ? Carbon::parse($from)->startOfDay() : null,
            $to ? Carbon::parse($to)->endOfDay() : null,
        ];
    }
}
