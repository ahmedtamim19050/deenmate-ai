import 'package:flutter/material.dart';
import 'package:flutter_timezone/flutter_timezone.dart';
import 'package:geolocator/geolocator.dart';

import '../../core/prayer/cities.dart';
import '../../core/prayer/prayer_calculator.dart';
import '../../core/prayer/prayer_settings.dart';
import '../../core/widgets/common.dart';

/// City, coordinates or optional GPS. Permission is only requested if the person taps GPS.
class LocationPicker extends StatefulWidget {
  const LocationPicker({super.key, required this.settings, required this.onChanged});

  final PrayerSettings settings;
  final ValueChanged<PrayerSettings> onChanged;

  @override
  State<LocationPicker> createState() => _LocationPickerState();
}

class _LocationPickerState extends State<LocationPicker> {
  bool _locating = false;
  String? _gpsMessage;

  Future<void> _useGps() async {
    final l = context.l10n;
    setState(() {
      _locating = true;
      _gpsMessage = null;
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        setState(() => _gpsMessage = l.gpsServiceOff);
        return;
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        setState(() => _gpsMessage = l.gpsDenied);
        return;
      }
      final position = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.low));
      final zone = await _deviceZone();
      widget.onChanged(
        widget.settings.copyWith(
          locationMode: 'gps',
          city: l.currentLocation,
          latitude: double.parse(position.latitude.toStringAsFixed(4)),
          longitude: double.parse(position.longitude.toStringAsFixed(4)),
          timezone: zone,
          followDeviceTimezone: true,
        ),
      );
    } catch (_) {
      setState(() => _gpsMessage = l.gpsFailed);
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<String> _deviceZone() async {
    try {
      return (await FlutterTimezone.getLocalTimezone()).identifier;
    } catch (_) {
      return widget.settings.timezone;
    }
  }

  Future<void> _pickCity() async {
    final city = await showModalBottomSheet<City>(context: context, isScrollControlled: true, useSafeArea: true, builder: (_) => const _CitySheet());
    if (city == null) return;
    widget.onChanged(
      widget.settings.copyWith(
        locationMode: 'city',
        city: city.name,
        country: city.country,
        latitude: city.latitude,
        longitude: city.longitude,
        timezone: city.timezone,
        followDeviceTimezone: false,
      ),
    );
  }

  Future<void> _manual() async {
    final zone = await _deviceZone();
    if (!mounted) return;
    final result = await showDialog<PrayerSettings>(
      context: context,
      builder: (_) => _CoordinatesDialog(settings: widget.settings, deviceZone: zone),
    );
    if (result != null) widget.onChanged(result);
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final s = widget.settings;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (s.hasLocation)
          Card(
            color: context.colors.primaryContainer,
            child: ListTile(
              leading: const Icon(Icons.place_outlined),
              title: Text(s.city ?? l.customLocation),
              subtitle: Text('${s.latitude}, ${s.longitude}\n${s.timezone}'),
            ),
          ),
        const SizedBox(height: 10),
        Card(
          child: Column(
            children: [
              NavTile(icon: Icons.location_city_outlined, title: l.chooseCity, onTap: _pickCity),
              NavTile(icon: Icons.edit_location_alt_outlined, title: l.enterCoordinates, onTap: _manual),
              NavTile(icon: Icons.my_location, title: l.useGps, subtitle: l.gpsOptional, onTap: _locating ? null : _useGps, trailing: _locating ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : null),
            ],
          ),
        ),
        if (_gpsMessage != null) ...[
          const SizedBox(height: 10),
          Text(_gpsMessage!, style: context.text.bodySmall),
        ],
      ],
    );
  }
}

class _CitySheet extends StatefulWidget {
  const _CitySheet();

  @override
  State<_CitySheet> createState() => _CitySheetState();
}

class _CitySheetState extends State<_CitySheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final q = _query.toLowerCase();
    final filtered = cities.where((c) => q.isEmpty || c.name.toLowerCase().contains(q) || c.country.toLowerCase() == q).toList();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              autofocus: true,
              decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: context.l10n.searchCity),
              onChanged: (v) => setState(() => _query = v),
            ),
          ),
          Expanded(
            child: filtered.isEmpty
                ? StateMessage(icon: Icons.search_off, title: context.l10n.noCityFound, body: context.l10n.noCityFoundBody, compact: true)
                : ListView.builder(
                    itemCount: filtered.length,
                    itemBuilder: (_, i) => ListTile(
                      title: Text(filtered[i].name),
                      subtitle: Text('${filtered[i].country} · ${filtered[i].timezone}'),
                      onTap: () => Navigator.pop(context, filtered[i]),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _CoordinatesDialog extends StatefulWidget {
  const _CoordinatesDialog({required this.settings, required this.deviceZone});

  final PrayerSettings settings;
  final String deviceZone;

  @override
  State<_CoordinatesDialog> createState() => _CoordinatesDialogState();
}

class _CoordinatesDialogState extends State<_CoordinatesDialog> {
  late final _lat = TextEditingController(text: widget.settings.latitude?.toString() ?? '');
  late final _lng = TextEditingController(text: widget.settings.longitude?.toString() ?? '');
  late final _zone = TextEditingController(text: widget.deviceZone);
  final _form = GlobalKey<FormState>();

  @override
  void dispose() {
    _lat.dispose();
    _lng.dispose();
    _zone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    String? number(String? v, double limit) {
      final n = double.tryParse(v ?? '');
      return n == null || n.abs() > limit ? l.coordinateInvalid : null;
    }

    return AlertDialog(
      title: Text(l.enterCoordinates),
      content: Form(
        key: _form,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _lat,
                keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
                decoration: InputDecoration(labelText: l.latitude),
                validator: (v) => number(v, 90),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _lng,
                keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
                decoration: InputDecoration(labelText: l.longitude),
                validator: (v) => number(v, 180),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _zone,
                decoration: InputDecoration(labelText: l.timezoneLabel, helperText: l.timezoneHelp),
                validator: (v) => (v == null || !v.contains('/') && v != 'UTC') ? l.timezoneInvalid : null,
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.cancel)),
        FilledButton(
          onPressed: () {
            if (!_form.currentState!.validate()) return;
            Navigator.pop(
              context,
              widget.settings.copyWith(
                locationMode: 'manual',
                city: l.customLocation,
                latitude: double.parse(_lat.text),
                longitude: double.parse(_lng.text),
                timezone: _zone.text.trim(),
                followDeviceTimezone: _zone.text.trim() == widget.deviceZone,
              ),
            );
          },
          child: Text(l.save),
        ),
      ],
    );
  }
}

class MethodPicker extends StatelessWidget {
  const MethodPicker({super.key, required this.settings, required this.onChanged});

  final PrayerSettings settings;
  final ValueChanged<PrayerSettings> onChanged;

  @override
  Widget build(BuildContext context) => RadioGroup<String>(
    groupValue: settings.calculationMethod,
    onChanged: (v) {
      if (v != null) onChanged(settings.copyWith(calculationMethod: v));
    },
    child: Card(
      child: Column(
        children: [
          for (final m in calculationMethods)
            RadioListTile<String>(
              value: m.key,
              title: Text(m.name),
              subtitle: Text(m.ishaInterval != null ? 'Fajr ${m.fajr}°, Isha ${m.ishaInterval} min' : 'Fajr ${m.fajr}°, Isha ${m.isha}°'),
            ),
        ],
      ),
    ),
  );
}

class AsrPicker extends StatelessWidget {
  const AsrPicker({super.key, required this.settings, required this.onChanged});

  final PrayerSettings settings;
  final ValueChanged<PrayerSettings> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return RadioGroup<String>(
      groupValue: settings.asrMethod,
      onChanged: (v) {
        if (v != null) onChanged(settings.copyWith(asrMethod: v));
      },
      child: Card(
        child: Column(
          children: [
            RadioListTile<String>(value: 'standard', title: Text(l.asrStandard), subtitle: Text(l.asrStandardBody)),
            RadioListTile<String>(value: 'hanafi', title: Text(l.asrHanafi), subtitle: Text(l.asrHanafiBody)),
          ],
        ),
      ),
    );
  }
}
