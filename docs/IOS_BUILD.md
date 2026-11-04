# iOS build

iOS cannot be built on Windows, so **the iOS app has not been compiled or run**. The project is configured, but expect to fix small issues on first build.

## What is configured

- Display name DeenMate AI, bundle id `com.deenmate.ai`
- Icons and splash generated
- Location usage text, which is only triggered if someone chooses GPS
- Notification action buttons
- AdMob test app id
- App Tracking Transparency usage text
- Background modes for remote notifications

## What you need

A Mac with Xcode and CocoaPods, an Apple Developer account, and your own signing team.

## Build

```bash
cd mobile
flutter pub get
dart run build_runner build --delete-conflicting-outputs
cd ios && pod install && cd ..
open ios/Runner.xcworkspace
```

In Xcode, set your **Team** under Signing & Capabilities, and add **Push Notifications** if you use FCM.

```bash
flutter build ipa --release --dart-define-from-file=env/production.json
```

## Before submitting

- Set the deployment target to whatever current plugins require. `pod install` reports this.
- Replace the test `GADApplicationIdentifier` in `Info.plist`.
- Add `GoogleService-Info.plist` and an APNs key if you use push.
- Complete App Privacy, including what goes to the AI provider and ad network.
- Show the account deletion path in your review notes. It is at Profile > Privacy and data.
- Test the notification buttons on a real device, as simulators are unreliable for this.
