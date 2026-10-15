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
    public function index(Request $request): JsonResponse
    {
        $this->authorize('document-view');

        $rows = Document::with('uploader:id,name')
            ->when($request->filled('attachable_type'), fn ($q) => $q->where('attachable_type', $request->query('attachable_type')))
            ->when($request->filled('attachable_id'), fn ($q) => $q->where('attachable_id', (int) $request->query('attachable_id')))
            ->when($request->filled('group_key'), fn ($q) => $q->where('group_key', $request->query('group_key')))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Document $d) => $this->present($d));

        return response()->json($rows);
    }

    /** All versions of one document's group, newest first. */
    public function versions(Document $document): JsonResponse
    {
        $this->authorize('document-view');

        $rows = Document::where('group_key', $document->group_key)
            ->with('uploader:id,name')
            ->orderByDesc('version')
            ->get()
            ->map(fn (Document $d) => $this->present($d));

        return response()->json($rows);
    }

    public function store(Request $request, CurrentCompany $current): JsonResponse
    {
        $this->authorize('document-manage');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'attachable_type' => ['nullable', 'string', 'max:64'],
            'attachable_id' => ['nullable', 'integer'],
            'replaces' => ['nullable', 'integer'], // document id this is a new version of
        ]);

        $file = $request->file('file');
        $companyId = $current->idOrFail();
        $path = $file->store("documents/{$companyId}");

        // Versioning: a replacement inherits the group and bumps the version.
        $version = 1;
        $groupKey = \Illuminate\Support\Str::uuid()->toString();
        $attachableType = $data['attachable_type'] ?? null;
        $attachableId = $data['attachable_id'] ?? null;

        if (! empty($data['replaces'])) {
            $prev = Document::find($data['replaces']); // company-scoped
            if ($prev !== null) {
                $version = (int) $prev->version + 1;
                $groupKey = $prev->group_key ?: $groupKey;
                $attachableType ??= $prev->attachable_type;
                $attachableId ??= $prev->attachable_id;
            }
        }

        $document = Document::create([
            'company_id' => $companyId,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'category' => $data['category'] ?? null,
            'version' => $version,
            'group_key' => $groupKey,
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($this->present($document), 201);
    }

    private function present(Document $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'filename' => $d->filename,
            'mime' => $d->mime,
            'size' => $d->size,
            'category' => $d->category,
            'version' => $d->version,
            'group_key' => $d->group_key,
            'attachable_type' => $d->attachable_type,
            'attachable_id' => $d->attachable_id,
            'uploaded_by' => $d->uploader?->name,
            'created_at' => $d->created_at,
        ];
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
