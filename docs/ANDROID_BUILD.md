# Android build

## Environment used

Flutter 3.47.9, Dart 3.13, Android SDK 36, build-tools 36.1, NDK 27.0.12077973, JDK 21 from Android Studio. Package `com.deenmate.ai`, minimum SDK 24.

## Debug APK

```bash
cd mobile
flutter pub get
dart run build_runner build --delete-conflicting-outputs
flutter build apk --debug
```

Output: `mobile/build/app/outputs/flutter-apk/app-debug.apk`

A debug build defaults to `http://deenmate-ai.test/api/v1`. Phones and emulators cannot resolve Laragon's `.test` names, so for a device copy `env/device.example.json` to `env/device.json`, put in your PC's address, and:

```bash
flutter build apk --debug --dart-define-from-file=env/device.json
php artisan serve --host=0.0.0.0 --port=8000     # in backend/
```

See `env/README.md`.

Prayer times, reminders and Tasbih work without any server. Quran text, lessons and the AI need one.

Debug builds allow plain HTTP for this purpose. Release builds do not.

## Release signing

No key is included, and there is no fallback: without one, a release build is unsigned. **Create your own and back it up. If you lose it you cannot update the app without Play App Signing.**

```bash
keytool -genkey -v -keystore ~/deenmate-release.jks -keyalg RSA -keysize 4096 -validity 10000 -alias deenmate
```

Create `mobile/android/key.properties`, which is git-ignored:

```properties
storeFile=C:/full/path/deenmate-release.jks
storePassword=...
keyAlias=deenmate
keyPassword=...
```

## Release builds

```bash
flutter build apk --release --dart-define-from-file=env/production.json
flutter build appbundle --release --dart-define-from-file=env/production.json
```

Outputs:

- `build/app/outputs/flutter-apk/app-release.apk`
- `build/app/outputs/bundle/release/app-release.aab`

Release builds default to `https://deen.sohojware.dev/api/v1`. See [ADMOB_SETUP.md](ADMOB_SETUP.md) for the ad flags.

Bump `version` in `pubspec.yaml` for each store upload.

## Icon and splash

Sources are in `assets/images/`. To regenerate:

```bash
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```

## Troubleshooting

| Problem | Fix |
| --- | --- |
| `Some Android licenses not accepted` | Run `flutter doctor --android-licenses` and read them |
| NDK mismatch | `ndkVersion` in `android/app/build.gradle.kts` must match an installed NDK |
| App cannot reach the API on a phone | Use your LAN address and `php artisan serve --host=0.0.0.0`, and check the firewall |
