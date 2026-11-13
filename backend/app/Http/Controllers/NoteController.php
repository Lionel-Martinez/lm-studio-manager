<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\AuditLog;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'scope' => ['nullable', 'in:general'],
            'client_id' => ['nullable', 'integer'],
        ]);

        $search = trim((string) $request->query('search', ''));
        // Se escapan % y _ para que se busquen como texto común.
        $like = '%'.addcslashes($search, '%_\\').'%';

        $notes = Note::query()
            ->with(['client', 'project'])
            ->when($request->query('scope') === 'general', fn ($q) => $q->whereNull('client_id'))
            ->when($request->query('client_id'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like)))
            ->latest('updated_at')
            ->paginate(20);

        return NoteResource::collection($notes);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = Note::create($request->validated());

        AuditLog::record('note.created', $note, ['title' => $note->title]);

        return NoteResource::make($note->load(['client', 'project']))->response()->setStatusCode(201);
    }

    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $note->update($request->validated());

        AuditLog::record('note.updated', $note, ['title' => $note->title]);

        return NoteResource::make($note->load(['client', 'project']));
    }

    public function destroy(Note $note): Response
    {
        $note->delete();

        AuditLog::record('note.deleted', $note, ['title' => $note->title]);

        return response()->noContent();
    }
}
