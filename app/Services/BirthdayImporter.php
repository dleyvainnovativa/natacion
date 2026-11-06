<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Carga SÓLO la fecha de nacimiento de los socios, emparejando por número de
 * socio. NUNCA crea socios nuevos: si el número no existe, se reporta y se
 * omite. Pensado para un archivo aparte (Excel o CSV) que la clienta prepare
 * con los cumpleaños, sin tocar nada más del socio.
 *
 * Detección de columnas flexible: busca un encabezado que parezca "número de
 * socio" y otro que parezca "fecha de nacimiento", en cualquier fila (el
 * encabezado no siempre está en la fila 1).
 */
class BirthdayImporter
{
    /** Fragmentos que identifican la columna de número de socio. */
    private const SOCIO_HINTS = ['numero de socio', 'número de socio', 'num socio', 'no socio', 'no. socio', 'socio'];

    /** Fragmentos que identifican la columna de fecha de nacimiento. */
    private const BIRTH_HINTS = ['fecha de nacimiento', 'fecha nacimiento', 'nacimiento', 'f. nac', 'f nac', 'fec nac', 'cumple', 'cumpleaños', 'cumpleanos', 'birth', 'birthday', 'dob'];

    /**
     * @param  bool  $dryRun  Si es true, cuenta lo que cambiaría pero NO guarda.
     * @return array{matched:int, updated:int, not_found:int, skipped:int, errors:array<string>}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $result = ['matched' => 0, 'updated' => 0, 'not_found' => 0, 'skipped' => 0, 'errors' => []];

        $sheet = IOFactory::load($path)->getActiveSheet();
        // formatData=false: las fechas llegan como serial de Excel (número), que
        // parseamos de forma determinista; así no dependemos del formato de celda.
        $rows  = $sheet->toArray(null, true, false, true); // claves A,B,C...

        [$headerRowIndex, $socioCol, $birthCol] = $this->locateColumns($rows);

        if ($headerRowIndex === null) {
            $result['errors'][] = 'No se encontró la columna de número de socio. '
                . 'Asegúrate de que el archivo tenga un encabezado con "Número de socio".';
            return $result;
        }
        if ($birthCol === null) {
            $result['errors'][] = 'No se encontró la columna de fecha de nacimiento '
                . '(se aceptan encabezados como "Fecha de nacimiento", "Nacimiento", "Cumpleaños").';
            return $result;
        }

        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue;
            }

            $socioRaw = $row[$socioCol] ?? null;
            $birthRaw = $row[$birthCol] ?? null;

            // Fila sin número de socio → en blanco, se salta en silencio.
            if ($socioRaw === null || trim((string) $socioRaw) === '') {
                $result['skipped']++;
                continue;
            }

            // El número de socio puede venir como float (119.0) desde Excel.
            $socio = is_numeric($socioRaw)
                ? (int) round((float) $socioRaw)
                : (int) preg_replace('/\D/', '', (string) $socioRaw);
            if ($socio <= 0) {
                $result['skipped']++;
                continue;
            }

            $birth = $this->parseDate($birthRaw);
            if ($birth === null) {
                $result['errors'][] = "Socio {$socio}: fecha de nacimiento vacía o no reconocida, se omitió.";
                $result['skipped']++;
                continue;
            }

            $member = Member::where('socio_number', $socio)->first();
            if (! $member) {
                $result['not_found']++;
                $result['errors'][] = "Socio {$socio}: no existe en el sistema, se omitió (no se crean socios nuevos).";
                continue;
            }

            $result['matched']++;

            // Sólo escribe si cambia, para no tocar updated_at sin necesidad.
            if ($member->birth_date?->toDateString() !== $birth) {
                if (! $dryRun) {
                    $member->birth_date = $birth;
                    $member->save();
                }
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * Encuentra la fila de encabezados y las letras de columna de socio y fecha.
     *
     * @return array{0:int|null,1:string|null,2:string|null} [headerRow, socioCol, birthCol]
     */
    private function locateColumns(array $rows): array
    {
        $fallback = [null, null, null];   // primer renglón con socio, por si no hay fecha

        foreach ($rows as $rowIndex => $row) {
            $socioCol = null;
            $birthCol = null;

            foreach ($row as $col => $value) {
                if ($value === null) {
                    continue;
                }
                $norm = $this->norm((string) $value);
                if ($norm === '') {
                    continue;
                }

                if ($socioCol === null && $this->matchesHint($norm, self::SOCIO_HINTS)) {
                    $socioCol = $col;
                }
                if ($birthCol === null && $this->matchesHint($norm, self::BIRTH_HINTS)) {
                    $birthCol = $col;
                }
            }

            // El encabezado válido tiene AMBAS columnas en el mismo renglón. Esto
            // evita que el título "Socios" (que contiene "socio") se tome como
            // encabezado y deje la fecha sin encontrar.
            if ($socioCol !== null && $birthCol !== null) {
                return [$rowIndex, $socioCol, $birthCol];
            }

            // Recordar el primer renglón que sí trae socio, para dar un error
            // claro ("falta la columna de fecha") si nunca aparecen las dos.
            if ($socioCol !== null && $fallback[0] === null) {
                $fallback = [$rowIndex, $socioCol, null];
            }
        }

        return $fallback;
    }

    private function matchesHint(string $norm, array $hints): bool
    {
        foreach ($hints as $h) {
            if (str_contains($norm, $h)) {
                return true;
            }
        }
        return false;
    }

    /** minúsculas, sin acentos, espacios colapsados. */
    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * Acepta serial de Excel, DateTime, o cadena. Para cadenas prueba primero los
     * formatos mexicanos (d/m/Y) antes del parseo genérico, para no confundir
     * día y mes.
     */
    private function parseDate(mixed $raw): ?string
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        try {
            if (is_numeric($raw)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
            }
            if ($raw instanceof \DateTimeInterface) {
                return Carbon::instance($raw)->toDateString();
            }

            $s = trim((string) $raw);
            foreach (['d/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y', 'Y-m-d', 'Y/m/d', 'd.m.Y'] as $fmt) {
                $dt = \DateTime::createFromFormat('!' . $fmt, $s);
                if ($dt !== false) {
                    return Carbon::instance($dt)->toDateString();
                }
            }

            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
