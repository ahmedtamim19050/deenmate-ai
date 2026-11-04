# App environments

Select one at build time:

```bash
flutter run --dart-define-from-file=env/development.json
flutter build apk --release --dart-define-from-file=env/production.json
```

| File | API | Use |
| --- | --- | --- |
| `development.json` | `http://deenmate-ai.test/api/v1` | Tools on the PC that resolve Laragon's `.test` names |
| `device.json` | `http://<PC LAN IP>:8000/api/v1` | A phone or emulator, which cannot resolve `.test` names |
| `production.json` | `https://deen.sohojware.dev/api/v1` | Release builds |

To make `device.json`, copy `device.example.json` and put in your PC's address. It is git-ignored, as the address changes between networks. It needs `php artisan serve --host=0.0.0.0 --port=8000` running in `backend/`, and port 8000 open in the Windows firewall.

With no file selected, debug builds use the development API and release builds use production. These files only hold public addresses: no credentials belong here.
