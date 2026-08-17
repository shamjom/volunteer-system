<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Support\AgentResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileToolsController extends Controller
{
    /** الحقول الأربعة الوحيدة التي يملك المستخدم تعديلها. */
    private const EDITABLE = ['phone', 'email', 'city', 'emergency_contact'];

    /**
     * get_my_profile — الهوية من الجلسة، لا معامل.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return AgentResponse::ok(
            $this->present($user, $this->profileOf($user))
        );
    }

    /**
     * update_my_profile — أربعة حقول فقط.
     *
     * الدور والحالة وساعات التطوع لا تُعدَّل من هنا إطلاقاً؛ الوسيط يحذفها من
     * الطلب، وهذا التحقق يرفض أي مفتاح خارج الأربعة صراحةً.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $unexpected = array_diff(array_keys($request->all()), self::EDITABLE);

        if ($unexpected) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'يمكن تعديل رقم الجوال والبريد والمدينة وجهة الاتصال للطوارئ فقط.'
            );
        }

        $data = $request->validate([
            'phone'             => 'sometimes|nullable|string|max:50',
            'email'             => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'city'              => 'sometimes|nullable|string|max:255',
            'emergency_contact' => 'sometimes|nullable|string|max:255',
        ]);

        if (! $data) {
            return AgentResponse::fail(
                AgentResponse::VALIDATION_ERROR,
                'حدّد حقلاً واحداً على الأقل لتحديثه.'
            );
        }

        if (array_key_exists('email', $data)) {
            $user->update(['email' => $data['email']]);
        }

        $profileFields = array_intersect_key($data, array_flip(['phone', 'city', 'emergency_contact']));

        if ($profileFields) {
            $profile = $this->profileOf($user);

            if ($profile) {
                $profile->update($profileFields);
            } else {
                VolunteerProfile::create($profileFields + ['user_id' => $user->id]);
            }
        }

        $user = $user->fresh();

        return AgentResponse::ok([
            'updated_fields' => array_keys($data),
            'profile'        => $this->present($user, $this->profileOf($user)),
        ]);
    }

    /**
     * get_my_volunteer_hours
     */
    public function hours(Request $request): JsonResponse
    {
        $data = $request->validate([
            'breakdown' => 'sometimes|nullable|boolean',
        ]);

        $user      = $request->user();
        $breakdown = (bool) ($data['breakdown'] ?? false);

        $registrations = EventRegistration::with('event')
            ->where('volunteer_id', $user->id)
            ->where('volunteer_hours', '>', 0)
            ->get();

        $payload = [
            'total_hours'  => (int) $registrations->sum('volunteer_hours'),
            'events_count' => $registrations->count(),
        ];

        if ($breakdown) {
            $payload['breakdown'] = $registrations
                ->sortByDesc(fn ($registration) => $registration->event?->start_date)
                ->map(fn (EventRegistration $registration) => [
                    'event_id' => $registration->event_id,
                    'title_ar' => $registration->event?->title,
                    'date'     => optional($registration->event?->start_date)
                        ? \Carbon\Carbon::parse($registration->event->start_date)->toDateTimeString()
                        : null,
                    'hours'    => (int) $registration->volunteer_hours,
                ])
                ->values()
                ->all();
        }

        return AgentResponse::ok($payload);
    }

    private function profileOf(User $user): ?VolunteerProfile
    {
        return VolunteerProfile::where('user_id', $user->id)->first();
    }

    private function present(User $user, ?VolunteerProfile $profile): array
    {
        return [
            'full_name'         => $user->name,
            'email'             => $user->email,
            'phone'             => $profile?->phone,
            'city'              => $profile?->city,
            'emergency_contact' => $profile?->emergency_contact,
            'join_date'         => optional($user->created_at)->toDateString(),
            'status'            => $user->status,
            'cv_status'         => $profile?->cv_status ?? 'not_received',
            'total_hours'       => (int) ($profile?->total_hours ?? 0),
        ];
    }
}
