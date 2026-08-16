<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\AgentResponse;
use App\Support\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskToolsController extends Controller
{
    /**
     * get_my_tasks — المهام الموكلة للمستخدم نفسه فقط.
     */
    public function myTasks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|nullable|in:pending,in_progress,done',
        ]);

        $query = Task::with('event')
            ->whereHas('volunteers', fn ($q) => $q->where('users.id', $request->user()->id));

        if (! empty($data['status'])) {
            $query->where('status', TaskStatus::toDatabase($data['status']));
        }

        $tasks = $query->orderByRaw('deadline is null, deadline asc')->get();

        return AgentResponse::ok([
            'count'  => $tasks->count(),
            'status' => $data['status'] ?? null,
            'tasks'  => $tasks->map(fn (Task $task) => $this->present($task))->all(),
        ]);
    }

    /**
     * update_task_status — فحص الملكية إلزامي.
     *
     * المهمة غير الموكلة للمستخدم تُرفض بـ PERMISSION_DENIED لا NOT_FOUND،
     * ولا يُسمح بتحديث مهمة أُنجزت من قبل.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id'    => 'required|integer',
            'new_status' => 'required|in:in_progress,done',
            'note'       => 'sometimes|nullable|string|max:1000',
        ]);

        $task = Task::with('event')->find($data['task_id']);

        if (! $task) {
            return AgentResponse::fail(
                AgentResponse::NOT_FOUND,
                'لم أجد هذه المهمة.'
            );
        }

        $isAssignee = $task->volunteers()
            ->where('users.id', $request->user()->id)
            ->exists();

        if (! $isAssignee) {
            return AgentResponse::fail(
                AgentResponse::PERMISSION_DENIED,
                'هذه المهمة ليست موكلة إليك.'
            );
        }

        if ($task->status === 'completed') {
            return AgentResponse::fail(
                AgentResponse::VALIDATION_ERROR,
                'هذه المهمة منجَزة بالفعل.'
            );
        }

        $task->update([
            'status'           => TaskStatus::toDatabase($data['new_status']),
            'last_status_note' => $data['note'] ?? null,
        ]);

        return AgentResponse::ok([
            'task' => $this->present($task->fresh()->load('event')),
        ]);
    }

    private function present(Task $task): array
    {
        return [
            'task_id'     => $task->id,
            'title_ar'    => $task->title,
            'description' => $task->description,
            'status'      => TaskStatus::toContract($task->status),
            'due_date'    => optional($task->deadline)->toDateTimeString(),
            'note'        => $task->last_status_note,
            'event_id'    => $task->event_id,
            'event_title' => $task->event?->title,
        ];
    }
}
