<?php

namespace App\Enums;

/**
 * Días de la semana en ISO-8601 (1=lunes ... 7=domingo), que es lo que usa
 * Carbon::isoWeekday(). El club opera de lunes a viernes.
 */
enum Weekday: int
{
    case Monday    = 1;
    case Tuesday   = 2;
    case Wednesday = 3;
    case Thursday  = 4;
    case Friday    = 5;
    case Saturday  = 6;
    case Sunday    = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday    => 'Lunes',
            self::Tuesday   => 'Martes',
            self::Wednesday => 'Miércoles',
            self::Thursday  => 'Jueves',
            self::Friday    => 'Viernes',
            self::Saturday  => 'Sábado',
            self::Sunday    => 'Domingo',
        };
    }

    /** Días laborables del club. */
    public static function operating(): array
    {
        return [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday];
    }
}
