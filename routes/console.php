<?php
// Comandos de consola. Horario "para siempre": cada lunes materializamos el
// horizonte hacia adelante (semana actual + próximas ~8), así la ventana de
// sesiones generadas avanza sola sin plantillas por mes.
use Illuminate\Support\Facades\Schedule;


Schedule::command('schedule:generate-forward')
    ->weeklyOn(1, '06:00'); // lunes 06:00
