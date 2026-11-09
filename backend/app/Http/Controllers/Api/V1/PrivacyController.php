<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\BuildDataExport;
use App\Models\DataExportRequest;
use App\Services\Privacy\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function requestExport(Request $request): JsonResponse
    {
        $export = DataExportRequest::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
        ]);
        BuildDataExport::dispatch($export->id);

        return response()->json(['data' => $this->present($export->refresh())], 202);
    }

    public function exportStatus(Request $request, string $uuid): JsonResponse
    {
        return response()->json(['data' => $this->present($this->owned($request, $uuid))]);
    }

    /** Streams from private storage. There is no public URL for an export. */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $export = $this->owned($request, $uuid);
        abort_unless($export->status === 'ready' && $export->file_path && $export->expires_at?->isFuture(), 404);

        return Storage::disk('local')->download($export->file_path, 'deenq-ai-data-export.json');
    }

    public function deleteAccount(Request $request, AccountDeletionService $deletion): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string'], 'confirm' => ['required', 'accepted']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not correct.']);
        }
        $deletion->delete($request->user());

        return response()->json(['message' => 'Your account and personal data have been deleted.']);
    }

    private function owned(Request $request, string $uuid): DataExportRequest
    {
        return DataExportRequest::query()->where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function present(DataExportRequest $e): array
    {
        return [
            'id' => $e->uuid,
            'status' => $e->status,
            'completed_at' => $e->completed_at?->toIso8601String(),
            'expires_at' => $e->expires_at?->toIso8601String(),
        ];
    }
}
