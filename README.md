# Hub dashboard (block_le_hub_dashboard)

A Dashboard block for education support (hub) staff: every assessment in the modules they look after, grouped by
whose turn it is (Ready to go, Chase, In progress, Done), each tagged with why it is there.

It reads the index that [My assessments (block_le_dashboard)](https://github.com/pd431/moodle_block_le_dashboard)
keeps, so it needs that block installed and indexing.

## Requirements

- Moodle 4.5 LTS to 5.2.
- block_le_dashboard 5.0.0-beta35 or later, configured with its summative and formative tags.

## Install

1. Unzip into `blocks/le_hub_dashboard` and visit Site administration > Notifications.
2. Give the education support role the capability `block/le_hub_dashboard:view`, at system level or on the course
   categories the team covers.
3. Add the Hub dashboard block to the default Dashboard (Site administration > Appearance > Default Dashboard page)
   and reset dashboards, or let hub staff add it themselves. People without the capability never see it.
4. Optional settings (Plugins > Blocks > Hub dashboard): the categories the hub works in (empty means every
   category; archived categories are usually left out), the marking-at-risk window (default 5 days), the default
   allocation window for new views (default 30 days) and the load budget in seconds (default 20).

## Using it

- **Views** are personal. Pick departments and module-code lines (starts with, contains), add modules by code, and
  ignore a module or a single assessment from its row. A view also says how many days before the submission
  deadline unallocated work becomes the hub's job.
- **Find** narrows the list as you type; a space between words means both must match. Enter pins the words as a
  chip, and a leading "-" excludes them. People (staff and students) come up as suggestions; a person widens the
  list to the whole institution and shows their part in each assessment.
- On a large site the block shows its frame at once and fills the rows in; a view that takes longer than the load
  budget says so and offers Try again.
- **Filter** narrows to rows carrying a reason or a flag.
- Each row opens for its dates, set-up, individual bars, people, hub notes and the Ignore menu.

## Accessibility

Font faces and sizes are the theme's. Colours reuse My assessments' palette and follow Moodle 5.3's dark colour
mode with it; CONTRAST.md lists the main pairs in both modes with their WCAG ratio and APCA lightness contrast, and
the Behat feature `dark-mode.feature` checks every element.

## Documentation

DECISIONS.md (why), ARCHITECTURE.md (where each rule lives), CONTRAST.md (colour).

## Releasing

Work goes on a branch, where CI runs on every push (it installs My assessments from its `main`). When CI is green,
the branch is pushed to `main`; nothing goes to `main` red. Each build on `main` is tagged `v<release>` from
`version.php` by `.github/workflows/tag.yml`, which never moves a tag: a broken tagged build gets a new beta.
