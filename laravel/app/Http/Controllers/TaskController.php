<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;


class TaskController extends Controller
{
    // إنشاء مهمة
    public function store(StoreTaskRequest $request)
    {
        $task = Task::create($request->validated());

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task->load('event')
        ], 201);
    }

    // عرض جميع المهام
    public function index()
    {
        $tasks = Task::with('event')->get();

        return response()->json($tasks);
    }

    // عرض مهمة
    public function show(Task $task)
    {
        return response()->json(
            $task->load('event')
        );
    }

    // تعديل
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task
        ]);
    }

    // حذف
    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.'
        ]);
    }

    public function search(Request $request)
{
    return Task::where('title','LIKE','%'.$request->title.'%')
                ->with('event','volunteers')
                ->get();
}

    public function updateStatus(Request $request, Task $task)
{
    $request->validate([
        'status'=>'required|in:pending,in_progress,completed'
    ]);

    $task->update([
        'status'=>$request->status
    ]);

    return response()->json([
        'message'=>'Task status updated.',
        'task'=>$task
    ]);
}
    public function tasksByEvent(Event $event)
{
    return response()->json(
        $event->load('tasks')
    );
}
   public function statistics()
{
    return response()->json([

        'total_tasks'=>Task::count(),

        'pending_tasks'=>Task::where('status','pending')->count(),

        'in_progress_tasks'=>Task::where('status','in_progress')->count(),

        'completed_tasks'=>Task::where('status','completed')->count(),

        'assigned_tasks'=>Task::has('volunteers')->count(),

        'unassigned_tasks'=>Task::doesntHave('volunteers')->count(),

    ]);
}

}