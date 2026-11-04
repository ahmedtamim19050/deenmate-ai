import 'package:deenmate_ai/features/salah/salah_models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  RakatSelection fajr() => RakatSelection.fromTemplates(defaultTemplates.where((t) => t.prayer == 'fajr'));

  test('nothing is pre-selected, so a person must choose what they actually prayed', () {
    final s = fajr();
    expect(s.selected, isEmpty);
    expect(s.isValid, isFalse);
    expect(s.totalRakats, 0);
  });

  test('disabled template categories are not offered', () {
    final templates = [
      const SalahTemplate(prayer: 'asr', key: 'sunnah_before', label: 'Optional', category: 'sunnah', rakats: 4, enabled: false),
      const SalahTemplate(prayer: 'asr', key: 'fard', label: 'Fard', category: 'fard', rakats: 4),
    ];
    expect(RakatSelection.fromTemplates(templates).entries.map((e) => e.key), ['fard']);
  });

  test('selecting categories adds up the rakats', () {
    final s = fajr().toggled(0).toggled(1);
    expect(s.totalRakats, 4);
    expect(s.isValid, isTrue);
    expect(s.toggled(0).totalRakats, 2);
  });

  test('rakat counts can be changed and stay within limits', () {
    expect(fajr().toggled(1).withRakats(1, 3).totalRakats, 3);
    expect(fajr().withRakats(0, 0).entries[0].rakats, 1);
    expect(fajr().withRakats(0, 99).entries[0].rakats, 20);
  });

  test('items carry only what was selected, ready for the server', () {
    final items = fajr().toggled(1).toItems();
    expect(items, hasLength(1));
    expect(items.first.toJson(), {'template_key': 'fard', 'label': 'Fard', 'category': 'fard', 'rakats': 2});
  });

  test('too many rakats is not valid', () {
    var s = fajr();
    s = s.toggled(0).withRakats(0, 20).toggled(1).withRakats(1, 20);
    expect(s.isValid, isTrue);
    s = s.added(const RakatEntry('extra', 'Nafl', 'nafl', 1, true));
    expect(s.isValid, isFalse);
  });

  test('an optional category can be added', () {
    final s = fajr().added(const RakatEntry('extra', 'Nafl', 'nafl', 2, true));
    expect(s.totalRakats, 2);
    expect(s.entries.last.category, 'nafl');
  });

  test('the point preview multiplies by the published rule and awards nothing', () {
    expect(fajr().toggled(0).toggled(1).previewPoints(1), 4);
  });

  test('default templates match the brief', () {
    List<int> rakats(String p) => defaultTemplates.where((t) => t.prayer == p).map((t) => t.rakats).toList();
    expect(rakats('fajr'), [2, 2]);
    expect(rakats('dhuhr'), [4, 4, 2]);
    expect(rakats('asr'), [4, 4]);
    expect(rakats('maghrib'), [3, 2]);
    expect(defaultTemplates.where((t) => t.prayer == 'isha').map((t) => t.category), ['fard', 'sunnah', 'witr']);
  });

  test('templates round trip through JSON', () {
    final t = defaultTemplates.first;
    final back = SalahTemplate.fromJson(t.toJson());
    expect(back.key, t.key);
    expect(back.rakats, t.rakats);
    expect(back.enabled, t.enabled);
  });
}
