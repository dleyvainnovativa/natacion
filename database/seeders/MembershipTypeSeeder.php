<?php

namespace Database\Seeders;

use App\Models\MembershipType;
use App\Models\Program;
use App\Services\MembershipTypeParser;
use Illuminate\Database\Seeder;

/**
 * Siembra los 31 tipos de socio observados en el archivo real. El importador
 * de socios (T1) creará cualquier tipo nuevo que aparezca, pero dejamos los
 * conocidos listos para que las relaciones funcionen desde el arranque.
 */
class MembershipTypeSeeder extends Seeder
{
    /** Tipos observados en Generador listado - Socios (julio 2026). */
    private const LABELS = [
        'ADULTO 2 DIAS', 'ADULTO 3 DIAS', 'ADULTO 4 DIAS', 'ADULTO 5 DIAS',
        'BABY 1 DIA', 'BABY 2 DIAS', 'BABY 3 DIAS', 'BECA',
        'CV 4 SEM 30 MIN 2 DIAS', 'CV 4 SEM 30 MIN 3 DIAS',
        'CV 5 SEM 30 MIN 2 DIAS', 'CV 5 SEM 30 MIN 4 DIAS', 'CV 5 SEM 45 MIN 5 DIAS',
        'CV 6 SEM 30 MIN 2 DIAS', 'CV 6 SEM 30 MIN 3 DIAS', 'CV 6 SEM 30 MIN 5 DIAS',
        'FITNESS 2 DIAS', 'FITNESS 3 DIAS', 'INDIVIDUAL 2 DIAS',
        'JUNIOR 30 / 1 DIA', 'JUNIOR 30/ 2 DIAS', 'JUNIOR 30/ 3 DIAS',
        'JUNIOR 30/ 4 DIAS', 'JUNIOR 30/ 5 DIAS', 'JUNIOR 45 / 3 DIAS',
        'JUNIOR 45/ 1 DIA', 'JUNIOR 45/ 2 DIAS',
        'NADO LIBRE 2 DIAS', 'NADO LIBRE 3 DIAS',
        'PAGO POR CLASE ADULTOS', 'SWIM ACUERDO',
    ];

    public function run(MembershipTypeParser $parser): void
    {
        $programsBySlug = Program::pluck('id', 'slug');

        foreach (self::LABELS as $label) {
            $parsed = $parser->parse($label);

            MembershipType::updateOrCreate(
                ['raw_label' => $label],
                [
                    'program_id'    => $parsed['program_slug']
                        ? ($programsBySlug[$parsed['program_slug']] ?? null)
                        : null,
                    'days_per_week' => $parsed['days_per_week'],
                    'duration_min'  => $parsed['duration_min'],
                    'special'       => $parsed['special'],
                ]
            );
        }
    }
}
