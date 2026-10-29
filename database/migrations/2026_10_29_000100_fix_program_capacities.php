<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el cupo por carril que pidió la clienta (oct 2026):
 *   - Fitness Swim: 2 -> 6
 *   - Swim Junior:  7 -> 6
 * Aplica directo a la tabla programs para que el cambio tome efecto sin tener
 * que re-sembrar. config/swimfit.php ya quedó alineado para futuros seeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('programs')->where('slug', 'fitness-swim')->update(['lane_capacity' => 6]);
        DB::table('programs')->where('slug', 'swim-junior')->update(['lane_capacity' => 6]);
    }

    public function down(): void
    {
        DB::table('programs')->where('slug', 'fitness-swim')->update(['lane_capacity' => 2]);
        DB::table('programs')->where('slug', 'swim-junior')->update(['lane_capacity' => 7]);
    }
};
