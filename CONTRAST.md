# Colour contrast in the Hub dashboard

Checked on 2026-11-05 with `tools/contrast.py` from the dark-mode work: WCAG 2.x contrast ratio and APCA-W3 (0.1.9)
lightness contrast, on the hex fallbacks of My assessments' `--ma-*` palette, which the hub reuses rather than defining
colours of its own. Targets: text 4.5:1 and Lc 60 or better; non-text (bars, borders, focus rings) 3:1 and Lc 45.

Fonts: the hub sets no `font-family` and no `font-size` of its own at the root (`font-size: inherit`). The only size
it uses is My assessments' small text (0.8203rem) for pills and flags, the size that palette's APCA targets were
checked at.

## Text

| Use | Foreground | Background | WCAG | APCA Lc |
|---|---|---|---|---|
| Body text on white | #1d2125 | #ffffff | 16.20:1 | 103.2 |
| Muted text (status, stacked-bar figure, hints) | #5f6770 | #ffffff | 5.74:1 | 78.7 |
| Attention ink on attention fill (Ready to go header, Release and Allocate tags) | rgb(55,44,29) | rgb(255,227,193) | 11.04:1 | 86.1 |
| Primary ink on primary fill (Chase header and tags) | rgb(24,60,60) | rgb(218,236,236) | 9.82:1 | 83.9 |
| Info ink on info fill (In progress header and tags) | rgb(0,62,76) | rgb(207,238,246) | 9.60:1 | 83.1 |
| Grey ink on grey fill (Done header and tags) | rgb(48,54,59) | rgb(229,232,235) | 9.95:1 | 84.1 |
| Danger text (Click again to confirm, editor error) | #ca3120 | #ffffff | 5.29:1 | 74.8 |
| Link text on white | #003c3c | #ffffff | 12.27:1 | 97.6 |
| White on primary solid (status pill count) | #ffffff | #003c3c | 12.27:1 | 101.1 |

All pass.

## Stacked progress bar

Each segment against the white track:

| Segment | Colour | WCAG | APCA Lc |
|---|---|---|---|
| Submitted | #6c757d | 4.69:1 | 72.6 |
| Marked | #003c3c | 12.27:1 | 97.6 |
| Moderated | #008196 | 4.58:1 | 71.3 |
| Sent to hub | rgb(189,115,0) | 3.74:1 | 64.5 |
| Released | #357a32 | 5.27:1 | 75.9 |

All pass. Neighbouring segments against each other do not (Submitted/Marked 2.62:1, Marked/Moderated 2.68:1,
Moderated/Sent 1.23:1, Sent/Released 1.41:1, Marked/Released 2.33:1), so the bar does not rely on hue alone:

- every segment ends in a 1px hairline of the track's white (`.hub-stack-seg` box-shadow), which is 3:1 or better
  against every segment;
- the bar carries its key figure in text beside it (for example "1 of 2 marked") and a full `aria-label` naming
  each segment and its count;
- the legend under the strip names the segments in order.

## Decorative borders

The group header's top rule (rgb(145,186,186), 2.11:1) and the grey pill border (rgb(170,178,185), 2.15:1) are under
3:1 on white. Neither carries meaning on its own: the group is named in its header text and its reason tags, and
the pill's text sits on its filled background, which passes above. They are decorative under WCAG 1.4.11 and left as
the palette has them.

## Focus

Focus rings are My assessments' `--ma-focus-ring`: a 0.2rem ring of the primary colour at 75% over the page
(#003c3c, 12.27:1 on white at full strength), on every button, link, chip, row toggle and input of the hub.
