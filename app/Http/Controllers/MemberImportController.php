<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMembersRequest;
use App\Services\MemberImporter;
use Illuminate\Support\Facades\DB;

class MemberImportController extends Controller
{
    public function show()
    {
        $this->authorize('import-members');

        return view('members.import');
    }

    public function store(ImportMembersRequest $request, MemberImporter $importer)
    {
        $path = $request->file('file')->getRealPath();

        // Todo o nada: si algo truena a media importación, no queda a medias.
        $result = DB::transaction(fn () => $importer->import($path));

        $summary = sprintf(
            '%d nuevos, %d actualizados, %d omitidos, %d tipos de socio creados.',
            $result['created'], $result['updated'], $result['skipped'], $result['types_created']
        );

        return redirect()->route('members.index')
            ->with('ok', "Importación lista: {$summary}")
            ->with('import_errors', $result['errors']);
    }
}
