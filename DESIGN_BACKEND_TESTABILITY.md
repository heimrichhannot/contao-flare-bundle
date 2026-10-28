# Design: a testable backend presentation of filter issues

Status: written 2026-10-28, reworked after the user's review (the reporter is expanded, not dropped; production code
must be testable without reaching into private methods), builder-checked, awaiting the user's final review. Branch `v0.2-refactor-filter-api`. Builds on
`DESIGN_FILTER_INSPECTION.md` (implemented).

## Intent

`FilterInspector` finds a list's filter issues and is unit-tested, including `inspect()` through
`ContaoFramework::getAdapter()` (9f10189). How the backend *presents* those issues is not testable today:

- `ElementDcaListener` mixes two jobs: configuring the DCA of the edited record, and reporting filter issues (overview
  counts, edit-form messages, the GET-only rule). The reporting sits in private methods, and both jobs reach Contao
  statically (`Input::get()`, `FilterModel::findByPk()`, `$filterModel->getRelated('pid')`, `ListModel::findByPk()`,
  `Message::addError()`/`addNew()`).
- `ListCallbacks` builds the row notices from the inspection itself and loads the list statically.
- So the only listener tests have to reach private state and private methods through bound closures.

Give the presentation of filter issues its own class with a public, testable API, let both callers delegate to it,
route every Contao call through the framework's adapters, and test everything through public methods.

## Goals

- **`FilterIssueReporter`** owns how the backend presents a list's filter issues: when to report, which messages,
  their severity and escaping, and the row notices.
- **`ElementDcaListener`** only configures the DCA; for reporting it delegates to the reporter. **`ListCallbacks`**
  only renders the row; the notices come from the reporter.
- Neither class calls Contao statically: models load through `ContaoFramework::getAdapter()`, messages are added
  through `getAdapter(Message::class)`, the record id comes from the request.
- **The production code is testable through public methods.** No test of this work needs a bound closure or
  reflection to reach a private method or property of the code under test. (Bound closures are not banned in
  tests as such; the point is a design that does not need them.)

## Non-goals

- No change in behaviour, messages, translations or the row template.
- The DCA-building part of `ElementDcaListener` (`buildDca`, the build-DCA events, `DcaBuilder::apply()`) gets no new
  tests beyond what the routing tests run through.
- `FilterInspector` stays `final` and gets no interface.

## Constraints

- Contao 4.13 + 5.x (`ContaoFramework::getAdapter()`, `contao/test-case`), PHP 8.2, PHPUnit 9, PHPStan level 5,
  `mago lint` clean (≤ 20 methods per class).
- Catch `\Exception`, never `\Throwable`. No `Co-Authored-By` trailer, never push. Keep §/SPEC references.

## Shape (my calls — check while reading)

### `FilterIssueReporter`

`src/DataContainer/Inspection/FilterIssueReporter.php`, `@internal`, `final readonly`, a service. Injects
`FilterInspector`, `ContaoFramework`, `RequestStack`, `TranslatorInterface`. It owns the *when* as well as the *what*,
so callers do not need to know the request rules:

- `reportOverview(ListModel $listModel): void` — only on a GET of the list's filter child view
  (`table=tl_flare_filter`, no `act`); then, per issue kind with a non-zero count, in the kinds' order: `addError` for
  programming error, duplicate alias and broken, `addNew` for ineffective, with today's keys and parameters; the
  duplicate message lists the blocking aliases quoted and escaped. The list is not inspected when nothing is to be
  reported.
- `reportFilter(ListModel $listModel, FilterModel $filterModel): void` — only on a GET with `act=edit`; one message
  per issue of the filter, its described text escaped, `addNew` for ineffective, `addError` otherwise.
- `getRowNotices(array $row): array<string, string>` — for the filter row: the described text of each issue, keyed
  by issue kind value, unescaped (Twig escapes it); the list is loaded by the row's `pid` through the framework;
  `[]` if it does not exist.

### `ElementDcaListener`

- Delegates: on the list table it calls `reportOverview()`, on the filter table `reportFilter()`; the reporter decides
  whether anything is shown. `reportFilterStates()`, `reportFilterState()`, `reportsFilterStates()` and
  `addMessage()` go, and with them the `FilterInspector` and `TranslatorInterface` dependencies.
- Loads through the framework: `getAdapter(FilterModel::class)->findByPk()`, and the filter's list through
  `getAdapter(ListModel::class)->findByPk($filterModel->pid)` instead of `getRelated('pid')`.
- Reads the record id from `RequestStack` (the query's `id`, validated as a positive integer as today) instead of
  `Input::get('id')`.
- The backend element scope (today's private `createElementScope()`, which builds the filter without its value so an
  unreadable value cannot block the form that repairs it, §6.4) moves to a small public class
  `src/DataContainer/BackendElementScopeFactory.php` (`@internal`, `final readonly`, a service, injects
  `FilterFactory`): `create(\Closure $getSpec, FilterModel $filterModel): ?ElementScope`, null on any `\Exception`.
  The listener hands it to `DcaContext` as today. This frees its existing test from closures.

### `ListCallbacks`

Passes `notices => $this->filterIssueReporter->getRowNotices($row)` to the template; drops `FilterInspector`.

### Tests

- **Shared helpers** (`tests/`):
  - model fixtures (user's decision): test-only subclasses `FilterModelFixture extends FilterModel` and
    `ListModelFixture extends ListModel` whose constructor takes a row and assigns `$this->arrData` — no database,
    no container. `Model::setRow()` is no option: it converts values through `Model::convertToPhpValue()`, which
    needs Contao's service container (`kernel.cache_dir`). The new and reworked tests of this work use them; the
    other tests' existing fixtures (`FilterModelStub`, `ControlFilterModelStub`, `ListModelStub`, `arrData`
    closures) stay as they are (my call: not part of this work).
  - an inspector helper, extracted from `FilterInspectorTest`: a real `FilterInspector` (stub elements `ok`,
    `throws`, `aborts`, `raw`, `errors`, `sensitive`, `ineffective`; list driver `test_list`) over a mocked framework
    whose `FilterModel` adapter returns the given rows. Tests describe situations in rows ("a published broken filter
    and an unpublished ineffective one").
  - a test translator that echoes the key and its parameters (`list.info.broken_filters %count%=1`), so assertions
    see keys, counts and escaped aliases; the copy itself is pinned by `FilterIssueTest`.
  - messages are recorded through a `Message` adapter mock (`ContaoTestCase::mockAdapter()`).
- **`FilterIssueReporterTest`** — the overview (each kind with its count and severity, escaped aliases, nothing for
  unpublished-only issues, nothing outside the child view or on POST), the edit form (each issue escaped, severity,
  nothing on POST or without `act=edit`), the row notices (keyed texts, `[]` for a missing list).
- **`ElementDcaListenerTest`** — drives the public `__invoke()` with real collaborators, a minimal
  `$GLOBALS['TL_DCA']` and the framework mock: a GET of a list's filter child view shows the overview messages, a GET
  of a filter's edit form its messages; a missing, non-numeric or non-positive id loads nothing. The GET-only rule is
  tested on the reporter. The two existing tests, which reach private methods, are replaced (the reporter and
  `BackendElementScopeFactoryTest` cover them).
- **`BackendElementScopeFactoryTest`** — today's "the backend scope knows the control but reads no value" case,
  through `create()`.
- **`ListCallbacksTest`** — the template receives the reporter's notices (Twig `Environment` mock capturing the
  context).

## What's left to the builder

- Names of the shared helpers (traits or a static fixture class) and test names.
- How many cases per behaviour, beyond those listed.
