<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewVolunteerPendingNotification extends Notification
{
    use Queueable;

    public function __construct(
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
            'type' => 'new_volunteer_pending',
            'volunteer_id' => $this->volunteer->id,
            'title' => 'New Volunteer Application',
            'message' => $this->volunteer->name .
                ' has submitted a volunteer application and is waiting for review.',
        ];
    }
}