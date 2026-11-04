<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Bookmark;
use App\Models\QuranVerse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookmarks = Bookmark::query()->where('user_id', $request->user()->id)->orderByDesc('id')->get();
        $verses = QuranVerse::query()->with('surah')
            ->whereIn('id', $bookmarks->where('target_type', 'verse')->pluck('target_id'))
            ->get()
            ->keyBy('id');

        return response()->json(['data' => $bookmarks->map(fn (Bookmark $b) => $b->only(['id', 'target_type', 'target_id', 'note', 'created_at']) + [
            'verse' => ($v = $b->target_type === 'verse' ? $verses->get($b->target_id) : null) ? [
                'surah_id' => $v->surah_id,
                'surah' => $v->surah->name_transliterated,
                'number' => $v->verse_number,
            ] : null,
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:verse,hadith,lesson'],
            'target_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $bookmark = Bookmark::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'target_type' => $data['target_type'], 'target_id' => $data['target_id']],
            ['note' => $data['note'] ?? null],
        );

        return response()->json(['data' => $bookmark->only(['id', 'target_type', 'target_id', 'note'])], 201);
    }

    public function destroy(Bookmark $bookmark): JsonResponse
    {
        $this->authorize('delete', $bookmark);
        $bookmark->delete();

        return response()->json(['message' => 'Bookmark removed.']);
    }
}
