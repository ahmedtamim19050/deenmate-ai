# Notifications

## Prayer reminders (local)

These are scheduled on the device and need no server, Firebase or account.

**The flow:**

```
Notification: "Have you prayed Fajr?"   [Yes, I Prayed]  [Remind Later]
  Yes, I Prayed -> rakat selection -> summary and point preview -> Confirm Prayer -> saved
  Remind Later  -> 5, 10, 15 minutes or custom -> one extra reminder
  Tap the body  -> the same question in the app
  Dismissed     -> nothing at all
```

No notification interaction creates a record or awards points. `routeForNotification` only returns a route, and it is unit tested. A dismissed or ignored notification is never taken to mean a prayer was missed.

## Scheduling

- The next 7 days are scheduled, because iOS allows at most 64 pending notifications.
- Ids are derived from the date and prayer, so rescheduling replaces rather than duplicates.
- Remind Later uses a separate id range, so it never touches the regular schedule, and a reschedule does not remove it.

Rescheduling happens when:

| Trigger | Where |
| --- | --- |
| Settings, method, location or preferences change | `NotificationScheduler.run()` |
| A new day on app open or resume | `AppLifecycleWatcher` |
| A device timezone change | `AppLifecycleWatcher` |
| Device reboot or app update (Android) | Plugin boot receiver restores scheduled alarms |

If the app is not opened for over 7 days, reminders stop until it is next opened. The app says this in settings and help.

## Platform limits

We do not promise exact delivery, and the app says so.

**Android**

- Android 13+ needs the notification permission. It is asked for during onboarding or from settings, and declining is fine.
- Exact timing uses `SCHEDULE_EXACT_ALARM` on Android 12 and below, where available. On Android 13+ the app schedules inexact alarms unless exact alarm access is granted, so a reminder can arrive some minutes late.
- Battery optimisation and some manufacturers' task killers can delay or drop alarms.
- Google restricts exact alarm permissions on Play. If you want `USE_EXACT_ALARM`, check the current policy and declare it in your Play Console listing.

**iOS**

- 64 pending notifications at most.
- Action buttons appear on a long press or swipe.
- Focus modes may silence reminders.

## Preferences

Per prayer: on or off, and a delay after the prayer time. Sound, vibration and private preview apply to all. Private preview replaces the prayer name with a generic title, and marks the notification private on the lock screen.

Android notification channels cannot change sound after creation, so the app uses three: `prayer_default`, `prayer_no_vibration` and `prayer_silent`.

## Push announcements (optional)

Firebase Cloud Messaging is only for content announcements, never for worship records. Without config the app runs normally, and logs that Firebase is not set up.

**App:**

1. Create a Firebase project and add Android app `com.deenmate.ai`.
2. Put `google-services.json` in `mobile/android/app/`. The Gradle plugin applies automatically once the file exists.
3. For iOS, add `GoogleService-Info.plist` to `ios/Runner` in Xcode and configure APNs.

**Server:**

```env
FCM_PROJECT_ID=your-project
FCM_CREDENTIALS_PATH=/secure/path/service-account.json
```

Keep the service account file outside the web root. Turn on `notifications_broadcast_enabled` in **Notification Configuration**, then:

```bash
php artisan push:announce "New course" "A new Seerah course is available."
```
