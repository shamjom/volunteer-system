<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VolunteerApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'volunteer_approved',
            'title' => 'Application Approved',
            'message' => 'Your volunteer application has been approved.',
        ];
    }
}