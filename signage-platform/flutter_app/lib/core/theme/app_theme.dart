import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../constants/app_colors.dart';

class AppTheme {
  AppTheme._();

  static ThemeData light() => _build(Brightness.light);
  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness b) {
    final dark = b == Brightness.dark;
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.teal,
      brightness: b,
      primary: AppColors.teal,
      onPrimary: Colors.white,
      secondary: AppColors.darkTeal,
      surface: dark ? AppColors.darkSurface : AppColors.white,
      onSurface: dark ? Colors.white : AppColors.charcoal,
      error: AppColors.danger,
    ).copyWith(
      surfaceContainerLowest: dark ? AppColors.darkBackground : AppColors.lightGrey,
      surfaceContainerLow: dark ? const Color(0xFF1C1F22) : AppColors.lightGrey,
      surfaceContainer: dark ? AppColors.darkSurface : AppColors.white,
      surfaceContainerHigh: dark ? const Color(0xFF282C31) : const Color(0xFFF0F2F3),
      outline: dark ? AppColors.darkBorder : AppColors.borderGrey,
      outlineVariant: dark ? AppColors.darkBorder : AppColors.borderGrey,
      primaryContainer: dark ? const Color(0xFF0D3B39) : AppColors.lightTeal,
      onPrimaryContainer: dark ? const Color(0xFF9FE3DE) : AppColors.darkTeal,
    );
    // Use the Inter font family name only (works with every google_fonts
    // version, unlike GoogleFonts.interTextTheme whose TextTheme type changed in v9).
    final baseText = dark ? ThemeData.dark().textTheme : ThemeData.light().textTheme;
    final text = baseText.apply(
      fontFamily: GoogleFonts.inter().fontFamily,
      bodyColor: scheme.onSurface,
      displayColor: scheme.onSurface,
    );
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: BorderSide(color: scheme.outline),
    );
    return ThemeData(
      useMaterial3: true,
      brightness: b,
      colorScheme: scheme,
      scaffoldBackgroundColor: dark ? AppColors.darkBackground : AppColors.lightGrey,
      textTheme: text.copyWith(
        headlineMedium: text.headlineMedium?.copyWith(fontWeight: FontWeight.w700, letterSpacing: -0.5),
        headlineSmall: text.headlineSmall?.copyWith(fontWeight: FontWeight.w700, letterSpacing: -0.3),
        titleLarge: text.titleLarge?.copyWith(fontWeight: FontWeight.w700),
        titleMedium: text.titleMedium?.copyWith(fontWeight: FontWeight.w600),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: dark ? AppColors.darkBackground : AppColors.lightGrey,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: dark ? const Color(0xFF1C1F22) : AppColors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: border,
        enabledBorder: border,
        focusedBorder: border.copyWith(borderSide: const BorderSide(color: AppColors.teal, width: 1.6)),
        errorBorder: border.copyWith(borderSide: const BorderSide(color: AppColors.danger)),
        labelStyle: TextStyle(color: AppColors.mediumGrey),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 50),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 50),
          side: BorderSide(color: scheme.outline),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.teal,
        foregroundColor: Colors.white,
        elevation: 2,
        shape: StadiumBorder(),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: scheme.surface,
        indicatorColor: scheme.primaryContainer,
        height: 68,
        labelTextStyle: WidgetStateProperty.resolveWith((s) => TextStyle(
              fontSize: 12,
              fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
              color: s.contains(WidgetState.selected) ? scheme.primary : AppColors.mediumGrey,
            )),
        iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(
              color: s.contains(WidgetState.selected) ? scheme.primary : AppColors.mediumGrey,
            )),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scheme.surface,
        showDragHandle: true,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        side: BorderSide(color: scheme.outline),
      ),
      dividerTheme: DividerThemeData(color: scheme.outline, space: 1),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      listTileTheme: const ListTileThemeData(contentPadding: EdgeInsets.symmetric(horizontal: 16)),
    );
  }
}
