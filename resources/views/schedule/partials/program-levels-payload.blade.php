{{-- Mapa programa→niveles para los <select> de nivel (lo consume el JS al
     cambiar de programa en los modales de clase). --}}
<script>
    window.SF_PROGRAM_LEVELS = {{ \Illuminate\Support\Js::from($programLevels ?? []) }};
</script>
