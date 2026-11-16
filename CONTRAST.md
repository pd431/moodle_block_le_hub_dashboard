# Colour contrast in the Hub dashboard

The hub takes every colour from My assessments' `--ma-*` palette rather than defining colours of its own, so it goes
dark with that palette when Moodle 5.3's colour mode sets `data-bs-theme="dark"` on the page (D1, beta3). The two
things the hub chooses for itself, the stacked bar's segment tones and the tint of a hovered or open row, are set
once per mode in `scss/styles.scss`.

Targets, as My assessments' (`tests/fixtures/contrast.js` there): every text APCA Lc 75 and WCAG 4.5:1; icons,
bar segments, control outlines and focus indicators Lc 45 and 3:1 (WCAG 1.4.11). The Behat feature
`tests/behat/dark-mode.feature` runs that checker over the hub at rest, with every kind of control hovered, with
the keyboard focus on each control in turn, with a flag list and a row open, and with the view editor open, in
each mode. The light scenarios run on every target; the dark ones need Moodle 5.3 and skip before it.

Measured on 2026-11-16 in Chromium on the 4.5 test site, with the dark mode emulated by setting the attribute and
Boost 5.3's dark surfaces (page #1d2125, card #343a40, recessed #292e33). The figures below are the main pairs;
the checker covers every visible element.

Fonts: the hub sets no `font-family` and no `font-size` of its own at the root (`font-size: inherit`). The only size
it uses is My assessments' small text (0.8203rem) for pills and flags, the size that palette's APCA targets were
checked at.

## Text

| Use | Light: fg on bg | WCAG | Lc | Dark: fg on bg | WCAG | Lc |
|---|---|---|---|---|---|---|
| Body text, reason in bold, status, module code | #1d2125 on #ffffff | 16.20:1 | 103.2 | #ecf0f4 on #343a40 | 10.49:1 | 90.9 |
| Muted text (flag count, stacked-bar figure, hints) | #5f6770 on #ffffff | 5.74:1 | 78.7 | #d1d9e1 on #343a40 | 8.43:1 | 76.1 |
| Muted text on a hovered or open row | #5f6770 on #f8f9fa | 5.44:1 | 75.0 | #d1d9e1 on #32373c | 8.43:1 | 76.1 |
| Link (assessment name) | #005bac on #ffffff | 7.16:1 | 83.5 | #c1e5ff on #343a40 | 9.11:1 | 81.1 |
| Link on a hovered or open row | #005bac on #f8f9fa | 6.44:1 | 79.0 | #c1e5ff on #32373c | 9.11:1 | 81.1 |
| Attention ink on attention fill (Ready to go) | #372c1d on #ffe3c1 | 11.04:1 | 86.1 | #fce0be on #432700 | 10.82:1 | 85.8 |
| Primary ink on primary fill (Chase band and tags) | rgb(24,60,60) on rgb(218,236,236) | 9.82:1 | 83.9 | #cce8ff on its fill | 9.07:1 | 82.9 |
| Info ink on info fill (In progress band and tags) | rgb(0,62,76) on rgb(207,238,246) | 9.60:1 | 83.1 | #ccebf3 on its fill | 9.17:1 | 83.7 |
| Grey ink on grey fill (Done band and tags) | rgb(48,54,59) on rgb(229,232,235) | 9.95:1 | 84.1 | #e1e5e8 on #2b2e31 | 10.78:1 | 86.0 |
| Danger text (Click again to confirm, editor error) | #ca3120 on #ffffff | 5.29:1 | 74.8 | Boost's lifted danger on the card | | |
| Surface on primary ink (pressed chip, pressed reason) | #ffffff on primary ink | 12.27:1 | 101.1 | #343a40 on light ink | 9.07:1 | 82.9 |

All pass the checker in both modes. The muted text on a hovered or open row is the tightest pair in light mode,
which is why the row tint is a quarter of the grey fill over the surface (`--hub-row-tint`) and not the fill
itself: the fill (#e5e8eb) takes muted text to Lc 64.9.

Bootstrap's `.text-muted` is re-pointed to `--ma-text-muted` inside the hub, because Boost's own muted grey reaches
only Lc 54 on its dark card.

## Stacked progress bar

Each segment against the track (the surface). Light uses the palette's solid tones, dark the bar step that My
assessments checked on its dark track (a lighter, less saturated tint of each).

| Segment | Light | WCAG | Lc | Dark | WCAG | Lc |
|---|---|---|---|---|---|---|
| Submitted | #6c757d on #ffffff | 4.69:1 | 72.6 | #a4acb3 on #343a40 | 5.00:1 | 49.0 |
| Marked | #0f6cbf on #ffffff | 5.36:1 | 76.1 | #70aff5 on #343a40 | 5.01:1 | 49.3 |
| Moderated | #008196 on #ffffff | 4.58:1 | 71.3 | info bar step on #343a40 | ≥ 3:1 | ≥ 45 |
| Sent to hub | rgb(189,115,0) on #ffffff | 3.74:1 | 64.5 | attention bar step on #343a40 | ≥ 3:1 | ≥ 45 |
| Released | #357a32 on #ffffff | 5.27:1 | 75.9 | #84bc80 on #343a40 | 5.20:1 | 51.0 |

All pass 3:1 and Lc 45. Neighbouring segments against each other do not, so the bar does not rely on hue alone:

- every segment ends in a 1px hairline of the track's surface colour (`.hub-stack-seg` box-shadow), which is 3:1 or
  better against every segment in both modes;
- the bar carries its key figure in text beside it (for example "1 of 2 marked") and a full `aria-label` naming
  each segment and its count;
- the legend under the strip names the segments in order.

## Control outlines

The Find box and the editor's inputs take `--ma-control-line` for their border (#6c757d on white, 4.69:1; the
palette's grey border on the dark card, 5.00:1) and `--ma-surface` for their background, instead of Bootstrap's
own, which fall under 3:1 in dark mode.

## Decorative borders

The group header's top rule and the grey pill border are under 3:1 on the surface in both modes. Neither carries
meaning on its own: the group is named in its header text and its reason tags, and the pill's text sits on its
filled background, which passes above. They are decorative under WCAG 1.4.11 and left as the palette has them.

## Focus

Focus rings are My assessments' `--ma-focus-ring`: a 0.2rem ring of the link colour at 85% over the page in light
mode and of the primary glyph tone in dark mode, on every button, link, chip, row toggle, flag count and input of
the hub. The Find box's and the editor's inputs show it in place of Bootstrap's input ring, which reaches only
Lc 12 on the dark card. The checker walks every Tab stop in the block (58 on the test view) and finds each ring at
Lc 45 and 3:1 or better in both modes.
