import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';

class AppColors {
  static const emerald = Color(0xFF075E45);
  static const green = Color(0xFF0B7555);
  static const cream = Color(0xFFF8F6EF);
  static const gold = Color(0xFFC6A45B);
  static const darkBg = Color(0xFF101C19);
  static const darkSurface = Color(0xFF17282F);
  static const muted = Color(0xFF6F7B75);
}

class AppTheme {
  static const fontFallback = ['HindSiliguri', 'NotoNaskhArabic'];

  static ThemeData light() => _build(
    ColorScheme.fromSeed(seedColor: AppColors.emerald, brightness: Brightness.light).copyWith(
      primary: AppColors.emerald,
      secondary: AppColors.gold,
      surface: Colors.white,
      onSurfaceVariant: AppColors.muted,
      primaryContainer: const Color(0xFFD7EEE5),
      onPrimaryContainer: AppColors.emerald,
      secondaryContainer: const Color(0xFFF3E9D2),
      onSecondaryContainer: const Color(0xFF5A4720),
    ),
    AppColors.cream,
  );

  static ThemeData dark() => _build(
    ColorScheme.fromSeed(seedColor: AppColors.emerald, brightness: Brightness.dark).copyWith(
      primary: const Color(0xFF6FD3AE),
      onPrimary: const Color(0xFF00382A),
      secondary: AppColors.gold,
      surface: const Color(0xFF182824),
      onSurface: const Color(0xFFE9EEEB),
      onSurfaceVariant: const Color(0xFFA4B1AB),
      primaryContainer: const Color(0xFF0E4A38),
      onPrimaryContainer: const Color(0xFFBDEFD9),
      secondaryContainer: const Color(0xFF443719),
      onSecondaryContainer: const Color(0xFFF0DFB6),
    ),
    AppColors.darkBg,
  );

  static ThemeData _build(ColorScheme scheme, Color background) {
    final base = ThemeData(useMaterial3: true, colorScheme: scheme, fontFamily: 'Manrope', fontFamilyFallback: fontFallback);
    final text = base.textTheme.copyWith(
      headlineLarge: base.textTheme.headlineLarge?.copyWith(fontWeight: FontWeight.w700, letterSpacing: -0.5),
      headlineMedium: base.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700, letterSpacing: -0.3),
      headlineSmall: base.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
      titleLarge: base.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      titleMedium: base.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
      titleSmall: base.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
      bodyMedium: base.textTheme.bodyMedium?.copyWith(height: 1.55),
      bodyLarge: base.textTheme.bodyLarge?.copyWith(height: 1.5),
    );
    final radius = BorderRadius.circular(14);
    return base.copyWith(
      scaffoldBackgroundColor: background,
      textTheme: text,
      appBarTheme: AppBarTheme(
        backgroundColor: background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleLarge?.copyWith(color: scheme.onSurface),
      ),
      cardTheme: CardThemeData(
        color: scheme.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.45)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(minimumSize: const Size(48, 52), shape: RoundedRectangleBorder(borderRadius: radius), textStyle: text.titleSmall),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(minimumSize: const Size(48, 52), shape: RoundedRectangleBorder(borderRadius: radius), textStyle: text.titleSmall),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surface,
        border: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: scheme.outlineVariant)),
        enabledBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: scheme.outlineVariant)),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surface,
        indicatorColor: scheme.primaryContainer,
        surfaceTintColor: Colors.transparent,
        labelTextStyle: WidgetStatePropertyAll(text.labelSmall?.copyWith(fontWeight: FontWeight.w600)),
      ),
      chipTheme: base.chipTheme.copyWith(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
      snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant.withValues(alpha: 0.5), space: 1),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {TargetPlatform.android: FadeForwardsPageTransitionsBuilder(), TargetPlatform.iOS: CupertinoPageTransitionsBuilder()},
      ),
    );
  }
}

/// Quran text uses a dedicated face and is always laid out right to left.
TextStyle quranTextStyle(BuildContext context, {double size = 30}) => TextStyle(
  fontFamily: 'ScheherazadeNew',
  fontSize: size,
  height: 1.9,
  color: Theme.of(context).colorScheme.onSurface,
);
