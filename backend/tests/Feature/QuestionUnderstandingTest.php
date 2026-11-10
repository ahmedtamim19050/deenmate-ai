<?php

namespace Tests\Feature;

use App\Services\AI\Understanding\QuestionAnalysis;
use App\Services\AI\Understanding\QuestionUnderstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Deterministic: none of these use an AI provider. */
class QuestionUnderstandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $now = now();
        DB::table('quran_surahs')->insert([
            ['id' => 2, 'name_arabic' => 'البقرة', 'name_transliterated' => 'Al-Baqara', 'name_english' => 'The Cow', 'revelation_place' => 'medinan', 'verse_count' => 286, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 36, 'name_arabic' => 'يس', 'name_transliterated' => 'Yaseen', 'name_english' => 'Yaseen', 'revelation_place' => 'meccan', 'verse_count' => 83, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 114, 'name_arabic' => 'الناس', 'name_transliterated' => 'An-Naas', 'name_english' => 'Mankind', 'revelation_place' => 'meccan', 'verse_count' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function analyze(string $question, ?array $previous = null): QuestionAnalysis
    {
        return app(QuestionUnderstandingService::class)->analyze($question, $previous);
    }

    public function test_banglish_questions_get_the_right_topic_language_and_bangla_answers(): void
    {
        $cases = [
            'namaz koy wakt?' => ['salah', ['salah', 'prayer_times']],
            'namaj keno porte hoy?' => ['salah', ['salah']],
            'rojar niyom ki?' => ['fasting', ['fasting']],
            'roja vangar karon ki?' => ['fasting', ['fasting']],
            'allah amader keno sristi korechen?' => ['quran_topic_search', ['creation_purpose']],
            'quran e ma baba somporke ki bola hoyeche?' => ['parents', ['quran', 'parents']],
            'meyeder porda niye quran ki bole?' => ['women', ['women', 'modesty', 'quran']],
            'manush mara gele ki hoy?' => ['afterlife', ['death']],
            'jannat pawar upay ki?' => ['jannah', ['jannah']],
            'gunah maf pawar upay ki?' => ['repentance', ['sin', 'repentance']],
            'husband wife er odhikar ki?' => ['marriage', ['marriage']],
            'zakat kake dite hoy?' => ['zakat', ['zakat']],
            'ami keno eto bipode achi?' => ['islamic_guidance', ['hardship']],
        ];
        foreach ($cases as $question => [$intent, $concepts]) {
            $a = $this->analyze($question);
            $this->assertSame('banglish', $a->question->language, $question);
            $this->assertSame('bn', $a->answerLanguage(), $question);
            $this->assertSame($intent, $a->primaryIntent, $question);
            $this->assertEqualsCanonicalizing($concepts, $a->entities['concepts'], $question);
            $this->assertSame($question, $a->question->original, 'the original question is kept as typed');
        }
    }

    public function test_banglish_topics_are_searched_with_english_and_bangla_words(): void
    {
        $a = $this->analyze('namaz koy wakt?');

        $this->assertContains('prayer', $a->searchEnglish);
        $this->assertContains('নামাজ', $a->searchBangla);
        // Banglish particles are not searched for in English text.
        $this->assertNotContains('koy', $a->searchEnglish);
        $this->assertStringContainsString('[salah (prayer)]', $a->question->normalized);
    }

    public function test_surah_summary_question(): void
    {
        $a = $this->analyze('surah baqarah er main message ki?');

        $this->assertSame('surah_summary', $a->primaryIntent);
        $this->assertSame([2], $a->entities['surahs']);
        $this->assertSame([], $a->entities['references']);
    }

    public function test_bangla_english_and_mixed_questions(): void
    {
        $bangla = $this->analyze('নামাজ কেন পড়তে হয়?');
        $this->assertSame(['bn', 'bn', 'salah'], [$bangla->question->language, $bangla->answerLanguage(), $bangla->primaryIntent]);
        // হয় ("is") must not be read as হজ (Hajj): য় is not folded like য.
        $this->assertSame(['salah'], $bangla->entities['concepts']);
        $this->assertContains('halal_haram', $this->analyze('এটা কি জায়েয?')->entities['concepts']);
        $this->assertContains('halal_haram', $this->analyze('এটা কি জায়েজ?')->entities['concepts']);

        $english = $this->analyze('What does the Quran say about patience?');
        $this->assertSame(['en', 'en'], [$english->question->language, $english->answerLanguage()]);
        $this->assertContains('patience', $english->entities['concepts']);

        $mixed = $this->analyze('namaz er time ki, what is the rule?');
        $this->assertSame(['mixed', 'bn', 'salah'], [$mixed->question->language, $mixed->answerLanguage(), $mixed->primaryIntent]);

        $banglaWithEnglishWord = $this->analyze('নামাজের rules কী?');
        $this->assertSame('bn', $banglaWithEnglishWord->question->language);
    }

    public function test_spelling_variants_reach_the_same_concept(): void
    {
        $groups = [
            'salah' => ['namaz', 'namaj', 'salat', 'salah', 'নামাজ', 'নামায', 'namajer', 'নামাযের'],
            'fasting' => ['roza', 'roja', 'sawm', 'rojar', 'রোজা', 'রোযা'],
            'quran' => ['quran', 'koran', "qur'an", 'কুরআন', 'কোরআন'],
            'jannah' => ['jannat', 'janat', 'jannah', 'জান্নাতের'],
            'jahannam' => ['jahannam', 'jahanam', 'জাহান্নাম'],
            'hadith' => ['hadis', 'hadith', 'হাদিস', 'হাদীস'],
            'repentance' => ['tawba', 'tauba', 'তওবা'],
            'sin' => ['gunah', 'gonah', 'গুনাহ'],
            'dua' => ['doa', 'dua', 'duya', 'দোয়া'],
            'halal_haram' => ['haram', 'haraam', 'halal'],
        ];
        foreach ($groups as $concept => $variants) {
            foreach ($variants as $variant) {
                $this->assertContains($concept, $this->analyze("{$variant} ki?")->entities['concepts'], $variant);
            }
        }
        $this->assertTrue($this->analyze('farz namaz koto rakat?')->rulingCaution);
        $this->assertTrue($this->analyze('eta ki haraam?')->rulingCaution);
    }

    public function test_negation_is_preserved(): void
    {
        $negated = $this->analyze('namaz na porle ki hoy?');
        $this->assertTrue($negated->question->negated());
        $this->assertSame(['na'], $negated->question->negations);
        $this->assertStringContainsString('NOT', $negated->question->normalized);
        $this->assertTrue($negated->toArray()['negated']);

        $this->assertFalse($this->analyze('namaz porle ki hoy?')->question->negated());
        $this->assertTrue($this->analyze('Why is it not allowed to pray at sunrise?')->question->negated());
        $this->assertTrue($this->analyze('নামাজ না পড়লে কী হয়?')->question->negated());
        // "nas" or "nai"-like syllables inside other words are not negations.
        $this->assertFalse($this->analyze('namaz er niyom ki?')->question->negated());
    }

    public function test_explicit_references_are_extracted_and_validated(): void
    {
        $ref = fn (string $q) => array_map(fn ($r) => "{$r['surah']}:{$r['ayah_from']}-{$r['ayah_to']}", $this->analyze($q)->entities['references']);

        $this->assertSame(['2:255-255'], $ref('2:255 er mane ki?'));
        $this->assertSame(['2:255-255'], $ref('২:২৫৫'));
        $this->assertSame(['2:255-255'], $ref('surah 2 ayah 255'));
        $this->assertSame(['2:255-255'], $ref('surah 2:255'));
        $this->assertSame(['2:255-255'], $ref('ayatul kursi ki?'));
        $this->assertSame(['2:153-153'], $ref('baqarah 153 ayat'));
        $this->assertSame(['2:255-255'], $ref('সূরা বাকারা ২৫৫'));
        $this->assertSame(['36:1-1'], $ref('surah yasin 1'));
        $this->assertSame(['2:1-5'], $ref('2:1-5'));
        // There is no verse 2:300 or surah 115.
        $this->assertSame([], $ref('2:300'));
        $this->assertSame([], $ref('115:1'));
        $this->assertSame('quran_verse_lookup', $this->analyze('2:255')->primaryIntent);
        $this->assertSame('tafsir_lookup', $this->analyze('2:255 er tafsir ki?')->primaryIntent);
    }

    public function test_short_surah_names_need_the_word_surah(): void
    {
        $this->assertSame([114], $this->analyze('surah nas er main message ki?')->entities['surahs']);
        $this->assertSame([], $this->analyze('nas mane manush')->entities['surahs']);
        $this->assertSame([2], $this->analyze('baqarah er shikkha ki?')->entities['surahs']);
    }

    public function test_explicit_language_request_wins(): void
    {
        $a = $this->analyze('namaz koy wakt? answer in english');
        $this->assertSame(['banglish', 'en'], [$a->question->language, $a->answerLanguage()]);
        $this->assertTrue($a->question->answerLanguageRequested);

        $this->assertSame('bn', $this->analyze('What is zakat? Please answer in Bangla.')->answerLanguage());
    }

    public function test_unknown_questions_are_not_forced_into_a_topic(): void
    {
        $a = $this->analyze('tell me something interesting');

        $this->assertSame('general_islamic_question', $a->primaryIntent);
        $this->assertLessThan(0.5, $a->confidence);
    }

    public function test_greeting_and_personal_questions(): void
    {
        $this->assertSame('greeting', $this->analyze('Assalamu alaikum')->primaryIntent);
        $this->assertSame('greeting', $this->analyze('আসসালামু আলাইকুম')->primaryIntent);
        $this->assertSame('personal_progress', $this->analyze('amar namaz er progress kemon?')->primaryIntent);
    }

    public function test_follow_up_uses_previous_verse_without_overriding_new_references(): void
    {
        $previous = $this->analyze('2:255 er mane ki?')->toArray();

        $followUp = $this->analyze('er tafsir ki?', $previous);
        $this->assertTrue($followUp->contextUsed);
        $this->assertSame(2, $followUp->entities['references'][0]['surah']);
        $this->assertSame(255, $followUp->entities['references'][0]['ayah_from']);

        $next = $this->analyze('porer ayat ta bujhiye dao', $previous);
        $this->assertSame(256, $next->entities['references'][0]['ayah_from']);

        // A question with its own reference ignores the earlier one.
        $own = $this->analyze('eta ki 2:153 er moto?', $previous);
        $this->assertFalse($own->contextUsed);
        $this->assertSame([153], array_column($own->entities['references'], 'ayah_from'));

        // A new, unrelated question is not treated as a follow-up.
        $this->assertFalse($this->analyze('zakat kake dite hoy?', $previous)->contextUsed);
    }
}
