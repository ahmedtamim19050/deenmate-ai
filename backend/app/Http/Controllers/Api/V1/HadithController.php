<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HadithCollection;
use App\Models\HadithRecord;
use Illuminate\Http\JsonResponse;

class HadithController extends Controller
{
    public function collections(): JsonResponse
    {
        return response()->json(['data' => HadithCollection::query()->withCount('records')->orderBy('name')->get()
            ->map->only(['id', 'slug', 'name', 'compiler', 'language', 'source_url', 'license', 'records_count'])->values()]);
    }

    public function records(HadithCollection $collection): JsonResponse
    {
        $page = HadithRecord::query()->where('hadith_collection_id', $collection->id)->orderBy('id')->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (HadithRecord $h) => [
                'id' => $h->id,
                'book' => $h->book,
                'chapter' => $h->chapter,
                'number' => $h->hadith_number,
                'arabic' => $h->text_arabic,
                'translation' => $h->translation,
                // Null means the dataset documents no grade. It is never guessed.
                'grade' => $h->grade,
                'grade_source' => $h->grade_source,
                'source_reference' => $h->source_reference,
            ])->values(),
            'collection' => $collection->only(['id', 'name', 'compiler', 'source_url', 'license']),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
