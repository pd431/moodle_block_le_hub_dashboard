# Decisions

The reasons behind the Hub dashboard's rules, under stable keys that code comments cite. The keys come from the two
approved scopes: `hub-view.feature` (D1 to D11, the behaviour) and `hub-view-simplified.feature` (P1 to P10, the
presentation), both in the project's `hub-dashboard` folder. My assessments' own decisions (its DECISIONS.md, the HB
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
