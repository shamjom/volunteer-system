<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNeedsVolunteersNotification;
use Illuminate\Console\Command;

class CheckTaskDeadlines extends Command
{
    protected $signature = 'tasks:check-deadlines';

    protected $description =
        'Notify admins and team leaders about tasks approaching deadline and missing volunteers';

    public function handle(): int
    {
        $tasks = Task::whereNotIn('status', ['completed'])
            ->whereNotNull('deadline')
            ->whereBetween(
                'deadline',
                [now(), now()->addDay()]
            )
            ->with('volunteers')
            ->get();

        $recipients = User::whereIn('role', ['admin', 'leader'])
            ->where('status', 'active')
            ->get();

        foreach ($tasks as $task) {

            $current = $task->volunteers->count();

            if ($current >= $task->required_volunteers) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $recipient->notify(
                    new TaskNeedsVolunteersNotification($task)
                );
            }
        }

        $this->info('Task deadline check completed.');

        return self::SUCCESS;
    }
}