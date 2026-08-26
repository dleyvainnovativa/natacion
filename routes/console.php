<?php
// Comandos de consola. En T2 se agrega aquí el comando que materializa las
// sesiones semanales desde schedule_slots.
use Illuminate\Support\Facades\Schedule;


Schedule::command('schedule:generate-week --next')
    ->weeklyOn(1, '06:00'); // lunes 06:00
