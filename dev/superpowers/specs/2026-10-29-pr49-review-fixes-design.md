# Design: fixes for the PR #49 code review

Status: agreed in conversation 2026-10-29, builder-checked, awaiting the user's review. Branch `v0.2-refactor-filter-api`
(unreleased; breaking changes are fine; no migration has run on any installation yet).

## Intent

A `/code-review high` of PR #49 produced ten findings; each was validated against the code and then decided one by
one with the user. This document records the decisions so the fixes can be planned and built without going back to
the conversation. The common thread is the existing principle of §8.1 of `2026-10-14-filter-forms-design.md`:
**what an editor configured but FLARE cannot honour must not degrade silently** — and its counterpart, that the
backend inspector (`FilterInspector`) reports exactly what the frontend does.

## Goals

One fix per finding, numbered as in the review (ordered here by area, not severity).

## Non-goals

- Rewriting the archive filter's value widget for dynamic ptables (#9 is documented, not fixed).
- A compile-time check that elements implement the contract their value provider needs (dropped with #7; can be added
  later if userland elements turn out to make this mistake).
- Adding `declare(strict_types=1);` to the files under `contao/` (#10 changes the rule instead).
- Touching `§`/`SPEC_*` references in comments, even stale ones (standing rule until the final PR review loop).

---

## #1 — `ListSpec::withFilter()` keeps alias keys

`src/List/ListSpec.php`: `[...$this->filters, $filter->alias => $filter]` renumbers integer keys, and filters without
an explicit alias are keyed by their row id (`"12"` → `int 12`). Replace the spread with a copy and an assignment:

```php
$filters = $this->filters;
$filters[$filter->alias] = $filter;

return $this->withFilters($filters);
```

Test: a list holding numeric-alias filters keeps its keys after `withFilter()`, and a later filter with alias `"0"` is
not rejected as a duplicate.

## #2 — Filter aliases must be valid form names

An alias like `price.min` or `-sort` passes Contao's `rgxp => alias` (`/^[\w.-]+$/u`) but fails
`Str::isValidFormName()`; a filter with a control is then skipped silently by `FormHarnessFactory` (no widget, no
initial value, no log, no inspector issue).

The user's decision: **the rule applies to every filter**, not only to filters with a control — keep it simple.

1. **DCA:** the `alias` field of `tl_flare_filter` gets a custom rgxp, `'rgxp' => 'flare_form_name'` (replacing
   `'alias'`), validated by an `addCustomRegexp` hook listener (`#[AsHook('addCustomRegexp')]`, works on Contao 4.13
   and 5) that calls `Str::isValidFormName()` and adds a translated error to the widget. Contao skips rgxp on empty
   input, so an empty alias still falls back to the row id. Error message translations in `de` and `en`.
2. **Runtime, fail closed** (builder's call, following the #7 decision that a control that cannot build fails the
   list): `FilterFactory` rejects a filter **with a control** whose alias is not a valid form name, with a
   `FlareException`, exactly like it already rejects a control without an alias. The collector then fails the list,
   and the inspector reports the row as broken through its existing `build()` path. Rows saved before the rgxp
   existed are covered by this.
3. `FormHarnessFactory` keeps its `isValidFormName()` check as a defensive guard for `Filter` objects built without
   the factory, but treats a controlled filter that fails it as a `buildForm()` failure (see #7) instead of a silent
   `continue`. Filters without a control are still skipped there without a log — they have no form representation.

Filters without a control may keep an invalid alias saved before the rgxp existed; it is harmless at runtime and not
reported.

## #3 — The backfill migration fills `ctl_label` with the old effective label

All controls follow one rule, which stays: an empty `ctl_label` renders no label. The old elements had fallbacks that
migrated filters lose. The migration has never run anywhere, so `BackfillFilterValuesMigration` is updated; the
controls are not.

For rows the migration processes, when `ctl_label` is empty:

- **Bool rows that get a control** (`flare_checkbox`, and `flare_choice_bool` for ternary mode — builder's call: the
  old element used the same label in both modes): copy `title` into `ctl_label`. `title` is mandatory, so the old
  `'CBX'` fallback never applied in practice.
- **Search-keywords rows that get `flare_keywords`:** write the translation key `label.text` (user's decision). Form
  labels are translated through the `flare_form` domain (`FormHarnessFactory` sets `translation_domain`), so it
  renders "Free text" / the German text, exactly as before.
- Rows that stay without a control are untouched.

`ctl_label` and `title` must be added to the columns the migration reads (`REQUIRED_COLUMNS` or the optional sources,
whichever fits). Tests in the existing migration test for both cases, including a non-empty `ctl_label` that is kept.

## #4 — Required config is required in the schema; `abort()` means "matches nothing" only

Today Bool, DCA select, field-value choice and keywords call `$builder->abort()` when their target `field` /
`columns` is not configured, which renders "no entries" instead of a failed list, and the inspector treats an abort
as "no issue". Bool even aborts with no value, emptying the list for every visitor.

The user's rule, applied by the builder to **every** filter element (bundled and integrations), not just these four:

- **A config key required in every case** is marked required in `configureOptions()` (`->required()`, plus a
  non-empty constraint where `null` / `''` / `[]` would otherwise pass). The options resolver's `FilterException`
  then fails the filter at creation: the list fails closed, and the inspector shows the row as broken.
- **A config key required only in some cases** (e.g. depending on another key or on whether a control is set) is
  checked at the top of `buildContext()`, before the value check, with a `FilterException`.
- **`abort()` stays only where data legitimately matches nothing** — no selected values, no options, no allowed
  parents. The bool element's early abort goes away.

Known candidates from the review: `field` for bool, DCA select, field-value choice and date range (whose existing
`throw` in `buildContext()` moves into the schema if the key is always required), `columns` for keywords. The audit
decides the final list.

The backend edit form is not affected: `BackendElementScopeFactory::create()` already catches any `\Exception` and
returns no scope, so a new row whose field is not chosen yet renders its value widget without options. Existing
tests that build these elements without the now-required keys must be updated.

## #5 — An invalid submission is not counted

`InteractiveProjector::project()` counts before it checks `$form->isSubmitted() && !$form->isValid()`, so a rejected
value (since this PR, `decode()` adds form errors) shows the total of a wider list next to an empty loader, and the
COUNT query is wasted.

Order of checks (user's decision, "failure wins cheaply"):

1. Failures known without building conditions win: the collection failures on the `ListSpec` (`$list->failures`)
   and the `buildForm()` failures recorded on the form harness (#7) → `createFailedView()`.
2. Then validity: an invalid submission gets `totalItems: 0`, an empty paginator (`createEmpty()`, as the failed
   view does) and the `InteractiveEmptyLoader`; it is not marked `failed`. No COUNT runs.
3. Otherwise as today: the COUNT, whose `FilterException` still yields the failed view.

Accepted gap: a list broken only in a filter's conditions renders as empty (with the form error) for an invalid
submission. Building all conditions without SQL to close it is recorded in
`dev/superpowers/ideas/2026-10-29-failed-list-wins-over-invalid-submission.md` for later. No bundled template shows the total, so none
needs a guard (the user would have accepted wrapping such output in `count > 0`).

## #6 — Collector and inspector judge filter failures the same way

Only userland code can make them disagree today:

| Scenario | Frontend today | Backend today |
|---|---|---|
| userland value provider throws a non-`FilterException` `\Exception` | 500 | "broken" |
| userland `choiceFromKey()` throws inside `ChoiceValueProvider` | 500 | "broken" |
| a `FilterCollectedEvent` listener throws, even a `FlareException` | 500 (dispatch is outside the collector's `try`) | "broken" |

Agreed fix:

1. **`ListModelFilterCollector::collectFromModels()`:** the `FilterCollectedEvent` dispatch moves inside the `try`. A
   `FlareException` from a listener fails the list like any other filter failure.
2. **`FilterFactory::provideValue()`:** value providers get the hook treatment. Their `\Exception`s are normalized
   with `FilterException::wrap()` (a non-`FilterException` is logged as an error), so without a control the list
   fails, and with a control the initial value is dropped with a warning (`provideInitialValue()`). **Exception:** a
   `FlareException` that is not a `FilterException` passes through unwrapped (needed by #7).
3. **`FilterInspector::build()`:** keeps its single `try`, but only a `FlareException` is reported as broken. Any
   other `\Exception` (only a listener can still throw one) is reported as a programming error — which is what still
   reaches the frontend as a 500. Its docblock ("like the collector does") becomes true.

User's confirmation: a `FlareException` from a listener fails the list; anything else crashes the request.

## #7 — `buildForm()` fails closed; `hydrate()` drops only the value

Today any `buildForm()` or `hydrate()` failure drops the filter's mount with a warning, and the filter runs without
its widget and value. Every realistic cause is broken config or data drift an editor must fix (all whitelisted
archives deleted, a DCA select field that lost its options, an element lacking the contract its control needs), and
dropping the widget can silently show "no entries" or an unfiltered list. This contradicts §8.1's rule that a control
the editor chose must not be silently lost.

| Hook | Behaviour after |
|---|---|
| `buildForm()` | **fail closed:** the list fails (`list.failed` in the view, error logged). The filter form still renders with the other filters' mounts, as the failed view already does. |
| `hydrate()` | **fail open:** the mount is kept **without initial data**, warning logged — like an unreadable initial value. (Today the whole mount is dropped; this changes.) |
| `decode()` | unchanged: the value is dropped, a submitted field gets a form error. |

- A listener **cancelling** `FilterControlBuildFormEvent` is a deliberate skip, not a failure.
- **Form-build listeners and the factory's own checks follow #6's rule** (builder's call, applying the rule the user
  confirmed for `FilterCollectedEvent`): a `FlareException` thrown by a `FilterControlBuildFormEvent` listener, or by
  `FormHarnessFactory` itself for one filter (e.g. "single field and children at the same time"), is a `buildForm()`
  failure that fails the list; any other `\Exception` from a listener crashes the request. A failure of the
  list-wide `FormHarnessBuildEvent` is not tied to one filter and keeps propagating.
- Non-`FilterException`s keep the existing treatment: wrapped, logged as error, rethrown in debug mode.
- How the failure travels is the builder's call. One fitting shape: `FormHarnessFactory` keeps building the other
  mounts and records the failure (as a `FilterException`) on the `FormHarness`; `InteractiveProjector::project()`
  checks it before counting and returns `createFailedView()` with the harness's form. `createForm()` (form-only use)
  needs a defined behaviour too.
- **`ChoiceValueProvider`:** when the element does not implement `ChoiceSourceContract`, throw a `FlareException`
  (not a `FilterException`). Through #6's pass-through it fails the list at collection instead of being swallowed as
  an unreadable initial value. For a filter with a control, `buildForm()` would fail closed anyway via
  `ControlScope::element()`.
- **Inspector:** for a filter with a control, it also runs `buildForm()` in a backend scope — including the
  `FilterControlBuildFormEvent` dispatch, so it judges what the frontend does (#6's principle) — and reports a
  failure as broken (a non-`FlareException` from a listener as a programming error), so the editor sees the cause on
  the filter row. Running it must not depend on a request (no `handleRequest()`). Sharing the per-filter build step
  between `FormHarnessFactory` and the inspector, as `FilterInvoker` is shared for conditions, is the obvious shape.
- **Docs:** §8.1 of `2026-10-14-filter-forms-design.md` gets a row for `buildForm()` / `hydrate()`; the failure
  paragraph in `AGENTS.md` ("A control's `buildForm()`/`hydrate()` failure drops its mount (warning)") is rewritten.

## #8 — The backfill migration never runs twice

The run-once guarantee rests on the marker column `legacyBackfilled`. If it is dropped while `intrinsic` stays (the
Contao 4.13 install tool allows dropping single columns), the migration runs again and re-assigns controls to
filters editors have since made intrinsic.

Both fixes (user's decision):

1. **Guard in `shouldRun()`:** if the marker is missing **and** any row already has a non-empty `control` or `value`,
   the new columns are in use — return false. On a real first upgrade both columns were just added by the schema
   diff and are empty everywhere. The class docblock explains the guard and its one blind spot (a table where every
   filter ended up with neither control nor value; a rerun then recomputes from the old columns and only differs
   where an editor changed something). Test for both branches.
2. **Upgrade note** in `docs/docs/migrating-from-v0.1.md`: drop `legacyBackfilled` together with the other retired
   columns, never on its own. The docs are a separate worktree on branch `docs/main`; that change is committed there,
   separately.

## #9 — Known limitation: archive value options over a dynamic ptable

For an archive filter **without** a control on a list whose data container has a dynamic ptable (e.g. `tl_content`),
the `value` widget's options load every record of every parent table with `findAll()`. For static ptables the cost
equals the existing whitelist widget (`DcaHelper::getArchiveOptions()`), which is fine. Not fixed (user's decision);
documented:

- a short comment on `ArchiveFilterElement::fetchAllParentChoices()` / `buildAllChoices()`,
- one sentence in the archive filter's docs reference in the docs worktree (`docs/main`).

## #10 — `strict_types` rule scoped to `src/` and `tests/`

`AGENTS.md`, Coding Conventions: "`declare(strict_types=1);` at the top of every PHP file" becomes "…of every PHP file
under `src/` and `tests/`". The DCA, language and config files under `contao/` are included by Contao and stay as
they are. (`CLAUDE.md` is a symlink; edit `AGENTS.md`.)

---

## Constraints

- Coding conventions of `AGENTS.md` (PER, 120 columns, Mago clean). PHPStan and PHPUnit via `make phpstan` /
  `make test`; Mago runs only in CI.
- Production code testable through its public API, no private access in tests.
- Migrations never drop or delete; SQL valid on MySQL and MariaDB.
- Commits without a `Co-Authored-By` trailer; never push.

## Left to the builder

- Order of the work and how it is split into commits.
- The exact shape of how a `buildForm()` failure reaches the projector (#7), within the behaviour above.
- The final list of required config keys from the #4 audit.
- Names of the new hook listener and the translation keys (#2).
