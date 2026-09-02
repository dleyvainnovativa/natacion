<?php

/*
|--------------------------------------------------------------------------
| Datos de Swim Fitness (fuente única de verdad)
|--------------------------------------------------------------------------
| Toda la información de programas, niveles y precios vive aquí. El seeder
| ProgramSeeder recorre este arreglo y llena las tablas programs /
| program_prices, así que no hay datos duplicados entre config y BD.
*/

return [
    'negocio' => [
        'nombre'    => 'Swim Fitness',
        'tagline'   => 'más que un deporte, un seguro de vida…',
        'direccion' => 'Av. 13 #3600 esq. Calle 36, Col. Nuevo Córdoba, Córdoba, Veracruz',
    ],
    'inscripcion' => 600.00,

    /*
    | Ventana de jornada para la vista de día (lienzo tipo Google Calendar).
    | El lienzo muestra SIEMPRE este rango completo. Si una clase cae fuera,
    | el controlador expande automáticamente al borde de hora (fallback).
    | En minutos desde medianoche: 7*60 = 420 (07:00), 21*60 = 1260 (21:00).
    */
    'horario' => [
        'inicio_min' => 7 * 60,   // 07:00
        'fin_min'    => 21 * 60,  // 21:00
    ],

    'programas' => [
        'swim-baby' => [
            'slug' => 'swim-baby', 'nombre' => 'Swim Baby', 'audiencia' => 'kids',
            'edad' => '4 meses a 4 años', 'icono' => 'fa-baby', 'color' => 'blue',
            'duracion_min' => 30, 'cupo_carril' => 6,
            'resumen' => 'Mamá o papá entra al agua con el pequeño. Estimulación y vínculo familiar.',
            'precios' => [
                ['concepto' => '2 días a la semana', 'dias' => 2, 'monto' => 770.00],
                ['concepto' => '3 días a la semana', 'dias' => 3, 'monto' => 915.00],
            ],
        ],
        'swim-junior' => [
            'slug' => 'swim-junior', 'nombre' => 'Swim Junior', 'audiencia' => 'kids',
            'edad' => '4 a 14 años', 'icono' => 'fa-child-reaching', 'color' => 'green',
            'duracion_min' => 45, 'cupo_carril' => 7,
            'resumen' => 'Niños aprenden a nadar en grupos de 5 a 6 por maestro.',
            'precios_grupos' => [
                ['titulo' => 'Niveles 1 y 2 (30 min)', 'precios' => [
                    ['concepto' => '2 días a la semana', 'dias' => 2, 'monto' => 770.00],
                    ['concepto' => '3 días a la semana', 'dias' => 3, 'monto' => 915.00],
                    ['concepto' => '4 días a la semana', 'dias' => 4, 'monto' => 1390.00],
                    ['concepto' => '5 días a la semana', 'dias' => 5, 'monto' => 1740.00],
                ]],
                ['titulo' => 'Niveles 3, 4 y 5 (45 min)', 'precios' => [
                    ['concepto' => '2 días a la semana', 'dias' => 2, 'monto' => 850.00],
                    ['concepto' => '3 días a la semana', 'dias' => 3, 'monto' => 970.00],
                    ['concepto' => '4 días a la semana', 'dias' => 4, 'monto' => 1450.00],
                    ['concepto' => '5 días a la semana', 'dias' => 5, 'monto' => 1820.00],
                ]],
            ],
        ],
        'swim-adultos' => [
            'slug' => 'swim-adultos', 'nombre' => 'Swim Adolescentes y Adultos', 'audiencia' => 'adults',
            'edad' => '15 años en adelante', 'icono' => 'fa-person-swimming', 'color' => 'teal',
            'duracion_min' => 50, 'cupo_carril' => 2,
            'resumen' => 'Enseñanza y acondicionamiento. Cada alumno trabaja individualmente.',
            'precios' => [
                ['concepto' => '2 días a la semana', 'dias' => 2, 'monto' => 870.00],
                ['concepto' => '3 días a la semana', 'dias' => 3, 'monto' => 1000.00],
            ],
        ],
        'fitness-swim' => [
            'slug' => 'fitness-swim', 'nombre' => 'Fitness Swim', 'audiencia' => 'adults',
            'edad' => '15 años en adelante', 'icono' => 'fa-dumbbell', 'color' => 'green',
            'duracion_min' => 50, 'cupo_carril' => 2,
            'resumen' => 'Zumba, cardio y kick boxing. No es necesario saber nadar.',
            'precios' => [
                ['concepto' => '2 días a la semana', 'dias' => 2, 'monto' => 870.00],
                ['concepto' => '3 días a la semana', 'dias' => 3, 'monto' => 1000.00],
            ],
        ],
    ],
];
