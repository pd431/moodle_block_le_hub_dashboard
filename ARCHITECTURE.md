# block_le_hub_dashboard: architecture and how to add to it

The Hub dashboard is a second reader of My assessments' index. It owns no activity knowledge: everything it knows
about an assignment or a coursework comes from `block_le_dashboard_cm`, `_stu` and `_mark`, written by My
assessments' adapters. If the hub needs a fact the index lacks, the change is in My assessments (an index field and
its adapters, as HB1 to HB3 did there), never a read of an activity's tables here.

## The shape in one paragraph

`local/dashboard` decides what is shown (a person, or a view in a year), asks `local/query` for one row of counts
per assessment from the index, asks `local/rules` which group and reasons each row gets, and hands each row to
`local/row` to build. Views, notes, people and access are small modules with one job. The templates render what
`row` and `dashboard` export; the JS module filters, expands and calls the web services, which all render the same
`view` template back.

```
block_le_dashboard_cm / _stu / _mark        (My assessments' index; read only here)
        │
        ▼
classes/local/query        one aggregate per assessment: counts, send step, early, changed (SQL)
classes/local/rules        group, reasons, order (pure PHP) + the unit rules in SQL and PHP twins
classes/local/views        saved views: shape SQL, PHP twin, life cycle, exceptions
classes/local/people       Find, a person's scope, convenors, markers
classes/local/notes        hub notes
        │
        ▼
classes/local/row          one row builder: status, progress, flags, dates, people, notes, Ignore menu
classes/local/dashboard    what is shown, groups, strip, filters, editor
classes/output/dashboard   the block's renderable
classes/external/*         get_view, refresh, save_view, ignore, add_note, find (all through external/base)
        │
        ▼
templates/dashboard, view, group, row, editor      scss/styles.scss      amd/src/hub.js
```

## Who owns what

| Concern | Owner | Guarded by |
|---|---|---|
| Who may open the hub (D1) | `local/access.php` | `access_test` |
| Which assessments a view holds (D3, D6, P5, P6) | `local/views.php` (`courses_sql`, `in_view_sql`, `course_in_view`, `cm_in_view`) | `views_test` (SQL and PHP agree for every kind of line) |
| Counts per assessment from the index | `local/query.php` | `rules/groups_test`, `rules/rows_test` |
| Group, reasons and order (D4, D10, P9, P10) | `local/rules.php` (`classify`, `sort_key`) | `rules_test` (situation table, reason order) |
| Sent before marked, changed since sent, changed since released (D10) | `local/rules.php` (`is_*` with `*_SQL` twins) | `rules_test::test_sql_and_php_forms_agree` |
| What a row says and carries | `local/row.php` | `rules/rows_test` |
| What a page shows: person or view, year, groups, strip, Filter list, editor | `local/dashboard.php` | `rules/*`, `external_test` |
| Find, a person's scope and status, convenors, markers | `local/people.php` | `external_test::test_find`, `rules/rows_test` |
| Hub notes (D11) | `local/notes.php`, `observer.php` | `external_test::test_add_note`, `privacy/provider_test` |
| Settings and preferences | `local/config.php` | |
| Privacy | `privacy/provider.php` | `privacy/provider_test` |
| Colours and contrast | `scss/styles.scss` maps on My assessments' `--ma-*` palette | CONTRAST.md |
| Decision keys in comments | DECISIONS.md | `decisions_test` |

Shared with My assessments and not restated here: dates, bars, hints and popovers (`block_le_dashboard\local\output
\format`, templates `hint`, `progress`, `datedtext`, JS `tips`, `localtime`), marking rules
(`block_le_dashboard\local\view\marking_rules`: expected, counted, ready, marker), academic years and the year scope,
the index's freshness and refresh.

## Tests

- `tests/rules_test.php`, `views_test.php`: the pure rules and the view shape, no fixtures.
- `tests/rules/*_test.php`: the scope's scenarios run as PHPUnit on rendered HTML, through `hub_testcase`, which
  extends My assessments' `view_testcase` and resolves the same sentences as the Behat context. A scenario that
  needs a module that is not installed (coursework) skips.
- `tests/behat/*.feature`: the same scenarios in a browser. Steps that write go through
  `block_le_hub_dashboard_sentences` in `tests/generator/lib.php`, so Behat and PHPUnit write identically.
- `access_test`, `external_test`, `privacy/provider_test`, `decisions_test`.

## Adding a reason

1. Add it to `rules::REASONS` in group order, and to `classify()`.
2. If it reads the index, add the count to `query::aggregates()`; if it is a per-unit rule, add the `is_*` method
   and its `*_SQL` twin side by side and extend `rules_test::test_sql_and_php_forms_agree`.
3. Add `reason_<key>` to the language file and a tone in `$hub-reason-tones`.
4. Add a row to `rules_test::situations()` and to the group outline in `tests/behat/groups.feature`.
