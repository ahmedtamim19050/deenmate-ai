# AdMob setup

The app uses Google's public test ids until you supply production ones, so development builds never serve real ads.

## Where ads can appear

| Placement | Screen | Admin switch |
| --- | --- | --- |
| Native ad | Home feed | `admob_home_native_enabled` |
| Native ad | Learn course list | `admob_learn_native_enabled` |
| Banner | Bottom of the Learn list | `admob_learn_banner_enabled` (off by default) |

**Never**: Quran reading, Salah confirmation, prayer notifications, Dua, Tasbih, AI answers, or any worship interaction. `mobile/test/ad_placement_test.dart` fails if an ad widget is added to those screens, or to any screen other than Home and Learn. Admin switches can only turn the allowed placements off.

Every ad is labelled "Sponsored", and sits in its own card, away from buttons.

## Production setup

1. Create Android and iOS apps in AdMob, and create one native and one banner ad unit for each.
2. Android app id, at build time:

   ```bash
   flutter build appbundle --release \
     -PADMOB_APP_ID=ca-app-pub-XXXX~YYYY \
     --dart-define=ADMOB_NATIVE_UNIT=ca-app-pub-XXXX/AAAA \
     --dart-define=ADMOB_BANNER_UNIT=ca-app-pub-XXXX/BBBB \
     --dart-define-from-file=env/production.json
   ```

3. iOS app id: replace `GADApplicationIdentifier` in `ios/Runner/Info.plist`. Pass the same `--dart-define` values.

Ad unit ids are not secrets, but real ids must not be used while testing. That risks your account.

## Consent and privacy

- The Google User Messaging Platform (UMP) runs at startup. Ads only initialise if `canRequestAds()` is true.
- **Before release, create a GDPR message and a US state privacy message in AdMob > Privacy & messaging.** Until those exist, UMP has nothing to show in regions that require it.
- Ads are non-personalised by default. People can opt in under Privacy and data. Worship records are never used for advertising.
- `maxAdContentRating` is `G`.
- In AdMob, block sensitive and unsuitable ad categories for your audience, such as gambling and alcohol. This cannot be done from the app.

## Children

The app does not target children. If your store listing or audience changes that, use the `tagForChildDirectedTreatment` and `tagForUnderAgeOfConsent` request settings in `AdService`, and review the Families policy.

## Store declarations

Google Play Data Safety and Apple App Privacy must both declare ad data collection, and the `AD_ID` permission is in the Android manifest.
