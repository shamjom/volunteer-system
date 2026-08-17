<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\VolunteerLeftTaskNotification;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function assign(Request $request, Task $task)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        // فقط المتطوع يمكن إسناد المهمة له
        if ($user->role !== 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can be assigned to tasks.',
            ], 422);
        }

        // التأكد أنه ليس مسندًا للمهمة مسبقًا
        $alreadyAssigned = $task->volunteers()
            ->where('users.id', $user->id)
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'message' => 'Volunteer is already assigned to this task.',
            ], 422);
        }

        // إسناد المهمة
        $task->volunteers()->attach($user->id);

        // إرسال الإشعار للمتطوع
        $user->notify(
            new TaskAssignedNotification($task)
        );

        return response()->json([
            'message' => 'Volunteer assigned successfully.',
            'task' => $task->load('volunteers'),
        ], 200);
    }


    public function unassign(Task $task, User $user)
    {
        $isAssigned = $task->volunteers()
            ->where('users.id', $user->id)
            ->exists();

        if (!$isAssigned) {
            return response()->json([
                'message' => 'Volunteer is not assigned to this task.',
            ], 404);
        }

        // إزالة المتطوع من المهمة
        $task->volunteers()->detach($user->id);

        // إرسال إشعار للأدمن وقائد الفريق
        $recipients = User::whereIn('role', ['admin', 'leader'])
            ->where('status', 'active')
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(
                new VolunteerLeftTaskNotification($task, $user)
            );
        }

        return response()->json([
            'message' => 'Volunteer removed from task successfully.',
        ]);
    }


    public function volunteers(Task $task)
    {
        return response()->json([
            'volunteers' => $task->volunteers()->get(),
        ]);
    }
}