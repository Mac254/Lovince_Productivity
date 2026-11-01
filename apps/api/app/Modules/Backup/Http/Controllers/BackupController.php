<?php

namespace App\Modules\Backup\Http\Controllers;

use App\Modules\Backup\Http\Requests\ImportBackupRequest;
use App\Modules\Backup\Services\BackupExporter;
use App\Modules\Backup\Services\BackupImporter;
use App\Modules\Backup\Services\BackupRules;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function export(Request $request, BackupExporter $exporter): JsonResponse
    {
        $name = 'focus-backup-'.today()->toDateString().'.json';

        return response()
            ->json($exporter->for($request->user()), options: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ->header('Content-Disposition', "attachment; filename=\"{$name}\"");
    }

    public function import(ImportBackupRequest $request, BackupRules $rules, BackupImporter $importer): JsonResponse
    {
        return response()->json(['data' => $importer->import($request->user(), $request->backup($rules))]);
    }
}
