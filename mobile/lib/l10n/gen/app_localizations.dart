import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_bn.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'gen/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('bn'),
    Locale('en'),
  ];

  /// No description provided for @about.
  ///
  /// In en, this message translates to:
  /// **'About'**
  String get about;

  /// No description provided for @aboutBody.
  ///
  /// In en, this message translates to:
  /// **'DeenQ AI is a private companion for building Islamic habits and learning from reliable references. Your records are for you alone. The app cannot verify worship and does not judge anyone.'**
  String get aboutBody;

  /// No description provided for @aboutSources.
  ///
  /// In en, this message translates to:
  /// **'Quran and Hadith text comes from imported datasets, with the source shown beside the text. Prayer times are calculated and may differ from your local mosque.'**
  String get aboutSources;

  /// No description provided for @accountDeleted.
  ///
  /// In en, this message translates to:
  /// **'Your account and personal data have been deleted.'**
  String get accountDeleted;

  /// No description provided for @accountRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'This needs an account'**
  String get accountRequiredTitle;

  /// No description provided for @achievements.
  ///
  /// In en, this message translates to:
  /// **'Achievements'**
  String get achievements;

  /// No description provided for @achievementsNote.
  ///
  /// In en, this message translates to:
  /// **'Private milestones of your app activity. They do not reflect spiritual status.'**
  String get achievementsNote;

  /// No description provided for @activityHistory.
  ///
  /// In en, this message translates to:
  /// **'Activity History'**
  String get activityHistory;

  /// No description provided for @add.
  ///
  /// In en, this message translates to:
  /// **'Add'**
  String get add;

  /// No description provided for @addBookmark.
  ///
  /// In en, this message translates to:
  /// **'Add bookmark'**
  String get addBookmark;

  /// No description provided for @addCategory.
  ///
  /// In en, this message translates to:
  /// **'Add category'**
  String get addCategory;

  /// No description provided for @addOptionalPrayer.
  ///
  /// In en, this message translates to:
  /// **'Add optional prayer'**
  String get addOptionalPrayer;

  /// No description provided for @adsPrivacy.
  ///
  /// In en, this message translates to:
  /// **'Advertising'**
  String get adsPrivacy;

  /// No description provided for @aiDisclaimer.
  ///
  /// In en, this message translates to:
  /// **'Educational help from reviewed sources. Not a fatwa or a scholar.'**
  String get aiDisclaimer;

  /// No description provided for @aiHistory.
  ///
  /// In en, this message translates to:
  /// **'Conversation history'**
  String get aiHistory;

  /// No description provided for @aiHistoryCleared.
  ///
  /// In en, this message translates to:
  /// **'Your AI history has been cleared.'**
  String get aiHistoryCleared;

  /// No description provided for @aiInputHint.
  ///
  /// In en, this message translates to:
  /// **'Ask about Islam...'**
  String get aiInputHint;

  /// No description provided for @aiIntroBody.
  ///
  /// In en, this message translates to:
  /// **'I answer from reviewed Islamic sources and show where each answer comes from. If the sources do not cover something, I will say so rather than guess.'**
  String get aiIntroBody;

  /// No description provided for @aiIntroTitle.
  ///
  /// In en, this message translates to:
  /// **'Learn with reliable references'**
  String get aiIntroTitle;

  /// No description provided for @aiNeedsAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to use the AI assistant, so your conversations stay private to you.'**
  String get aiNeedsAccount;

  /// No description provided for @aiNotConfiguredBanner.
  ///
  /// In en, this message translates to:
  /// **'The AI assistant has not been set up on the server yet, so it cannot answer. Everything else works normally.'**
  String get aiNotConfiguredBanner;

  /// No description provided for @aiOffline.
  ///
  /// In en, this message translates to:
  /// **'You appear to be offline. The AI assistant needs a connection.'**
  String get aiOffline;

  /// No description provided for @aiPersonalization.
  ///
  /// In en, this message translates to:
  /// **'Use my activity in AI answers'**
  String get aiPersonalization;

  /// No description provided for @aiPersonalizationBody.
  ///
  /// In en, this message translates to:
  /// **'Off by default. When on, and only if you ask about your own progress, seven-day totals by category are shared with the AI provider.'**
  String get aiPersonalizationBody;

  /// No description provided for @aiPrivacy.
  ///
  /// In en, this message translates to:
  /// **'AI assistant'**
  String get aiPrivacy;

  /// No description provided for @aiRateLimited.
  ///
  /// In en, this message translates to:
  /// **'You are sending messages quickly. Please wait a moment and try again.'**
  String get aiRateLimited;

  /// No description provided for @aiSearching.
  ///
  /// In en, this message translates to:
  /// **'Looking through reviewed sources...'**
  String get aiSearching;

  /// No description provided for @aiTitle.
  ///
  /// In en, this message translates to:
  /// **'AI Assistant'**
  String get aiTitle;

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'DeenQ AI'**
  String get appName;

  /// No description provided for @archive.
  ///
  /// In en, this message translates to:
  /// **'Archive'**
  String get archive;

  /// No description provided for @askAssistant.
  ///
  /// In en, this message translates to:
  /// **'Ask the assistant'**
  String get askAssistant;

  /// No description provided for @askAssistantBody.
  ///
  /// In en, this message translates to:
  /// **'Explore a question using reviewed sources.'**
  String get askAssistantBody;

  /// No description provided for @asrHanafi.
  ///
  /// In en, this message translates to:
  /// **'Hanafi'**
  String get asrHanafi;

  /// No description provided for @asrHanafiBody.
  ///
  /// In en, this message translates to:
  /// **'Asr begins when an object\'s shadow is twice its length.'**
  String get asrHanafiBody;

  /// No description provided for @asrMethod.
  ///
  /// In en, this message translates to:
  /// **'Asr calculation'**
  String get asrMethod;

  /// No description provided for @asrStandard.
  ///
  /// In en, this message translates to:
  /// **'Standard (Shafi\'i, Maliki, Hanbali)'**
  String get asrStandard;

  /// No description provided for @asrStandardBody.
  ///
  /// In en, this message translates to:
  /// **'Asr begins when an object\'s shadow equals its length.'**
  String get asrStandardBody;

  /// No description provided for @atPrayerTime.
  ///
  /// In en, this message translates to:
  /// **'At prayer time'**
  String get atPrayerTime;

  /// No description provided for @audioUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Recitation audio is not available right now.'**
  String get audioUnavailable;

  /// No description provided for @awaitingReview.
  ///
  /// In en, this message translates to:
  /// **'Awaiting scholar review'**
  String get awaitingReview;

  /// No description provided for @awaitingReviewBody.
  ///
  /// In en, this message translates to:
  /// **'A scholar has not reviewed this lesson yet. Please check anything important with a qualified teacher.'**
  String get awaitingReviewBody;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @bookmarkNeedsAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to save bookmarks.'**
  String get bookmarkNeedsAccount;

  /// No description provided for @bookmarks.
  ///
  /// In en, this message translates to:
  /// **'Bookmarks'**
  String get bookmarks;

  /// No description provided for @calculationMethod.
  ///
  /// In en, this message translates to:
  /// **'Calculation method'**
  String get calculationMethod;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @catAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get catAll;

  /// No description provided for @catBasics.
  ///
  /// In en, this message translates to:
  /// **'Basics'**
  String get catBasics;

  /// No description provided for @catBeginner.
  ///
  /// In en, this message translates to:
  /// **'Beginner journey'**
  String get catBeginner;

  /// No description provided for @catHistory.
  ///
  /// In en, this message translates to:
  /// **'Islamic history'**
  String get catHistory;

  /// No description provided for @catQuran.
  ///
  /// In en, this message translates to:
  /// **'Quran learning'**
  String get catQuran;

  /// No description provided for @catSeerah.
  ///
  /// In en, this message translates to:
  /// **'Seerah'**
  String get catSeerah;

  /// No description provided for @category.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get category;

  /// No description provided for @categoryAdded.
  ///
  /// In en, this message translates to:
  /// **'Category added'**
  String get categoryAdded;

  /// No description provided for @categoryFard.
  ///
  /// In en, this message translates to:
  /// **'Fard'**
  String get categoryFard;

  /// No description provided for @categoryNafl.
  ///
  /// In en, this message translates to:
  /// **'Nafl'**
  String get categoryNafl;

  /// No description provided for @categorySunnah.
  ///
  /// In en, this message translates to:
  /// **'Sunnah'**
  String get categorySunnah;

  /// No description provided for @categoryWitr.
  ///
  /// In en, this message translates to:
  /// **'Witr'**
  String get categoryWitr;

  /// No description provided for @change.
  ///
  /// In en, this message translates to:
  /// **'Change'**
  String get change;

  /// No description provided for @chartSummary.
  ///
  /// In en, this message translates to:
  /// **'Total for this period: {total}'**
  String chartSummary(Object total);

  /// No description provided for @chooseCity.
  ///
  /// In en, this message translates to:
  /// **'Choose a city'**
  String get chooseCity;

  /// No description provided for @chooseLocation.
  ///
  /// In en, this message translates to:
  /// **'Choose location'**
  String get chooseLocation;

  /// No description provided for @clear.
  ///
  /// In en, this message translates to:
  /// **'Clear'**
  String get clear;

  /// No description provided for @clearAiHistory.
  ///
  /// In en, this message translates to:
  /// **'Clear AI history'**
  String get clearAiHistory;

  /// No description provided for @clearAiHistoryBody.
  ///
  /// In en, this message translates to:
  /// **'This permanently removes all your AI conversations.'**
  String get clearAiHistoryBody;

  /// No description provided for @clearDeviceData.
  ///
  /// In en, this message translates to:
  /// **'Clear data on this device'**
  String get clearDeviceData;

  /// No description provided for @clearDeviceDataBody.
  ///
  /// In en, this message translates to:
  /// **'Removes records waiting to sync, saved sessions, settings and reminders from this device, and signs you out.'**
  String get clearDeviceDataBody;

  /// No description provided for @confirmPrayer.
  ///
  /// In en, this message translates to:
  /// **'Confirm Prayer'**
  String get confirmPrayer;

  /// No description provided for @confirmRecord.
  ///
  /// In en, this message translates to:
  /// **'Confirm and save'**
  String get confirmRecord;

  /// No description provided for @confirmedAt.
  ///
  /// In en, this message translates to:
  /// **'Confirmed {time}'**
  String confirmedAt(Object time);

  /// No description provided for @contactSupport.
  ///
  /// In en, this message translates to:
  /// **'Contact support'**
  String get contactSupport;

  /// No description provided for @continueAsGuest.
  ///
  /// In en, this message translates to:
  /// **'Continue as guest'**
  String get continueAsGuest;

  /// No description provided for @continueLabel.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get continueLabel;

  /// No description provided for @continueReading.
  ///
  /// In en, this message translates to:
  /// **'Continue reading'**
  String get continueReading;

  /// No description provided for @coordinateInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid coordinate'**
  String get coordinateInvalid;

  /// No description provided for @copied.
  ///
  /// In en, this message translates to:
  /// **'Copied'**
  String get copied;

  /// No description provided for @copy.
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get copy;

  /// No description provided for @countryCode.
  ///
  /// In en, this message translates to:
  /// **'Country code (for example BD)'**
  String get countryCode;

  /// No description provided for @courses.
  ///
  /// In en, this message translates to:
  /// **'Courses'**
  String get courses;

  /// No description provided for @createAccount.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get createAccount;

  /// No description provided for @currentLocation.
  ///
  /// In en, this message translates to:
  /// **'Current location'**
  String get currentLocation;

  /// No description provided for @customLocation.
  ///
  /// In en, this message translates to:
  /// **'Custom location'**
  String get customLocation;

  /// No description provided for @customTarget.
  ///
  /// In en, this message translates to:
  /// **'Custom target'**
  String get customTarget;

  /// No description provided for @customizeTemplates.
  ///
  /// In en, this message translates to:
  /// **'Customize'**
  String get customizeTemplates;

  /// No description provided for @dailyActivities.
  ///
  /// In en, this message translates to:
  /// **'Daily activities'**
  String get dailyActivities;

  /// No description provided for @decrease.
  ///
  /// In en, this message translates to:
  /// **'Decrease'**
  String get decrease;

  /// No description provided for @deedCharity.
  ///
  /// In en, this message translates to:
  /// **'Giving charity'**
  String get deedCharity;

  /// No description provided for @deedCustom.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get deedCustom;

  /// No description provided for @deedFamily.
  ///
  /// In en, this message translates to:
  /// **'Supporting family'**
  String get deedFamily;

  /// No description provided for @deedHelping.
  ///
  /// In en, this message translates to:
  /// **'Helping someone'**
  String get deedHelping;

  /// No description provided for @deedNoteHelp.
  ///
  /// In en, this message translates to:
  /// **'Please avoid names or sensitive details. A category is enough.'**
  String get deedNoteHelp;

  /// No description provided for @deedVisiting.
  ///
  /// In en, this message translates to:
  /// **'Visiting someone'**
  String get deedVisiting;

  /// No description provided for @deedVolunteering.
  ///
  /// In en, this message translates to:
  /// **'Volunteering'**
  String get deedVolunteering;

  /// No description provided for @deedsPrivate.
  ///
  /// In en, this message translates to:
  /// **'Your records here are private to you.'**
  String get deedsPrivate;

  /// No description provided for @deenPoints.
  ///
  /// In en, this message translates to:
  /// **'Deen Points'**
  String get deenPoints;

  /// No description provided for @deleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Delete account'**
  String get deleteAccount;

  /// No description provided for @deleteAccountBody.
  ///
  /// In en, this message translates to:
  /// **'This permanently deletes your account and all your personal records. It cannot be undone. Enter your password to confirm.'**
  String get deleteAccountBody;

  /// No description provided for @deleteAccountShort.
  ///
  /// In en, this message translates to:
  /// **'Permanently removes your account and data.'**
  String get deleteAccountShort;

  /// No description provided for @deletePermanently.
  ///
  /// In en, this message translates to:
  /// **'Delete permanently'**
  String get deletePermanently;

  /// No description provided for @deviceDataCleared.
  ///
  /// In en, this message translates to:
  /// **'Data on this device has been cleared.'**
  String get deviceDataCleared;

  /// No description provided for @dhikrCount.
  ///
  /// In en, this message translates to:
  /// **'Dhikr count'**
  String get dhikrCount;

  /// No description provided for @dhikrTitle.
  ///
  /// In en, this message translates to:
  /// **'Dhikr'**
  String get dhikrTitle;

  /// No description provided for @edit.
  ///
  /// In en, this message translates to:
  /// **'Edit'**
  String get edit;

  /// No description provided for @editRecord.
  ///
  /// In en, this message translates to:
  /// **'Edit record'**
  String get editRecord;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get email;

  /// No description provided for @emailInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email address'**
  String get emailInvalid;

  /// No description provided for @enableNotifications.
  ///
  /// In en, this message translates to:
  /// **'Allow notifications'**
  String get enableNotifications;

  /// No description provided for @enterCoordinates.
  ///
  /// In en, this message translates to:
  /// **'Enter coordinates'**
  String get enterCoordinates;

  /// No description provided for @errorBody.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get errorBody;

  /// No description provided for @errorTitle.
  ///
  /// In en, this message translates to:
  /// **'That did not work'**
  String get errorTitle;

  /// No description provided for @exportBody.
  ///
  /// In en, this message translates to:
  /// **'Download a copy of your personal data as a file.'**
  String get exportBody;

  /// No description provided for @exportLater.
  ///
  /// In en, this message translates to:
  /// **'Your export is still being prepared. Please try again in a few minutes.'**
  String get exportLater;

  /// No description provided for @exportPreparing.
  ///
  /// In en, this message translates to:
  /// **'Preparing your export...'**
  String get exportPreparing;

  /// No description provided for @exportReady.
  ///
  /// In en, this message translates to:
  /// **'Your export is ready.'**
  String get exportReady;

  /// No description provided for @exportTitle.
  ///
  /// In en, this message translates to:
  /// **'Export my data'**
  String get exportTitle;

  /// No description provided for @faqAiA.
  ///
  /// In en, this message translates to:
  /// **'It only uses sources that a scholar reviewer has approved, and shows them with each answer. If they do not cover a question, it says so. It is not a scholar and does not issue fatwas.'**
  String get faqAiA;

  /// No description provided for @faqAiQ.
  ///
  /// In en, this message translates to:
  /// **'How reliable is the AI assistant?'**
  String get faqAiQ;

  /// No description provided for @faqDataA.
  ///
  /// In en, this message translates to:
  /// **'Your records are private and never shared publicly. Admin screens do not show personal prayer history. You can export or delete your data from Privacy and data.'**
  String get faqDataA;

  /// No description provided for @faqDataQ.
  ///
  /// In en, this message translates to:
  /// **'Who can see my records?'**
  String get faqDataQ;

  /// No description provided for @faqNotificationsQ.
  ///
  /// In en, this message translates to:
  /// **'Why was a reminder late or missing?'**
  String get faqNotificationsQ;

  /// No description provided for @faqPointsQ.
  ///
  /// In en, this message translates to:
  /// **'What are Deen Points?'**
  String get faqPointsQ;

  /// No description provided for @faqSyncA.
  ///
  /// In en, this message translates to:
  /// **'Records you make offline are saved on your device and marked Pending Sync. They are sent when you are back online and signed in, and only then are points awarded by the server.'**
  String get faqSyncA;

  /// No description provided for @faqSyncQ.
  ///
  /// In en, this message translates to:
  /// **'What does Pending Sync mean?'**
  String get faqSyncQ;

  /// No description provided for @faqTimesA.
  ///
  /// In en, this message translates to:
  /// **'Times are calculated from your location and chosen method, so they can differ from your local mosque\'s timetable by a few minutes. You can change the method or adjust each prayer by hand in Prayer settings.'**
  String get faqTimesA;

  /// No description provided for @faqTimesQ.
  ///
  /// In en, this message translates to:
  /// **'Why are the times different from my mosque?'**
  String get faqTimesQ;

  /// No description provided for @fastMondayThursday.
  ///
  /// In en, this message translates to:
  /// **'Monday or Thursday'**
  String get fastMondayThursday;

  /// No description provided for @fastNotRecorded.
  ///
  /// In en, this message translates to:
  /// **'No record'**
  String get fastNotRecorded;

  /// No description provided for @fastOther.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get fastOther;

  /// No description provided for @fastRamadan.
  ///
  /// In en, this message translates to:
  /// **'Ramadan'**
  String get fastRamadan;

  /// No description provided for @fastRecorded.
  ///
  /// In en, this message translates to:
  /// **'Recorded'**
  String get fastRecorded;

  /// No description provided for @fastVoluntary.
  ///
  /// In en, this message translates to:
  /// **'Voluntary'**
  String get fastVoluntary;

  /// No description provided for @fastingDays.
  ///
  /// In en, this message translates to:
  /// **'Fasting days recorded'**
  String get fastingDays;

  /// No description provided for @fastingHistory.
  ///
  /// In en, this message translates to:
  /// **'Fasting history'**
  String get fastingHistory;

  /// No description provided for @fastingStatus.
  ///
  /// In en, this message translates to:
  /// **'Fasting today'**
  String get fastingStatus;

  /// No description provided for @fastingTitle.
  ///
  /// In en, this message translates to:
  /// **'Fasting'**
  String get fastingTitle;

  /// No description provided for @feedbackThanks.
  ///
  /// In en, this message translates to:
  /// **'Thank you for your feedback.'**
  String get feedbackThanks;

  /// No description provided for @fieldRequired.
  ///
  /// In en, this message translates to:
  /// **'This field is required'**
  String get fieldRequired;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @getStarted.
  ///
  /// In en, this message translates to:
  /// **'Get started'**
  String get getStarted;

  /// No description provided for @goalCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get goalCompleted;

  /// No description provided for @goalInvalid.
  ///
  /// In en, this message translates to:
  /// **'Please add a title and a target of at least 1.'**
  String get goalInvalid;

  /// No description provided for @goalPaused.
  ///
  /// In en, this message translates to:
  /// **'Paused'**
  String get goalPaused;

  /// No description provided for @goalTarget.
  ///
  /// In en, this message translates to:
  /// **'Target: {target}'**
  String goalTarget(Object target);

  /// No description provided for @goalTitle.
  ///
  /// In en, this message translates to:
  /// **'Goal'**
  String get goalTitle;

  /// No description provided for @goalTypeCustom.
  ///
  /// In en, this message translates to:
  /// **'Custom'**
  String get goalTypeCustom;

  /// No description provided for @goalTypeDhikr.
  ///
  /// In en, this message translates to:
  /// **'Daily dhikr'**
  String get goalTypeDhikr;

  /// No description provided for @goalTypeHabit.
  ///
  /// In en, this message translates to:
  /// **'Worship habit'**
  String get goalTypeHabit;

  /// No description provided for @goalTypeLearning.
  ///
  /// In en, this message translates to:
  /// **'Learning'**
  String get goalTypeLearning;

  /// No description provided for @goalTypeQuran.
  ///
  /// In en, this message translates to:
  /// **'Quran reading'**
  String get goalTypeQuran;

  /// No description provided for @goalsNeedAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to set personal goals.'**
  String get goalsNeedAccount;

  /// No description provided for @goodDeedsTitle.
  ///
  /// In en, this message translates to:
  /// **'Good Deeds'**
  String get goodDeedsTitle;

  /// No description provided for @gpsDenied.
  ///
  /// In en, this message translates to:
  /// **'Location permission was not given. That is fine: you can choose a city or enter coordinates instead.'**
  String get gpsDenied;

  /// No description provided for @gpsFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not get your location. You can choose a city instead.'**
  String get gpsFailed;

  /// No description provided for @gpsOptional.
  ///
  /// In en, this message translates to:
  /// **'Optional. Only used to calculate prayer times.'**
  String get gpsOptional;

  /// No description provided for @gpsServiceOff.
  ///
  /// In en, this message translates to:
  /// **'Location services are turned off on this device.'**
  String get gpsServiceOff;

  /// No description provided for @gradeLabel.
  ///
  /// In en, this message translates to:
  /// **'Grade: {grade} ({source})'**
  String gradeLabel(Object grade, Object source);

  /// No description provided for @gradeNotDocumented.
  ///
  /// In en, this message translates to:
  /// **'Grade: not documented in this dataset'**
  String get gradeNotDocumented;

  /// No description provided for @greetingAfternoon.
  ///
  /// In en, this message translates to:
  /// **'Good afternoon'**
  String get greetingAfternoon;

  /// No description provided for @greetingEvening.
  ///
  /// In en, this message translates to:
  /// **'Good evening'**
  String get greetingEvening;

  /// No description provided for @greetingMorning.
  ///
  /// In en, this message translates to:
  /// **'Good morning'**
  String get greetingMorning;

  /// No description provided for @guest.
  ///
  /// In en, this message translates to:
  /// **'Guest'**
  String get guest;

  /// No description provided for @guestExplain.
  ///
  /// In en, this message translates to:
  /// **'As a guest you can see prayer times, set reminders, use Tasbih and browse lessons. Records wait safely on your device until you create an account.'**
  String get guestExplain;

  /// No description provided for @guestProgressBody.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to keep private records and see your progress across devices.'**
  String get guestProgressBody;

  /// No description provided for @guestProgressTitle.
  ///
  /// In en, this message translates to:
  /// **'Your personal progress'**
  String get guestProgressTitle;

  /// No description provided for @guestSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Your records stay on this device'**
  String get guestSubtitle;

  /// No description provided for @hadithNumber.
  ///
  /// In en, this message translates to:
  /// **'Hadith {number}'**
  String hadithNumber(Object number);

  /// No description provided for @hadithTitle.
  ///
  /// In en, this message translates to:
  /// **'Hadith'**
  String get hadithTitle;

  /// No description provided for @hadithUnavailableBody.
  ///
  /// In en, this message translates to:
  /// **'No verified Hadith collection has been added yet.'**
  String get hadithUnavailableBody;

  /// No description provided for @hadithUnavailableTitle.
  ///
  /// In en, this message translates to:
  /// **'Hadith library unavailable'**
  String get hadithUnavailableTitle;

  /// No description provided for @hapticFeedback.
  ///
  /// In en, this message translates to:
  /// **'Vibrate on each count'**
  String get hapticFeedback;

  /// No description provided for @helpAndSupport.
  ///
  /// In en, this message translates to:
  /// **'Help and support'**
  String get helpAndSupport;

  /// No description provided for @helpful.
  ///
  /// In en, this message translates to:
  /// **'Helpful'**
  String get helpful;

  /// No description provided for @highLatAngle.
  ///
  /// In en, this message translates to:
  /// **'Angle based (recommended)'**
  String get highLatAngle;

  /// No description provided for @highLatMiddle.
  ///
  /// In en, this message translates to:
  /// **'Middle of the night'**
  String get highLatMiddle;

  /// No description provided for @highLatSeventh.
  ///
  /// In en, this message translates to:
  /// **'One seventh of the night'**
  String get highLatSeventh;

  /// No description provided for @highLatitudeHelp.
  ///
  /// In en, this message translates to:
  /// **'Used at high latitudes, where twilight may not end in summer.'**
  String get highLatitudeHelp;

  /// No description provided for @highLatitudeRule.
  ///
  /// In en, this message translates to:
  /// **'High latitude adjustment'**
  String get highLatitudeRule;

  /// No description provided for @hijriAdjustment.
  ///
  /// In en, this message translates to:
  /// **'Hijri date adjustment'**
  String get hijriAdjustment;

  /// No description provided for @hijriAdjustmentHelp.
  ///
  /// In en, this message translates to:
  /// **'The Hijri date is calculated. Local moon sighting can differ by a day or two, so adjust it to match your community.'**
  String get hijriAdjustmentHelp;

  /// No description provided for @historyNeedsAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to keep a private history of your records.'**
  String get historyNeedsAccount;

  /// No description provided for @inMinutes.
  ///
  /// In en, this message translates to:
  /// **'In {minutes} minutes'**
  String inMinutes(Object minutes);

  /// No description provided for @increase.
  ///
  /// In en, this message translates to:
  /// **'Increase'**
  String get increase;

  /// No description provided for @keepBuilding.
  ///
  /// In en, this message translates to:
  /// **'Keep building your habits'**
  String get keepBuilding;

  /// No description provided for @label.
  ///
  /// In en, this message translates to:
  /// **'Label'**
  String get label;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @languageAndTheme.
  ///
  /// In en, this message translates to:
  /// **'Language and appearance'**
  String get languageAndTheme;

  /// No description provided for @latitude.
  ///
  /// In en, this message translates to:
  /// **'Latitude'**
  String get latitude;

  /// No description provided for @lessonCompleted.
  ///
  /// In en, this message translates to:
  /// **'Lesson completed'**
  String get lessonCompleted;

  /// No description provided for @lessonCompletedPoints.
  ///
  /// In en, this message translates to:
  /// **'Lesson completed. {points} app points.'**
  String lessonCompletedPoints(Object points);

  /// No description provided for @lessonNeedsAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to track your lessons.'**
  String get lessonNeedsAccount;

  /// No description provided for @lessonsCompleted.
  ///
  /// In en, this message translates to:
  /// **'Lessons completed'**
  String get lessonsCompleted;

  /// No description provided for @lessonsProgress.
  ///
  /// In en, this message translates to:
  /// **'{done} of {total} lessons'**
  String lessonsProgress(Object done, Object total);

  /// No description provided for @loadMore.
  ///
  /// In en, this message translates to:
  /// **'Load more'**
  String get loadMore;

  /// No description provided for @location.
  ///
  /// In en, this message translates to:
  /// **'Location'**
  String get location;

  /// No description provided for @longitude.
  ///
  /// In en, this message translates to:
  /// **'Longitude'**
  String get longitude;

  /// No description provided for @manualOffsets.
  ///
  /// In en, this message translates to:
  /// **'Manual corrections (minutes)'**
  String get manualOffsets;

  /// No description provided for @manualOffsetsHelp.
  ///
  /// In en, this message translates to:
  /// **'Adjust any time to match your local timetable.'**
  String get manualOffsetsHelp;

  /// No description provided for @markComplete.
  ///
  /// In en, this message translates to:
  /// **'Mark complete'**
  String get markComplete;

  /// No description provided for @markLessonComplete.
  ///
  /// In en, this message translates to:
  /// **'Mark lesson complete'**
  String get markLessonComplete;

  /// No description provided for @minutes.
  ///
  /// In en, this message translates to:
  /// **'Minutes'**
  String get minutes;

  /// No description provided for @minutesAfter.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min after'**
  String minutesAfter(Object minutes);

  /// No description provided for @minutesShort.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min'**
  String minutesShort(Object minutes);

  /// No description provided for @name.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get name;

  /// No description provided for @navAi.
  ///
  /// In en, this message translates to:
  /// **'AI Assistant'**
  String get navAi;

  /// No description provided for @navHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// No description provided for @navLearn.
  ///
  /// In en, this message translates to:
  /// **'Learn'**
  String get navLearn;

  /// No description provided for @navProfile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get navProfile;

  /// No description provided for @navTracker.
  ///
  /// In en, this message translates to:
  /// **'Tracker'**
  String get navTracker;

  /// No description provided for @newChat.
  ///
  /// In en, this message translates to:
  /// **'New conversation'**
  String get newChat;

  /// No description provided for @newGoal.
  ///
  /// In en, this message translates to:
  /// **'New goal'**
  String get newGoal;

  /// No description provided for @newPassword.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPassword;

  /// No description provided for @nextPrayer.
  ///
  /// In en, this message translates to:
  /// **'Next prayer'**
  String get nextPrayer;

  /// No description provided for @noBookmarksBody.
  ///
  /// In en, this message translates to:
  /// **'Tap the bookmark icon on a verse to find it here later.'**
  String get noBookmarksBody;

  /// No description provided for @noBookmarksTitle.
  ///
  /// In en, this message translates to:
  /// **'No bookmarks yet'**
  String get noBookmarksTitle;

  /// No description provided for @noCityFound.
  ///
  /// In en, this message translates to:
  /// **'No matching city'**
  String get noCityFound;

  /// No description provided for @noCityFoundBody.
  ///
  /// In en, this message translates to:
  /// **'You can enter your coordinates instead.'**
  String get noCityFoundBody;

  /// No description provided for @noConversationsBody.
  ///
  /// In en, this message translates to:
  /// **'Questions you ask will be saved here for you.'**
  String get noConversationsBody;

  /// No description provided for @noConversationsTitle.
  ///
  /// In en, this message translates to:
  /// **'No conversations yet'**
  String get noConversationsTitle;

  /// No description provided for @noCoursesBody.
  ///
  /// In en, this message translates to:
  /// **'New content will appear here once it is published.'**
  String get noCoursesBody;

  /// No description provided for @noCoursesTitle.
  ///
  /// In en, this message translates to:
  /// **'No courses here yet'**
  String get noCoursesTitle;

  /// No description provided for @noGoalsBody.
  ///
  /// In en, this message translates to:
  /// **'Set one small goal when you feel ready. You can pause or change it at any time.'**
  String get noGoalsBody;

  /// No description provided for @noGoalsTitle.
  ///
  /// In en, this message translates to:
  /// **'No goals yet'**
  String get noGoalsTitle;

  /// No description provided for @noLessonsTitle.
  ///
  /// In en, this message translates to:
  /// **'No lessons in this course yet'**
  String get noLessonsTitle;

  /// No description provided for @noLocationBody.
  ///
  /// In en, this message translates to:
  /// **'Choose a city or enter coordinates to see prayer times. GPS is optional.'**
  String get noLocationBody;

  /// No description provided for @noLocationTitle.
  ///
  /// In en, this message translates to:
  /// **'Set your location'**
  String get noLocationTitle;

  /// No description provided for @noPointsBody.
  ///
  /// In en, this message translates to:
  /// **'Points from your activities will appear here.'**
  String get noPointsBody;

  /// No description provided for @noPointsTitle.
  ///
  /// In en, this message translates to:
  /// **'Nothing here yet'**
  String get noPointsTitle;

  /// No description provided for @noRecordsBody.
  ///
  /// In en, this message translates to:
  /// **'Records you choose to make will appear here, private to you.'**
  String get noRecordsBody;

  /// No description provided for @noRecordsTitle.
  ///
  /// In en, this message translates to:
  /// **'Nothing recorded yet'**
  String get noRecordsTitle;

  /// No description provided for @noResults.
  ///
  /// In en, this message translates to:
  /// **'No results'**
  String get noResults;

  /// No description provided for @noSessionsYet.
  ///
  /// In en, this message translates to:
  /// **'Saved sessions will appear here.'**
  String get noSessionsYet;

  /// No description provided for @notHelpful.
  ///
  /// In en, this message translates to:
  /// **'Not helpful'**
  String get notHelpful;

  /// No description provided for @notifChannelDescription.
  ///
  /// In en, this message translates to:
  /// **'Reminders asking whether you have prayed'**
  String get notifChannelDescription;

  /// No description provided for @notifChannelName.
  ///
  /// In en, this message translates to:
  /// **'Prayer reminders'**
  String get notifChannelName;

  /// No description provided for @notifHavePrayed.
  ///
  /// In en, this message translates to:
  /// **'Have you prayed {prayer}?'**
  String notifHavePrayed(Object prayer);

  /// No description provided for @notifPrivateTitle.
  ///
  /// In en, this message translates to:
  /// **'A reminder from DeenQ AI'**
  String get notifPrivateTitle;

  /// No description provided for @notifRemindLater.
  ///
  /// In en, this message translates to:
  /// **'Remind Later'**
  String get notifRemindLater;

  /// No description provided for @notifYesPrayed.
  ///
  /// In en, this message translates to:
  /// **'Yes, I Prayed'**
  String get notifYesPrayed;

  /// No description provided for @notificationLimits.
  ///
  /// In en, this message translates to:
  /// **'Reminders are scheduled on your device for the next 7 days. Android and iOS decide the exact delivery, and battery saving or a restart can delay them, so please do not rely on a reminder alone. Opening the app now and then keeps them scheduled.'**
  String get notificationLimits;

  /// No description provided for @notificationPreferences.
  ///
  /// In en, this message translates to:
  /// **'Notification preferences'**
  String get notificationPreferences;

  /// No description provided for @notificationsAllowed.
  ///
  /// In en, this message translates to:
  /// **'Notifications are on. You can change them at any time.'**
  String get notificationsAllowed;

  /// No description provided for @notificationsDenied.
  ///
  /// In en, this message translates to:
  /// **'Notifications are off. The app still works, and you can turn them on later in settings.'**
  String get notificationsDenied;

  /// No description provided for @notificationsOffBody.
  ///
  /// In en, this message translates to:
  /// **'Your device is not allowing reminders from this app.'**
  String get notificationsOffBody;

  /// No description provided for @notificationsOffTitle.
  ///
  /// In en, this message translates to:
  /// **'Notifications are off'**
  String get notificationsOffTitle;

  /// No description provided for @offlineBody.
  ///
  /// In en, this message translates to:
  /// **'Check your connection and try again. Anything you record is saved on your device.'**
  String get offlineBody;

  /// No description provided for @offlineTitle.
  ///
  /// In en, this message translates to:
  /// **'You are offline'**
  String get offlineTitle;

  /// No description provided for @onboardAccountBody.
  ///
  /// In en, this message translates to:
  /// **'An account keeps your records safe across devices. You are welcome to explore first.'**
  String get onboardAccountBody;

  /// No description provided for @onboardAccountTitle.
  ///
  /// In en, this message translates to:
  /// **'Create an account, or explore first'**
  String get onboardAccountTitle;

  /// No description provided for @onboardAsrBody.
  ///
  /// In en, this message translates to:
  /// **'Choose according to your practice. You can change this later.'**
  String get onboardAsrBody;

  /// No description provided for @onboardAsrTitle.
  ///
  /// In en, this message translates to:
  /// **'Asr calculation'**
  String get onboardAsrTitle;

  /// No description provided for @onboardDoneBody.
  ///
  /// In en, this message translates to:
  /// **'One step at a time. You can change any of this in your profile.'**
  String get onboardDoneBody;

  /// No description provided for @onboardDoneTitle.
  ///
  /// In en, this message translates to:
  /// **'You are all set'**
  String get onboardDoneTitle;

  /// No description provided for @onboardFinish.
  ///
  /// In en, this message translates to:
  /// **'Start using DeenQ AI'**
  String get onboardFinish;

  /// No description provided for @onboardLanguageTitle.
  ///
  /// In en, this message translates to:
  /// **'Choose your language'**
  String get onboardLanguageTitle;

  /// No description provided for @onboardLocationBody.
  ///
  /// In en, this message translates to:
  /// **'Used to calculate prayer times. GPS is optional, and you can skip this for now.'**
  String get onboardLocationBody;

  /// No description provided for @onboardLocationTitle.
  ///
  /// In en, this message translates to:
  /// **'Where are you?'**
  String get onboardLocationTitle;

  /// No description provided for @onboardMethodBody.
  ///
  /// In en, this message translates to:
  /// **'Different regions follow different methods. Choose the one your local community uses, or compare it with your mosque\'s timetable.'**
  String get onboardMethodBody;

  /// No description provided for @onboardMethodTitle.
  ///
  /// In en, this message translates to:
  /// **'Prayer calculation method'**
  String get onboardMethodTitle;

  /// No description provided for @onboardNotificationsBody.
  ///
  /// In en, this message translates to:
  /// **'A gentle question at prayer time, which you can answer or ignore. Nothing is ever assumed from it.'**
  String get onboardNotificationsBody;

  /// No description provided for @onboardNotificationsTitle.
  ///
  /// In en, this message translates to:
  /// **'Prayer reminders'**
  String get onboardNotificationsTitle;

  /// No description provided for @onboardPointsTitle.
  ///
  /// In en, this message translates to:
  /// **'About Deen Points'**
  String get onboardPointsTitle;

  /// No description provided for @onboardTemplatesBody.
  ///
  /// In en, this message translates to:
  /// **'Starting points that you can edit to match your own practice. They are not universal requirements.'**
  String get onboardTemplatesBody;

  /// No description provided for @onboardTemplatesTitle.
  ///
  /// In en, this message translates to:
  /// **'Your prayer templates'**
  String get onboardTemplatesTitle;

  /// No description provided for @onboardWelcomeBody.
  ///
  /// In en, this message translates to:
  /// **'A private space to build habits, track your journey and learn from reliable references.'**
  String get onboardWelcomeBody;

  /// No description provided for @onboardWelcomeTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome to DeenQ AI'**
  String get onboardWelcomeTitle;

  /// No description provided for @oneStepAtATime.
  ///
  /// In en, this message translates to:
  /// **'One step at a time.'**
  String get oneStepAtATime;

  /// No description provided for @openSourceLicenses.
  ///
  /// In en, this message translates to:
  /// **'Open source licenses'**
  String get openSourceLicenses;

  /// No description provided for @optional.
  ///
  /// In en, this message translates to:
  /// **'Optional'**
  String get optional;

  /// No description provided for @optionalNote.
  ///
  /// In en, this message translates to:
  /// **'Note (optional)'**
  String get optionalNote;

  /// No description provided for @outsideAppDhikr.
  ///
  /// In en, this message translates to:
  /// **'Record dhikr done outside the app'**
  String get outsideAppDhikr;

  /// No description provided for @outsideAppDhikrBody.
  ///
  /// In en, this message translates to:
  /// **'Add a count only if you would like it in your personal record.'**
  String get outsideAppDhikrBody;

  /// No description provided for @outsideReadingBody.
  ///
  /// In en, this message translates to:
  /// **'Read from a printed copy or another app? You can add it to your personal record.'**
  String get outsideReadingBody;

  /// No description provided for @pagesRead.
  ///
  /// In en, this message translates to:
  /// **'Pages read'**
  String get pagesRead;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Your password has been changed. You can sign in now.'**
  String get passwordChanged;

  /// No description provided for @passwordHint.
  ///
  /// In en, this message translates to:
  /// **'At least 8 characters'**
  String get passwordHint;

  /// No description provided for @pause.
  ///
  /// In en, this message translates to:
  /// **'Pause'**
  String get pause;

  /// No description provided for @pauseRecitation.
  ///
  /// In en, this message translates to:
  /// **'Pause recitation'**
  String get pauseRecitation;

  /// No description provided for @pendingSync.
  ///
  /// In en, this message translates to:
  /// **'Pending Sync'**
  String get pendingSync;

  /// No description provided for @pendingSyncBody.
  ///
  /// In en, this message translates to:
  /// **'These will be sent when you are online. Points are confirmed once the server replies.'**
  String get pendingSyncBody;

  /// No description provided for @pendingSyncCount.
  ///
  /// In en, this message translates to:
  /// **'{count} records waiting to sync'**
  String pendingSyncCount(Object count);

  /// No description provided for @pendingSyncGuest.
  ///
  /// In en, this message translates to:
  /// **'These are safe on your device, and will sync when you create an account.'**
  String get pendingSyncGuest;

  /// No description provided for @perPrayerReminders.
  ///
  /// In en, this message translates to:
  /// **'Reminders for each prayer'**
  String get perPrayerReminders;

  /// No description provided for @periodDaily.
  ///
  /// In en, this message translates to:
  /// **'Daily'**
  String get periodDaily;

  /// No description provided for @periodTotal.
  ///
  /// In en, this message translates to:
  /// **'Overall'**
  String get periodTotal;

  /// No description provided for @periodWeekly.
  ///
  /// In en, this message translates to:
  /// **'Weekly'**
  String get periodWeekly;

  /// No description provided for @personalGoals.
  ///
  /// In en, this message translates to:
  /// **'Personal Goals'**
  String get personalGoals;

  /// No description provided for @personalInformation.
  ///
  /// In en, this message translates to:
  /// **'Personal information'**
  String get personalInformation;

  /// No description provided for @personalizedAds.
  ///
  /// In en, this message translates to:
  /// **'Personalized ads'**
  String get personalizedAds;

  /// No description provided for @personalizedAdsBody.
  ///
  /// In en, this message translates to:
  /// **'Off by default. Worship records are never used for advertising, whichever you choose.'**
  String get personalizedAdsBody;

  /// No description provided for @playRecitation.
  ///
  /// In en, this message translates to:
  /// **'Play recitation'**
  String get playRecitation;

  /// No description provided for @pointPreferences.
  ///
  /// In en, this message translates to:
  /// **'Point preferences'**
  String get pointPreferences;

  /// No description provided for @pointsAwarded.
  ///
  /// In en, this message translates to:
  /// **'{points} app points'**
  String pointsAwarded(Object points);

  /// No description provided for @pointsDisclaimerLong.
  ///
  /// In en, this message translates to:
  /// **'Deen Points are personal motivational app points. They do not measure faith, sincerity or reward from Allah, which only Allah knows. They are never shown to others, and they are never taken away for missing something.'**
  String get pointsDisclaimerLong;

  /// No description provided for @pointsDisclaimerShort.
  ///
  /// In en, this message translates to:
  /// **'Personal app points only'**
  String get pointsDisclaimerShort;

  /// No description provided for @pointsExplain1.
  ///
  /// In en, this message translates to:
  /// **'Deen Points are simple app points that can help you stay motivated.'**
  String get pointsExplain1;

  /// No description provided for @pointsExplain2.
  ///
  /// In en, this message translates to:
  /// **'They do not measure faith, sincerity or reward from Allah. Only Allah knows those.'**
  String get pointsExplain2;

  /// No description provided for @pointsExplain3.
  ///
  /// In en, this message translates to:
  /// **'They are private. There are no leaderboards and no competitions.'**
  String get pointsExplain3;

  /// No description provided for @pointsExplain4.
  ///
  /// In en, this message translates to:
  /// **'You never lose points for missing something, and you can hide them at any time.'**
  String get pointsExplain4;

  /// No description provided for @pointsHistory.
  ///
  /// In en, this message translates to:
  /// **'Point history'**
  String get pointsHistory;

  /// No description provided for @pointsNeedAccount.
  ///
  /// In en, this message translates to:
  /// **'Create a free account to see your points and achievements.'**
  String get pointsNeedAccount;

  /// No description provided for @pointsPreview.
  ///
  /// In en, this message translates to:
  /// **'About {points} app points'**
  String pointsPreview(Object points);

  /// No description provided for @pointsPreviewNote.
  ///
  /// In en, this message translates to:
  /// **'A preview. The app confirms points after you save, and they are not a measure of worship.'**
  String get pointsPreviewNote;

  /// No description provided for @pointsToday.
  ///
  /// In en, this message translates to:
  /// **'+{points} today'**
  String pointsToday(Object points);

  /// No description provided for @prayerAsr.
  ///
  /// In en, this message translates to:
  /// **'Asr'**
  String get prayerAsr;

  /// No description provided for @prayerDhuhr.
  ///
  /// In en, this message translates to:
  /// **'Dhuhr'**
  String get prayerDhuhr;

  /// No description provided for @prayerFajr.
  ///
  /// In en, this message translates to:
  /// **'Fajr'**
  String get prayerFajr;

  /// No description provided for @prayerHistory.
  ///
  /// In en, this message translates to:
  /// **'Prayer history'**
  String get prayerHistory;

  /// No description provided for @prayerIsha.
  ///
  /// In en, this message translates to:
  /// **'Isha'**
  String get prayerIsha;

  /// No description provided for @prayerMaghrib.
  ///
  /// In en, this message translates to:
  /// **'Maghrib'**
  String get prayerMaghrib;

  /// No description provided for @prayerSettings.
  ///
  /// In en, this message translates to:
  /// **'Prayer settings'**
  String get prayerSettings;

  /// No description provided for @prayerSunrise.
  ///
  /// In en, this message translates to:
  /// **'Sunrise'**
  String get prayerSunrise;

  /// No description provided for @prayerTemplates.
  ///
  /// In en, this message translates to:
  /// **'Prayer templates'**
  String get prayerTemplates;

  /// No description provided for @privacyAndData.
  ///
  /// In en, this message translates to:
  /// **'Privacy and data'**
  String get privacyAndData;

  /// No description provided for @privacyAndDataBody.
  ///
  /// In en, this message translates to:
  /// **'Export, delete and control your data'**
  String get privacyAndDataBody;

  /// No description provided for @privacyPromise.
  ///
  /// In en, this message translates to:
  /// **'Your worship records are private by default and never shared publicly.'**
  String get privacyPromise;

  /// No description provided for @privatePreview.
  ///
  /// In en, this message translates to:
  /// **'Private preview'**
  String get privatePreview;

  /// No description provided for @privatePreviewBody.
  ///
  /// In en, this message translates to:
  /// **'Hide the prayer name in notifications.'**
  String get privatePreviewBody;

  /// No description provided for @progressNote.
  ///
  /// In en, this message translates to:
  /// **'These are simply the records you chose to make. They are not a measure of anyone\'s faith.'**
  String get progressNote;

  /// No description provided for @quizKeepLearning.
  ///
  /// In en, this message translates to:
  /// **'{score} of {total}. Keep going, you can try again whenever you like.'**
  String quizKeepLearning(Object score, Object total);

  /// No description provided for @quizPassed.
  ///
  /// In en, this message translates to:
  /// **'{score} of {total}. Well done on your learning.'**
  String quizPassed(Object score, Object total);

  /// No description provided for @quranPages.
  ///
  /// In en, this message translates to:
  /// **'Quran pages'**
  String get quranPages;

  /// No description provided for @quranTitle.
  ///
  /// In en, this message translates to:
  /// **'Quran'**
  String get quranTitle;

  /// No description provided for @quranUnavailableBody.
  ///
  /// In en, this message translates to:
  /// **'Quran text has not been loaded on the server yet.'**
  String get quranUnavailableBody;

  /// No description provided for @quranUnavailableTitle.
  ///
  /// In en, this message translates to:
  /// **'Quran text unavailable'**
  String get quranUnavailableTitle;

  /// No description provided for @rakatCount.
  ///
  /// In en, this message translates to:
  /// **'{count} rakat'**
  String rakatCount(Object count);

  /// No description provided for @rakatInstructions.
  ///
  /// In en, this message translates to:
  /// **'Select what you prayed and adjust the rakats if needed. This is your own record, and nothing is saved until you confirm.'**
  String get rakatInstructions;

  /// No description provided for @rakatTooMany.
  ///
  /// In en, this message translates to:
  /// **'One record can hold at most 40 rakats.'**
  String get rakatTooMany;

  /// No description provided for @readingOptions.
  ///
  /// In en, this message translates to:
  /// **'Reading options'**
  String get readingOptions;

  /// No description provided for @reciter.
  ///
  /// In en, this message translates to:
  /// **'Reciter'**
  String get reciter;

  /// No description provided for @reciterLowQuality.
  ///
  /// In en, this message translates to:
  /// **'Lower quality (64 kbps)'**
  String get reciterLowQuality;

  /// No description provided for @reciterOpeningsWarning.
  ///
  /// In en, this message translates to:
  /// **'Surah openings may include extra recitation (unverified)'**
  String get reciterOpeningsWarning;

  /// No description provided for @recentActivity.
  ///
  /// In en, this message translates to:
  /// **'Recently completed'**
  String get recentActivity;

  /// No description provided for @record.
  ///
  /// In en, this message translates to:
  /// **'Record'**
  String get record;

  /// No description provided for @recordAFast.
  ///
  /// In en, this message translates to:
  /// **'Record a fast'**
  String get recordAFast;

  /// No description provided for @recordDua.
  ///
  /// In en, this message translates to:
  /// **'Record a dua'**
  String get recordDua;

  /// No description provided for @recordOutsideReading.
  ///
  /// In en, this message translates to:
  /// **'Record reading outside the app'**
  String get recordOutsideReading;

  /// No description provided for @recordPending.
  ///
  /// In en, this message translates to:
  /// **'Saved on your device. Pending Sync.'**
  String get recordPending;

  /// No description provided for @recordRemoved.
  ///
  /// In en, this message translates to:
  /// **'Record removed.'**
  String get recordRemoved;

  /// No description provided for @recordSaved.
  ///
  /// In en, this message translates to:
  /// **'Saved.'**
  String get recordSaved;

  /// No description provided for @recordSavedPoints.
  ///
  /// In en, this message translates to:
  /// **'Saved. {points} app points.'**
  String recordSavedPoints(Object points);

  /// No description provided for @recordUpdated.
  ///
  /// In en, this message translates to:
  /// **'Record updated.'**
  String get recordUpdated;

  /// No description provided for @recordsCount.
  ///
  /// In en, this message translates to:
  /// **'{count} records'**
  String recordsCount(Object count);

  /// No description provided for @references.
  ///
  /// In en, this message translates to:
  /// **'References'**
  String get references;

  /// No description provided for @registerPrivacyNote.
  ///
  /// In en, this message translates to:
  /// **'Your records are private. We never share your worship data publicly.'**
  String get registerPrivacyNote;

  /// No description provided for @remindCustom.
  ///
  /// In en, this message translates to:
  /// **'Custom time'**
  String get remindCustom;

  /// No description provided for @remindCustomHelp.
  ///
  /// In en, this message translates to:
  /// **'From 1 to 720 minutes'**
  String get remindCustomHelp;

  /// No description provided for @remindNote.
  ///
  /// In en, this message translates to:
  /// **'This adds one extra reminder. Your other prayer reminders stay as they are.'**
  String get remindNote;

  /// No description provided for @remindQuestion.
  ///
  /// In en, this message translates to:
  /// **'When should we remind you about {prayer}?'**
  String remindQuestion(Object prayer);

  /// No description provided for @remindSet.
  ///
  /// In en, this message translates to:
  /// **'Reminder set for {minutes} minutes from now.'**
  String remindSet(Object minutes);

  /// No description provided for @reminderOffset.
  ///
  /// In en, this message translates to:
  /// **'Remind me'**
  String get reminderOffset;

  /// No description provided for @remove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get remove;

  /// No description provided for @removeBookmark.
  ///
  /// In en, this message translates to:
  /// **'Remove bookmark'**
  String get removeBookmark;

  /// No description provided for @removeRecordBody.
  ///
  /// In en, this message translates to:
  /// **'This removes the record and reverses its app points. An audit entry is kept.'**
  String get removeRecordBody;

  /// No description provided for @removeRecordTitle.
  ///
  /// In en, this message translates to:
  /// **'Remove this record?'**
  String get removeRecordTitle;

  /// No description provided for @reportIncorrect.
  ///
  /// In en, this message translates to:
  /// **'Report incorrect response'**
  String get reportIncorrect;

  /// No description provided for @reportThanks.
  ///
  /// In en, this message translates to:
  /// **'Thank you. A reviewer will look at this.'**
  String get reportThanks;

  /// No description provided for @reportWhat.
  ///
  /// In en, this message translates to:
  /// **'What looks wrong? (optional)'**
  String get reportWhat;

  /// No description provided for @reset.
  ///
  /// In en, this message translates to:
  /// **'Reset'**
  String get reset;

  /// No description provided for @resetCode.
  ///
  /// In en, this message translates to:
  /// **'Reset code from the email'**
  String get resetCode;

  /// No description provided for @resetCounterBody.
  ///
  /// In en, this message translates to:
  /// **'This clears the current count without saving it.'**
  String get resetCounterBody;

  /// No description provided for @resetCounterTitle.
  ///
  /// In en, this message translates to:
  /// **'Reset the counter?'**
  String get resetCounterTitle;

  /// No description provided for @resetEmailSent.
  ///
  /// In en, this message translates to:
  /// **'If that address has an account, a reset email is on its way. Enter the code from it below.'**
  String get resetEmailSent;

  /// No description provided for @resetPassword.
  ///
  /// In en, this message translates to:
  /// **'Reset password'**
  String get resetPassword;

  /// No description provided for @restore.
  ///
  /// In en, this message translates to:
  /// **'Restore'**
  String get restore;

  /// No description provided for @restoreDefaults.
  ///
  /// In en, this message translates to:
  /// **'Restore defaults'**
  String get restoreDefaults;

  /// No description provided for @restoreDefaultsBody.
  ///
  /// In en, this message translates to:
  /// **'This replaces your templates with the starting templates. Your past records are not changed.'**
  String get restoreDefaultsBody;

  /// No description provided for @resume.
  ///
  /// In en, this message translates to:
  /// **'Resume'**
  String get resume;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get retry;

  /// No description provided for @salahPending.
  ///
  /// In en, this message translates to:
  /// **'Saved on your device. Pending Sync. Points are confirmed once it reaches the server.'**
  String get salahPending;

  /// No description provided for @salahPendingGuest.
  ///
  /// In en, this message translates to:
  /// **'Saved on your device. Create an account to sync it.'**
  String get salahPendingGuest;

  /// No description provided for @salahRecorded.
  ///
  /// In en, this message translates to:
  /// **'Salah recorded'**
  String get salahRecorded;

  /// No description provided for @salahSaved.
  ///
  /// In en, this message translates to:
  /// **'Saved. {points} app points.'**
  String salahSaved(Object points);

  /// No description provided for @salahSavedNoPoints.
  ///
  /// In en, this message translates to:
  /// **'Saved.'**
  String get salahSavedNoPoints;

  /// No description provided for @salahTitle.
  ///
  /// In en, this message translates to:
  /// **'Salah'**
  String get salahTitle;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @saveSession.
  ///
  /// In en, this message translates to:
  /// **'Save session'**
  String get saveSession;

  /// No description provided for @saved.
  ///
  /// In en, this message translates to:
  /// **'Saved'**
  String get saved;

  /// No description provided for @savedOnDevice.
  ///
  /// In en, this message translates to:
  /// **'Saved on this device. It will update on the server when you are online.'**
  String get savedOnDevice;

  /// No description provided for @scholarReviewed.
  ///
  /// In en, this message translates to:
  /// **'Scholar reviewed'**
  String get scholarReviewed;

  /// No description provided for @searchCity.
  ///
  /// In en, this message translates to:
  /// **'Search for a city'**
  String get searchCity;

  /// No description provided for @searchQuran.
  ///
  /// In en, this message translates to:
  /// **'Search the Quran'**
  String get searchQuran;

  /// No description provided for @searchSurah.
  ///
  /// In en, this message translates to:
  /// **'Search surahs'**
  String get searchSurah;

  /// No description provided for @searchTranslationHint.
  ///
  /// In en, this message translates to:
  /// **'Search the translation'**
  String get searchTranslationHint;

  /// No description provided for @seeMore.
  ///
  /// In en, this message translates to:
  /// **'See more'**
  String get seeMore;

  /// No description provided for @selfReportedNote.
  ///
  /// In en, this message translates to:
  /// **'Your own record. The app does not verify or judge worship.'**
  String get selfReportedNote;

  /// No description provided for @send.
  ///
  /// In en, this message translates to:
  /// **'Send'**
  String get send;

  /// No description provided for @sendResetEmail.
  ///
  /// In en, this message translates to:
  /// **'Send reset email'**
  String get sendResetEmail;

  /// No description provided for @sessionHistory.
  ///
  /// In en, this message translates to:
  /// **'Session history'**
  String get sessionHistory;

  /// No description provided for @setLocationForTimes.
  ///
  /// In en, this message translates to:
  /// **'Set location for prayer times'**
  String get setLocationForTimes;

  /// No description provided for @showFromStart.
  ///
  /// In en, this message translates to:
  /// **'Show from the first verse'**
  String get showFromStart;

  /// No description provided for @showPoints.
  ///
  /// In en, this message translates to:
  /// **'Show Deen Points'**
  String get showPoints;

  /// No description provided for @showPointsBody.
  ///
  /// In en, this message translates to:
  /// **'Prefer to track without points? Turn this off to hide them around the app.'**
  String get showPointsBody;

  /// No description provided for @showTranslation.
  ///
  /// In en, this message translates to:
  /// **'Show translation'**
  String get showTranslation;

  /// No description provided for @showTransliteration.
  ///
  /// In en, this message translates to:
  /// **'Show transliteration'**
  String get showTransliteration;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @signOutBody.
  ///
  /// In en, this message translates to:
  /// **'Records not yet synced and local data will be removed from this device.'**
  String get signOutBody;

  /// No description provided for @skipForNow.
  ///
  /// In en, this message translates to:
  /// **'Skip for now'**
  String get skipForNow;

  /// No description provided for @sound.
  ///
  /// In en, this message translates to:
  /// **'Sound'**
  String get sound;

  /// No description provided for @soundAndPrivacy.
  ///
  /// In en, this message translates to:
  /// **'Sound and privacy'**
  String get soundAndPrivacy;

  /// No description provided for @sourceLabel.
  ///
  /// In en, this message translates to:
  /// **'Source: {source}'**
  String sourceLabel(Object source);

  /// No description provided for @sources.
  ///
  /// In en, this message translates to:
  /// **'Sources'**
  String get sources;

  /// No description provided for @sponsored.
  ///
  /// In en, this message translates to:
  /// **'Sponsored'**
  String get sponsored;

  /// No description provided for @startSmallBody.
  ///
  /// In en, this message translates to:
  /// **'Record a prayer, read a page or try a lesson whenever you are ready.'**
  String get startSmallBody;

  /// No description provided for @startSmallTitle.
  ///
  /// In en, this message translates to:
  /// **'One step at a time'**
  String get startSmallTitle;

  /// No description provided for @submitAnswers.
  ///
  /// In en, this message translates to:
  /// **'Submit answers'**
  String get submitAnswers;

  /// No description provided for @suggestedForYou.
  ///
  /// In en, this message translates to:
  /// **'Your learning journey'**
  String get suggestedForYou;

  /// No description provided for @summary.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get summary;

  /// No description provided for @sunnahBismillah.
  ///
  /// In en, this message translates to:
  /// **'Saying Bismillah before eating'**
  String get sunnahBismillah;

  /// No description provided for @sunnahIntro.
  ///
  /// In en, this message translates to:
  /// **'Note a Sunnah practice, if it helps you. These are optional, and nothing is ever marked as missed.'**
  String get sunnahIntro;

  /// No description provided for @sunnahSalam.
  ///
  /// In en, this message translates to:
  /// **'Greeting with salam'**
  String get sunnahSalam;

  /// No description provided for @sunnahSiwak.
  ///
  /// In en, this message translates to:
  /// **'Using siwak'**
  String get sunnahSiwak;

  /// No description provided for @sunnahSmile.
  ///
  /// In en, this message translates to:
  /// **'Smiling at someone'**
  String get sunnahSmile;

  /// No description provided for @sunnahTitle.
  ///
  /// In en, this message translates to:
  /// **'Sunnah'**
  String get sunnahTitle;

  /// No description provided for @sunnahVisitSick.
  ///
  /// In en, this message translates to:
  /// **'Visiting the sick'**
  String get sunnahVisitSick;

  /// No description provided for @supportNotConfigured.
  ///
  /// In en, this message translates to:
  /// **'A support address has not been set up yet.'**
  String get supportNotConfigured;

  /// No description provided for @tagline.
  ///
  /// In en, this message translates to:
  /// **'Build Your Deen. Strengthen Your Connection with Allah.'**
  String get tagline;

  /// No description provided for @target.
  ///
  /// In en, this message translates to:
  /// **'Target'**
  String get target;

  /// No description provided for @targetOf.
  ///
  /// In en, this message translates to:
  /// **'Target {target}'**
  String targetOf(Object target);

  /// No description provided for @targetReached.
  ///
  /// In en, this message translates to:
  /// **'Target reached'**
  String get targetReached;

  /// No description provided for @tasbihHaptics.
  ///
  /// In en, this message translates to:
  /// **'Digital Tasbih'**
  String get tasbihHaptics;

  /// No description provided for @tasbihTapLabel.
  ///
  /// In en, this message translates to:
  /// **'Count {count}. Double tap to add one.'**
  String tasbihTapLabel(Object count);

  /// No description provided for @templatesNote.
  ///
  /// In en, this message translates to:
  /// **'Editable starting points, not universal religious requirements. Configure them according to your practice.'**
  String get templatesNote;

  /// No description provided for @templatesSaved.
  ///
  /// In en, this message translates to:
  /// **'Templates saved.'**
  String get templatesSaved;

  /// No description provided for @textSourceLabel.
  ///
  /// In en, this message translates to:
  /// **'Arabic text: {source}'**
  String textSourceLabel(Object source);

  /// No description provided for @theme.
  ///
  /// In en, this message translates to:
  /// **'Theme'**
  String get theme;

  /// No description provided for @themeDark.
  ///
  /// In en, this message translates to:
  /// **'Dark'**
  String get themeDark;

  /// No description provided for @themeLight.
  ///
  /// In en, this message translates to:
  /// **'Light'**
  String get themeLight;

  /// No description provided for @themeSystem.
  ///
  /// In en, this message translates to:
  /// **'System'**
  String get themeSystem;

  /// No description provided for @thisWeek.
  ///
  /// In en, this message translates to:
  /// **'This week'**
  String get thisWeek;

  /// No description provided for @timeRemaining.
  ///
  /// In en, this message translates to:
  /// **'Time remaining until the next prayer'**
  String get timeRemaining;

  /// No description provided for @timezoneHelp.
  ///
  /// In en, this message translates to:
  /// **'For example Asia/Dhaka'**
  String get timezoneHelp;

  /// No description provided for @timezoneInvalid.
  ///
  /// In en, this message translates to:
  /// **'Use a zone such as Asia/Dhaka'**
  String get timezoneInvalid;

  /// No description provided for @timezoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Timezone'**
  String get timezoneLabel;

  /// No description provided for @todayPrayers.
  ///
  /// In en, this message translates to:
  /// **'Today\'s prayers'**
  String get todayPrayers;

  /// No description provided for @todaySummary.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get todaySummary;

  /// No description provided for @trackerDeedsBody.
  ///
  /// In en, this message translates to:
  /// **'Privately note the good you do'**
  String get trackerDeedsBody;

  /// No description provided for @trackerDhikrBody.
  ///
  /// In en, this message translates to:
  /// **'Digital Tasbih and dhikr sessions'**
  String get trackerDhikrBody;

  /// No description provided for @trackerFastingBody.
  ///
  /// In en, this message translates to:
  /// **'Ramadan and voluntary fasts'**
  String get trackerFastingBody;

  /// No description provided for @trackerGoalsBody.
  ///
  /// In en, this message translates to:
  /// **'Small goals you choose yourself'**
  String get trackerGoalsBody;

  /// No description provided for @trackerHistoryBody.
  ///
  /// In en, this message translates to:
  /// **'Review, edit or remove records'**
  String get trackerHistoryBody;

  /// No description provided for @trackerNote.
  ///
  /// In en, this message translates to:
  /// **'Your private records. Record what you like, when you like.'**
  String get trackerNote;

  /// No description provided for @trackerProgressBody.
  ///
  /// In en, this message translates to:
  /// **'A private view of your journey'**
  String get trackerProgressBody;

  /// No description provided for @trackerQuranBody.
  ///
  /// In en, this message translates to:
  /// **'Read, listen and keep your place'**
  String get trackerQuranBody;

  /// No description provided for @trackerSalahBody.
  ///
  /// In en, this message translates to:
  /// **'Times, reminders and your record'**
  String get trackerSalahBody;

  /// No description provided for @trackerSunnahBody.
  ///
  /// In en, this message translates to:
  /// **'Optional practices and dua'**
  String get trackerSunnahBody;

  /// No description provided for @translationSourceLabel.
  ///
  /// In en, this message translates to:
  /// **'Translation: {source}'**
  String translationSourceLabel(Object source);

  /// No description provided for @translationUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Translation unavailable'**
  String get translationUnavailable;

  /// No description provided for @tryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get tryAgain;

  /// No description provided for @tryAsking.
  ///
  /// In en, this message translates to:
  /// **'Try asking'**
  String get tryAsking;

  /// No description provided for @txAdjust.
  ///
  /// In en, this message translates to:
  /// **'Adjustment'**
  String get txAdjust;

  /// No description provided for @txEarn.
  ///
  /// In en, this message translates to:
  /// **'Earned'**
  String get txEarn;

  /// No description provided for @txReverse.
  ///
  /// In en, this message translates to:
  /// **'Reversed'**
  String get txReverse;

  /// No description provided for @unavailableShort.
  ///
  /// In en, this message translates to:
  /// **'Unavailable'**
  String get unavailableShort;

  /// No description provided for @unknown.
  ///
  /// In en, this message translates to:
  /// **'Unknown'**
  String get unknown;

  /// No description provided for @useGps.
  ///
  /// In en, this message translates to:
  /// **'Use GPS'**
  String get useGps;

  /// No description provided for @verseCount.
  ///
  /// In en, this message translates to:
  /// **'{count} verses'**
  String verseCount(Object count);

  /// No description provided for @versionLabel.
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String versionLabel(Object version);

  /// No description provided for @vibration.
  ///
  /// In en, this message translates to:
  /// **'Vibration'**
  String get vibration;

  /// No description provided for @viewSources.
  ///
  /// In en, this message translates to:
  /// **'{count} sources'**
  String viewSources(Object count);

  /// No description provided for @whichDidYouPray.
  ///
  /// In en, this message translates to:
  /// **'Your {prayer} record'**
  String whichDidYouPray(Object prayer);

  /// No description provided for @yourData.
  ///
  /// In en, this message translates to:
  /// **'Your data'**
  String get yourData;

  /// No description provided for @yourProgress.
  ///
  /// In en, this message translates to:
  /// **'Your personal progress'**
  String get yourProgress;

  /// No description provided for @addNote.
  ///
  /// In en, this message translates to:
  /// **'Add note'**
  String get addNote;

  /// No description provided for @arabicTextSize.
  ///
  /// In en, this message translates to:
  /// **'Arabic text size'**
  String get arabicTextSize;

  /// No description provided for @autoPlayShort.
  ///
  /// In en, this message translates to:
  /// **'Auto play'**
  String get autoPlayShort;

  /// No description provided for @backgroundPlaybackNote.
  ///
  /// In en, this message translates to:
  /// **'Recitation keeps playing when you leave the app or lock the screen. Use the notification to control it.'**
  String get backgroundPlaybackNote;

  /// No description provided for @bangla.
  ///
  /// In en, this message translates to:
  /// **'Bangla'**
  String get bangla;

  /// No description provided for @bismillah.
  ///
  /// In en, this message translates to:
  /// **'Bismillah'**
  String get bismillah;

  /// No description provided for @both.
  ///
  /// In en, this message translates to:
  /// **'Both'**
  String get both;

  /// No description provided for @deleteDownload.
  ///
  /// In en, this message translates to:
  /// **'Delete download'**
  String get deleteDownload;

  /// No description provided for @deleteNote.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get deleteNote;

  /// No description provided for @downloadDone.
  ///
  /// In en, this message translates to:
  /// **'{surah} saved for offline listening'**
  String downloadDone(Object surah);

  /// No description provided for @downloadRange.
  ///
  /// In en, this message translates to:
  /// **'Download verses…'**
  String get downloadRange;

  /// No description provided for @downloadStopped.
  ///
  /// In en, this message translates to:
  /// **'Download stopped. Check your connection and try again.'**
  String get downloadStopped;

  /// No description provided for @downloadSurah.
  ///
  /// In en, this message translates to:
  /// **'Download whole surah'**
  String get downloadSurah;

  /// No description provided for @downloadedCount.
  ///
  /// In en, this message translates to:
  /// **'{done} of {total} verses saved'**
  String downloadedCount(Object done, Object total);

  /// No description provided for @downloadingProgress.
  ///
  /// In en, this message translates to:
  /// **'Downloading {done} of {total}'**
  String downloadingProgress(Object done, Object total);

  /// No description provided for @downloads.
  ///
  /// In en, this message translates to:
  /// **'Downloads'**
  String get downloads;

  /// No description provided for @downloadsHint.
  ///
  /// In en, this message translates to:
  /// **'Saved audio plays without internet, for the reciter shown above. The surah text and translation are saved too. Tafsir and word-by-word need internet.'**
  String get downloadsHint;

  /// No description provided for @editNote.
  ///
  /// In en, this message translates to:
  /// **'Edit note'**
  String get editNote;

  /// No description provided for @english.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @largerText.
  ///
  /// In en, this message translates to:
  /// **'Larger text'**
  String get largerText;

  /// No description provided for @moreOptions.
  ///
  /// In en, this message translates to:
  /// **'More options'**
  String get moreOptions;

  /// No description provided for @nextVerse.
  ///
  /// In en, this message translates to:
  /// **'Next verse'**
  String get nextVerse;

  /// No description provided for @nextWord.
  ///
  /// In en, this message translates to:
  /// **'Next word'**
  String get nextWord;

  /// No description provided for @noneOption.
  ///
  /// In en, this message translates to:
  /// **'None'**
  String get noneOption;

  /// No description provided for @note.
  ///
  /// In en, this message translates to:
  /// **'Note'**
  String get note;

  /// No description provided for @noteHint.
  ///
  /// In en, this message translates to:
  /// **'Write a private note for this verse'**
  String get noteHint;

  /// No description provided for @notesPrivate.
  ///
  /// In en, this message translates to:
  /// **'Notes are saved only on this device.'**
  String get notesPrivate;

  /// No description provided for @nothingPlaying.
  ///
  /// In en, this message translates to:
  /// **'Nothing is playing'**
  String get nothingPlaying;

  /// No description provided for @nowPlaying.
  ///
  /// In en, this message translates to:
  /// **'Now playing'**
  String get nowPlaying;

  /// No description provided for @openInReader.
  ///
  /// In en, this message translates to:
  /// **'Open in reader'**
  String get openInReader;

  /// No description provided for @playFromHere.
  ///
  /// In en, this message translates to:
  /// **'Play from here'**
  String get playFromHere;

  /// No description provided for @playWord.
  ///
  /// In en, this message translates to:
  /// **'Play word'**
  String get playWord;

  /// No description provided for @playbackSpeed.
  ///
  /// In en, this message translates to:
  /// **'Speed'**
  String get playbackSpeed;

  /// No description provided for @previousVerse.
  ///
  /// In en, this message translates to:
  /// **'Previous verse'**
  String get previousVerse;

  /// No description provided for @previousWord.
  ///
  /// In en, this message translates to:
  /// **'Previous word'**
  String get previousWord;

  /// No description provided for @queue.
  ///
  /// In en, this message translates to:
  /// **'Queue'**
  String get queue;

  /// No description provided for @rangeFrom.
  ///
  /// In en, this message translates to:
  /// **'From verse'**
  String get rangeFrom;

  /// No description provided for @rangeTo.
  ///
  /// In en, this message translates to:
  /// **'To verse'**
  String get rangeTo;

  /// No description provided for @repeat.
  ///
  /// In en, this message translates to:
  /// **'Repeat'**
  String get repeat;

  /// No description provided for @repeatOff.
  ///
  /// In en, this message translates to:
  /// **'Off'**
  String get repeatOff;

  /// No description provided for @repeatRange.
  ///
  /// In en, this message translates to:
  /// **'Verse range'**
  String get repeatRange;

  /// No description provided for @repeatRangeValue.
  ///
  /// In en, this message translates to:
  /// **'Verses {from}–{to}'**
  String repeatRangeValue(Object from, Object to);

  /// No description provided for @repeatSurah.
  ///
  /// In en, this message translates to:
  /// **'Whole surah'**
  String get repeatSurah;

  /// No description provided for @repeatThisVerse.
  ///
  /// In en, this message translates to:
  /// **'Repeat this verse'**
  String get repeatThisVerse;

  /// No description provided for @repeatTimes.
  ///
  /// In en, this message translates to:
  /// **'{count}×'**
  String repeatTimes(Object count);

  /// No description provided for @repeatVerse.
  ///
  /// In en, this message translates to:
  /// **'This verse'**
  String get repeatVerse;

  /// No description provided for @secondTranslation.
  ///
  /// In en, this message translates to:
  /// **'Also show'**
  String get secondTranslation;

  /// No description provided for @share.
  ///
  /// In en, this message translates to:
  /// **'Share'**
  String get share;

  /// No description provided for @showMeaning.
  ///
  /// In en, this message translates to:
  /// **'Show meaning'**
  String get showMeaning;

  /// No description provided for @showWordTransliteration.
  ///
  /// In en, this message translates to:
  /// **'Show word transliteration'**
  String get showWordTransliteration;

  /// No description provided for @sleepActive.
  ///
  /// In en, this message translates to:
  /// **'Stops at {time}'**
  String sleepActive(Object time);

  /// No description provided for @sleepEndOfVerse.
  ///
  /// In en, this message translates to:
  /// **'End of this verse'**
  String get sleepEndOfVerse;

  /// No description provided for @sleepMinutes.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min'**
  String sleepMinutes(Object minutes);

  /// No description provided for @sleepOff.
  ///
  /// In en, this message translates to:
  /// **'Off'**
  String get sleepOff;

  /// No description provided for @sleepTimer.
  ///
  /// In en, this message translates to:
  /// **'Sleep timer'**
  String get sleepTimer;

  /// No description provided for @smallerText.
  ///
  /// In en, this message translates to:
  /// **'Smaller text'**
  String get smallerText;

  /// No description provided for @stopPlayback.
  ///
  /// In en, this message translates to:
  /// **'Stop'**
  String get stopPlayback;

  /// No description provided for @tafsir.
  ///
  /// In en, this message translates to:
  /// **'Tafsir'**
  String get tafsir;

  /// No description provided for @tafsirAttribution.
  ///
  /// In en, this message translates to:
  /// **'Source: {name} · {author}. Provided by Quran.com.'**
  String tafsirAttribution(Object author, Object name);

  /// No description provided for @tafsirGrouped.
  ///
  /// In en, this message translates to:
  /// **'This source explains this verse together with a nearby verse. Try the previous verse.'**
  String get tafsirGrouped;

  /// No description provided for @tafsirOnlineOnly.
  ///
  /// In en, this message translates to:
  /// **'Tafsir needs internet and is not saved on the device, under the provider\'s terms.'**
  String get tafsirOnlineOnly;

  /// No description provided for @tafsirUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Tafsir is not available right now.'**
  String get tafsirUnavailable;

  /// No description provided for @translationLabel.
  ///
  /// In en, this message translates to:
  /// **'Translation'**
  String get translationLabel;

  /// No description provided for @translationTextSize.
  ///
  /// In en, this message translates to:
  /// **'Translation text size'**
  String get translationTextSize;

  /// No description provided for @verseNumber.
  ///
  /// In en, this message translates to:
  /// **'Verse {number}'**
  String verseNumber(Object number);

  /// No description provided for @verseRangeInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a verse from 1 to {count}, with the end after the start.'**
  String verseRangeInvalid(Object count);

  /// No description provided for @wordByWord.
  ///
  /// In en, this message translates to:
  /// **'Word by word'**
  String get wordByWord;

  /// No description provided for @wordByWordHint.
  ///
  /// In en, this message translates to:
  /// **'Tap a word for its meaning and sound'**
  String get wordByWordHint;

  /// No description provided for @wordByWordUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Word-by-word meanings could not be loaded. They need internet.'**
  String get wordByWordUnavailable;

  /// No description provided for @wordDataSource.
  ///
  /// In en, this message translates to:
  /// **'Word meanings and audio: Quran.com'**
  String get wordDataSource;

  /// No description provided for @wordLearning.
  ///
  /// In en, this message translates to:
  /// **'Word learning'**
  String get wordLearning;

  /// No description provided for @wordProgress.
  ///
  /// In en, this message translates to:
  /// **'Word {current} of {total}'**
  String wordProgress(Object current, Object total);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'bn', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'bn':
      return AppLocalizationsBn();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
