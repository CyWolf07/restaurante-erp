<?php

namespace App\Http\Controllers\Programmer;

use App\Http\Controllers\Controller;
use App\Models\InventoryCsvUpload;
use App\Services\CommerceInventoryImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryImportController extends Controller
{
    public function show()
    {
        $uploads = InventoryCsvUpload::with('user')
            ->latest()
            ->paginate(15);

        return view('programmer.inventory-import', [
            'uploads'     => $uploads,
            'points'      => config('restaurant.points'),
            'families'    => config('restaurant.families'),
            'departments' => config('restaurant.departments'),
        ]);
    }

    /** Solo guarda el archivo en el servidor (sin importar aún). */
    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => $this->fileRules(),
        ]);

        $upload = $this->storeUploadedFile($request->file('csv_file'));

        return back()->with('success', "Archivo «{$upload->original_name}» cargado correctamente.");
    }

    /** Sube el archivo e importa al inventario en un solo paso. */
    public function import(Request $request, CommerceInventoryImportService $importer)
    {
        $request->validate([
            'csv_file'        => $this->fileRules(),
            'update_existing' => 'sometimes|boolean',
        ]);

        $upload = $this->storeUploadedFile($request->file('csv_file'));

        return $this->runImport($upload, $importer, $request->boolean('update_existing', true));
    }

    /** Importa un archivo previamente cargado. */
    public function importStored(
        Request $request,
        InventoryCsvUpload $upload,
        CommerceInventoryImportService $importer
    ) {
        if (!Storage::disk('local')->exists($upload->stored_path)) {
            return back()->with('error', 'El archivo ya no existe en el servidor.');
        }

        return $this->runImport($upload, $importer, $request->boolean('update_existing', true));
    }

    public function destroy(InventoryCsvUpload $upload)
    {
        $name = $upload->original_name;
        $upload->deleteStoredFile();
        $upload->delete();

        return back()->with('success', "Archivo «{$name}» eliminado.");
    }

    public function downloadTemplate(CommerceInventoryImportService $importer): StreamedResponse
    {
        $filename = 'plantilla_inventario_comercial.csv';

        return response()->streamDownload(function () use ($importer) {
            echo "\xEF\xBB\xBF";
            echo $importer->templateCsv();
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function fileRules(): array
    {
        return [
            'required',
            'file',
            'max:10240',
            'mimes:csv,txt',
            'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
        ];
    }

    private function storeUploadedFile(UploadedFile $file): InventoryCsvUpload
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $storedName = uniqid('inv_', true) . '.' . $extension;
        $path = $file->storeAs('inventory-csv', $storedName, 'local');

        return InventoryCsvUpload::create([
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => $path,
            'mime_type'     => $file->getMimeType(),
            'extension'     => $extension,
            'size_bytes'    => $file->getSize(),
            'user_id'       => Auth::id(),
        ]);
    }

    private function runImport(
        InventoryCsvUpload $upload,
        CommerceInventoryImportService $importer,
        bool $updateExisting
    ) {
        $fullPath = $upload->fullPath();
        $fakeFile = new UploadedFile(
            $fullPath,
            $upload->original_name,
            $upload->mime_type,
            null,
            true
        );

        $result = $importer->import($fakeFile, $updateExisting);

        $upload->update([
            'imported_at'    => now(),
            'import_summary' => [
                'created'    => $result['created'],
                'updated'    => $result['updated'],
                'skipped'    => $result['skipped'],
                'total_rows' => $result['total_rows'],
                'errors'     => count($result['errors']),
            ],
        ]);

        $message = "«{$upload->original_name}»: {$result['created']} creados, {$result['updated']} actualizados, {$result['skipped']} omitidos.";

        if (count($result['errors']) > 0) {
            $preview = array_slice($result['errors'], 0, 15);
            session()->flash('import_errors', $result['errors']);

            return back()
                ->with('warning', $message . ' Se encontraron ' . count($result['errors']) . ' advertencia(s).')
                ->with('import_preview', $preview);
        }

        return back()->with('success', $message);
    }
}
