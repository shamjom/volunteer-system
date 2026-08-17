<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\RegistrationSeats;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    // المتطوع يطلب التسجيل في فعالية
    public function register(Request $request, Event $event)
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can register for events.'
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.'
            ], 403);
        }

        if ($event->status !== 'open') {
            return response()->json([
                'message' => 'Event is not open for registration.'
            ], 422);
        }

        if ($event->start_date < now()) {
            return response()->json([
                'message' => 'Event has already started.'
            ], 422);
        }

        $existing = EventRegistration::where('event_id', $event->id)
            ->where('volunteer_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a registration for this event.',
                'registration_status' => $existing->registration_status,
            ], 422);
        }

        // حجز ذرّي — التسجيل المعلّق يحجز مقعداً ويحرّره عند الرفض أو الإلغاء
        if (! RegistrationSeats::reserve($event->id)) {
            return response()->json([
                'message' => 'Event is full.'
            ], 422);
        }

        $registration = EventRegistration::create([
            'event_id' => $event->id,
            'volunteer_id' => $user->id,
            'registration_status' => 'pending',
            'attendance_status' => 'pending',
            'volunteer_hours' => 0,
        ]);

        return response()->json([
            'message' => 'Registration request submitted successfully.',
            'registration' => $registration->load('event'),
        ], 201);
    }

    // المتطوع يرى تسجيلاته
    public function mine(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can access this endpoint.'
            ], 403);
        }

        return response()->json([
            'registrations' => $user
                ->eventRegistrations()
                ->with('event')
                ->latest()
                ->get(),
        ]);
    }

    // الأدمن يرى طلبات التسجيل لفعالية
    public function index(Event $event)
    {
        return response()->json([
            'registrations' => $event
                ->registrations()
                ->with('volunteer')
                ->latest()
                ->get(),
        ]);
    }

    // الأدمن يقبل طلب التسجيل
    public function approve(EventRegistration $registration)
    {
        if ($registration->registration_status !== 'pending') {
            return response()->json([
                'message' => 'Only pending registrations can be approved.'
            ], 422);
        }

        // المقعد محجوز منذ إنشاء الطلب — الموافقة لا تحجز مقعداً جديداً
        $registration->update([
            'registration_status' => 'approved',
        ]);

        return response()->json([
            'message' => 'Registration approved successfully.',
            'registration' => $registration->load('event', 'volunteer'),
        ]);
    }

    // الأدمن يرفض طلب التسجيل
    public function reject(EventRegistration $registration)
    {
        if ($registration->registration_status !== 'pending') {
            return response()->json([
                'message' => 'Only pending registrations can be rejected.'
            ], 422);
        }

        $registration->update([
            'registration_status' => 'rejected',
        ]);

        RegistrationSeats::release($registration->event_id);

        return response()->json([
            'message' => 'Registration rejected successfully.',
            'registration' => $registration->load('event', 'volunteer'),
        ]);
    }

    // المتطوع يلغي تسجيله
    public function cancel(
        Request $request,
        EventRegistration $registration
    ) {
        if ($registration->volunteer_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        if (in_array(
            $registration->registration_status,
            ['cancelled', 'rejected']
        )) {
            return response()->json([
                'message' => 'Registration cannot be cancelled.'
            ], 422);
        }

        $registration->update([
            'registration_status' => 'cancelled',
            'cancelled_at'        => now(),
        ]);

        RegistrationSeats::release($registration->event_id);

        return response()->json([
            'message' => 'Registration cancelled successfully.',
        ]);
    }
}