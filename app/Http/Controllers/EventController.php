<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateEventRequest;

class EventController extends Controller
{

    //إنشاء فعالية 
    public function store(StoreEventRequest $request)
    {
        $event = Event::create([

            ...$request->validated(),

            'created_by' => auth()->id(),

        ]);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $event,
        ],201);
    }
    // عرض الفعاليات 
    public function index()
    {
    $events = Event::with('creator')
        ->latest()
        ->get();

    return response()->json([
        'events' => $events
    ]);
    }
    
    // عرض تفاصيل فعالية معينة
    public function show(Event $event)
    {
    return response()->json([
        'event' => $event->load('creator')
    ]);
    }
    
    // تحديث فعالية
    public function update(UpdateEventRequest $request, Event $event)
   {
    $event->update($request->validated());

    return response()->json([
        'message' => 'Event updated successfully.',
        'event' => $event
    ]);
   }
   
   // حذف فعالية
   public function destroy(Event $event)
 {
    $event->delete();

    return response()->json([
        'message' => 'Event deleted successfully.'
    ]);
}

}