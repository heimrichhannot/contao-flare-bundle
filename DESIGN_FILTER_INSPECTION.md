# Design: one inspector for every filter issue in the backend

Status: agreed in conversation 2026-10-28, builder-checked, awaiting the user's review. Branch `v0.2-refactor-filter-api` (unreleased;
breaking changes are fine). Builds on `DESIGN_FILTER_FAILURES.md` (implemented, commits c3b9b17..9da2d4b).

## Intent

The backend's filter diagnostics grew in three places — `IneffectiveFilterInspector`, `BrokenFilterInspector` and the
duplicate-alias check in `ElementDcaListener` (handing its result to the row template through
`$GLOBALS['FLARE']['duplicate_filter_aliases']`) — and they disagree:

- A published and an unpublished filter share an alias: nothing is shown, although publishing both breaks the list.
- Two published filters share an alias: both rows show the skull, the later one also the bone-fracture icon, and the
  overview shows two error messages with different texts for the same cause.
- A filter whose code throws an `\Error` makes the list's whole filter backend answer 500 (the editor cannot even
  unpublish or delete it).

One inspector replaces all three. It inspects every saved filter of a list — published or not — and reports each
problem exactly once, as one **issue** of one **kind**, the same way on the row, in the overview messages and on the
edit form.

## Issue kinds

| Kind | Raised when | Row icon (Lucide) | Overview message (published only) | Severity |
|---|---|---|---|---|
| **Programming error** | building or checking the filter throws an `\Error` (only outside debug mode, see below) | `bug`, red `#dc3545` | "A programming error was detected; an administrator may need to be contacted." | error (red) |
| **Duplicate alias** | see "Duplicate aliases" | `copy-x`, red `#dc3545` (replaces the skull) | "A duplicated filter alias is blocking the list." | error (red) |
| **Broken** | the filter cannot be created, or its conditions cannot be built with its default value — any `\Exception` (as today) | `bone-fracture`, red `#dc3545` | "A filter's configuration is breaking the list." (today's `list.info.broken_filters`) | error (red) |
| **Ineffective** | the element's `IneffectiveFilterContract::getIneffectiveReason()` returns a reason (as today) | `engine`, `#e0a800` | "A filter is ineffective and needs attention." (today's `list.info.ineffective_filters`) | `Message::addNew` (orange) |

- A row may carry several kinds at once (e.g. duplicate and ineffective); each shows its own icon, side by side, no
  precedence logic (as today).
- Sharing an alias never produces a broken issue by itself: a duplicate is reported as a duplicate only, not as
  broken, and is not counted in the broken message. A row that is also broken for its own reason (e.g. an unknown
  type) carries both issues.
- The overview messages appear in the table's order, each only when its count is non-zero; each states its count
  (pluralized like today's messages). The duplicate message also names the clashing aliases, like today's.

## Published vs. unpublished (user's decision)

- **Every saved filter row** of the list (`tstamp > 0`) is inspected, published or not, and gets the same icon for
  the same issue. On an unpublished row the icon is muted — the existing `--flare-filter-notice-opacity` CSS
  (`public/backend/styles.css`), unchanged.
- An unpublished row's issues **never** produce or count toward an overview message. All four overview messages count
  published filters only. (Change from today: the ineffective count included unpublished rows.)
- Issues of filters without a row — added by the list driver (`buildList()`) or a `ListDriverBuildListEvent`
  listener — are active in the list, so they count toward the overview messages; there is no row to mark.

## Duplicate aliases (user's decision)

Rows are grouped by their runtime alias — the alias of the built `Filter`, after `FilterCollectedEvent` (what the
collector compares), falling back to `FilterModel::generateFormName($row)` for a row that cannot be built. Per group
of two or more rows:

- **Two or more published rows:** the list is blocked. **Every** row of the group gets the duplicate icon — published
  ones at full opacity, unpublished ones muted — and the overview shows the duplicate message.
- **Exactly one published row:** the list is not blocked. **Only the unpublished rows** of the group get the
  duplicate icon (muted); the published row gets nothing; no overview message.
- **No published row:** nothing is flagged — no row of the group gets the icon, no overview message (user's
  decision).

The runtime behaviour is unchanged: `ListModelFilterCollector` still records a `FilterFailure` for each later
published duplicate and the list fails.

## Programming errors (`\Error`)

- **Outside debug mode** (`%kernel.debug%` false): the inspector catches an `\Error` from anything it runs for a filter
  — building it (`FilterFactory::createFromFilterModel()`, `FilterCollectedEvent` listeners), building its conditions
  (`FilterInvoker::invoke()`), asking for its ineffective reason — marks the row as a programming error with the
  error's message as reason, and logs it as **critical** (with the throwable in the context).
- **In debug mode** the `\Error` escapes as today (developers see the trace).
- The runtime is unchanged: an `\Error` still fails the frontend request; only the backend inspection catches it.
- If the list scope cannot be built because of an `\Error` (e.g. the driver's `buildList()`), outside debug mode the
  overview shows the programming-error message (count 1) without a row mark. A list-level `\Exception` there adds no
  message of its own (as today; the list driver's backend messages cover list configuration).
- Either way, the per-row issues that do not need the list scope are still reported: building each row, duplicate
  grouping, the ineffective reason, and `\Error`s from these. Only the conditions check needs the scope and is skipped
  without it.
- Note: the transformer resolvers already wrap any `\Throwable` (so an `\Error` in a transformer arrives as a
  `FilterException` and is reported as broken). That stays.

## Edit form

On `act=edit` of a filter, each of the row's issues is shown at the top of the form, in its kind's severity
(`Message::addError` for programming error, duplicate and broken; `Message::addNew` for ineffective), with the row's
reason. Unpublished rows use "once published" wordings. Today's per-kind code in `ElementDcaListener` is replaced by
one loop over the row's issues.

## Request gating

All messages (overview and edit form) are added on GET requests only — the rule `ElementDcaListener::
reportsFilterStates()` introduced in 9da2d4b, extended to the duplicate message (today's `checkDuplicateFilterAliases()`
is not gated and can flash a stale message across the save redirect).

## Shape (names are the builder's call)

- `src/DataContainer/FilterInspector` (`@internal`, shared service, `ResetInterface`, memoized per list id for the
  request): `inspect(ListModel): ListInspection`, plus a testable core that takes the list scope and the rows (like
  today's `BrokenFilterInspector::diagnose()`). Injects `%kernel.debug%`.
- `ListInspection`: the issues per row id (`forRow(int): list<FilterIssue>`) and the overview counts per kind
  (published rows + filters without a row), plus the clashing aliases for the duplicate message.
- `FilterIssue`: kind (an enum `FilterIssueKind` with the four cases), translated-or-raw reason, the row's published
  state.
- How it gets its rows and list scope:
  1. Build **every** row itself, like the collector does (`FilterFactory::createFromFilterModel()` +
     `FilterCollectedEvent`, unpublished rows included), catching `\Exception` (broken) and, outside debug mode,
     `\Error` (programming error) per row. Group the aliases (duplicates). This needs no list scope.
  2. Build the list scope **the way the runtime does**: a `ListSpecBuilder` for the list model with the published,
     built, non-duplicate rows added (in sorting order, under their aliases) **before** `build()`, so the driver's
     `buildList()` and `ListDriverBuildListEvent` listeners see the same filters as at runtime (e.g. the News/Events
     drivers add their own published filter only if no row provides one). Exceptions/errors here are list-level
     (see "Programming errors").
  3. Check the conditions (and the ineffective reason) of every filter of that spec — rows and filters added by the
     driver/listeners — and of the unpublished rows, with the spec as their list.
  A row filter that a listener removed or replaced is checked as it ends up in the spec; a row whose alias clashes
  with a filter added by the driver or a listener makes `build()` throw, a list-level failure (see above). Builder's
  call if a cleaner seam exists (e.g. a collector method that takes the built filters).
- The checks per built filter are today's: conditions with the default value (fixed value without a control, `null`
  with one) through `FilterInvoker`, `AbortFilteringException` is no issue; then the ineffective reason.

**Removed:** `IneffectiveFilterInspector`, `BrokenFilterInspector`, `FilterDiagnosis`,
`ElementDcaListener::checkDuplicateFilterAliases()` / `reportFilterStates()` / `reportFilterState()` in their current
form, `$GLOBALS['FLARE']['duplicate_filter_aliases']`, the skull block and the duplicate logic in
`templates/backend/filter_child_record/_base.html.twig` and `ListCallbacks`. `FilterModel::findDuplicateFormNames()`
may go if nothing else uses it. `IneffectiveFilterContract` stays (public).

## Display

- **Row** (`ListCallbacks` → template): one `{% if %}` block per kind, each a `<span>` with the kind's colour and
  `opacity: var(--flare-filter-notice-opacity)`, the SVG at `1rem`, and the issue's text as `<title>`. Icons
  (lucide-static 1.62.0):
  - `copy-x`:
    ```
    <path d="M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2"/>
    <rect x="8" y="8" width="14" height="14" rx="2"/>
    <path d="m12.5 12.5 5 5"/>
    <path d="m12.5 17.5 5-5"/>
    ```
  - `bug`:
    ```
    <path d="M12 20v-9"/>
    <path d="M14 7a4 4 0 0 1 4 4v3a6 6 0 0 1-12 0v-3a4 4 0 0 1 4-4z"/>
    <path d="M14.12 3.88 16 2"/>
    <path d="M21 21a4 4 0 0 0-3.81-4"/>
    <path d="M21 5a4 4 0 0 1-3.55 3.97"/>
    <path d="M22 13h-4"/>
    <path d="M3 21a4 4 0 0 1 3.81-4"/>
    <path d="M3 5a4 4 0 0 0 3.55 3.97"/>
    <path d="M6 13H2"/>
    <path d="m8 2 1.88 1.88"/>
    <path d="M9 7.13V6a3 3 0 1 1 6 0v1.13"/>
    ```
  - `bone-fracture` and `engine`: as in the template today.
- Escape the reason wherever it reaches `Message::add*` (`StringUtil::specialchars`), since Contao renders messages
  raw; Twig autoescapes the `<title>`.

## Translations (`flare` domain; en exact, de as given)

Overview (`list.info.*`, pluralized with `%count%`):

- `programming_errors` (says nothing about whether the list displays: an `\Error` in the ineffective check does not
  affect the frontend) — en: `"{1} A programming error was detected in a filter. An administrator may need to be contacted.|]1,Inf[ Programming errors were detected in %count% filters. An administrator may need to be contacted."` — de: `"{1} In einem Filter wurde ein Programmierfehler festgestellt. Wenden Sie sich gegebenenfalls an einen Administrator.|]1,Inf[ In %count% Filtern wurden Programmierfehler festgestellt. Wenden Sie sich gegebenenfalls an einen Administrator."`
- `duplicate_filter_alias` (reworded; `%alias%` = the quoted, comma-separated aliases) — en: `"A duplicated filter alias is blocking the list: %alias%. Give each published filter a unique alias."` — de: `"Ein doppelter Filter-Alias blockiert die Liste: %alias%. Vergeben Sie jedem veröffentlichten Filter einen eindeutigen Alias."`
- `broken_filters`, `ineffective_filters`: unchanged. (Their "marked below" may not hold for a filter without a row;
  accepted.)

Row tooltip and edit form (`filter.info.*`, `%reason%` / `%alias%`):

- `programming_error.published` — en: `"A programming error occurred in this filter; an administrator may need to be contacted: %reason%"` — de: `"In diesem Filter ist ein Programmierfehler aufgetreten; wenden Sie sich gegebenenfalls an einen Administrator: %reason%"`
- `programming_error.unpublished` — en: `"A programming error occurred in this unpublished filter; an administrator may need to be contacted: %reason%"` — de: `"In diesem unveröffentlichten Filter ist ein Programmierfehler aufgetreten; wenden Sie sich gegebenenfalls an einen Administrator: %reason%"`
- `duplicate_alias.published` (replaces today's string key `filter.info.duplicate_alias`) — en: `"The alias \"%alias%\" is also used by another published filter, which blocks the list."` — de: `"Der Alias \"%alias%\" wird auch von einem anderen veröffentlichten Filter verwendet; das blockiert die Liste."`
- `duplicate_alias.unpublished` — en: `"Once published, this filter's alias \"%alias%\" would clash with another filter and block the list."` — de: `"Veröffentlicht würde der Alias \"%alias%\" dieses Filters mit einem anderen Filter kollidieren und die Liste blockieren."`
- `broken.*`, `ineffective.*`: unchanged.

## Docs

- `AGENTS.md` (the inspector paragraph under `src/Contract/FilterElement/`), `SPEC_FILTER_FORMS.md` §8.1 backend
  paragraph.
- `docs/` worktree (separate commit on `docs/main`): `spec/filtering.md` "Backend" paragraph, `dev/filter-elements/
  index.md` (the marks paragraph: four kinds, `\Error` caught outside debug mode in the backend only),
  `migrating-from-v0.1.md` line on backend marks.

## Non-goals

- Changing runtime failure handling (collector, director, projectors, reader).
- Catching `\Error` anywhere but the backend inspection.
- Translating framework failure messages.
- `composePrefix` rework and the targeting question (postponed).

## Calls made on the user's behalf (check while reading)

- Overview messages in the order programming error, duplicate, broken, ineffective; each with a count; the duplicate
  message still names the aliases.
- Driver/listener-added filters (no row) count toward the overview messages.
- A list-level `\Error` while building the list scope shows the programming-error message without a row mark; per-row
  issues that need no scope (build, duplicates, ineffective) are still reported when the scope fails.
- The inspection's list scope mirrors the runtime (published, built, non-duplicate rows added before `build()`), so
  driver and listener behaviour matches; a row-vs-driver alias clash surfaces as a list-level failure.
- The programming-error texts don't claim the list cannot be displayed (an `\Error` in the ineffective check doesn't
  affect the frontend); this follows your wording "a programming error has been detected, an admin might need to be
  contacted".
- `\Error`s are logged as critical.
- Edit form: one message per issue, kind's severity, "once published" wording for unpublished rows.
- Reasons escaped before `Message::add*`.
- The inspector builds the list scope without the collector and builds every row itself (dispatching
  `FilterCollectedEvent` for unpublished rows too), so `\Error`s are attributable to rows.
- New translation texts as above (en/de).

## Constraints

- Catch `\Exception`, never `\Throwable`, except this inspector's documented `\Error` catch outside debug mode
  (and the transformer resolvers, as today).
- PHP 8.2, Contao 4.13 + 5.x, PHPUnit 9 (`make test`), PHPStan level 5 (`make phpstan`), `mago lint` clean (≤ 20
  methods per class).
- No `Co-Authored-By` trailer, never push; docs committed separately on `docs/main`. Keep §/SPEC references.
