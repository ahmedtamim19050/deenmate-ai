<?php

/*
| Question-understanding lexicon for Bangla, Banglish and English.
|
| This file holds vocabulary and retrieval hints only. It never holds Quran, Hadith or Tafsir
| text: every passage shown to a user is read from an approved, synced source at query time.
|
| concepts.*:
|   intent    - the question intent this concept points to
|   latin     - Banglish / English stems. Common endings (er, r, e, te, ke, der, ra, gulo, s) are
|               recognised on top of these, so "namajer" matches "namaj".
|   bangla    - Bangla-script stems. Endings and জ/য, ী/ি, ূ/ু spellings are recognised too.
|   search_en - words to search for in English translations
|   search_bn - words to search for in Bangla translations
|   anchors   - well-known verses on the topic (surah:ayah). They are only retrieval hints: a hint
|               returns nothing unless an approved edition contains that verse.
*/

return [
    'concepts' => [
        'salah' => [
            'intent' => 'salah', 'gloss' => 'salah (prayer)',
            'latin' => ['namaz', 'namaj', 'salat', 'salah', 'salaat', 'prayer', 'pray', 'praying', 'prayed'],
            'bangla' => ['নামাজ', 'নামায', 'সালাত', 'সালাহ'],
            'search_en' => ['prayer', 'prayers', 'pray'],
            'search_bn' => ['নামাজ', 'সালাত'],
            'anchors' => ['2:43', '2:45', '2:238', '4:103', '11:114', '17:78', '20:14', '29:45'],
        ],
        'prayer_times' => [
            'intent' => 'salah', 'gloss' => 'prayer times',
            'latin' => ['wakt', 'waqt', 'oyakto', 'waqto', 'wakto'],
            'bangla' => ['ওয়াক্ত', 'ওয়াক্ত'],
            'search_en' => ['times', 'dawn', 'night', 'sun'],
            'search_bn' => ['ওয়াক্ত'],
            'anchors' => ['4:103', '11:114', '17:78', '20:130'],
        ],
        'wudu' => [
            'intent' => 'salah', 'gloss' => 'wudu (ablution)',
            'latin' => ['wudu', 'oju', 'ozu', 'ozo', 'wuzu', 'ablution'],
            'bangla' => ['ওজু', 'অজু', 'ওযু'],
            'search_en' => ['wash', 'faces', 'ablution'],
            'search_bn' => ['ওজু', 'অজু'],
            'anchors' => ['5:6'],
        ],
        'fasting' => [
            'intent' => 'fasting', 'gloss' => 'fasting',
            'latin' => ['roza', 'roja', 'rozah', 'sawm', 'siyam', 'fast', 'fasting', 'fasts', 'ramadan', 'ramzan', 'romjan'],
            'bangla' => ['রোজা', 'রোযা', 'সিয়াম', 'রমজান', 'রমযান'],
            'search_en' => ['fasting', 'fast', 'Ramadan'],
            'search_bn' => ['রোজা', 'সিয়াম', 'রমজান'],
            'anchors' => ['2:183', '2:184', '2:185', '2:187'],
        ],
        'zakat' => [
            'intent' => 'zakat', 'gloss' => 'zakat (obligatory charity)',
            'latin' => ['zakat', 'zakah', 'jakat', 'zakaat', 'sadaqah', 'sadka', 'sodka', 'charity', 'alms'],
            'bangla' => ['যাকাত', 'জাকাত', 'সদকা', 'সাদাকা'],
            'search_en' => ['charity', 'alms', 'poor', 'needy'],
            'search_bn' => ['যাকাত', 'সদকা'],
            'anchors' => ['9:60', '2:43', '2:110', '2:177', '9:103'],
        ],
        'hajj' => [
            'intent' => 'hajj', 'gloss' => 'hajj (pilgrimage)',
            'latin' => ['hajj', 'haj', 'umrah', 'umra', 'pilgrimage', 'kaba', 'kabah'],
            'bangla' => ['হজ', 'হজ্জ', 'উমরা', 'উমরাহ', 'কাবা'],
            'search_en' => ['pilgrimage', 'Hajj', 'Kaaba'],
            'search_bn' => ['হজ', 'হজ্জ'],
            'anchors' => ['2:196', '2:197', '3:97', '22:27'],
        ],
        'halal_haram' => [
            'intent' => 'halal_haram', 'gloss' => 'lawful and unlawful',
            'latin' => ['halal', 'haram', 'haraam', 'jayez', 'jaiz', 'najayez', 'permissible', 'forbidden', 'lawful', 'unlawful', 'allowed'],
            'bangla' => ['হালাল', 'হারাম', 'জায়েজ', 'জায়েয', 'নাজায়েজ'],
            'search_en' => ['lawful', 'unlawful', 'forbidden', 'prohibited'],
            'search_bn' => ['হালাল', 'হারাম'],
            'anchors' => ['2:168', '2:172', '2:173', '5:3', '5:90'],
        ],
        'interest' => [
            'intent' => 'halal_haram', 'gloss' => 'riba (usury, interest)',
            'latin' => ['riba', 'sud', 'shud', 'interest', 'usury'],
            'bangla' => ['সুদ', 'রিবা'],
            'search_en' => ['usury', 'interest'],
            'search_bn' => ['সুদ'],
            'anchors' => ['2:275', '2:276', '2:278', '3:130'],
        ],
        'parents' => [
            'intent' => 'parents', 'gloss' => 'parents',
            'latin' => ['ma baba', 'baba ma', 'ma-baba', 'pitamata', 'parents', 'parent', 'mother', 'father', 'abba', 'amma'],
            'bangla' => ['মা-বাবা', 'মা বাবা', 'বাবা-মা', 'বাবা মা', 'পিতামাতা', 'পিতা-মাতা', 'মাতাপিতা', 'মা', 'বাবা'],
            'search_en' => ['parents', 'mother', 'father'],
            'search_bn' => ['পিতামাতা', 'মাতাপিতা', 'পিতা-মাতা'],
            'anchors' => ['17:23', '17:24', '31:14', '31:15', '46:15', '4:36'],
        ],
        'marriage' => [
            'intent' => 'marriage', 'gloss' => 'marriage and spouses',
            'latin' => ['husband wife', 'swami stri', 'biye', 'bie', 'bibaho', 'nikah', 'marriage', 'married', 'marry', 'husband', 'wife', 'spouse', 'swami', 'stri', 'bou', 'jamai'],
            'bangla' => ['স্বামী-স্ত্রী', 'স্বামী স্ত্রী', 'বিয়ে', 'বিবাহ', 'নিকাহ', 'স্বামী', 'স্ত্রী'],
            'search_en' => ['wives', 'husbands', 'spouses', 'marry', 'marriage'],
            'search_bn' => ['স্বামী', 'স্ত্রী', 'বিবাহ'],
            'anchors' => ['30:21', '2:187', '2:228', '4:19', '4:34'],
        ],
        'divorce' => [
            'intent' => 'fiqh_question', 'gloss' => 'divorce',
            'latin' => ['talak', 'talaq', 'divorce'],
            'bangla' => ['তালাক'],
            'search_en' => ['divorce', 'divorced'],
            'search_bn' => ['তালাক'],
            'anchors' => ['2:229', '2:231', '65:1'],
        ],
        'children' => [
            'intent' => 'children', 'gloss' => 'children',
            'latin' => ['bachcha', 'baccha', 'sontan', 'santan', 'chele meye', 'children', 'child', 'son', 'daughter'],
            'bangla' => ['সন্তান', 'বাচ্চা', 'ছেলেমেয়ে', 'ছেলে-মেয়ে'],
            'search_en' => ['children', 'offspring', 'son'],
            'search_bn' => ['সন্তান'],
            'anchors' => ['17:31', '66:6', '31:13', '31:17'],
        ],
        'women' => [
            'intent' => 'women', 'gloss' => 'women',
            'latin' => ['meye', 'meyeder', 'mohila', 'nari', 'women', 'woman', 'female'],
            'bangla' => ['মেয়ে', 'মহিলা', 'নারী'],
            'search_en' => ['women', 'woman', 'female'],
            'search_bn' => ['নারী', 'মহিলা'],
            'anchors' => ['4:1', '4:124', '33:35'],
        ],
        'modesty' => [
            'intent' => 'women', 'gloss' => 'modesty and hijab',
            'latin' => ['porda', 'purdah', 'pordah', 'hijab', 'niqab', 'borka', 'burqa', 'modesty', 'veil'],
            'bangla' => ['পর্দা', 'হিজাব', 'বোরকা'],
            'search_en' => ['modesty', 'cover', 'gaze', 'chastity'],
            'search_bn' => ['পর্দা'],
            'anchors' => ['24:30', '24:31', '33:59'],
        ],
        'men' => [
            'intent' => 'men', 'gloss' => 'men',
            'latin' => ['purush', 'chele', 'cheleder', 'men', 'man', 'male'],
            'bangla' => ['পুরুষ', 'ছেলে'],
            'search_en' => ['men', 'male'],
            'search_bn' => ['পুরুষ'],
            'anchors' => ['4:34', '24:30'],
        ],
        'prophets' => [
            'intent' => 'prophets', 'gloss' => 'prophets and messengers',
            'latin' => ['nabi', 'rasul', 'rasool', 'poygombor', 'paigambar', 'prophet', 'prophets', 'messenger', 'messengers'],
            'bangla' => ['নবী', 'নবি', 'রাসূল', 'রসুল', 'রাসুল', 'পয়গম্বর'],
            'search_en' => ['prophet', 'prophets', 'messenger', 'messengers'],
            'search_bn' => ['নবী', 'রাসূল'],
            'anchors' => ['2:136', '2:285', '4:164', '33:40'],
        ],
        'death' => [
            'intent' => 'afterlife', 'gloss' => 'death',
            'latin' => ['mara gele', 'mara jaoa', 'mrittu', 'mrittur', 'mout', 'maut', 'death', 'die', 'dies', 'died', 'dead', 'grave', 'kobor', 'qabr'],
            'bangla' => ['মারা', 'মৃত্যু', 'মউত', 'কবর'],
            'search_en' => ['death', 'die', 'dies', 'grave', 'barrier'],
            'search_bn' => ['মৃত্যু', 'কবর'],
            'anchors' => ['3:185', '23:99', '23:100', '62:8', '50:19'],
        ],
        'afterlife' => [
            'intent' => 'afterlife', 'gloss' => 'the Hereafter',
            'latin' => ['akhirat', 'akherat', 'porokal', 'parakal', 'kiyamat', 'qiyamah', 'keyamot', 'hereafter', 'afterlife', 'resurrection', 'judgment'],
            'bangla' => ['আখিরাত', 'আখেরাত', 'পরকাল', 'কিয়ামত', 'কেয়ামত', 'হাশর'],
            'search_en' => ['Hereafter', 'Resurrection', 'Judgment'],
            'search_bn' => ['আখিরাত', 'পরকাল', 'কিয়ামত'],
            'anchors' => ['2:4', '99:7', '99:8', '22:7'],
        ],
        'jannah' => [
            'intent' => 'jannah', 'gloss' => 'Paradise',
            'latin' => ['jannat', 'janat', 'jannah', 'jannath', 'behesht', 'paradise', 'heaven'],
            'bangla' => ['জান্নাত', 'জান্নাতে', 'বেহেশত', 'বেহেশ্ত'],
            'search_en' => ['Paradise', 'gardens', 'Garden'],
            'search_bn' => ['জান্নাত', 'জান্নাতে'],
            'anchors' => ['3:133', '3:134', '79:40', '79:41', '98:7', '98:8'],
        ],
        'jahannam' => [
            'intent' => 'jahannam', 'gloss' => 'Hell',
            'latin' => ['jahannam', 'jahanam', 'jahannom', 'dozakh', 'dojokh', 'dozokh', 'hell', 'hellfire'],
            'bangla' => ['জাহান্নাম', 'দোজখ', 'দোযখ'],
            'search_en' => ['Hell', 'Fire'],
            'search_bn' => ['জাহান্নাম', 'দোজখ'],
            'anchors' => ['66:6', '4:56', '2:24'],
        ],
        'repentance' => [
            'intent' => 'repentance', 'gloss' => 'repentance and forgiveness',
            'latin' => ['tawba', 'tauba', 'tawbah', 'tobah', 'touba', 'istighfar', 'maf', 'maaf', 'khoma', 'kkhoma', 'forgive', 'forgiveness', 'forgiven', 'repent', 'repentance'],
            'bangla' => ['তওবা', 'তাওবা', 'ইস্তিগফার', 'মাফ', 'ক্ষমা'],
            'search_en' => ['repent', 'repentance', 'forgive', 'forgiveness', 'Forgiving'],
            'search_bn' => ['তওবা', 'ক্ষমা'],
            'anchors' => ['39:53', '66:8', '4:110', '25:70', '3:135'],
        ],
        'sin' => [
            'intent' => 'repentance', 'gloss' => 'sin',
            'latin' => ['gunah', 'gonah', 'gunaho', 'paap', 'sin', 'sins', 'sinful'],
            'bangla' => ['গুনাহ', 'গোনাহ', 'পাপ'],
            'search_en' => ['sins', 'sin', 'wrongdoing'],
            'search_bn' => ['গুনাহ', 'পাপ'],
            'anchors' => ['39:53', '4:31', '53:32'],
        ],
        'dua' => [
            'intent' => 'dua', 'gloss' => 'dua (supplication)',
            'latin' => ['dua', 'doa', 'duya', 'doya', 'munajat', 'supplication', 'supplicate'],
            'bangla' => ['দোয়া', 'দুআ', 'দোআ', 'দুয়া', 'মোনাজাত'],
            'search_en' => ['call', 'pray', 'respond', 'supplication'],
            'search_bn' => ['দোয়া', 'ডাক'],
            'anchors' => ['2:186', '40:60', '7:55', '2:201'],
        ],
        'creation_purpose' => [
            'intent' => 'quran_topic_search', 'gloss' => 'purpose of creation',
            'latin' => ['sristi', 'srishti', 'shristi', 'created', 'create', 'creation'],
            'bangla' => ['সৃষ্টি'],
            'search_en' => ['created', 'worship', 'test'],
            'search_bn' => ['সৃষ্টি', 'ইবাদত'],
            'anchors' => ['51:56', '67:2', '23:115'],
        ],
        'hardship' => [
            'intent' => 'islamic_guidance', 'gloss' => 'hardship and trials',
            'latin' => ['bipod', 'bipode', 'bipad', 'kosto', 'koshto', 'dukkho', 'dukho', 'pariksha', 'porikkha', 'musibat', 'hardship', 'trial', 'trials', 'suffering', 'difficulty', 'depressed', 'sad'],
            'bangla' => ['বিপদ', 'কষ্ট', 'দুঃখ', 'পরীক্ষা', 'মুসিবত'],
            'search_en' => ['hardship', 'test', 'patient', 'ease', 'afflicted'],
            'search_bn' => ['বিপদ', 'কষ্ট', 'ধৈর্য'],
            'anchors' => ['2:155', '2:156', '2:157', '2:286', '94:5', '94:6', '29:2'],
        ],
        'patience' => [
            'intent' => 'islamic_guidance', 'gloss' => 'patience',
            'latin' => ['sabr', 'sobor', 'sobr', 'dhoirjo', 'doirjo', 'patience', 'patient'],
            'bangla' => ['সবর', 'ধৈর্য', 'ধৈর্য্য'],
            'search_en' => ['patience', 'patient', 'steadfast'],
            'search_bn' => ['ধৈর্য', 'সবর'],
            'anchors' => ['2:153', '39:10', '3:200'],
        ],
        'tawhid' => [
            'intent' => 'quran_topic_search', 'gloss' => 'the oneness of Allah',
            'latin' => ['tawhid', 'tauhid', 'towhid', 'shirk', 'oneness'],
            'bangla' => ['তাওহীদ', 'তাওহিদ', 'শিরক'],
            'search_en' => ['one', 'partners', 'associate'],
            'search_bn' => ['শরীক', 'এক'],
            'anchors' => ['112:1', '112:2', '112:3', '112:4', '2:255', '4:48'],
        ],
        'quran' => [
            'intent' => 'quran_topic_search', 'gloss' => 'the Quran',
            'latin' => ['quran', 'koran', 'kuran', 'koran', 'quraan'],
            'bangla' => ['কুরআন', 'কোরআন', 'কোরান'],
            'search_en' => [],
            'search_bn' => [],
            'anchors' => [],
        ],
        'hadith' => [
            'intent' => 'hadith_lookup', 'gloss' => 'hadith',
            'latin' => ['hadis', 'hadith', 'hadees', 'sunnah', 'sunnat', 'sunnot'],
            'bangla' => ['হাদিস', 'হাদীস', 'সুন্নাহ', 'সুন্নত'],
            'search_en' => [],
            'search_bn' => [],
            'anchors' => [],
        ],
        'tafsir' => [
            'intent' => 'tafsir_lookup', 'gloss' => 'tafsir (commentary)',
            'latin' => ['tafsir', 'tafseer', 'tofsir', 'byakkha', 'bakkha', 'commentary', 'exegesis'],
            'bangla' => ['তাফসীর', 'তাফসির', 'ব্যাখ্যা'],
            'search_en' => [],
            'search_bn' => [],
            'anchors' => [],
        ],
    ],

    // Prophets: Banglish/Bangla names to the English names used in English translations.
    'prophets' => [
        'muhammad' => ['latin' => ['muhammad', 'mohammad', 'mohammed', 'muhammod'], 'bangla' => ['মুহাম্মদ', 'মোহাম্মদ'], 'en' => 'Muhammad'],
        'ibrahim' => ['latin' => ['ibrahim', 'ibraheem', 'abraham'], 'bangla' => ['ইবরাহিম', 'ইব্রাহিম', 'ইবরাহীম'], 'en' => 'Abraham'],
        'musa' => ['latin' => ['musa', 'moosa', 'moses'], 'bangla' => ['মুসা', 'মূসা'], 'en' => 'Moses'],
        'isa' => ['latin' => ['isa', 'eesa', 'jesus'], 'bangla' => ['ঈসা', 'ইসা'], 'en' => 'Jesus'],
        'nuh' => ['latin' => ['nuh', 'nooh', 'noah'], 'bangla' => ['নূহ', 'নুহ'], 'en' => 'Noah'],
        'yusuf' => ['latin' => ['yusuf', 'yousuf', 'joseph'], 'bangla' => ['ইউসুফ'], 'en' => 'Joseph'],
        'adam' => ['latin' => ['adam'], 'bangla' => ['আদম'], 'en' => 'Adam'],
        'dawud' => ['latin' => ['dawud', 'daud', 'david'], 'bangla' => ['দাউদ'], 'en' => 'David'],
        'sulaiman' => ['latin' => ['sulaiman', 'sulayman', 'solaiman', 'solomon'], 'bangla' => ['সুলাইমান', 'সোলায়মান'], 'en' => 'Solomon'],
        'yunus' => ['latin' => ['yunus', 'younus', 'jonah'], 'bangla' => ['ইউনুস'], 'en' => 'Jonah'],
        'ayyub' => ['latin' => ['ayyub', 'ayub', 'aiyub'], 'bangla' => ['আইয়ুব'], 'en' => 'Job'],
        'ismail' => ['latin' => ['ismail', 'ismael', 'ishmael'], 'bangla' => ['ইসমাইল', 'ইসমাঈল'], 'en' => 'Ishmael'],
        'yaqub' => ['latin' => ['yaqub', 'yakub', 'jacob'], 'bangla' => ['ইয়াকুব'], 'en' => 'Jacob'],
        'maryam' => ['latin' => ['maryam', 'mariam', 'mary'], 'bangla' => ['মারইয়াম', 'মরিয়ম'], 'en' => 'Mary'],
    ],

    // Banglish question words and particles, with an English gloss used only for the readable
    // normalized form. Unknown words are kept as typed.
    'function_words' => [
        'ki' => 'what', 'kii' => 'what', 'keno' => 'why', 'kno' => 'why', 'kn' => 'why', 'kivabe' => 'how', 'kibhabe' => 'how',
        'kemne' => 'how', 'kemon' => 'how', 'koto' => 'how many', 'koy' => 'how many', 'koyta' => 'how many', 'kotota' => 'how much',
        'kake' => 'to whom', 'kar' => 'whose', 'ke' => 'who', 'kokhon' => 'when', 'kobe' => 'when', 'kothay' => 'where',
        'somporke' => 'about', 'shomporke' => 'about', 'niye' => 'about', 'bishoye' => 'about', 'bisoye' => 'about',
        'upay' => 'way', 'upai' => 'way', 'niyom' => 'rules', 'niom' => 'rules', 'karon' => 'reasons', 'karan' => 'reasons',
        'vangar' => 'breaking', 'bhangar' => 'breaking', 'vange' => 'breaks', 'bhange' => 'breaks', 'pawar' => 'gaining', 'paoar' => 'gaining', 'paowar' => 'gaining',
        'odhikar' => 'rights', 'adhikar' => 'rights', 'dayitto' => 'duties', 'kortobbo' => 'duties',
        'main' => 'main', 'mul' => 'main', 'message' => 'message', 'bani' => 'message', 'shikkha' => 'lesson', 'sikkha' => 'lesson',
        'bole' => 'says', 'bolo' => 'say', 'bolun' => 'say', 'bola' => 'said', 'hoyeche' => 'has been', 'hoy' => 'is', 'hoye' => 'being',
        'porte' => 'to perform', 'pora' => 'reading', 'porbo' => 'should perform', 'korte' => 'to do', 'kora' => 'doing', 'kore' => 'does',
        'korechen' => 'did', 'korbo' => 'should do', 'dite' => 'to give', 'deya' => 'giving', 'dibo' => 'should give', 'gele' => 'when goes',
        'jabe' => 'will go', 'jay' => 'goes', 'ache' => 'is', 'achi' => 'am', 'achhe' => 'is', 'achhi' => 'am', 'thake' => 'stays',
        'ami' => 'I', 'amar' => 'my', 'amake' => 'me', 'amader' => 'our', 'amra' => 'we', 'tumi' => 'you', 'apni' => 'you', 'apnar' => 'your',
        'allah' => 'Allah', 'manush' => 'person', 'manus' => 'person', 'eto' => 'so much', 'onek' => 'much', 'shob' => 'all', 'sob' => 'all',
        'er' => 'of', 'e' => 'in', 'te' => 'in', 'o' => 'also', 'ar' => 'and', 'ba' => 'or', 'jodi' => 'if', 'tahole' => 'then',
        'eta' => 'this', 'ei' => 'this', 'oi' => 'that', 'ota' => 'that', 'porer' => 'next', 'agher' => 'previous', 'ager' => 'previous',
        'aro' => 'more', 'bujhiye' => 'explaining', 'bujhao' => 'explain', 'bujhan' => 'explain', 'dao' => 'give', 'din' => 'give',
        'kotha' => 'words', 'jonno' => 'for', 'theke' => 'from', 'diye' => 'with', 'shathe' => 'with', 'sathe' => 'with',
    ],

    // Words that turn a statement around. They are kept in place, never dropped.
    'negations' => [
        'latin' => ['na', 'nai', 'nei', 'noy', 'nah', 'chara', 'chhara', 'not', 'no', 'never', 'dont', "don't", 'doesnt', "doesn't", 'cannot', "can't", 'cant', 'without', 'isnt', "isn't"],
        'bangla' => ['না', 'নেই', 'নয়', 'নয়', 'নাই', 'ছাড়া', 'ছাড়া'],
    ],

    // Latin words that only appear in Banglish, used to tell Banglish from English.
    'banglish_markers' => [
        'ki', 'keno', 'kivabe', 'kibhabe', 'koto', 'koy', 'koyta', 'kake', 'kokhon', 'kothay', 'somporke', 'shomporke', 'niye',
        'bole', 'bolo', 'hoyeche', 'hoy', 'korte', 'kora', 'kore', 'korechen', 'amar', 'amader', 'ami', 'tumi', 'apni', 'achi', 'ache',
        'porte', 'dite', 'gele', 'jabe', 'upay', 'niyom', 'karon', 'odhikar', 'pawar', 'vangar', 'bhangar', 'manush', 'eto', 'er',
        'bipode', 'meyeder', 'mara', 'tahole', 'jodi', 'eta', 'ei', 'porer', 'bujhiye', 'dao', 'shob', 'sob',
    ],

    // Explicit requests for an answer language.
    'language_requests' => [
        'en' => ['in english', 'english e', 'englishe', 'english a', 'ingreji', 'ইংরেজিতে', 'ইংরেজি তে'],
        'bn' => ['in bangla', 'in bengali', 'bangla te', 'banglay', 'bangla y', 'banglai', 'বাংলায়', 'বাংলায়', 'বাংলা তে'],
        'ar' => ['in arabic', 'arabic e', 'আরবিতে'],
    ],

    // Surah names as people write them. Transliterated names are also matched from the database.
    'surah_aliases' => [
        1 => ['fatiha', 'fateha', 'alhamdu', 'ফাতিহা', 'ফাতেহা'],
        2 => ['baqarah', 'baqara', 'bakara', 'bakarah', 'বাকারা', 'বাকারাহ'],
        3 => ['al imran', 'ale imran', 'aal imran', 'ali imran', 'imran', 'আল ইমরান', 'আলে ইমরান'],
        4 => ['nisa', 'nisaa', 'নিসা'],
        5 => ['maidah', 'maida', 'মায়িদা', 'মায়েদা'],
        12 => ['yusuf', 'yousuf', 'ইউসুফ'],
        18 => ['kahf', 'kahaf', 'kahf', 'কাহফ'],
        19 => ['maryam', 'mariam', 'মারইয়াম', 'মরিয়ম'],
        24 => ['nur', 'noor', 'নূর', 'নুর'],
        31 => ['luqman', 'lukman', 'লুকমান'],
        36 => ['yasin', 'yaseen', 'ya sin', 'ইয়াসিন', 'ইয়াসীন'],
        49 => ['hujurat', 'হুজুরাত'],
        55 => ['rahman', 'আর রহমান', 'রহমান'],
        56 => ['waqiah', 'waqia', 'wakia', 'wakiya', 'ওয়াকিয়া', 'ওয়াকিআ'],
        67 => ['mulk', 'মুলক'],
        112 => ['ikhlas', 'ইখলাস'],
        113 => ['falaq', 'falak', 'ফালাক'],
        114 => ['nas', 'naas', 'নাস'],
    ],

    // Names that are clearly a surah even without the word "surah" (others, like "nas" or
    // "rahman", are ordinary words and need "surah" in front).
    'standalone_surahs' => [1, 2, 3, 18, 36, 49, 56, 67, 112, 113],

    // Named verses.
    'named_verses' => [
        'ayatul kursi' => '2:255', 'ayat al kursi' => '2:255', 'ayatal kursi' => '2:255', 'ayatul kurshi' => '2:255', 'আয়াতুল কুরসি' => '2:255', 'আয়াতুল কুরসী' => '2:255',
    ],
];
