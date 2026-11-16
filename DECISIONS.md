# Decisions

The reasons behind the Hub dashboard's rules, under stable keys that code comments cite. The keys come from the three
approved scopes: `hub-view.feature` (D1 to D11, the behaviour), `hub-view-simplified.feature` (P1 to P10, the
presentation) and `hub-beta3.feature` (L, C, E, F, A, R and DM1: loading, categories, the editor, Find, alignment,
the row and dark mode), all in the project's `hub-dashboard` folder. My assessments' own decisions (its DECISIONS.md, the HB
series in particular) cover what its index records for the hub.

## Behaviour (D)

- **D1** Access is the capability `block/le_hub_dashboard:view`, granted to the education support role at system level
  or on a course category. No course enrolment is needed. People without it never see the block, even when it is on
  their Dashboard page. The capability's context grants access only: every hub user then sees the whole institution,
  shaped by their own views (D6).
- **D2** Summative work is shown by default. The Summative / Formative / Both choice is My assessments', and is
  remembered per user.
- **D3** Ticking a faculty in a view includes its departments: a category line matches the category and everything
  under it.
- **D4** "Marking at risk" is a marking deadline within the site's risk window (default 5 days) with work still
  unmarked; "Marking late" is a marking deadline passed with work still unmarked.
- **D5** The block can't see exports to the student records system, so Released is the end state it knows.
- **D6** Every hub user can see the whole institution (Pierre, 2026-11-04). What they see is shaped by their own
  saved views: departments, module code lines (starts with, contains; any line matching), modules added by code,
  modules taken out and assessments ignored (P6). Views are personal; a new one starts empty or as a copy. Words,
  person, type and Filter are for now and are not saved with a view.
- **D7** Export is not built in this cut. The scope marked it possible; its control is left out of the bar until the
  shape is agreed.
- **D8** Flags use My assessments' hint component and its admin on/off switches (Pierre, 2026-11-02). Set-up flags
  are neutral and labelled; count flags keep My assessments' tones. Anonymous marking comes from the index's
  `anonymous` field (HB3 in My assessments).
- **D9** Tracker columns (Pierre, 2026-11-04): each row carries the module code and name, the module lead, and the
  tracker's dates: submission deadline, marking deadline, marks due to students (My assessments' feedback-expected
  rule, shown to hubs whatever students see), and release.
- **D10** Send marks to hub (Pierre, 2026-11-04): fully marked work is not ready to release until the send step,
  where the activity has one, covers every unit that should be sent (not exempt, deferred or flagged). Work sent
  before it is marked is tagged and never counts as ready. Marks changed after sending or after release are tagged.
  For assign the send step is marking workflow's Ready for release (HB2 in My assessments); coursework's is not
  identified yet, so nothing is ever sent there and its fully marked work waits in Chase as Not sent to hub.
- **D11** Hub notes (Pierre, 2026-11-04): a dated log per assessment, newest first, with the author. A note can
  record that late marks were escalated. Hub staff only; notes are not edited after saving. A note outlives its
  author (privacy deletion makes the author anonymous and keeps the note) and goes with its activity when that is
  deleted.

## Presentation (P)

- **P1** One control bar: view, year, type, Find, Filter, then Refresh. No second row.
- **P2** Find replaces the Words and Person boxes. Typing offers people (staff the index knows as markers, students
  it knows as students) and the typed word as Only or Not. Chosen words are chips with a visible Only/Not toggle; a
  chosen person is a scope banner, because it widens the list to the whole institution. Word chips filter in the
  browser, on the row's name, module and reasons.
- **P3** Filter is one button holding the "Only with" list, grouped: Reason, Work, Allocation, Hub notes, Set-up.
  Active ones show as chips beside the button. Filtering runs in the browser, on the flags each row carries.
- **P4** One status strip of four count pills replaces the tiles. Group headers are slim and sticky.
- **P5** A collapsed row is one line of aligned columns (Pierre, 2026-11-05): type icon, Assessment, Module code,
  Reason tag, Status sentence, Progress (one stacked bar with its key figure), Flags (icon and count only) and a
  chevron, under one column header. Module name, module lead, full set-up and tracker dates are in the expanded
  row. At phone width the columns stack.
- **P6** The expanded row holds the tracker dates, the full set-up, the individual bars, People (names link to
  profiles, no email shown), Notes with "+ Note", and an "Ignore in <view>" menu with two items, This module and
  This assessment only, each needing a second click to confirm as My assessments' Delete mitigation does (Pierre,
  2026-11-05), with an Undo toast. The assessment name links to the activity and the module code to the course, so
  there is no Open in Moodle button. Ignored assessments are a new kind of line in a view, listed under Exceptions
  in the editor with Put back.
- **P7** The view editor opens as a side panel on wide screens and a full-screen sheet on phones; the list behind
  it updates as rules change. The menu is "This view"; the add button is "New view".
- **P8** Word filters start empty on every visit; nothing is saved, so nothing is pre-filled.
- **P9** Four groups by whose turn it is (Pierre, 2026-11-04): Ready to go (Release, Allocate), Chase (Marking
  late, Marking at risk, Not sent to hub, Sent before marked, Changed since sent), In progress (Upcoming, Marking on
  track), Done (Released, Changed since released). Every row carries its reasons as tags; the first reason in the
  fixed order leads, and a mark changed since release leads over "Released" so the header says what changed. The group header lists each reason with its count
  and clicking one filters to those rows. Chase runs late first then by days left; Ready to go and In progress by
  deadline; Done latest release first. A row with a Chase reason and a Ready reason sits in Chase; a released row
  stays in Done whatever else is tagged.
- **P10** Allocation window per view (Pierre, 2026-11-05): each view carries "flag unallocated work from N days
  before the submission deadline" (site default 30). Inside the window, unallocated work is Ready to go: Allocate;
  before it, it stays In progress and its unallocated count still shows as a flag and under Filter.

## Loading, categories, editor, Find, row and dark mode (beta3, `hub-beta3.feature`)

Pierre, 2026-11-16: "We need to timeout loading the dashboard as we have about 30,000 courses ... Ideally, we get
a partial load in a reasonable amount of time, or we load asynchronously and display things as they go"; valid
categories for the administrator; a search box in both category pickers; a bigger editor; "space is an 'and'
operator"; aligned columns; dark mode "in the same way that the Dashboard's 5.3 upgrade has done it"; and a calmer
row ("a mix of current version and mockup C"). Approved "Yes to all".

- **L1** The block renders its frame at once (control bar, group headers, strip) and the rows arrive by web service,
  so the Dashboard page is never held up by the hub. A view of 25 rows or fewer renders inline, because Behat without
  a browser, and a reader without JavaScript, must still see the rows; above that the page carries only the ids.
- **L2** Rows are fetched in batches of 25 ids, groups filled in as each batch lands, counts "…" until a group has
  its rows. The scope said one group at a time; a batch of ids is the same shape for the server and lets a slow
  view show its first rows sooner, since the group split is only known once every row is classified.
- **L3** A load budget in seconds (setting `loadseconds`, default 20). When the batches run over it the block says
  "Still loading: this view covers N assessments. Narrow it, or try again." and stops asking; Try again resumes. The
  server never cancels a query; the page just stops waiting.
- **L4** The department tree is not in the page: the editor is rendered by its first opening, through the same
  service as the rows, once per page.
- **L5** "N of M modules" is computed with the editor, not on every load.
- **L6** A view's category match is `co.category IN (...)` with the descendants resolved in PHP from the category
  tree (cached per request in MUC), not a LIKE on the category path, so the planner uses the index on
  `course.category`. Measured on a 30,000-course copy (NOTES.md): the match was not the slow part; the index
  aggregates are, and a summary table is a separate scope if still needed.
- **C1** The administrator sets the categories the hub works in (setting `categories`, a searchable tree; empty
  means every category). The department picker offers only those and their descendants; code rules, "add by
  code" and a person search match only courses under them (Q1, "Yes": the hub never shows work outside its
  categories); a view that names a course outside them keeps it and the editor marks it "outside the hub's
  categories". Saving keeps only ids that exist and stores the minimal cover.
- **C2** Both pickers filter as you type (300 ms, every word must match, case-insensitive) and show the ancestors of
  a match greyed so the path reads; ticked categories stay ticked while filtered.
- **C3** Typical checkbox tree: ticking a category ticks everything under it; unticking a child leaves its parent
  half-ticked. What is saved is the minimal cover (a fully ticked subtree saves its top category only), and the
  match rule (a category and everything under it) is unchanged, so existing views keep their meaning.
- **E1** The editor is a full-window dialog (`role="dialog"`, `aria-modal`, Escape closes) in three columns on a
  wide screen (Departments; Module codes and exceptions; This view) and stacked on a phone. The list behind it
  still updates as rules change.
- **F1** Space means AND wherever words are typed: the Find box, a word chip, a code rule line. "starts with"
  applies to the first word and "contains" to the rest; the SQL and PHP twins of a code line agree.
- **F2** Find narrows the list live as you type; Enter pins the words as a chip, and a leading "-" makes the chip
  an exclusion. People still come up as suggestions and a chosen person is still a scope banner (P2).
- **A1** One fixed track list for the column header and every row; only the name flexes, and the stacked bar has one
  width in every row. At the Dashboard's 782px the two-line layout stays, on fixed tracks of its own, so status,
  bar and flags line up down the list.
- **DM1** Dark mode as My assessments beta38 (the scope's D1; D1 here is access): the hub takes every colour from
  the `--ma-*` palette, which My assessments re-points under `[data-bs-theme="dark"]`, and sets only its own two
  choices per mode (bar segment tones, the hovered-row tint). No literal colour in the styles. CONTRAST.md
  records both modes and `tests/behat/dark-mode.feature` checks them.
- **R1** The row is a mix of the beta2 row and mockup C: the group header carries the group's colour as a tinted
  band and the rows are neutral; the reason, in bold, leads the status cell and there is no reason column; flags
  are one muted count that opens a list; the chevron shows on hover or focus (always where there is no pointer).
  Kept from beta2: the type icon, the stacked bar with its key number, the module code in a figure column. The
  expanded row gains "What has happened": deadlines, marking finished, release and notes in date order, in the
  viewer's time zone.

## Deviations noted at delivery

- The plugin is `block_le_hub_dashboard` (repository `moodle_block_le_hub_dashboard`), so the capability is
  `block/le_hub_dashboard:view`, not the scope's `block/le_hub:view`.
- Assign has no separate release time: the gradebook's release time is the grade's last change, so "Changed since
  released" can never show for assign until the index keeps the first release time. Coursework (first published)
  does carry it.
- The Dashboard is a limited-width page in Boost (`body.limitedwidth`, content 830px), so the block is 782px wide
  there at any window size: narrower than the one-line row of P5 needs. At that width the row is two lines (name,
  module, reason and flags, then status and progress) and the column header is hidden; the one-line row and header
  appear where the block is wider than 992px. Squeezing eight columns into 782px would wrap the status sentence, so
  the two-line form is kept there (to confirm with Pierre, 2026-11-05).
- Behat runs its browser against Moodle's own JavaScript waits: `core/ajax` does not register pending work in 4.5,
  so the hub's script registers its own (`core/pending`) around every service call and timer, or the steps that
  follow a click would read the page before the list had changed.
- beta3: rows come in batches of 25 ids rather than one group at a time (L2), and a view of 25 rows or fewer is
  rendered inline (L1). The budget message counts assessments, not modules, because that is what a batch holds.
  The Escape step in Behat is the hub's own, since Moodle 4.5 has no "I press the escape key" step. The two-line
  782px layout stays (A1). The `col_reason` string is no longer used by any template.
