<?php

namespace App\Services\Prayer;

use App\Models\PrayerSchedule;
use App\Models\User;
use App\Models\UserPrayerSetting;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use IslamicNetwork\PrayerTimes\Method;
use IslamicNetwork\PrayerTimes\PrayerTimes;

class PrayerTimeService
{
    /** Methods are never assumed from a country: the user chooses, with MWL as a neutral start. */
    public const METHODS = [
        'MWL' => 'Muslim World League',
        'ISNA' => 'Islamic Society of North America',
        'MAKKAH' => 'Umm Al-Qura, Makkah',
        'KARACHI' => 'University of Islamic Sciences, Karachi',
        'EGYPT' => 'Egyptian General Authority of Survey',
        'TEHRAN' => 'Institute of Geophysics, University of Tehran',
        'GULF' => 'Gulf Region',
        'KUWAIT' => 'Kuwait',
        'QATAR' => 'Qatar',
        'SINGAPORE' => 'Singapore',
        'FRANCE' => 'Union of Islamic Organisations of France',
        'TURKEY' => 'Diyanet, Turkey',
        'RUSSIA' => 'Spiritual Administration of Muslims of Russia',
    ];

    public const HIGH_LATITUDE_RULES = [
        'angle_based' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
        'one_seventh' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH,
        'middle_of_night' => PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
    ];

    /** @return array<string, CarbonImmutable> prayer name => UTC instant */
    public function calculate(UserPrayerSetting $s, string $date): array
    {
        $school = $s->asr_method === 'hanafi' ? PrayerTimes::SCHOOL_HANAFI : PrayerTimes::SCHOOL_STANDARD;
        $calc = new PrayerTimes($s->calculation_method, $school);
        $calc->tune(0, $s->fajr_offset, $s->sunrise_offset, $s->dhuhr_offset, $s->asr_offset, $s->maghrib_offset, 0, $s->isha_offset, 0);

        $tz = new DateTimeZone($s->timezone);
        $times = $calc->getTimes(
            new DateTime($date.' 12:00:00', $tz),
            $s->latitude,
            $s->longitude,
            null,
            self::HIGH_LATITUDE_RULES[$s->high_latitude_rule] ?? PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
            null,
            PrayerTimes::TIME_FORMAT_ISO8601,
        );

        return [
            'fajr' => $this->toUtc($times[PrayerTimes::FAJR]),
            'sunrise' => $this->toUtc($times[PrayerTimes::SUNRISE]),
            'dhuhr' => $this->toUtc($times[PrayerTimes::ZHUHR]),
            'asr' => $this->toUtc($times[PrayerTimes::ASR]),
            'maghrib' => $this->toUtc($times[PrayerTimes::MAGHRIB]),
            'isha' => $this->toUtc($times[PrayerTimes::ISHA]),
        ];
    }

    /** Returns the stored schedule, recalculating when any setting has changed. */
    public function scheduleFor(User $user, UserPrayerSetting $s, string $date): PrayerSchedule
    {
        $hash = sha1(json_encode($s->only([
            'latitude', 'longitude', 'timezone', 'calculation_method', 'asr_method', 'high_latitude_rule',
            'fajr_offset', 'sunrise_offset', 'dhuhr_offset', 'asr_offset', 'maghrib_offset', 'isha_offset',
        ])));

        $existing = PrayerSchedule::query()->where('user_id', $user->id)->whereDate('date', $date)->first();
        if ($existing !== null && $existing->settings_hash === $hash) {
            return $existing;
        }

        $values = ['timezone' => $s->timezone, 'settings_hash' => $hash];
        foreach ($this->calculate($s, $date) as $prayer => $instant) {
            $values[$prayer.'_at'] = $instant->format('Y-m-d H:i:s');
        }

        if ($existing !== null) {
            $existing->update($values);

            return $existing;
        }

        return PrayerSchedule::query()->create($values + ['user_id' => $user->id, 'date' => $date]);
    }

    private function toUtc(string $iso): CarbonImmutable
    {
        return CarbonImmutable::parse($iso)->utc();
    }
}
