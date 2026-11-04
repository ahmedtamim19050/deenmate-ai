import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../config.dart';

/// Ads stay off until consent has been handled, and are not personalised by default.
class AdService {
  bool _ready = false;
  bool personalized = false;

  bool get ready => _ready;

  Future<void> init({required bool personalized}) async {
    this.personalized = personalized;
    try {
      await _gatherConsent();
      if (!await ConsentInformation.instance.canRequestAds()) return;
      await MobileAds.instance.initialize();
      await MobileAds.instance.updateRequestConfiguration(RequestConfiguration(maxAdContentRating: MaxAdContentRating.g));
      _ready = true;
    } catch (e) {
      // The whole app works without ads.
      debugPrint('Ads are unavailable: ${e.runtimeType}');
    }
  }

  Future<void> _gatherConsent() async {
    final updated = Completer<void>();
    ConsentInformation.instance.requestConsentInfoUpdate(
      ConsentRequestParameters(),
      () => updated.complete(),
      (_) => updated.complete(),
    );
    await updated.future;
    final shown = Completer<void>();
    await ConsentForm.loadAndShowConsentFormIfRequired((_) => shown.complete());
    await shown.future;
  }

  AdRequest get request => AdRequest(nonPersonalizedAds: !personalized);
}

/// A clearly labelled native ad for content lists. It is only placed on Home and Learn
/// lists, never on Quran, Salah, Dua, Tasbih or AI answer screens.
class NativeAdCard extends StatefulWidget {
  const NativeAdCard({super.key, required this.service, required this.label});

  final AdService service;
  final String label;

  @override
  State<NativeAdCard> createState() => _NativeAdCardState();
}

class _NativeAdCardState extends State<NativeAdCard> {
  NativeAd? _ad;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_ad != null || !widget.service.ready) return;
    final scheme = Theme.of(context).colorScheme;
    _ad = NativeAd(
      adUnitId: AppConfig.nativeAdUnit,
      request: widget.service.request,
      listener: NativeAdListener(
        onAdLoaded: (_) {
          if (mounted) setState(() => _loaded = true);
        },
        onAdFailedToLoad: (ad, _) => ad.dispose(),
      ),
      nativeTemplateStyle: NativeTemplateStyle(templateType: TemplateType.small, mainBackgroundColor: scheme.surface, cornerRadius: 16),
    )..load();
  }

  @override
  void dispose() {
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ad == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(widget.label, style: Theme.of(context).textTheme.labelSmall),
          const SizedBox(height: 4),
          ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 90, maxHeight: 120),
            child: AdWidget(ad: _ad!),
          ),
        ],
      ),
    );
  }
}

/// Non-intrusive banner for the bottom of the course list, where an admin has enabled it.
class BannerAdStrip extends StatefulWidget {
  const BannerAdStrip({super.key, required this.service});

  final AdService service;

  @override
  State<BannerAdStrip> createState() => _BannerAdStripState();
}

class _BannerAdStripState extends State<BannerAdStrip> {
  BannerAd? _ad;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    if (!widget.service.ready) return;
    _ad = BannerAd(
      adUnitId: AppConfig.bannerAdUnit,
      size: AdSize.banner,
      request: widget.service.request,
      listener: BannerAdListener(
        onAdLoaded: (_) {
          if (mounted) setState(() => _loaded = true);
        },
        onAdFailedToLoad: (ad, _) => ad.dispose(),
      ),
    )..load();
  }

  @override
  void dispose() {
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ad == null) return const SizedBox.shrink();
    return SizedBox(height: _ad!.size.height.toDouble(), child: AdWidget(ad: _ad!));
  }
}
