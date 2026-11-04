<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * Starter lessons drafted for the app. Every row stays review_status=pending and the app
 * shows an "awaiting scholar review" label until a Scholar Reviewer approves it in admin.
 */
class StarterCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->curriculum() as $i => $course) {
            $row = Course::query()->firstOrCreate(['slug' => $course['slug']], [
                'title' => $course['title'],
                'description' => $course['description'],
                'category' => $course['category'],
                'level' => 'beginner',
                'is_published' => true,
                'sort_order' => $i,
            ]);
            if ($row->lessons()->exists()) {
                continue;
            }
            foreach ($course['lessons'] as $j => $lesson) {
                $l = $row->lessons()->create([
                    'title' => $lesson['title'],
                    'body' => $lesson['body'],
                    'references' => $lesson['references'],
                    'duration_minutes' => $lesson['minutes'] ?? 5,
                    'is_published' => true,
                    'sort_order' => $j,
                ]);
                if (! empty($lesson['quiz'])) {
                    $quiz = $l->quiz()->create(['title' => 'Check your understanding', 'pass_percent' => 60]);
                    foreach ($lesson['quiz'] as $k => [$question, $options, $correct, $explanation]) {
                        $quiz->questions()->create([
                            'question' => $question,
                            'options' => $options,
                            'correct_index' => $correct,
                            'explanation' => $explanation,
                            'sort_order' => $k,
                        ]);
                    }
                }
            }
        }
    }

    private function curriculum(): array
    {
        return [
            [
                'slug' => 'beginner-muslim-journey',
                'title' => 'Beginner Muslim Journey',
                'description' => 'A gentle first path for new Muslims and anyone starting to learn.',
                'category' => 'beginner',
                'lessons' => [
                    [
                        'title' => 'The Shahadah',
                        'body' => "The Shahadah is the declaration of faith: that there is no god worthy of worship except Allah, and that Muhammad is the Messenger of Allah.\n\nThe Quran says: \"Know, therefore, that there is no god but Allah\" (paraphrased from Quran 47:19). Believing this and living by it is the starting point of a Muslim's life.\n\nThere is no need to be perfect before you begin. Learning takes time, and it is fine to take it one step at a time. A knowledgeable and kind teacher at a local mosque or community can help you with questions along the way.",
                        'references' => ['Quran 47:19', 'Quran 3:18'],
                        'quiz' => [
                            ['What does the first part of the Shahadah affirm?', ['That only Allah is worthy of worship', 'That prayer is five times a day', 'That fasting is in Ramadan'], 0, 'The first part affirms that there is no god worthy of worship except Allah.'],
                        ],
                    ],
                    [
                        'title' => 'The Five Pillars of Islam',
                        'body' => "A well-known hadith states that Islam is built upon five things:\n\n1. The testimony of faith (Shahadah)\n2. Establishing prayer (Salah)\n3. Giving Zakat\n4. Hajj, pilgrimage to the House in Makkah, for those able\n5. Fasting in Ramadan\n\nThese are the framework of a Muslim's practice. Rulings about the details of each one are taught in more depth by qualified teachers, and scholars have recognised differences on some of those details.",
                        'references' => ['Sahih al-Bukhari 8', 'Sahih Muslim 16'],
                        'quiz' => [
                            ['How many pillars does this hadith describe?', ['Three', 'Five', 'Seven'], 1, 'The hadith lists five.'],
                            ['Which of these is one of the five pillars?', ['Fasting in Ramadan', 'Visiting Madinah', 'Reciting a specific surah daily'], 0, 'Fasting in Ramadan is one of the five.'],
                        ],
                    ],
                    [
                        'title' => 'The Six Articles of Faith',
                        'body' => "In the well-known hadith of Jibril, faith (iman) is described as believing in:\n\n1. Allah\n2. His angels\n3. His books\n4. His messengers\n5. The Last Day\n6. Divine decree, its good and its bad\n\nThe Quran also mentions belief in Allah, His angels, His books and His messengers together.",
                        'references' => ['Sahih Muslim 8', 'Quran 2:285', 'Quran 4:136'],
                        'quiz' => [
                            ['Which is one of the articles of faith?', ['Belief in the angels', 'Belief in lucky numbers', 'Belief in the app'], 0, 'Belief in the angels is mentioned in the hadith of Jibril.'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'basic-islamic-knowledge',
                'title' => 'Basic Islamic Knowledge',
                'description' => 'Short overviews of core acts of worship, with their sources.',
                'category' => 'basics',
                'lessons' => [
                    [
                        'title' => 'Understanding Salah',
                        'body' => "Salah, the ritual prayer, is one of the pillars of Islam. The Quran says that prayer has been decreed upon the believers at specified times (paraphrased from Quran 4:103), and tells believers to maintain their prayers with care (paraphrased from Quran 2:238).\n\nThe five daily prayers are Fajr, Dhuhr, Asr, Maghrib and Isha. The washing before prayer, wudu, is described in Quran 5:6.\n\nPractice details vary between recognised schools of thought. If you are learning to pray, a trusted teacher can show you in person. Tracking in this app is only a personal record: it cannot verify or judge your prayer.",
                        'references' => ['Quran 4:103', 'Quran 2:238', 'Quran 5:6'],
                        'quiz' => [
                            ['Which of these is one of the five daily prayers?', ['Asr', 'Eid', 'Tarawih'], 0, 'Asr is one of the five daily prayers.'],
                            ['Which verse describes wudu?', ['Quran 5:6', 'Quran 1:1', 'Quran 112:1'], 0, 'Quran 5:6 describes washing before prayer.'],
                        ],
                    ],
                    [
                        'title' => 'Fasting and Ramadan',
                        'body' => "The Quran says that fasting has been prescribed for believers as it was prescribed for those before them, so that they may become mindful of Allah (paraphrased from Quran 2:183). The month of Ramadan is described as the month in which the Quran was sent down (paraphrased from Quran 2:185).\n\nThe same passage makes allowance for people who are ill or travelling. If you have a health condition, please speak with a doctor and a qualified scholar about your situation.",
                        'references' => ['Quran 2:183', 'Quran 2:185'],
                        'quiz' => [
                            ['Which verse mentions fasting being prescribed for believers?', ['Quran 2:183', 'Quran 96:1', 'Quran 114:1'], 0, 'Quran 2:183 mentions it.'],
                        ],
                    ],
                    [
                        'title' => 'Zakat and Hajj',
                        'body' => "Zakat is the obligatory charity, and the Quran names the categories of people it is for in Quran 9:60. Hajj is the pilgrimage to Makkah, an obligation for those able to make the journey (paraphrased from Quran 3:97).\n\nCalculating Zakat and the details of Hajj depend on circumstances and school of thought. For your own situation, please consult a qualified scholar or a reputable organisation.",
                        'references' => ['Quran 9:60', 'Quran 3:97'],
                        'quiz' => [
                            ['Hajj is an obligation for whom, according to Quran 3:97?', ['Those able to make the journey', 'Only people living in Makkah', 'Only scholars'], 0, 'The verse refers to those who are able.'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'quran-learning-foundations',
                'title' => 'Quran Learning Foundations',
                'description' => 'What the Quran is and how to begin building a reading habit.',
                'category' => 'quran',
                'lessons' => [
                    [
                        'title' => 'Introducing the Quran',
                        'body' => "Muslims believe the Quran is the word of Allah, revealed to the Prophet Muhammad, peace be upon him, over about twenty-three years. It has 114 surahs (chapters).\n\nThe first words revealed are widely reported to be the opening of Surah Al-Alaq, beginning with \"Read\" (Quran 96:1-5).\n\nThe Quran describes itself as made easy for remembrance (paraphrased from Quran 54:17), which many learners find encouraging when they are starting out.",
                        'references' => ['Quran 96:1', 'Quran 54:17'],
                        'quiz' => [
                            ['How many surahs does the Quran have?', ['99', '114', '30'], 1, 'The Quran has 114 surahs.'],
                        ],
                    ],
                    [
                        'title' => 'Building a Reading Habit',
                        'body' => "The Quran tells the Prophet to recite it with measured recitation (paraphrased from Quran 73:4). Many learners find that a small amount read regularly is easier to keep up than a large amount now and then.\n\nSome ideas that may help:\n\n- Pick a time that already exists in your day, such as after a prayer.\n- Begin with a short surah you can read with a translation.\n- Learn pronunciation (tajwid) from a teacher where you can, as reading alone can only take you so far.\n\nThis app can help you remember where you stopped. How much you read is between you and Allah, and it is always fine to start small.",
                        'references' => ['Quran 73:4'],
                    ],
                ],
            ],
            [
                'slug' => 'seerah-essentials',
                'title' => 'Seerah Essentials',
                'description' => 'An outline of the life of the Prophet Muhammad, peace be upon him.',
                'category' => 'seerah',
                'lessons' => [
                    [
                        'title' => 'Early Life in Makkah',
                        'body' => "The Prophet Muhammad, peace be upon him, was born in Makkah around 570 CE, a time often called the Year of the Elephant. He was orphaned young, cared for by his grandfather and then his uncle Abu Talib, and became known for his honesty.\n\nAround 610 CE, while in the Cave of Hira, he received the first revelation (Quran 96:1-5).\n\nThe Quran describes him as a mercy to the worlds (Quran 21:107) and an excellent example (Quran 33:21).",
                        'references' => ['Quran 96:1', 'Quran 21:107', 'Quran 33:21'],
                        'quiz' => [
                            ['Where did the first revelation take place?', ['The Cave of Hira', 'Madinah', 'Jerusalem'], 0, 'It took place in the Cave of Hira, near Makkah.'],
                        ],
                    ],
                    [
                        'title' => 'From Makkah to Madinah',
                        'body' => "In Makkah the early Muslims faced hardship and persecution. In 622 CE the Prophet, peace be upon him, and his companions migrated to Madinah. This journey is the Hijrah, and the Islamic calendar counts its years from it.\n\nIn Madinah a community formed around the new faith. In about 630 CE (8 AH) Makkah came under Muslim control. The Prophet passed away in Madinah in 632 CE (11 AH).\n\nDates here are the commonly given ones. Historical works sometimes differ slightly on details.",
                        'references' => ['Commonly given dates in classical seerah works'],
                        'quiz' => [
                            ['What event does the Islamic calendar count its years from?', ['The Hijrah', 'The first revelation', 'The Year of the Elephant'], 0, 'The calendar counts from the Hijrah in 622 CE.'],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'islamic-history-overview',
                'title' => 'Islamic History Overview',
                'description' => 'A brief look at the early centuries of Muslim history.',
                'category' => 'history',
                'lessons' => [
                    [
                        'title' => 'The Early Caliphs',
                        'body' => "After the Prophet's passing, leadership of the community passed to the caliphs. Sunni tradition speaks of four \"rightly guided\" caliphs: Abu Bakr (632-634 CE), Umar (634-644), Uthman (644-656) and Ali (656-661).\n\nShia Muslims understand the question of succession differently, holding that leadership belonged to Ali and his descendants. Both perspectives are part of Muslim history, and you can read about them further with a trusted teacher.\n\nAccording to historical reports, the written Quran was gathered under Abu Bakr, and standard copies were distributed under Uthman.",
                        'references' => ['Commonly given dates in classical Islamic histories'],
                        'quiz' => [
                            ['Who is traditionally named as the first caliph?', ['Abu Bakr', 'Uthman', 'Umar'], 0, 'Abu Bakr became the first caliph in 632 CE.'],
                        ],
                    ],
                    [
                        'title' => 'Learning and Scholarship',
                        'body' => "In the centuries that followed, cities such as Baghdad, Cordoba and Cairo became known for learning. Muslim scholars studied the Quran and hadith, and also contributed to fields such as medicine, astronomy and mathematics.\n\nThe word \"algebra\" comes from the title of a work by the ninth-century mathematician al-Khwarizmi, and Ibn Sina's medical writing was studied for centuries, including in Europe.",
                        'references' => ['General historical overview'],
                    ],
                ],
            ],
        ];
    }
}
