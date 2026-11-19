<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nivel dentro de un programa (p. ej. los niveles de Swim Junior: Náufragos 1-3,
 * Super Boyas, Salvavidas, Torpedos, Super Torpedos). El nivel define nombre y
 * duración de la clase; el cupo lo pone el programa.
 */
class ProgramLevel extends Model
{
    /**
     * Paleta de 10 tonos para colorear el nivel en el calendario. La clave es la
     * clase CSS (chip-<clave>, definida en theme.css); el valor es la etiqueta
     * para la UI. Si el nivel no tiene color, el bloque usa el del programa.
     */
    public const TONES = [
        'teal'   => 'Verde azulado',
        'green'  => 'Verde',
        'blue'   => 'Azul',
        'cyan'   => 'Cian',
        'violet' => 'Violeta',
        'pink'   => 'Rosa',
        'rose'   => 'Rojo suave',
        'orange' => 'Naranja',
        'amber'  => 'Ámbar',
        'slate'  => 'Gris',
    ];

    protected $fillable = ['program_id', 'slug', 'name', 'color', 'duration_min', 'position', 'active'];

    protected $casts = [
        'duration_min' => 'integer',
        'position'     => 'integer',
        'active'       => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}
