<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Program;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa el archivo "Generador listado - Socios" (.xlsx).
 *
 * Realidades del archivo real (validadas contra la muestra de 364 socios):
 *  - El encabezado NO está en la fila 1; suele estar en la fila 4. Se busca
 *    dinámicamente la fila que contenga "Número de socio".
 *  - "Apellido 2" a veces es "X" (placeholder) -> se guarda null.
 *  - Muchas celdas traen espacios al final -> se hace trim en todo.
 *  - Puede haber filas en blanco intercaladas -> se saltan.
 *  - "Tipo socio" es una de ~31 cadenas; si no existe en membership_types se
 *    crea al vuelo usando MembershipTypeParser.
 *
 * Estrategia de escritura: upsert por socio_number (llave del negocio). Re-
 * importar el mismo archivo actualiza, no duplica.
 */
class MemberImporter
{
    /** Encabezados esperados -> clave interna. */
    private const HEADER_MAP = [
        'número de socio'            => 'socio_number',
        'numero de socio'            => 'socio_number',
        'apellido 1'                 => 'last_name_1',
        'apellido 2'                 => 'last_name_2',
        'nombre'                     => 'first_name',
        'fecha siguiente generación' => 'next_billing_date',
        'fecha siguiente generacion' => 'next_billing_date',
        'estado socio'               => 'status',
        'tipo socio'                 => 'membership_type',
        'importe cuota'              => 'fee',
    ];

    public function __construct(private MembershipTypeParser $parser) {}

    /**
     * @return array{created:int, updated:int, skipped:int, types_created:int, errors:array<string>}
     */
    public function import(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true); // claves A,B,C...

        [$headerRowIndex, $columnMap] = $this->locateHeader($rows);

        if ($headerRowIndex === null) {
            return $this->emptyResult(['No se encontró la fila de encabezados (se esperaba "Número de socio").']);
        }

        // Cache de tipos y programas para no consultar en cada fila.
        $typesByLabel = MembershipType::pluck('id', 'raw_label')
            ->mapWithKeys(fn ($id, $label) => [mb_strtoupper(trim($label)) => $id])
            ->all();
        $programsBySlug = Program::pluck('id', 'slug')->all();

        $result = $this->emptyResult();

        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue; // saltar título + encabezado
            }

            $data = $this->normalizeRow($row, $columnMap);

            if ($data === null) {
                $result['skipped']++;
                continue; // fila en blanco
            }

            if (! $data['socio_number']) {
                $result['errors'][] = "Fila {$index}: sin número de socio, se omitió.";
                $result['skipped']++;
                continue;
            }

            // Resolver / crear tipo de socio.
            $typeId = null;
            if ($data['membership_type']) {
                $key = mb_strtoupper(trim($data['membership_type']));
                if (isset($typesByLabel[$key])) {
                    $typeId = $typesByLabel[$key];
                } else {
                    $parsed = $this->parser->parse($data['membership_type']);
                    $newType = MembershipType::create([
                        'raw_label'     => trim($data['membership_type']),
                        'program_id'    => $parsed['program_slug']
                            ? ($programsBySlug[$parsed['program_slug']] ?? null)
                            : null,
                        'days_per_week' => $parsed['days_per_week'],
                        'duration_min'  => $parsed['duration_min'],
                        'special'       => $parsed['special'],
                    ]);
                    $typesByLabel[$key] = $newType->id;
                    $typeId = $newType->id;
                    $result['types_created']++;
                }
            }

            $existing = Member::where('socio_number', $data['socio_number'])->first();

            Member::updateOrCreate(
                ['socio_number' => $data['socio_number']],
                [
                    'last_name_1'        => $data['last_name_1'] ?? '',
                    'last_name_2'        => $data['last_name_2'],
                    'first_name'         => $data['first_name'] ?? '',
                    'membership_type_id' => $typeId,
                    'next_billing_date'  => $data['next_billing_date'],
                    'status'             => $data['status'] ?: 'ALTA',
                    'fee'                => $data['fee'],
                ]
            );

            $existing ? $result['updated']++ : $result['created']++;
        }

        return $result;
    }

    /**
     * Busca la fila de encabezados y arma el mapa columna-letra -> clave interna.
     *
     * @return array{0: int|null, 1: array<string,string>}
     */
    private function locateHeader(array $rows): array
    {
        foreach ($rows as $rowIndex => $row) {
            $map = [];
            foreach ($row as $col => $value) {
                if ($value === null) {
                    continue;
                }
                $key = mb_strtolower(trim((string) $value));
                if (isset(self::HEADER_MAP[$key])) {
                    $map[$col] = self::HEADER_MAP[$key];
                }
            }
            // Se considera encontrada si aparece la llave del negocio.
            if (in_array('socio_number', $map, true)) {
                return [$rowIndex, $map];
            }
        }

        return [null, []];
    }

    /**
     * Normaliza una fila. Devuelve null si está en blanco.
     *
     * @return array<string,mixed>|null
     */
    private function normalizeRow(array $row, array $columnMap): ?array
    {
        $data = array_fill_keys(array_values(self::HEADER_MAP), null);
        $allEmpty = true;

        foreach ($columnMap as $col => $key) {
            $raw = $row[$col] ?? null;

            if (is_string($raw)) {
                $raw = trim($raw);
                if ($raw === '') {
                    $raw = null;
                }
            }

            if ($raw !== null) {
                $allEmpty = false;
            }

            $data[$key] = $this->castValue($key, $raw);
        }

        if ($allEmpty) {
            return null;
        }

        // "X" en apellido 2 es un placeholder, no un apellido real.
        if ($data['last_name_2'] !== null && mb_strtoupper($data['last_name_2']) === 'X') {
            $data['last_name_2'] = null;
        }

        return $data;
    }

    private function castValue(string $key, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($key) {
            'socio_number' => (int) $raw,
            'fee'          => is_numeric($raw) ? (float) $raw : null,
            'next_billing_date' => $this->parseDate($raw),
            default        => (string) $raw,
        };
    }

    /** Acepta serial de Excel, DateTime, o cadena. */
    private function parseDate(mixed $raw): ?string
    {
        try {
            if (is_numeric($raw)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($raw))->toDateString();
            }
            if ($raw instanceof \DateTimeInterface) {
                return Carbon::instance($raw)->toDateString();
            }
            return Carbon::parse((string) $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function emptyResult(array $errors = []): array
    {
        return [
            'created'       => 0,
            'updated'       => 0,
            'skipped'       => 0,
            'types_created' => 0,
            'errors'        => $errors,
        ];
    }
}
