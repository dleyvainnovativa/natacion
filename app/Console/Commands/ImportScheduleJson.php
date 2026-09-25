<?php

namespace App\Console\Commands;

use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Member;
use App\Models\Program;
use App\Models\ScheduleSlot;
use App\Models\ScheduleTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa un horario mensual desde un archivo JSON "limpio" (ver
 * september_schedule.json), generado a partir del HORARIO de Excel.
 *
 *   php artisan schedule:import-json storage/app/september_schedule.json
 *   php artisan schedule:import-json storage/app/september_schedule.json --month=2026-09
 *   php artisan schedule:import-json ... --dry     (solo reporta, no escribe)
 *
 * Qué hace:
 *  - Resuelve (crea/clona) la plantilla del mes destino.
 *  - Crea instructores nuevos por nombre (p. ej. Joanna).
 *  - Crea socios "stub" para los números que no existen todavía.
 *  - Crea un slot por cada día de la clase, con carril asignado por concurrencia
 *    (clases a la misma hora y día -> carriles 1,2,3…).
 *  - Adjunta el roster (socios) a cada slot.
 *
 * Idempotente: borra los slots source='horario' de ESA plantilla antes de
 * recrear, así se puede re-importar sin duplicar.
 */
class ImportScheduleJson extends Command
{
    protected $signature = 'schedule:import-json
                            {path : Ruta al archivo JSON del horario}
                            {--month= : Mes destino YYYY-MM (default: meta.target_month del JSON, o el mes actual)}
                            {--dry : Solo reporta lo que haría, sin escribir}';

    protected $description = 'Importa el horario mensual desde un JSON limpio hacia la plantilla del mes.';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['classes'])) {
            $this->error('El JSON no tiene la forma esperada (falta "classes").');
            return self::FAILURE;
        }

        $monthOpt = $this->option('month') ?: ($data['meta']['target_month'] ?? null);
        $monthRef = $monthOpt && preg_match('/^\d{4}-\d{2}$/', $monthOpt)
            ? Carbon::createFromFormat('Y-m-d', $monthOpt.'-01')->startOfMonth()
            : Carbon::now()->startOfMonth();

        $dry = (bool) $this->option('dry');
        $this->info(($dry ? '[DRY] ' : '')."Importando horario a la plantilla de {$monthRef->format('Y-m')}…");

        // --- Pre-resolución de catálogos ---
        $programsBySlug = Program::pluck('id', 'slug')->all();

        // Nombres de instructores conocidos (normalizados) -> id
        $instrByName = [];
        foreach (Instructor::all() as $i) {
            $instrByName[$this->keyName($i->name)] = $i->id;
        }

        // 1) Instructores nuevos
        $createdInstr = [];
        foreach (($data['new_instructors'] ?? []) as $name) {
            $k = $this->keyName($name);
            if (! isset($instrByName[$k])) {
                if (! $dry) {
                    $ins = Instructor::firstOrCreate(['name' => $name], ['pay_per_class' => 150.00, 'active' => true]);
                    $instrByName[$k] = $ins->id;
                }
                $createdInstr[] = $name;
            }
        }

        // 2) Socios stub
        $membersBySocio = Member::pluck('id', 'socio_number')->all();
        $createdMembers = [];
        foreach (($data['new_members'] ?? []) as $nm) {
            $sn = (int) $nm['socio_number'];
            if (! isset($membersBySocio[$sn])) {
                if (! $dry) {
                    $m = Member::create([
                        'socio_number' => $sn,
                        'first_name'   => $nm['first_name'] ?? 'Socio',
                        'last_name_1'  => $nm['last_name_1'] ?? '—',
                        'status'       => 'ALTA',
                    ]);
                    $membersBySocio[$sn] = $m->id;
                }
                $createdMembers[] = $sn;
            }
        }

        if ($dry) {
            $this->line("  Instructores a crear: ".count($createdInstr)." → ".implode(', ', $createdInstr));
            $this->line("  Socios stub a crear:  ".count($createdMembers));
            $this->line("  Clases en el JSON:    ".count($data['classes']));
            return self::SUCCESS;
        }

        // --- Plantilla destino ---
        $template = ScheduleTemplate::resolveFor($monthRef);

        $created = 0; $roster = 0; $skipped = 0; $warnings = [];

        DB::transaction(function () use ($data, $template, $programsBySlug, $instrByName, $membersBySocio, &$created, &$roster, &$skipped, &$warnings) {
            // Idempotencia: quitar slots importados antes en esta plantilla.
            ScheduleSlot::where('schedule_template_id', $template->id)
                ->where('source', 'horario')->delete();

            // Asignación de carril por concurrencia: (weekday|time) -> contador
            $laneIds = Lane::orderBy('position')->pluck('id')->all();
            $concurrency = [];

            foreach ($data['classes'] as $c) {
                $pid = $programsBySlug[$c['program_slug']] ?? null;
                if (! $pid) { $skipped++; $warnings[] = "Programa desconocido: {$c['program_slug']} ({$c['time']})"; continue; }

                $iid = null;
                if (! empty($c['instructor'])) {
                    $iid = $instrByName[$this->keyName($c['instructor'])] ?? null;
                }

                $duration = Program::find($pid)?->duration_min ?? 45;

                // Roster -> ids de socios
                $memberIds = [];
                foreach (($c['roster'] ?? []) as $sn) {
                    if (isset($membersBySocio[(int) $sn])) $memberIds[] = $membersBySocio[(int) $sn];
                }

                foreach (($c['weekdays'] ?? []) as $wd) {
                    // Carril por concurrencia en (weekday,time)
                    $ck = $wd.'|'.$c['time'];
                    $n  = $concurrency[$ck] ?? 0;
                    $laneId = $laneIds[$n % max(1, count($laneIds))] ?? null;
                    $concurrency[$ck] = $n + 1;

                    $slot = $template->slots()->create([
                        'program_id'    => $pid,
                        'instructor_id' => $iid,
                        'lane_id'       => $laneId,
                        'weekday'       => $wd,
                        'start_time'    => $c['time'],
                        'duration_min'  => $duration,
                        'active'        => true,
                        'source'        => 'horario',
                    ]);

                    if ($memberIds) {
                        $slot->members()->sync($memberIds);
                        $roster += count($memberIds);
                    }
                    $created++;
                }
            }
        });

        $this->info("Plantilla \"{$template->display_label}\":");
        $this->line("  Instructores creados: ".count($createdInstr)." (".implode(', ', $createdInstr).")");
        $this->line("  Socios stub creados:  ".count($createdMembers));
        $this->line("  Slots creados:        {$created}");
        $this->line("  Vínculos de roster:   {$roster}");
        if ($skipped) $this->warn("  Clases omitidas:      {$skipped}");
        foreach (array_slice($warnings, 0, 20) as $w) $this->warn("   - {$w}");

        $this->newLine();
        $this->info('Listo. Genera las sesiones para verlas en el calendario:');
        $this->line('  php artisan schedule:generate-week --date='.$monthRef->format('Y-m-d'));

        return self::SUCCESS;
    }

    /** Clave de nombre normalizada (sin acentos/espacios/puntos, mayúsculas). */
    private function keyName(string $s): string
    {
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U']);
        return preg_replace('/[^A-Z]/', '', strtoupper($s));
    }
}
