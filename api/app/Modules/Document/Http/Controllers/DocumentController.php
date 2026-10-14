<?php

namespace App\Modules\Document\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Document\Models\Document;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Document repository. Files are stored on the private local disk under a
 * per-company folder; downloads stream through the app so tenant isolation and
 * authorization are always enforced (files are never publicly served).
 */
class DocumentController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('document-view');

        $rows = Document::with('uploader:id,name')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'filename' => $d->filename,
                'mime' => $d->mime,
                'size' => $d->size,
                'category' => $d->category,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at,
            ]);

        return response()->json($rows);
    }

    public function store(Request $request, CurrentCompany $current): JsonResponse
    {
        $this->authorize('document-manage');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
        ]);

        $file = $request->file('file');
        $companyId = $current->idOrFail();
        $path = $file->store("documents/{$companyId}");

        $document = Document::create([
            'company_id' => $companyId,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => $data['category'] ?? null,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json(['id' => $document->id, 'title' => $document->title], 201);
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('document-view');
        abort_unless(Storage::exists($document->path), 404);

        return Storage::download($document->path, $document->filename);
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('document-manage');

        if (Storage::exists($document->path)) {
            Storage::delete($document->path);
        }
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
