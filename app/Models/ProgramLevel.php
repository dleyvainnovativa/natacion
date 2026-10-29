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
    protected $fillable = ['program_id', 'slug', 'name', 'duration_min', 'position', 'active'];

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
