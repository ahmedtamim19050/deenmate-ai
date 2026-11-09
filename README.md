# DeenQ AI

**Build Your Deen. Strengthen Your Connection with Allah.**

An Islamic personal growth, learning and worship-tracking companion for Android and iOS, with a Laravel API, a Filament admin panel and a source-grounded AI assistant.

| Folder | What it is |
| --- | --- |
| `mobile/` | Flutter app (Riverpod, GoRouter, Dio, Drift) |
| `backend/` | Laravel API under `/api/v1`, and the Filament panel at `/admin` |
| `docs/` | Architecture, database, API and operations guides |
| `deploy/` | nginx, supervisor and cron configs for the production server |

## Principles built into the code

- Deen Points are personal app points. They never represent faith, sincerity or reward from Allah.
- Worship records are always self-reported and user-confirmed. Notifications and AI cannot create them.
- There are no leaderboards, no public worship sharing, no point penalties and no shame-based streaks.
- The AI only uses scholar-approved sources, rejects answers that cite anything it did not retrieve, and says so when it cannot give a verified answer.
- Personal worship data is not visible to any admin role.
- Location, notifications and ads personalisation are optional. People can export and delete their data.

## Quick start

### Requirements

PHP 8.4, Composer, MySQL 8, Flutter 3.47 stable, JDK 17 or newer, Android SDK 36.

### Backend

```bash
cd backend
composer install
cp ../.env.example .env        # for local work set APP_ENV=local and APP_DEBUG=true
php artisan key:generate
# Create the database, then set DB_* in .env:
#   CREATE DATABASE deenmate_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed
php artisan filament:assets

php artisan quran:import              # 114 surahs, 6,236 verses. The run fails if incomplete.
php artisan quran:import-arabic       # Arabic text from Tanzil, validated; repeatable. Add --dry-run first
php artisan hadith:import nawawi      # or: bukhari, muslim, abudawud, tirmidhi, nasai, ibnmajah, malik

php artisan deenmate:make-admin you@example.com --role=super_admin

php artisan queue:work                # data exports run here
```

Seeding creates no users and no default admin credentials.

If `quran:import` fails with cURL error 60, your PHP has no CA bundle. Point `curl.cainfo` in `php.ini` at one. Do not turn off certificate checks.

On Laragon the project lives at `C:\laragon\www\deenmate-ai`, and is served at http://deenmate-ai.test (admin at `/admin`, API at `/api/v1`). See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for the `public` junction this relies on.

Production is https://deen.sohojware.dev, with settings from `backend/.env.production.example` and server configs in `deploy/`.

### Mobile

```bash
cd mobile
flutter pub get
dart run build_runner build --delete-conflicting-outputs
flutter gen-l10n

flutter run --dart-define-from-file=env/device.json        # phone or emulator, see env/README.md
flutter build apk --release --dart-define-from-file=env/production.json
```

### Tests

```bash
cd backend && php artisan test      # isolated in-memory SQLite database
cd mobile  && flutter test
```

## Before you ship

The app runs without any of these, and shows an honest state where a feature depends on them.

| Task | Guide |
| --- | --- |
| Have a Scholar Reviewer review sources. The AI cannot answer until sources are approved | [docs/ISLAMIC_SOURCE_VERIFICATION.md](docs/ISLAMIC_SOURCE_VERIFICATION.md) |
| Have a Scholar Reviewer review the starter lessons. They show "Awaiting scholar review" until then | [docs/ISLAMIC_SOURCE_VERIFICATION.md](docs/ISLAMIC_SOURCE_VERIFICATION.md) |
| Confirm the licence of each Quran translation and Hadith dataset | [docs/ISLAMIC_SOURCE_VERIFICATION.md](docs/ISLAMIC_SOURCE_VERIFICATION.md) |
| Add an AI provider key | [docs/AI_KNOWLEDGE_BASE.md](docs/AI_KNOWLEDGE_BASE.md) |
| Add production AdMob ids | [docs/ADMOB_SETUP.md](docs/ADMOB_SETUP.md) |
| Add Firebase config (optional, for push announcements) | [docs/NOTIFICATIONS.md](docs/NOTIFICATIONS.md) |
| Create a release signing key | [docs/ANDROID_BUILD.md](docs/ANDROID_BUILD.md) |
| Have native speakers review the Arabic and Bangla text | `mobile/lib/l10n/` |
| Deploy to https://deen.sohojware.dev | [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) |

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [API](docs/API.md)
- [AI knowledge base](docs/AI_KNOWLEDGE_BASE.md)
- [Islamic source verification](docs/ISLAMIC_SOURCE_VERIFICATION.md)
- [Prayer calculation](docs/PRAYER_CALCULATION.md)
- [Notifications](docs/NOTIFICATIONS.md)
- [AdMob setup](docs/ADMOB_SETUP.md)
- [Android build](docs/ANDROID_BUILD.md)
- [iOS build](docs/IOS_BUILD.md)
- [Deployment](docs/DEPLOYMENT.md)
