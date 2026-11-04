class SalahTemplate {
  const SalahTemplate({
    required this.prayer,
    required this.key,
    required this.label,
    required this.category,
    required this.rakats,
    this.enabled = true,
  });

  final String prayer;
  final String key;
  final String label;
  final String category;
  final int rakats;
  final bool enabled;

  SalahTemplate copyWith({String? label, String? category, int? rakats, bool? enabled}) => SalahTemplate(
    prayer: prayer,
    key: key,
    label: label ?? this.label,
    category: category ?? this.category,
    rakats: rakats ?? this.rakats,
    enabled: enabled ?? this.enabled,
  );

  Map<String, dynamic> toJson() => {
    'prayer': prayer,
    'template_key': key,
    'label': label,
    'category': category,
    'rakats': rakats,
    'is_enabled': enabled,
  };

  factory SalahTemplate.fromJson(Map<String, dynamic> j) => SalahTemplate(
    prayer: j['prayer'] as String,
    key: j['template_key'] as String,
    label: j['label'] as String,
    category: j['category'] as String,
    rakats: (j['rakats'] as num).toInt(),
    enabled: j['is_enabled'] as bool? ?? true,
  );
}

/// Editable starting points only, not universal religious requirements.
const defaultTemplates = <SalahTemplate>[
  SalahTemplate(prayer: 'fajr', key: 'sunnah_before', label: 'Sunnah', category: 'sunnah', rakats: 2),
  SalahTemplate(prayer: 'fajr', key: 'fard', label: 'Fard', category: 'fard', rakats: 2),
  SalahTemplate(prayer: 'dhuhr', key: 'sunnah_before', label: 'Sunnah (before)', category: 'sunnah', rakats: 4),
  SalahTemplate(prayer: 'dhuhr', key: 'fard', label: 'Fard', category: 'fard', rakats: 4),
  SalahTemplate(prayer: 'dhuhr', key: 'sunnah_after', label: 'Sunnah (after)', category: 'sunnah', rakats: 2),
  SalahTemplate(prayer: 'asr', key: 'sunnah_before', label: 'Optional Sunnah', category: 'sunnah', rakats: 4),
  SalahTemplate(prayer: 'asr', key: 'fard', label: 'Fard', category: 'fard', rakats: 4),
  SalahTemplate(prayer: 'maghrib', key: 'fard', label: 'Fard', category: 'fard', rakats: 3),
  SalahTemplate(prayer: 'maghrib', key: 'sunnah_after', label: 'Sunnah', category: 'sunnah', rakats: 2),
  SalahTemplate(prayer: 'isha', key: 'fard', label: 'Fard', category: 'fard', rakats: 4),
  SalahTemplate(prayer: 'isha', key: 'sunnah_after', label: 'Sunnah', category: 'sunnah', rakats: 2),
  SalahTemplate(prayer: 'isha', key: 'witr', label: 'Witr', category: 'witr', rakats: 3),
];

class SalahItem {
  const SalahItem({this.templateKey, required this.label, required this.category, required this.rakats});

  final String? templateKey;
  final String label;
  final String category;
  final int rakats;

  Map<String, dynamic> toJson() => {'template_key': templateKey, 'label': label, 'category': category, 'rakats': rakats};

  factory SalahItem.fromJson(Map<String, dynamic> j) => SalahItem(
    templateKey: j['template_key'] as String?,
    label: j['label'] as String,
    category: j['category'] as String,
    rakats: (j['rakats'] as num).toInt(),
  );
}

class SalahRecord {
  const SalahRecord({
    required this.id,
    required this.prayer,
    required this.date,
    required this.confirmedAt,
    required this.totalRakats,
    required this.items,
    required this.points,
  });

  final int id;
  final String prayer;
  final String date;
  final DateTime? confirmedAt;
  final int totalRakats;
  final List<SalahItem> items;
  final int points;

  factory SalahRecord.fromJson(Map<String, dynamic> j) => SalahRecord(
    id: (j['id'] as num).toInt(),
    prayer: j['prayer'] as String,
    date: j['prayer_date'] as String,
    confirmedAt: DateTime.tryParse(j['confirmed_at'] as String? ?? ''),
    totalRakats: (j['total_rakats'] as num).toInt(),
    items: (j['items'] as List).map((e) => SalahItem.fromJson(e as Map<String, dynamic>)).toList(),
    points: (j['points'] as num?)?.toInt() ?? 0,
  );
}

/// What the person selected on the rakat screen. Nothing is recorded until they confirm.
class RakatSelection {
  RakatSelection(this.entries);

  factory RakatSelection.fromTemplates(Iterable<SalahTemplate> templates) =>
      RakatSelection(templates.where((t) => t.enabled).map((t) => RakatEntry(t.key, t.label, t.category, t.rakats, false)).toList());

  final List<RakatEntry> entries;

  RakatSelection toggled(int i) => RakatSelection([
    for (var j = 0; j < entries.length; j++) j == i ? entries[j].copyWith(selected: !entries[j].selected) : entries[j],
  ]);

  RakatSelection withRakats(int i, int rakats) => RakatSelection([
    for (var j = 0; j < entries.length; j++) j == i ? entries[j].copyWith(rakats: rakats.clamp(1, 20)) : entries[j],
  ]);

  RakatSelection added(RakatEntry entry) => RakatSelection([...entries, entry]);

  Iterable<RakatEntry> get selected => entries.where((e) => e.selected);

  bool get isValid => selected.isNotEmpty && totalRakats <= 40;

  int get totalRakats => selected.fold(0, (sum, e) => sum + e.rakats);

  List<SalahItem> toItems() => selected.map((e) => SalahItem(templateKey: e.key, label: e.label, category: e.category, rakats: e.rakats)).toList();

  /// A preview from the server's published rule. The server calculates the actual award.
  int previewPoints(int pointsPerRakat) => totalRakats * pointsPerRakat;
}

class RakatEntry {
  const RakatEntry(this.key, this.label, this.category, this.rakats, this.selected);

  final String key;
  final String label;
  final String category;
  final int rakats;
  final bool selected;

  RakatEntry copyWith({int? rakats, bool? selected}) => RakatEntry(key, label, category, rakats ?? this.rakats, selected ?? this.selected);
}
