<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\Lane;
use App\Models\Member;
use App\Models\Pool;
use App\Models\Program;
use App\Models\ScheduleSlot;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Importa el archivo HORARIO (.xlsx) hacia schedule_slots + rosters.
 *
 * Estructura del archivo (validada contra el real):
 *  - Índice de clases en columnas Z..AC: HORA | DIAS | MAESTRO | NIVEL.
 *    En realidad: Z = hora, AA = patrón de días (LMV/LM/MJ), AB = instructor,
 *    AC = nivel/programa.
 *  - Cada fila de índice encabeza un "bloque"; dentro del bloque, las columnas
 *    izquierdas (A, I, Q) listan socio# por día-columna = el roster.
 *
 * Cada clase se expande a un slot por día del patrón (LMV -> lunes+miércoles+
 * viernes). El roster del bloque se adjunta a todos esos slots.
 *
 * Idempotente vía un flag: borra los slots importados previos (source='horario')
 * antes de re-importar, para no duplicar.
 */
class ScheduleImporter
{
    // Columnas 0-based del índice de clases.
    private const COL_HORA = 25;   // Z
    private const COL_DIAS = 26;   // AA
    private const COL_MAESTRO = 27; // AB
    private const COL_NIVEL = 28;  // AC

    // Columnas de socio# por día dentro de un bloque (A, I, Q).
    private const ROSTER_NUM_COLS = [0, 8, 16];

    // Patrón de días -> weekdays ISO.
    private const DAY_PATTERNS = [
        'LMV' => [1, 3, 5],
        'LM'  => [1, 3],
        'MJ'  => [2, 4],
    ];

    /**
     * @return array{classes:int, slots:int, roster_links:int, skipped:int, warnings:array<string>}
     */
    public function import(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false); // 0-based arrays

        $indexRows = $this->findIndexRows($rows);

        $programsBySlug = Program::pluck('id', 'slug')->all();
        $membersBySocio = Member::pluck('id', 'socio_number')->all();
        $lanes = Lane::orderBy('position')->get();

        $result = ['classes' => 0, 'slots' => 0, 'roster_links' => 0, 'skipped' => 0, 'warnings' => []];

        DB::transaction(function () use ($rows, $indexRows, $programsBySlug, $membersBySocio, $lanes, &$result) {
            // Idempotencia: quitar slots importados antes.
            ScheduleSlot::where('source', 'horario')->delete();

            $instructorCache = [];

            foreach ($indexRows as $k => $ir) {
                $row = $rows[$ir];
                $time    = $this->parseTime($row[self::COL_HORA] ?? null);
                $pattern = strtoupper(trim((string) ($row[self::COL_DIAS] ?? '')));
                $rawInstr = (string) ($row[self::COL_MAESTRO] ?? '');
                $nivel   = (string) ($row[self::COL_NIVEL] ?? '');

                $weekdays = self::DAY_PATTERNS[$pattern] ?? [];
                if (! $time || ! $weekdays) {
                    $result['skipped']++;
                    continue;
                }

                $programId = $this->resolveProgram($nivel, $programsBySlug);
                $duration  = $programId
                    ? (Program::find($programId)?->duration_min ?? 45)
                    : 45;

                $instructorId = $this->resolveInstructor($rawInstr, $instructorCache);

                // Rango del bloque para el roster.
                $blockEnd = $indexRows[$k + 1] ?? count($rows);
                $socioNumbers = $this->rosterSocios($rows, $ir, $blockEnd);

                $memberIds = [];
                foreach ($socioNumbers as $sn) {
                    if (isset($membersBySocio[$sn])) {
                        $memberIds[$membersBySocio[$sn]] = true;
                    }
                }
                $memberIds = array_keys($memberIds);

                // Un slot por día del patrón; el roster va a todos.
                $laneId = $lanes->first()?->id;
                foreach ($weekdays as $wd) {
                    $slot = ScheduleSlot::create([
                        'program_id'    => $programId,
                        'instructor_id' => $instructorId,
                        'lane_id'       => $laneId,
                        'weekday'       => $wd,
                        'start_time'    => $time,
                        'duration_min'  => $duration,
                        'active'        => true,
                        'source'        => 'horario',
                    ]);

                    if ($memberIds) {
                        $slot->members()->sync($memberIds);
                        $result['roster_links'] += count($memberIds);
                    }
                    $result['slots']++;
                }

                $result['classes']++;
            }
        });

        return $result;
    }

    /** Filas que encabezan una clase (columna HORA con una hora real). */
    private function findIndexRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $v = $row[self::COL_HORA] ?? null;
            if ($v === null) {
                continue;
            }
            $s = trim((string) $v);
            if ($s === '' || strtoupper($s) === 'HORA') {
                continue;
            }
            if (preg_match('/^\d{1,2}:\d{2}/', $s)) {
                $out[] = $i;
            }
        }
        return $out;
    }

    /** "07:00:00" o datetime -> "07:00". */
    private function parseTime(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if (preg_match('/(\d{1,2}):(\d{2})/', $s, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        return null;
    }

    /** Nivel (SW AD, JN 30, FIT SW, BB...) -> program_id. */
    private function resolveProgram(string $nivel, array $programsBySlug): ?int
    {
        $L = strtoupper($nivel);
        $slug = match (true) {
            str_contains($L, 'BB'), str_contains($L, 'BABY')       => 'swim-baby',
            str_starts_with($L, 'JN'), str_contains($L, 'JUNIOR')  => 'swim-junior',
            str_contains($L, 'FIT')                                => 'fitness-swim',
            str_contains($L, 'ADU'), str_contains($L, 'AD'),
            str_contains($L, 'IND')                                => 'swim-adultos',
            default                                                => null,
        };
        return $slug ? ($programsBySlug[$slug] ?? null) : null;
    }

    /**
     * Nombre de instructor -> Instructor (crea si no existe). Normaliza combos
     * y espacios: "C. EDUARDO" y "C.EDUARDO" son el mismo; "JESUS/ IVAN" toma
     * el primero.
     */
    private function resolveInstructor(string $raw, array &$cache): ?int
    {
        $name = trim(preg_split('/[\/(\-]/', $raw)[0] ?? '');
        // Normaliza espacios internos: "C.  EDUARDO" -> "C. EDUARDO"
        $name = preg_replace('/\s+/', ' ', $name);
        // Une "C.EDUARDO" y "C. EDUARDO"
        $key = strtoupper(str_replace('. ', '.', $name));
        if ($name === '') {
            return null;
        }
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $instructor = Instructor::firstOrCreate(
            ['name' => ucwords(strtolower($name))],
            ['pay_per_class' => 150.00, 'active' => true]
        );
        return $cache[$key] = $instructor->id;
    }

    /** Socio# dentro de un bloque (columnas A, I, Q). */
    private function rosterSocios(array $rows, int $start, int $end): array
    {
        $out = [];
        for ($i = $start; $i < $end; $i++) {
            $row = $rows[$i] ?? [];
            foreach (self::ROSTER_NUM_COLS as $col) {
                $v = $row[$col] ?? null;
                if ($v === null) {
                    continue;
                }
                $s = trim((string) $v);
                if (preg_match('/^\d{2,5}$/', $s)) {
                    $out[(int) $s] = true;
                }
            }
        }
        return array_keys($out);
    }
}
