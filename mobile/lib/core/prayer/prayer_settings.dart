/// Mirrors the server's user_prayer_settings. Works without an account or GPS.
class PrayerSettings {
  const PrayerSettings({
    this.locationMode = 'manual',
    this.city,
    this.country,
    this.latitude,
    this.longitude,
    this.timezone = 'UTC',
    this.followDeviceTimezone = true,
    this.calculationMethod = 'MWL',
    this.asrMethod = 'standard',
    this.highLatitudeRule = 'angle_based',
    this.offsets = const {},
  });

  final String locationMode;
  final String? city;
  final String? country;
  final double? latitude;
  final double? longitude;
  final String timezone;
  final bool followDeviceTimezone;
  final String calculationMethod;
  final String asrMethod;
  final String highLatitudeRule;
  final Map<String, int> offsets;

  bool get hasLocation => latitude != null && longitude != null;

  int offset(String prayer) => offsets[prayer] ?? 0;

  PrayerSettings copyWith({
    String? locationMode,
    String? city,
    String? country,
    double? latitude,
    double? longitude,
    String? timezone,
    bool? followDeviceTimezone,
    String? calculationMethod,
    String? asrMethod,
    String? highLatitudeRule,
    Map<String, int>? offsets,
  }) => PrayerSettings(
    locationMode: locationMode ?? this.locationMode,
    city: city ?? this.city,
    country: country ?? this.country,
    latitude: latitude ?? this.latitude,
    longitude: longitude ?? this.longitude,
    timezone: timezone ?? this.timezone,
    followDeviceTimezone: followDeviceTimezone ?? this.followDeviceTimezone,
    calculationMethod: calculationMethod ?? this.calculationMethod,
    asrMethod: asrMethod ?? this.asrMethod,
    highLatitudeRule: highLatitudeRule ?? this.highLatitudeRule,
    offsets: offsets ?? this.offsets,
  );

  Map<String, dynamic> toJson() => {
    'location_mode': locationMode,
    'city': city,
    'country': country,
    'latitude': latitude,
    'longitude': longitude,
    'timezone': timezone,
    'follow_device_timezone': followDeviceTimezone,
    'calculation_method': calculationMethod,
    'asr_method': asrMethod,
    'high_latitude_rule': highLatitudeRule,
    for (final p in prayerOffsetKeys) '${p}_offset': offset(p),
  };

  /// Only the fields the API accepts: the device-only timezone choice stays local.
  Map<String, dynamic> toApiJson() => toJson()..remove('follow_device_timezone');

  factory PrayerSettings.fromJson(Map<String, dynamic> j) => PrayerSettings(
    locationMode: j['location_mode'] as String? ?? 'manual',
    city: j['city'] as String?,
    country: j['country'] as String?,
    latitude: (j['latitude'] as num?)?.toDouble(),
    longitude: (j['longitude'] as num?)?.toDouble(),
    timezone: j['timezone'] as String? ?? 'UTC',
    followDeviceTimezone: j['follow_device_timezone'] as bool? ?? true,
    calculationMethod: j['calculation_method'] as String? ?? 'MWL',
    asrMethod: j['asr_method'] as String? ?? 'standard',
    highLatitudeRule: j['high_latitude_rule'] as String? ?? 'angle_based',
    offsets: {for (final p in prayerOffsetKeys) p: (j['${p}_offset'] as num?)?.toInt() ?? 0},
  );

  static const prayerOffsetKeys = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
}

class PrayerNotificationPref {
  const PrayerNotificationPref({
    required this.prayer,
    this.enabled = true,
    this.offsetMinutes = 0,
    this.sound = 'default',
    this.vibration = true,
    this.privatePreview = false,
  });

  final String prayer;
  final bool enabled;
  final int offsetMinutes;
  final String sound;
  final bool vibration;
  final bool privatePreview;

  PrayerNotificationPref copyWith({bool? enabled, int? offsetMinutes, String? sound, bool? vibration, bool? privatePreview}) => PrayerNotificationPref(
    prayer: prayer,
    enabled: enabled ?? this.enabled,
    offsetMinutes: offsetMinutes ?? this.offsetMinutes,
    sound: sound ?? this.sound,
    vibration: vibration ?? this.vibration,
    privatePreview: privatePreview ?? this.privatePreview,
  );

  Map<String, dynamic> toJson() => {
    'prayer': prayer,
    'enabled': enabled,
    'offset_minutes': offsetMinutes,
    'sound': sound,
    'vibration': vibration,
    'private_preview': privatePreview,
  };

  factory PrayerNotificationPref.fromJson(Map<String, dynamic> j) => PrayerNotificationPref(
    prayer: j['prayer'] as String,
    enabled: j['enabled'] as bool? ?? true,
    offsetMinutes: (j['offset_minutes'] as num?)?.toInt() ?? 0,
    sound: j['sound'] as String? ?? 'default',
    vibration: j['vibration'] as bool? ?? true,
    privatePreview: j['private_preview'] as bool? ?? false,
  );
}
