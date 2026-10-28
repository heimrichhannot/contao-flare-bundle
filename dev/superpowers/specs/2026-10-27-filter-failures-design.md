# Design: unresolvable controls fail closed, broken filters shown in the backend

Status: agreed in conversation 2026-10-27, builder-checked, awaiting the user's review. Branch `v0.2-refactor-filter-api` (unreleased;
breaking changes are fine).

## Intent

Two related changes, one principle: **a filter the editor configured but FLARE cannot honour must never degrade
silently — the list fails, and the backend says which filter is to blame.**

1. `Filter::$controlDropped` (commit 2fe75a6) is removed. It leaked a storage/provenance fact ("the editor chose a
   control, it failed to load") into the public runtime DTO, created a third control state every `control === null`
   check would have to consider, and only the archive element read it. The underlying rule changes instead: a
   control that cannot be resolved fails the list. Example that motivates it (the user's): a bundle providing a
   "hide super-secret items" control/element is uninstalled — the list must fail until the editor replaces the
   filter, never show everything.
2. The backend marks every filter that is **guaranteed to break the list** — fails under every user input — with the
   Lucide `bone-fracture` icon, and reports it on the list's filter overview and on the filter's edit form. Today the
   edit form shows only errors that happen to surface while loading a choice element's `value` options; broken
   element configs, `buildContext()`/predicate failures, unresolvable controls and failures of *other* filters are
   invisible or misattributed ("choices could not be loaded").

## Goals

- No `controlDropped` flag, no `withDroppedControl()`; the archive element's `isControlled()` is `control !== null`.
- Collection failures are structured (source, message, exception), so the backend can map them to filter rows.
- One code path builds a filter's conditions, used by both `ListQueryDirector` and the backend check.
- Broken filters: bone-fracture icon in the filter list, red count on the list overview, red error on the edit form.

## Non-goals

- Running the list's SQL in the backend. Building every filter's conditions is enough (user's decision).
- New exception classes. `FilterException`/`UnreadableValueException` (public) vs. everything else (unexpected)
  already carries the meaning; no catch site needs another distinction (user agreed).
- `composePrefix` rework and the targeting/`isTargeted` question (postponed by the user).
- Detecting failures that depend on a user's input or on one engine context only.

## Part 1 — Failure rules in `FilterFactory::createFromFilterModel()`

| Situation | Before | After |
|---|---|---|
| `control` column blank | intrinsic filter | unchanged — no error, even if no control exists for the value class |
| stored `control` does not resolve for the element's value class (unknown type, uninstalled bundle, wrong value class) | fail open: dropped control, `controlDropped` | **fail closed**: exception propagates, the collector records a list failure |
| control config broken (its transformer or schema throws) | fail open | **fail closed** |
| no value provider for the value class (setup error) | fail open with a control | **fail closed** |
| initial value (with a control) unreadable — any `FilterException` from the value provider | whole control dropped (form field vanished) | **control kept, value dropped**, warning logged |
| fixed value (no control) unreadable | fail closed | unchanged |

Implementation shape: resolve the control and its config outside any catch; wrap only the value read for a
controlled filter in `catch (FilterException)` — the public `ValueProviderInterface::provide()` promises a
`FilterException` (of which `UnreadableValueException` is one) for an unreadable stored value, so a userland provider
following that contract keeps the control too. A missing value provider throws a plain `FlareException` and still
fails closed.

Unchanged runtime rules: a controlled filter that fails at query time with a value is retried once with `null`
(success drops the value); a control whose `buildForm()`/`hydrate()` throws only loses its mount; `decode()` failures
become form errors. These are runtime failures of a control that *did* resolve; the element's own config still bounds
the list.

`ElementDcaListener::createElementScope()` now returns `null` for a filter with an unresolvable control (the factory
throws); the edit form's new error (Part 2) explains why.

## Part 2 — Structured failures

A new immutable value object for a filter that fails the list (name and namespace are the builder's call; e.g.
`HeimrichHannot\FlareBundle\List\FilterFailure`):

- `source` — `?string`, the failing filter's source, e.g. `tl_flare_filter.42` (null for an inline filter)
- `message` — `string`, as today's failure strings (`Filter tl_flare_filter.3 of type "x" on tl_flare_list.1 could
  not be created: …`)
- `exception` — `?\Exception`, the cause (null for e.g. the duplicate-alias failure)

It replaces `string` in `CollectedFilters::$failures`, `ListSpec::$failures` / `withFailure()` and
`ListSpecBuilder::addFailure()`. `ListSpec::hash()` must stay deterministic (hash source + message, not the exception
object). `ListQueryDirector::createQueryBuilder()` keeps joining the messages into its `FilterException`.

## Part 3 — Shared condition building

Extract `ListQueryDirector::buildFilterConditionsBuilders()` (create the `FilterContext` via
`FilterContextFactory`, run each proposition's predicate into a `FilterConditionsBuilder`, wrap failures with
`FilterException::wrap`-style source info) into a small internal service both the director and the backend check
use. The director keeps its own value selection and retry logic.

## Part 4 — The backend check

A new internal inspector next to `IneffectiveFilterInspector` (`src/DataContainer/`), memoized per list per request
(the child-record callback runs once per row). For a list it determines, per filter row id, a failure or none:

1. Build the list spec once (`ListSpecBuilderFactory::createFromListModel()->build()`). Map its failures to rows by
   `source`. If the spec itself cannot be built (list driver unresolvable), no row is marked — that is a list-level
   problem outside this check.
2. For every collected filter that has a row source and no failure yet: build its conditions with its **default
   value** — the fixed `Filter::$value` without a control, `null` with one — through the shared service from Part 3.
   Any `\Exception` marks the row broken (message from the exception, which an element has already translated where
   it throws a `FilterException`). `AbortFilteringException` is **not** a failure ("matches nothing" is legitimate).
   `\Error`s are not caught.
3. **Unpublished rows** (not collected by the list): build each with `FilterFactory::createFromFilterModel()`
   (catching like the collector) and, if that succeeds, its conditions as in step 2, using the spec from step 1 as
   the list. They are marked with the icon but are **not** list failures: they never enter the overview count or
   the list's failure message. An unpublished row whose alias clashes with a published filter is **not** flagged
   (known gap: the existing duplicate-alias check covers published rows only, and a clash depends on other rows,
   not on the filter itself).

Engine context for step 2: `ConfigurationContext`, the context the backend scope already uses (builder may
revisit if an element turns out to need another).

### Editor-facing text

Framework messages are shown as they are (English), as agreed; only an element's `FilterException` arrives
translated (`AbstractFilterElement::trans()`). On the row's `<title>` and the edit form, show the cause's message
(`FilterFailure::$exception?->getMessage()`, falling back to `$message` when there is no exception, e.g. a duplicate
alias) — the long log line's "Filter tl_flare_filter.3 of type … on tl_flare_list.1 could not be created:" prefix is
redundant next to the row it marks. No new translation keys for framework failure kinds.

### Display

- **Filter list (child records)** — `templates/backend/filter_child_record/_base.html.twig`: a red (`#dc3545`,
  like the duplicate-alias skull) Lucide `bone-fracture` icon (lucide-static 1.62.0, class
  `lucide-bone-fracture-icon`) with the failure text as `<title>`, wrapped in a span with
  `opacity: var(--flare-filter-notice-opacity)` like the skull and the engine icon: notices are muted on an
  unpublished filter (the list skips it) and fully opaque on a published one, set in `public/backend/styles.css` by
  `.cte_type.{un}published ~ .flare-filter-info` (user's decision;
  `--flare-filter-control-opacity` mutes only the control icon and alias of a filter without control). It is an independent `{% if %}` block next to the skull and the engine icon; any combination may show,
  no precedence logic (user's decision). Passed from `ListCallbacks::listLabelLabel()` like `ineffective_reason`.
  The SVG paths:
  ```
  <path d="M14 4.5a1 1 0 0 1 5 0 .5.5 0 0 0 .5.5 1 1 0 0 1 0 5c-.81 0-1.8-.7-2.5 0l-1.958 1.957a.15.15 0 0 1-.252-.072l-.493-2.07a.15.15 0 0 0-.111-.112l-2.072-.494a.15.15 0 0 1-.072-.252L14 7c.7-.7 0-1.69 0-2.5"/>
  <path d="m16 20-1-2"/>
  <path d="m20 16-2-1"/>
  <path d="m4 8 2 1"/>
  <path d="m8 4 1 2"/>
  <path d="M9.698 14.19a.15.15 0 0 0 .112.112l2.074.489a.15.15 0 0 1 .072.252L10 17c-.7.7 0 1.69 0 2.5a1 1 0 0 1-5 0 .495.495 0 0 0-.5-.5 1 1 0 0 1 0-5c.81 0 1.8.7 2.5 0l1.956-1.957a.15.15 0 0 1 .252.072z"/>
  ```
- **List overview** (child view of the list, where `reportIneffectiveFilters()` runs): `Message::addError` with a
  pluralized count of *published* broken filters — new key `list.info.broken_filters` (en + de), wording along the
  lines of "n filters break the list; it cannot be created until they are fixed. They are marked below." The
  orange ineffective count stays as is.
- **Edit form** (`act=edit` on `tl_flare_filter`): `Message::addError` with the filter's failure message, for
  published and unpublished filters alike (builder's call on wording, e.g. a prefix "This filter breaks the list:"
  / "This filter would break the list once published:" as translation keys). Shown in addition to the existing
  ineffective message.

## Docs and specs to update

- `docs/` worktree (separate commit on `docs/main`): `spec/filtering.md` §6 failure table (control row → fail
  closed; unreadable initial value keeps the control), `migrating-from-v0.1.md` (a list whose stored control is
  missing now fails instead of degrading), a short note on the backend marks where the ineffective marks are
  documented (`dev/filter-elements/index.md`).
- `AGENTS.md` architecture paragraph ("a control that cannot be resolved only drops the control …"),
  `dev/superpowers/specs/2026-10-14-filter-forms-design.md` §8.1, PHPDoc of `createFromFilterModel()`, `ListModelFilterCollector`, `ListSpec`.

## Calls made on the user's behalf (check while reading)

- Broken control config and a missing value provider fail closed, like an unresolvable control.
- Duplicate-alias failures are failures like any other: the later row gets the bone-fracture icon in addition to the
  existing skull, and counts in the overview's broken count (alongside the existing duplicate-alias error).
- Failures of inline/programmatic filters (no row source) still fail the list but mark no row.
- The edit-form error is shown for unpublished filters too.
- `ConfigurationContext` as the engine context of the check.
- The value read for a controlled filter catches any `FilterException` (the provider's public contract), not only
  `UnreadableValueException`.
- Row and edit form show the cause's message, not the long log line; framework messages stay untranslated.
- An unpublished row whose alias clashes with a published one is not flagged (known gap).
- When the new edit-form error explains a missing scope, the generic "choices could not be loaded" may stay — the
  builder may suppress it if that is simple.

## Constraints

- Catch `\Exception`, never `\Throwable` (transformer resolvers excepted, as today).
- PHP 8.2, Contao 4.13 + 5.x, PHPUnit 9 (`make test`), PHPStan level 5 (`make phpstan`), `mago lint` clean
  (20-method limit per class).
- No `Co-Authored-By` trailer, never push; docs committed separately on `docs/main`.
- Keep §/SPEC references in comments.
