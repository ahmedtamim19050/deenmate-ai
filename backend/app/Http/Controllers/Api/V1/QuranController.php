<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\QuranSurah;
use App\Models\QuranTranslation;
use App\Models\QuranTranslationEdition;
use App\Models\QuranVerse;
use App\Services\Quran\BasmalaSeparator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuranController extends Controller
{
    public function surahs(): JsonResponse
    {
        return response()->json([
            'data' => QuranSurah::query()->orderBy('id')->get(['id', 'name_arabic', 'name_transliterated', 'name_english', 'revelation_place', 'verse_count']),
        ]);
    }

    public function surah(Request $request, QuranSurah $surah): JsonResponse
    {
        $edition = $this->edition($request->query('edition'));
        $verses = QuranVerse::query()->where('surah_id', $surah->id)->orderBy('verse_number')->get();
        $translations = $edition
            ? QuranTranslation::query()
                ->where('quran_translation_edition_id', $edition->id)
                ->whereIn('quran_verse_id', $verses->pluck('id'))
                ->pluck('text', 'quran_verse_id')
            : collect();

        return response()->json(['data' => [
            'surah' => $surah->only(['id', 'name_arabic', 'name_transliterated', 'name_english', 'revelation_place', 'verse_count']),
            'edition' => $edition?->only(['identifier', 'language', 'name', 'translator', 'source_url', 'license']),
            // The opening line shown above a surah. In Al-Fatihah it is verse 1, and At-Tawbah has none.
            'basmala' => BasmalaSeparator::hasHeading($surah->id)
                ? QuranVerse::query()->where('surah_id', 1)->where('verse_number', 1)->value('text_arabic')
                : null,
            'verses' => $verses->map(fn (QuranVerse $v) => [
                'id' => $v->id,
                'number' => $v->verse_number,
                'page' => $v->page,
                'juz' => $v->juz,
                'arabic' => $v->text_arabic,
                'transliteration' => $v->transliteration,
                'translation' => $translations->get($v->id),
                'text_source' => $v->text_source,
            ])->values(),
        ]]);
    }

    public function editions(): JsonResponse
    {
        return response()->json([
            'data' => QuranTranslationEdition::query()->where('is_active', true)->orderBy('language')->get(['identifier', 'language', 'name', 'translator', 'source_url', 'license']),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'edition' => ['sometimes', 'string', 'max:64']]);
        $edition = $this->edition($data['edition'] ?? null);
        if ($edition === null) {
            return response()->json(['data' => []]);
        }
        $term = '%'.addcslashes($data['q'], '%_').'%';

        return response()->json(['data' => QuranTranslation::query()
            ->with('verse.surah')
            ->where('quran_translation_edition_id', $edition->id)
            ->where('text', 'like', $term)
            ->limit(50)
            ->get()
            ->map(fn (QuranTranslation $t) => [
                'surah_id' => $t->verse->surah_id,
                'surah' => $t->verse->surah->name_transliterated,
                'verse' => $t->verse->verse_number,
                'arabic' => $t->verse->text_arabic,
                'translation' => $t->text,
            ])->values()]);
    }

    private function edition(?string $identifier): ?QuranTranslationEdition
    {
        $query = QuranTranslationEdition::query()->where('is_active', true);

        return $identifier ? $query->where('identifier', $identifier)->first() : $query->orderBy('id')->first();
    }
}
