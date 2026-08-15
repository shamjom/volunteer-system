<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:check-deadlines')
    ->everyMinute();