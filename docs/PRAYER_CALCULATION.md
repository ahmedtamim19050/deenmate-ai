# Prayer calculation

## Where it runs

| Side | Library | Used for |
| --- | --- | --- |
| Device | `adhan` (Dart) | Times on screen, reminders, offline and guest use |
| Server | `islamic-network/prayer-times` (PHP) | `GET /prayer/today` |

`mobile/test/prayer_calculator_test.dart` compares the device against reference values from the server library for several cities and dates, allowing 3 minutes.

## Methods

The person chooses. No method is assumed from a country, and MWL is only a neutral starting value.

| Key | Name | Fajr | Isha |
| --- | --- | --- | --- |
| MWL | Muslim World League | 18° | 17° |
| ISNA | Islamic Society of North America | 15° | 15° |
| MAKKAH | Umm Al-Qura, Makkah | 18.5° | 90 minutes |
| KARACHI | University of Islamic Sciences, Karachi | 18° | 18° |
| EGYPT | Egyptian General Authority of Survey | 19.5° | 17.5° |
| TEHRAN | Institute of Geophysics, Tehran | 17.7° | 14° (Maghrib 4.5°) |
| GULF | Gulf Region | 19.5° | 90 minutes |
| KUWAIT | Kuwait | 18° | 17.5° |
| QATAR | Qatar | 18° | 90 minutes |
| SINGAPORE | Singapore | 20° | 18° |
| FRANCE | Union of Islamic Organisations of France | 12° | 12° |
| TURKEY | Diyanet, Turkey | 18° | 17° |
| RUSSIA | Spiritual Administration of Muslims of Russia | 16° | 15° |

The method tables live in `prayer_calculator.dart` and `PrayerTimeService.php`. Keep them in step.

**Known limitation:** Umm Al-Qura officially uses 120 minutes for Isha during Ramadan. The app uses 90 minutes all year, so people in that case should add a manual Isha offset during Ramadan. Official national timetables can also differ from any calculation.

## Asr

Standard: shadow equals the object's length. Hanafi: shadow is twice its length.

## High latitude

Angle based (the default), one seventh of the night, or middle of the night. Above about 48° in summer, twilight may not end. A local scholar or mosque timetable is the better guide there.

## Manual corrections

Each prayer accepts an offset from -120 to +120 minutes.

## Location

All optional, and all can be changed later:

- **City**: a built-in list with coordinates and timezone
- **Coordinates**: manual latitude, longitude and timezone
- **GPS**: only asks permission if chosen, with coarse accuracy. Coordinates are rounded to 4 decimals

## Dates and timezones

Calculation takes the calendar date at the prayer location, not on the device, and returns UTC instants. These are shown and scheduled in the location's timezone. Someone in Dhaka with a phone set to London still sees Dhaka times for Dhaka.

With `follow device timezone` on (GPS, or coordinates entered in the device's zone), a detected timezone change updates the settings and reschedules reminders.

## Hijri date

Calculated with the Umm al-Qura calendar. Local moon sighting can differ, so there is a -2 to +2 day adjustment in settings.
