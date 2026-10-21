# CultivaTrace dashboard fonts

These original font files are self-hosted so the Figma typography does not
depend on fonts installed on a user's machine or a third-party font service.
They are used only inside the `.ct-theme` scope in `executive-tokens.css`.

| File | Official source | Licence |
| --- | --- | --- |
| `Geist-Variable.woff2` | [Vercel Geist variable webfont](https://github.com/vercel/geist-font/blob/main/fonts/Geist/webfonts/Geist%5Bwght%5D.woff2) | `Geist-LICENSE.txt` (SIL OFL 1.1) |
| `LiberationSerif-Regular.ttf` | [Liberation Fonts 2.1.5 release](https://github.com/liberationfonts/liberation-fonts/releases/tag/2.1.5) | `LiberationSerif-LICENSE.txt` (SIL OFL 1.1) |
| `LiberationSerif-Bold.ttf` | Same original 2.1.5 release archive | Same licence |
| `LiberationSerif-Italic.ttf` | Same original 2.1.5 release archive | Same licence |
| `LiberationSerif-BoldItalic.ttf` | Same original 2.1.5 release archive | Same licence |

Downloaded from the official repositories on 2026-10-21. Font outlines,
metadata and family names have not been altered.

The Liberation files are extracted from the release's original
`liberation-fonts-ttf-2.1.5.tar.gz` archive. Regular and bold styles are used
in Figma; the original italic styles are included to avoid synthesized glyphs.
Geist is the original variable WOFF2,
renamed on disk for a URL without brackets; its internal family remains Geist.
