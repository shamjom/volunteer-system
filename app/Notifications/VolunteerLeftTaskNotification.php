<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VolunteerLeftTaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $volunteer
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'volunteer_left_task',
            'task_id' => $this->task->id,
            'volunteer_id' => $this->volunteer->id,
            'title' => 'Volunteer Left Task',
            'message' => $this->volunteer->name .
                ' has left the task "' . $this->task->title . '".',
        ];
    }
}