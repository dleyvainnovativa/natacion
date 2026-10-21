{{-- Panel lateral de socios para el calendario. Cada socio muestra cuántas
     clases tiene esta semana y se puede arrastrar a una clase para inscribirlo.
     Variable: $panelMembers = [['id','name','socio','count'], ...] --}}
<aside class="member-panel" id="memberPanel" aria-label="Socios">
    <div class="mp-head">
        <div class="mp-title"><i class="fa-regular fa-id-card"></i> Socios</div>
        <button type="button" class="mp-close" title="Ocultar panel"
                onclick="document.querySelector('.cal-layout')?.classList.toggle('mp-collapsed')">
            <i class="fa-solid fa-angles-right"></i>
        </button>
    </div>
    <input type="text" class="form-control form-control-sm mp-search"
           placeholder="Buscar socio…" oninput="SF.filterMemberPanel(this)">
    <div class="mp-list" id="mpList">
        @foreach ($panelMembers as $m)
            <div class="mp-member" draggable="true"
                 data-member-id="{{ $m['id'] }}"
                 data-text="{{ mb_strtolower($m['name'] . ' ' . $m['socio']) }}">
                <span class="mp-grip"><i class="fa-solid fa-grip-vertical"></i></span>
                <span class="mp-name">{{ $m['name'] }}</span>
                <span class="mp-count {{ $m['count'] ? '' : 'is-zero' }}"
                      title="Clases esta semana">{{ $m['count'] }}</span>
            </div>
        @endforeach
    </div>
    <p class="mp-hint">Arrastra un socio a una clase para inscribirlo.</p>
</aside>
