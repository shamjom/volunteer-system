<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function assign(Request $request, Task $task)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->role != 'volunteer') {
            return response()->json([
                'message' => 'Only volunteers can be assigned.'
            ],422);
        }

        $task->volunteers()->syncWithoutDetaching([$user->id]);

        return response()->json([
            'message' => 'Volunteer assigned successfully.'
        ]);
    }

    public function unassign(Task $task, User $user)
    {
        $task->volunteers()->detach($user->id);

        return response()->json([
            'message' => 'Volunteer removed successfully.'
        ]);
    }

    public function volunteers(Task $task)
    {
        return response()->json(
            $task->load('volunteers')
        );
    }
}