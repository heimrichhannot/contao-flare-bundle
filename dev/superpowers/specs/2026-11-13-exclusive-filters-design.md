# Design: exclusive filters — a filter that narrows an output, not the whole list

Status: agreed in conversation 2026-11-13, awaiting the user's review. Its own PR on `v0.2`, before the query
architecture refactor (`2026-11-04-query-architecture-design.md`), which assumes it (its D40). Independent of the
follow-up to PR #54. Branch name and order relative to that follow-up — Claude's call.

## Intent

A list view and its reader do not always show the same entries. The common case is a list of events: the list view
shows what is coming, the reader still finds an event that has passed (as Contao's own event reader does). Formally
these are **two lists** (user): the entries of the one are bounded by a period, the entries of the other are not.

Today exactly one filter element produces that difference, and it does so by asking for the intent:
`Filter/Element/CalendarCurrentFilterElement::buildEffect()` drops its configured window when
`$context->intent instanceof ValidationIntent` and `is_limited` is off ("unlimited defaults only shape the initial
display"). It is the only reader of an intent among all elements and controls. The user's judgement: that is an error
in the element's setup, and it contradicts the rule agreed for the query design — **the list defines its entries, the
intent only order, section and embedding.** The element also mixes two purposes behind its `is_limited` checkbox: a
hard bound of the period entries must lie in, and a preselection the visitor can move with a control.

What the user wants instead:

- The difference between the two lists is **configured on the filter**, for every filter type, as an expert setting:
  a filter applies either to the whole list, or exclusively to the outputs the editor ticks.
- No element and no control sees the intent.
- New outputs must be right without touching existing filters: a sitemap (a later PR) is generated from the whole
  list — what the reader sees —, not from what the list view shows.

## Goals

1. An expert setting on every filter row: **apply exclusively** (off by default), and with it a list of **outputs**
   to tick. Today's only output is the list view; sitemap and export follow in later PRs.
2. Two lists from one configuration: the **whole list** (the filters that are not exclusive) and the list of an
   output (those plus the filters exclusive to it). The reader and the lookup run on the whole list, the list view on
   its output's list, its count with it.
3. `flare_calendar_current` with one rule and without `is_limited`: the configured period is always a bound of that
   filter, the stored value is the preselection.
4. `ElementContext` and `ControlContext` without an intent.
5. Existing lists behave as before; the exceptions are named under Migration.

## Non-goals

- The sitemap and export outputs themselves. Only the storage and the semantics are prepared: a new output is a new
  option, existing rows need no migration.
- Outputs registered by userland.
- Per-content-element filters, derived lists, or any other way to have several list views of one list differ (the
  approaches considered below).
- Changing how a control's value is treated: it stays a matter of the list view (the visitor's values and the
  initial value never reach the reader).
- The query assembly (`ListQueryDirector`, loaders): the query refactor's business. This PR only changes which list
  they are handed.

## Anti-goals

- An output that can show an entry the reader does not find. The reader is the whole list; every output is a subset.
  So "reader" is no option to tick, and neither is the lookup (it follows the reader) nor the count (it follows the
  list view).
- An element, control or framework step that decides by the intent's type which filters or conditions apply.
- A new output that silently inherits list-view filters (the reason for "exclusive" instead of "do not apply in").

## Constraints

- Project conventions (`AGENTS.md`): vocabulary *Compound / Effect / Context / Intent*; the user's naming taste (short,
  sober nouns); the bundle catches `\Exception`, not `\Throwable`; programming errors are `\Error`s.
- Migrations never delete or drop; they update values only; SQL for MySQL and MariaDB.
- Testable through public API. `v0.2` is unreleased: breaking changes are fine, no BC shims.
- Backend fields via `mvo/contao-group-widget` are already in use, but a plain checkbox list suffices here.

## Approaches

Every list-view entry must be findable in the reader, so the list view's list is always the reader's list plus
narrowing filters. The approaches differ in where those filters live.

| Approach | Trade-off |
|---|---|
| **Filter with exclusive outputs** (chosen) — one list configuration, each filter row says whether it bounds the whole list or only the ticked outputs | Smallest change, works for every filter type. Two list views of one list that differ still need two configurations or a control |
| A derived list — "current events" refers to "all events" as its base and adds filters | Literally two lists, several views possible. Largest change: inherited filters in the backend, the inspector, two records to maintain |
| The content element narrows — the list describes the whole set, the list-view element adds its own filters | One list serves several views. The rich filter backend of `tl_flare_filter` would have to exist on `tl_content` |

Rejected early: two independent lists (nothing guarantees the reader finds what the view shows; shared filters
drift), a reader that hides filters (the list view's lookup does not know the reader element's setting), a per-filter
switch per intent (of four intents one choice is meaningful, two break "count = entries" and "list ⊆ reader"), and
"do not apply in: reader" (a later sitemap would inherit every list-view filter).

The user chose the first, in the form "apply exclusively" + outputs.

## Design

### 1. The setting

Two fields on `tl_flare_filter`, in the palette suffix under the expert legend, for every filter type:

- a checkbox (working name `exclusive`; label "Apply exclusively" / „Exklusiv anwenden“), off by default, opening
- a checkbox list (working name `outputs`) of the outputs. Today one option: the list view. The field stores the
  ticked keys as a list, so later options need no migration.

Meaning:

| Stored | The filter applies |
|---|---|
| not exclusive | everywhere: it bounds the whole list |
| exclusive, outputs ticked | only in those outputs; not in the reader, the lookup or any other output |
| exclusive, nothing ticked | nowhere |

The outputs are a closed set in code (working name `Output`, a backed enum; today `ListView`). Reader, lookup and
count are no outputs.

### 2. Two lists from one compound

`FilterCompound` carries the setting (working name `$outputs`: `null` = not exclusive, else the list of outputs); it
is part of `fingerprint()`. `Contao/Factory/FilterCompoundFactory::create(FilterModel)` reads it; a filter created in
code (`Filter/Factory/FilterCompoundFactory`, engine mods' `withFilter()`) is not exclusive unless it says so.

`ListCompound` keeps every published filter, as today, and derives (working names):

- `whole()` — the list without its exclusive filters;
- `forOutput(Output $output)` — the list without the filters exclusive to other outputs.

Both return a `ListCompound` and keep everything else: config, catalog (query design), `$failures`. A list with
failures is not queried in any output, also when the failed filter is exclusive to another one (Claude's call: fail
closed, one rule). The duplicate-alias check stays over all published filters.

Who asks for which list — the entry points, not the intent:

| Entry point | List |
|---|---|
| `ListViewController` (entries, count, form) | `forOutput(Output::ListView)` |
| the list view's lookup (`InteractiveProjector` → `EntryCollectorFactory::createLookup()`) | `whole()` of the list it runs on, engine mods included |
| `Contao/Reader/ReaderResolver` (reader, breadcrumb; `ReaderResolution::$list`) | `whole()` |
| backend: `FilterInspector`, option callbacks, `ElementDcaHook` | the full compound, as today |
| a list handed to `FlareRuntime::project()` from Twig or code | as given |

A control of a filter that is not part of the list view's list is not mounted (the form harness works on that list).

### 3. The inspector

`Contao/Inspection/Inspector/FilterInspector` reports a filter that is exclusive without an output as *ineffective*
(`FilterIssueKind::Ineffective`), with its own translated reason — a framework check next to the element's
`IneffectiveFilterContract`. Everything else is inspected as today, for every saved row.

### 4. `flare_calendar_current`

One rule, for every list the filter is part of (user's decision):

- **The configured period is a bound.** `start_at` applies where `configure_start` is set, `stop_at` where
  `configure_stop` is set; an unconfigured side is open. Today `buildEffect()` ignores `configure_*` and applies any
  non-empty `start_at`/`stop_at` — also a leftover of a side the editor switched off (the save callback does not
  clear it, the subpalette hides it); only `getDateBounds()` honors the switch. The migration (§6) keeps such
  leftovers bounding.
- **Every stored form of a side is read.** The sides are read with `DateTimeHelper::toTimestamp()`, like the value
  provider does: a time-span key, a `strtotime()` string or a timestamp. Today both `buildEffect()` and
  `getDateBounds()` use `strtotime()`, which does not read the bare timestamp the `date` mode stores (`rgxp =>
  datim`): an absolute date is not applied, or lands far in the future and empties the list. That is fixed here
  (Claude's call after the builder check).
- **The value narrows within it.** A `from` earlier than the start or a `to` later than the stop is ignored on that
  side — today's `is_limited` behavior, always.
- **The preselection is the filter's stored value** — the control's initial value (`hydrate()`), with relative
  expressions such as `today` (`DateRangeValueProvider`). The visitor moves it freely within the bound.
- `getDateBounds()` returns the configured period, so the date inputs are limited whenever a bound is configured.
- `is_limited` goes: from the schema, the transformer, the palette and the translations. The column stays in the
  database.

The two purposes are then two settings, and where both are wanted with different periods, two filter rows of the same
type:

| Wanted | Filter rows |
|---|---|
| a hard bound | one, not exclusive |
| the list shows what is coming, the reader finds everything | one, exclusive to the list view |
| a hard bound and a narrower section in the list view | two: one not exclusive, one exclusive |
| a preselection the visitor can move | one with a control; its stored value is the preselection |

### 5. Contexts without an intent

`ElementContext` and `ControlContext` lose `$intent` (list × filter remains). Call sites: `ListQueryDirector`,
`Form/Factory/FormHarnessFactory`, `FilterInspector`, `Contao/DataContainer/BackendElementContextFactory`,
`ControlContext::forElement()`. `ConfigurationIntent` goes if nothing else needs it (builder's call). The form
harness itself keeps its intent (form name, action).

### 6. Migration

A run-once migration for the rows of type `flare_calendar_current`, so that visitors see what they saw. Today's
behavior and its replacement:

| `is_limited` | Control | Today | After |
|---|---|---|---|
| on | with or without | the period bounds list view and reader; a value narrows within | unchanged row, not exclusive |
| off | none, no stored value | the period bounds the list view only | exclusive to the list view, period unchanged |
| off | with a control | the period is a default the visitor's value replaces side by side; the reader is unbounded | exclusive to the list view; the period moves into the stored value (a side the stored value already sets wins), and `configure_start`/`configure_stop` are switched off so no bound remains |
| off | none, with a stored value | list view: the value replaces the period side by side; reader: the value alone bounds | left not exclusive: the value was a bound of the reader and must stay one (Claude's call: never widen the whole list) |

Before that, for every row of the type: a side whose `configure_*` is blank but whose `start_at`/`stop_at` holds a
non-numeric expression gets `configure_*` switched on (`str`), so the backend shows the bound that applies today and
the new rule keeps applying it. A numeric leftover of a blank side stays switched off: it did not bound.

Accepted changes, named in the migration notes:

- a side in `date` mode (an absolute date) starts to bound as configured — the fix of §4. It narrows the lists that
  have one, the "unchanged" rows included; in the third row the date becomes the working preselection;

- off + control: the form now shows the preselection in its date inputs (before, the default window applied
  invisibly), and a side the visitor empties is open instead of falling back to the default;
- off + no control + stored value: the configured period now bounds list view and reader too — the list can only
  become narrower. The inspector does not flag this; the notes tell the editor to split the filter in two.

The migration must run exactly once per row and never touch a row an editor saved after the update; `is_limited`
stays in the database and cannot serve as the marker once editors save rows. The marker follows the pattern the
query design fixes for its own migration (a DCA field with default `'0'`, set on every backend save and by the
migration) or `BackfillFilterValuesMigration`'s — builder's call, the property is what counts.

### 7. Documentation

`AGENTS.md`: the filter subsystem (`FilterCompound`, `ElementContext`/`ControlContext` "list × filter", the reader's
rules now "the whole list"), the migration list. The docs worktree (`docs/`, branch `docs/main`, committed
separately): the filter pages (the expert setting, the calendar filter), `templating.mdx` (the lookup and the reader
address the whole list), `removed-in-v0.2.md` (`is_limited`, the contexts' `$intent`), `migrating-from-v0.1.md` (the
accepted changes). Translations in `contao/languages/{de,en}/tl_flare_filter.php` and the `flare` domain.

## Decisions

| # | Decision | By |
|---|---|---|
| E1 | The list view and the reader are two lists; the difference is configured per filter, not decided by the intent | user |
| E2 | Expert setting "apply exclusively" + ticked outputs in the palette suffix of every filter; off by default | user |
| E3 | Outputs are subsets of the whole list: list view now, sitemap and export later. Reader, lookup and count are no options | user (proposed by Claude) |
| E4 | Exclusive instead of "do not apply in", so a new output gets the whole list without touching existing filters | user |
| E5 | The setting is stored as a list of output keys; exclusive without an output applies nowhere and is reported as ineffective | Claude |
| E6 | The entry points ask the compound for their list (`whole()`, `forOutput()`); no step looks at the intent | Claude |
| E7 | One element type `flare_calendar_current`; configured period = bound, stored value = preselection; `is_limited` goes | user |
| E8 | `ElementContext` and `ControlContext` lose the intent | user |
| E9 | Failures fail the list in every output | Claude |
| E10 | Migration never widens the whole list; the two accepted changes above | Claude |
| E11 | Own PR on `v0.2` before the query refactor | Claude |
| E12 | Absolute dates of the configured period are read (today they are not applied); leftover bounds of switched-off sides are made visible by the migration instead of dropped | Claude (after the builder check) |

## Left to the builder

Final names (`exclusive`, `outputs`, `Output`, `whole()`, `forOutput()` are working names), the storage format of
the output list, where the enum lives, the marker of the migration, whether `ConfigurationIntent` goes, how the
inspector words the new reason, the order of commits.

## Testing

- `FilterCompound`: the setting, its part in `fingerprint()`; `ListCompound::whole()` / `forOutput()` (exclusive
  filters dropped or kept, failures and config kept, filters added by `withFilter()` not exclusive).
- `Contao/Factory/FilterCompoundFactory` and `FilterCollector`: the stored fields, exclusive without outputs.
- Through the SQLite pipeline of PR #54 (`tests/Engine/EnginePipelineTrait.php`): a list with a filter exclusive to
  the list view — entries and count apply it, the lookup and the reader find an entry it excludes, the breadcrumb
  too; a control of a filter outside the list view's list is not mounted.
- `CalendarCurrentFilterElement`: bound and value under the one rule, unconfigured sides open, a side as time-span
  key, expression and bare timestamp, the same conditions whatever the caller; `getDateBounds()`.
- The migration: the four rows of the table above, a leftover side switched on, a numeric leftover left off, run
  once, a row saved in the backend afterwards left alone.
- `FilterInspector`: exclusive without output is ineffective.
- Existing tests follow (the contexts' constructors in 26 places across `src/` and `tests/`); `make test` and
  `make phpstan` green; Mago runs in CI only.
