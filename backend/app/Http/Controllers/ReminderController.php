<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReminderRequest;
use App\Http\Requests\UpdateReminderRequest;
use App\Models\AuditLog;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReminderController extends Controller
{
    public function store(StoreReminderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $done = (bool) ($data['done'] ?? false);
        unset($data['done']);

        $reminder = Reminder::create($data + ['done_at' => $done ? now() : null]);

        AuditLog::record('reminder.created', $reminder, ['title' => $reminder->title]);

        return response()->json(['data' => $this->present($reminder)], 201);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): JsonResponse
    {
        $data = $request->validated();
        $done = (bool) ($data['done'] ?? false);
        unset($data['done']);

        // Si ya estaba hecho y sigue hecho, se conserva su fecha de cierre original.
        $data['done_at'] = $done ? ($reminder->done_at ?? now()) : null;

        $reminder->update($data);

        AuditLog::record('reminder.updated', $reminder, ['title' => $reminder->title, 'done' => $done]);

        return response()->json(['data' => $this->present($reminder->refresh())]);
    }

    public function destroy(Reminder $reminder): Response
    {
        $reminder->delete();

        AuditLog::record('reminder.deleted', $reminder, ['title' => $reminder->title]);

        return response()->noContent();
    }

    private function present(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'client_id' => $reminder->client_id,
            'title' => $reminder->title,
            'notes' => $reminder->notes,
            'remind_on' => $reminder->remind_on->toDateString(),
            'done' => $reminder->done_at !== null,
        ];
    }
}
