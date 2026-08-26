<?php

namespace App\Services;

/**
 * Interpreta las cadenas de "Tipo socio" del archivo Excel (31 variantes
 * como "ADULTO 3 DIAS", "CV 4 SEM 30 MIN 2 DIAS", "BECA") y extrae:
 * programa, días por semana, duración y si es un caso especial.
 *
 * Este parser lo usan tanto el seeder (T0) como el importador de socios (T1),
 * por eso vive en un solo lugar. Validado contra las 31 cadenas reales.
 */
class MembershipTypeParser
{
    public function parse(string $label): array
    {
        $L = mb_strtoupper(trim($label));

        // Días por semana: "3 DIAS", "1 DIA"
        $days = null;
        if (preg_match('/(\d+)\s*D[IÍ]A/u', $L, $m)) {
            $days = (int) $m[1];
        }

        // Duración: "30 MIN", "45 MIN" (presente en la serie CV y algunos JUNIOR)
        $duration = null;
        if (preg_match('/(\d+)\s*MIN/u', $L, $m)) {
            $duration = (int) $m[1];
        } elseif (str_contains($L, 'JUNIOR 45')) {
            $duration = 45;
        } elseif (str_contains($L, 'JUNIOR 30')) {
            $duration = 30;
        }

        // Casos especiales: no mapean a programa ni a días estándar.
        $special = false;
        foreach (['BECA', 'ACUERDO', 'PAGO POR CLASE'] as $kw) {
            if (str_contains($L, $kw)) {
                $special = true;
            }
        }

        // Programa (solo para las 4 disciplinas del catálogo).
        $program = null;
        if (str_starts_with($L, 'BABY')) {
            $program = 'swim-baby';
        } elseif (str_starts_with($L, 'JUNIOR')) {
            $program = 'swim-junior';
        } elseif (str_starts_with($L, 'ADULTO') || str_starts_with($L, 'INDIVIDUAL')) {
            $program = 'swim-adultos';
        } elseif (str_starts_with($L, 'FITNESS')) {
            $program = 'fitness-swim';
        }
        // CV (cardiovascular) y NADO LIBRE quedan sin programa a propósito:
        // conservan raw_label + días, pero no son parte del catálogo de 4.

        return [
            'program_slug' => $program,
            'days_per_week' => $days,
            'duration_min' => $duration,
            'special' => $special,
        ];
    }
}
