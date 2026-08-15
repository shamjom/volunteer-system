<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskNeedsVolunteersNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $current = $this->task->volunteers()->count();
        $required = $this->task->required_volunteers;

        return [
            'type' => 'task_needs_volunteers',
            'task_id' => $this->task->id,
            'title' => 'Task Needs Volunteers',
            'message' => 'Task "' . $this->task->title .
                '" is approaching its deadline and still needs ' .
                ($required - $current) .
                ' volunteer(s).',
        ];
    }
}