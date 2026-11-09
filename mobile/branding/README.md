# DeenQ AI brand assets

**The mark.** The Q is drawn like a qalam (reed pen) stroke: a hairline at the upper right, a full
stroke at the lower left, and a slim swash tail. The Arabic qaf's two dots sit above it as gold
rhombuses, so the letter reads as both Q and ق. The standalone symbol holds an open book in the bowl.

**The wordmark.** "Deen" and "AI" are set in Marcellus, a flared inscriptional typeface whose
pen-like strokes sit well beside Arabic calligraphy, around the calligraphic Q. The letters are
converted to outlines, so the SVGs need no font installed. Marcellus is under the SIL Open Font
License (`fonts/Marcellus-OFL.txt`) and is only used here, not bundled in the app.

**Colours.** Only those in `lib/app/theme/app_theme.dart`: emerald `#075E45`, green `#0B7555`,
gold `#C6A45B`, cream `#F8F6EF`, dark `#101C19`, mint `#6FD3AE` and `#BDEFD9`.

| File | Use |
| --- | --- |
| `deenq_logo_light.svg/.png` | Primary logo (wordmark), for light backgrounds |
| `deenq_logo_dark.svg/.png` | Primary logo (wordmark), for dark backgrounds |
| `deenq_logo_stacked_light.svg/.png`, `deenq_logo_stacked_dark.svg/.png` | Symbol above the wordmark |
| `deenq_symbol_light.svg/.png`, `deenq_symbol_dark.svg/.png` | Standalone symbol |
| `deenq_app_icon.svg` | App icon master (cream mark on emerald) |
| `preview_on_light.png`, `preview_on_dark.png` | Previews only |

The app reads `assets/images/`: `app_icon.png` and `app_icon_foreground.png` (launcher icons),
`splash_logo.png` and `splash_logo_dark.png` (splash), and `logo_mark*.png` (in-app logo).

## Regenerating

`build_brand.py` writes all of the above. It renders PNGs with headless Microsoft Edge and needs
Python with `fontTools` and `Pillow`. Use absolute paths, then regenerate the platform icons and splash:

```bash
python branding/build_brand.py C:\path\to\mobile C:\path\to\scratch-folder
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```
