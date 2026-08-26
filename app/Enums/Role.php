<?php

namespace App\Enums;

/**
 * Los cuatro roles del sistema. El valor string se guarda en users.role.
 * Todos pueden VER el horario; los permisos por acción viven en el Gate
 * (ver AuthServiceProvider) para no repetir lógica en cada controlador.
 */
enum Role: string
{
    case Admin        = 'admin';
    case Coordinator  = 'coordinator';
    case Instructor   = 'instructor';
    case Receptionist = 'receptionist';

    /** Etiqueta legible en español para la UI. */
    public function label(): string
    {
        return match ($this) {
            self::Admin        => 'Administrador',
            self::Coordinator  => 'Coordinador',
            self::Instructor   => 'Instructor',
            self::Receptionist => 'Recepcionista',
        };
    }

    /** Roles que pueden mover/agendar clases. */
    public static function canMoveClasses(): array
    {
        return [self::Admin, self::Coordinator, self::Receptionist];
    }
}
