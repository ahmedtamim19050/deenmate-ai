import 'dart:convert';

import 'package:deenmate_ai/core/storage/app_database.dart';
import 'package:deenmate_ai/features/salah/rakat_selection_screen.dart';
import 'package:deenmate_ai/features/salah/salah_prompt_screens.dart';
import 'package:deenmate_ai/features/salah/salah_repository.dart';
import 'package:deenmate_ai/features/salah/salah_screen.dart';
import 'package:deenmate_ai/shared/providers/providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

import 'widget_test_helpers.dart';

Future<void> _pump(WidgetTester tester, Widget app) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(app);
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(tzdata.initializeTimeZones);

  group('notification prompt', () {
    testWidgets('asks the exact question with the two exact buttons', (tester) async {
      final (app, db) = await testApp(const SalahPromptScreen(prayer: 'fajr', date: '2026-03-20'));
      addTearDown(db.close);
      await _pump(tester, app);

      expect(find.text('Have you prayed Fajr?'), findsOneWidget);
      expect(find.text('Yes, I Prayed'), findsOneWidget);
      expect(find.text('Remind Later'), findsOneWidget);
      expect(await db.select(db.outboxItems).get(), isEmpty, reason: 'Showing the prompt must record nothing');
    });

    testWidgets('private text is localized in Arabic', (tester) async {
      final (app, db) = await testApp(const SalahPromptScreen(prayer: 'fajr', date: '2026-03-20'), locale: 'ar');
      addTearDown(db.close);
      await _pump(tester, app);
      expect(find.text('هل صليت الفجر؟'), findsOneWidget);
    });
  });

  group('remind later', () {
    testWidgets('offers 5, 10, 15 minutes and a custom time', (tester) async {
      final (app, db) = await testApp(const RemindLaterScreen(prayer: 'asr', date: '2026-03-20'));
      addTearDown(db.close);
      await _pump(tester, app);

      expect(find.text('In 5 minutes'), findsOneWidget);
      expect(find.text('In 10 minutes'), findsOneWidget);
      expect(find.text('In 15 minutes'), findsOneWidget);
      expect(find.text('Custom time'), findsOneWidget);
    });
  });

  group('rakat selection', () {
    testWidgets('Confirm Prayer stays disabled until something is selected', (tester) async {
      final (app, db) = await testApp(const RakatSelectionScreen(prayer: 'fajr', date: '2026-03-20'));
      addTearDown(db.close);
      await _pump(tester, app);

      FilledButton button() => tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'Confirm Prayer'));
      expect(button().onPressed, isNull);

      await tester.tap(find.text('Fard'));
      await tester.pumpAndSettle();
      expect(button().onPressed, isNotNull);
      expect(find.text('2 rakat'), findsOneWidget);
    });

    testWidgets('selecting does not save anything until confirmed', (tester) async {
      final (app, db) = await testApp(const RakatSelectionScreen(prayer: 'fajr', date: '2026-03-20'));
      addTearDown(db.close);
      await _pump(tester, app);

      await tester.tap(find.text('Fard'));
      await tester.tap(find.text('Sunnah'));
      await tester.pumpAndSettle();
      expect(find.text('4 rakat'), findsOneWidget);
      expect(await db.select(db.outboxItems).get(), isEmpty);
    });

    testWidgets('confirming as a guest queues one record with an idempotency key, and no points', (tester) async {
      await tester.runAsync(() async {
        final (app, db) = await testApp(const RakatSelectionScreen(prayer: 'fajr', date: '2026-03-20'));
        addTearDown(db.close);
        await _pump(tester, app);

        await tester.tap(find.text('Fard'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Confirm Prayer'));
        await tester.pump();
        await Future<void>.delayed(const Duration(milliseconds: 200));
        await tester.pump();

        final rows = await db.select(db.outboxItems).get();
        expect(rows, hasLength(1));
        expect(rows.first.kind, 'salah');
        expect(rows.first.status, 'pending');
        expect(rows.first.pointsAwarded, isNull);
        expect(rows.first.payload, contains('"confirmed":true'));
        expect(rows.first.payload, contains(rows.first.idempotencyKey));
        await tester.pump(const Duration(seconds: 5));
      });
    });

    testWidgets('no point preview is shown when points are turned off', (tester) async {
      final (app, db) = await testApp(
        const RakatSelectionScreen(prayer: 'fajr', date: '2026-03-20'),
        prefs: {'show_points': false},
        extraOverrides: [activityTypesProvider.overrideWith((_) async => {'salah_rakat': const ActivityRule(1, 1)})],
      );
      addTearDown(db.close);
      await _pump(tester, app);
      await tester.tap(find.text('Fard'));
      await tester.pumpAndSettle();
      expect(find.textContaining('app points'), findsNothing);
    });

    testWidgets('the point preview is labelled as a preview', (tester) async {
      final (app, db) = await testApp(
        const RakatSelectionScreen(prayer: 'fajr', date: '2026-03-20'),
        extraOverrides: [activityTypesProvider.overrideWith((_) async => {'salah_rakat': const ActivityRule(1, 1)})],
      );
      addTearDown(db.close);
      await _pump(tester, app);
      await tester.tap(find.text('Fard'));
      await tester.pumpAndSettle();
      expect(find.text('About 2 app points'), findsOneWidget);
      expect(find.textContaining('A preview'), findsOneWidget);
    });
  });

  group('today screen', () {
    const located = '{"latitude":23.81,"longitude":90.41,"timezone":"Asia/Dhaka","calculation_method":"KARACHI"}';

    // A live Drift stream leaves a timer behind at teardown, so these screen tests supply the
    // list directly. test/offline_queue_test.dart covers the real queue.
    Object noPending() => pendingOutboxProvider.overrideWith((_) => Stream.value(const <OutboxItem>[]));

    testWidgets('never calls a prayer missed', (tester) async {
      final (app, db) = await testApp(const SalahScreen(), prefs: {'prayer_settings': located}, extraOverrides: [noPending()]);
      addTearDown(db.close);
      await _pump(tester, app);

      expect(find.text('Fajr'), findsOneWidget);
      expect(find.text('Isha'), findsOneWidget);
      expect(find.text('Record'), findsNWidgets(5));
      expect(find.textContaining(RegExp('miss', caseSensitive: false)), findsNothing);
    });

    testWidgets('works without a location and explains why times are absent', (tester) async {
      final (app, db) = await testApp(const SalahScreen(), extraOverrides: [noPending()]);
      addTearDown(db.close);
      await _pump(tester, app);
      expect(find.text('Set your location'), findsOneWidget);
      expect(find.text('Record'), findsWidgets);
    });

    testWidgets('a queued record shows Pending Sync rather than points', (tester) async {
      final now = tz.TZDateTime.now(tz.getLocation('Asia/Dhaka'));
      final date = '${now.year.toString().padLeft(4, '0')}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
      final item = OutboxItem(
        id: 1,
        idempotencyKey: 'k',
        kind: 'salah',
        payload: jsonEncode({'prayer': 'fajr', 'prayer_date': date}),
        status: 'pending',
        attempts: 0,
        createdAt: DateTime.now(),
      );
      final (app, db) = await testApp(
        const SalahScreen(),
        prefs: {'prayer_settings': located},
        extraOverrides: [pendingOutboxProvider.overrideWith((_) => Stream.value([item]))],
      );
      addTearDown(db.close);
      await _pump(tester, app);

      expect(find.text('Pending Sync'), findsOneWidget);
      expect(find.text('Record'), findsNWidgets(4));
      expect(find.textContaining('app points'), findsNothing);
    });
  });
}
