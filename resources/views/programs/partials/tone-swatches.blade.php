{{-- Selector de 10 tonos para un nivel. $current = tono actual ('' = sin color,
     usa el del programa). El radio se llama "color" para enviarse con el form. --}}
@php $current = $current ?? null; @endphp
<span class="lvl-swatch-set" title="Color del nivel en el calendario">
    <label class="lvl-swatch lvl-swatch-none" title="Usar color del programa">
        <input type="radio" name="color" value="" @checked(empty($current))>
        <span class="lvl-dot"><i class="fa-solid fa-ban"></i></span>
    </label>
    @foreach (\App\Models\ProgramLevel::TONES as $key => $label)
        <label class="lvl-swatch" title="{{ $label }}">
            <input type="radio" name="color" value="{{ $key }}" @checked($current === $key)>
            <span class="lvl-dot chip-{{ $key }}"></span>
        </label>
    @endforeach
</span>
