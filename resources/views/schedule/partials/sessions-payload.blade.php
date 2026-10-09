{{-- Payload de sesiones (roster por clase) disponible para TODOS los roles que
     ven el calendario, no solo quienes pueden editar. Lo consume la hoja de
     acciones (Ver alumnos) y el modal de mover socio si está presente. --}}
<script>
    window.SF_SESSIONS = {{ \Illuminate\Support\Js::from($sessionsPayload ?? []) }};
</script>
