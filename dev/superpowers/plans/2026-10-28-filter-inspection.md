# Filter Inspection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One backend inspector reports every filter issue of a list — programming error, duplicate alias, broken,
ineffective — exactly once, on the row, in the overview messages and on the edit form.

**Architecture:** Value types (`FilterIssueKind`, `FilterIssue`, `ListInspection`) carry the result; `FilterInspector`
builds every saved row itself (catching `\Exception`, and `\Error` outside debug mode), groups aliases, builds the
list scope like the runtime does, and checks conditions and ineffective reasons. `ListCallbacks`, the row template and
`ElementDcaListener` render the result; `IneffectiveFilterInspector`, `BrokenFilterInspector`, `FilterDiagnosis` and
the `$GLOBALS` duplicate handoff are removed.

**Tech Stack:** PHP 8.2, Symfony 5.4–7, Contao 4.13/5.x, PHPUnit 9, PHPStan level 5, Mago.

**Spec:** `dev/superpowers/specs/2026-10-28-filter-inspection-design.md` (approved by the user 2026-10-28).

## Global Constraints

- Catch `\Exception`, never `\Throwable`; the only `\Error` catch is this inspector's, outside debug mode
  (`%kernel.debug%` false). The transformer resolvers keep wrapping `\Throwable`, as today.
- `declare(strict_types=1);`, PER style, 120-char lines, ≤ 20 methods per class (`mago lint`).
- Tests: `make test` (all), `make test tests/Path/SomeTest.php`. Static analysis: `make phpstan`. Lint: `mago lint`.
- Commits: conventional style, **no `Co-Authored-By` trailer, never push**. Docs in the `docs/` worktree (branch
  `docs/main`) are committed separately there; `npm run build` in `docs/` must pass.
- Keep §/SPEC references in comments.
- Overview messages count **published** filters (plus filters without a row) only; unpublished rows get muted icons.
- Translation texts are fixed by the spec (section "Translations") — copy them verbatim.

## Review Focus

1. A list whose scope cannot be built (unknown driver, driver `buildList()` throws an `\Exception`) — rows are still
   built and marked for duplicates, broken builds and ineffectiveness; no condition check, no crash. → Task 3 test
   `testWithoutAListScopeRowIssuesAreStillReported`.
2. Duplicate groups mixing a broken row with a healthy one — the broken row has no built filter, so its alias falls
   back to `FilterModel::generateFormName()`; both still group. → Task 2 test
   `testARowThatCannotBeBuiltStillGroupsByItsFormName`.
3. Debug mode — every `\Error` escapes the inspector unchanged (developers see the trace). → Task 2 test
   `testErrorsEscapeInDebugMode`, Task 3 test `testScopeErrorsEscapeInDebugMode`.
4. A published duplicate pair — only the first one enters the list scope, so `ListSpecBuilder::addFilter()` never
   throws for it, and the healthy first filter is still condition-checked. → Task 3 test
   `testOnlyTheFirstOfADuplicateGroupEntersTheScope`.
5. Saving a form (POST) — no overview or edit-form message is added (stale flash after the redirect). → Task 4 keeps
   `ElementDcaListenerTest::testFilterStatesAreReportedOnGetRequestsOnly` green.

---

### Task 1: Issue value types and translations

**Files:**
- Create: `src/DataContainer/Inspection/FilterIssueKind.php`, `src/DataContainer/Inspection/FilterIssue.php`,
  `src/DataContainer/Inspection/ListInspection.php`
- Modify: `translations/flare.en.yaml`, `translations/flare.de.yaml`, `config/services.yaml` (exclude
  `../src/DataContainer/Inspection/{FilterIssue,FilterIssueKind,ListInspection}.php` — value objects, not services)
- Test: `tests/DataContainer/Inspection/FilterIssueTest.php`, `tests/DataContainer/Inspection/ListInspectionTest.php`

**Interfaces:**
- Produces:
  ```php
  namespace HeimrichHannot\FlareBundle\DataContainer\Inspection;
  /** @internal Case order = order of the overview messages and row icons. */
  enum FilterIssueKind: string {
      case ProgrammingError = 'programming_error';
      case DuplicateAlias = 'duplicate_alias';
      case Broken = 'broken';
      case Ineffective = 'ineffective';
  }
  /** @internal */
  final readonly class FilterIssue {
      /** $reason: the cause; for DuplicateAlias the alias; for Ineffective the already translated reason. */
      public function __construct(public FilterIssueKind $kind, public string $reason, public bool $published) {}
      /** Row tooltip / edit-form text: Ineffective → $reason as is; DuplicateAlias →
       *  trans('filter.info.duplicate_alias.{published|unpublished}', ['%alias%' => $reason]); others →
       *  trans('filter.info.{kind value}.{published|unpublished}', ['%reason%' => $reason]); domain 'flare'. */
      public function describe(TranslatorInterface $translator): string;
  }
  /** @internal */
  final readonly class ListInspection {
      /** @param array<int, list<FilterIssue>> $rows by tl_flare_filter id
       *  @param array<string, int> $counts by FilterIssueKind value — published filters and filters without a row
       *  @param list<string> $blockingAliases aliases shared by two or more published rows */
      public function __construct(public array $rows = [], public array $counts = [], public array $blockingAliases = []) {}
      /** @return list<FilterIssue> */
      public function forRow(int $id): array;
      public function count(FilterIssueKind $kind): int;
  }
  ```

- [ ] **Step 1: Write the tests**

`FilterIssueTest` (translator: `IdentityTranslator`-like `TranslatorTrait` stub that returns `id|params`, or a real
`Symfony\Component\Translation\Translator` loaded with `translations/flare.en.yaml` via `YamlFileLoader` — use the
real one so the copy is checked):

```php
testIneffectiveIsDescribedByItsReason      // new FilterIssue(Ineffective, 'No value.', true) → 'No value.'
testDuplicateIsDescribedWithItsAlias       // (DuplicateAlias, 'dup', true) → 'The alias "dup" is also used by another published filter, which blocks the list.'
testUnpublishedDuplicateWording            // (DuplicateAlias, 'dup', false) → 'Once published, this filter\'s alias "dup" would clash with another filter and block the list.'
testProgrammingErrorWording                // (ProgrammingError, 'bug', false) → 'A programming error occurred in this unpublished filter; an administrator may need to be contacted: bug'
testBrokenWording                          // (Broken, 'x', true) → 'This filter breaks the list: x'
```

`ListInspectionTest`: `forRow()` returns `[]` for an unknown id; `count()` returns `0` for a kind without entry and
the stored count otherwise.

- [ ] **Step 2: Run, verify fail** — `make test tests/DataContainer/Inspection` → FAIL, classes not found.

- [ ] **Step 3: Implement the three types; add the translations**

Translations exactly as the spec's "Translations" section: add `list.info.programming_errors`, reword
`list.info.duplicate_filter_alias`, add `filter.info.programming_error.{published,unpublished}`, turn the string key
`filter.info.duplicate_alias` into `filter.info.duplicate_alias.{published,unpublished}` (en + de). Until Task 4
the template still reads `filter.info.duplicate_alias` as a string — it renders the key path; acceptable for one
commit (Task 4 replaces it).

- [ ] **Step 4: Run** — `make test` → all pass; `make phpstan`, `mago lint` clean.

- [ ] **Step 5: Commit** — `git add src tests translations config dev/superpowers/specs/2026-10-28-filter-inspection-design.md dev/superpowers/plans/2026-10-28-filter-inspection.md`;
  `git commit -m "feat: add the filter issue types of the backend inspection"`

---

### Task 2: `FilterInspector` — building rows and grouping aliases

**Files:**
- Create: `src/DataContainer/Inspection/FilterInspector.php`
- Modify: `config/services.yaml` (`HeimrichHannot\FlareBundle\DataContainer\Inspection\FilterInspector:` with
  `arguments: { $debug: '%kernel.debug%' }`)
- Test: `tests/DataContainer/Inspection/FilterInspectorTest.php`

**Interfaces:**
- Consumes: Task 1 types; `FilterFactory::createFromFilterModel()`, `FilterCollectedEvent`,
  `FilterModel::generateFormName()`, `FilterFailure::getReason()` semantics (reuse it: `(new FilterFailure(null,
  $e->getMessage(), $e))->getReason()` for an `\Exception`; `$e->getMessage()` for an `\Error`).
- Produces:
  ```php
  /** @internal */
  final class FilterInspector implements ResetInterface {
      public function __construct(
          EventDispatcherInterface $eventDispatcher, FilterFactory $filterFactory, FilterInvoker $filterInvoker,
          ListSpecBuilderFactory $listSpecBuilderFactory, TranslatorInterface $translator, LoggerInterface $logger,
          bool $debug,
      ) {}
      /** Memoized per list id; loads FilterModel::findBy(['pid=?', 'tstamp>0'], [$id], ['order' => 'sorting']). */
      public function inspect(ListModel $listModel): ListInspection;
      /** The testable core.
       *  @param iterable<FilterModel> $models every saved row of the list, in sorting order
       *  @param \Closure(array<string, Filter>): ListSpec $buildScope gets the scope's row filters keyed by alias */
      public function inspectModels(iterable $models, \Closure $buildScope): ListInspection;
      public function reset(): void;
  }
  ```

This task implements, inside `inspectModels()`, the row part (spec "Shape" step 1 and "Duplicate aliases"); Task 3
adds the scope part. In this task `$buildScope` is not yet called.

Per row (sorting order): build `createFromFilterModel($model)`, then dispatch `new FilterCollectedEvent($filter,
$model)` and take `->filter`. `\Exception` → `Broken` issue (row stays unbuilt). `\Error` → in debug mode rethrow;
else `ProgrammingError` issue, `$this->logger->critical('[FLARE] Programming error in filter %s: %s', ['exception'
=> $e])`. Row alias: built filter's `alias`, else `FilterModel::generateFormName($model)` (if that throws
`\RuntimeException`, the row has no alias). Duplicates per alias group of ≥ 2 rows, `$p` = published rows in it:
`$p >= 2` → every row gets `DuplicateAlias` (reason = alias) and the alias goes into `blockingAliases`; `$p === 1` →
only the unpublished rows get it; `$p === 0` → none. One issue per kind per row. `published` of an issue =
`(bool) $model->published`. Counts: each published row counts once per kind it has.

- [ ] **Step 1: Write the tests** (real `FilterFactory` with stub elements registered as `ok` and `throws` (throws
  `FilterException('broken')` in `buildContext()`); a real `EventDispatcher` for `FilterCollectedEvent`; models via
  reflection like today's `BrokenFilterInspectorTest::model()`, each with `id`, `type`, `alias`, `published`;
  `$buildScope` = a closure returning an empty `ListSpec`)

```php
testHealthyRowsHaveNoIssues
testAnUnknownTypeIsBroken                       // type 'gone' → forRow(id)[0]->kind === Broken, count(Broken) === 1 when published
testUnpublishedIssuesDoNotCount                 // same, published '' → issue present, count(Broken) === 0
testTwoPublishedDuplicatesBlockTheList          // ids 1,2 alias 'dup', both published + id 3 unpublished 'dup'
                                                //   → all three have DuplicateAlias; blockingAliases === ['dup']; count(DuplicateAlias) === 2
testOnePublishedDuplicateFlagsOnlyTheUnpublished // 1 published, 2+3 unpublished 'dup' → only 2 and 3 flagged, published false; blockingAliases === []
testUnpublishedDuplicatesAloneAreNotFlagged     // 2 and 3 unpublished 'dup' → no issues
testARowThatCannotBeBuiltStillGroupsByItsFormName // id 1 type 'gone' alias 'dup' published, id 2 ok alias 'dup' published → both DuplicateAlias, id 1 also Broken
testAListenerRenamingAnAliasIsHonoured          // FilterCollectedEvent listener sets alias 'dup' on row 2 → grouped with row 1 'dup'
testAProgrammingErrorMarksTheRow                // FilterCollectedEvent listener throws \Error('bug') for row 4, debug false → ProgrammingError, reason 'bug', one critical log
testErrorsEscapeInDebugMode                     // same with debug true → expectException(\Error::class)
```

Note for `count(DuplicateAlias)`: count published rows carrying the kind (2 in that test); the overview message uses
`blockingAliases`, the count only decides whether it shows.

- [ ] **Step 2: Run, verify fail** — `make test tests/DataContainer/Inspection/FilterInspectorTest.php` → FAIL, class
  not found.
- [ ] **Step 3: Implement** the constructor, `reset()`, `inspect()` (memo + loading; scope closure per Task 3's
  description can already be passed, it is unused here) and the row part of `inspectModels()`.
- [ ] **Step 4: Run** — the file passes; `make test`, `make phpstan`, `mago lint` clean.
- [ ] **Step 5: Commit** — `git commit -m "feat: inspect every filter row of a list for build errors and duplicate aliases"`

---

### Task 3: `FilterInspector` — list scope, conditions, ineffectiveness

**Files:**
- Modify: `src/DataContainer/Inspection/FilterInspector.php`
- Test: `tests/DataContainer/Inspection/FilterInspectorTest.php`

**Interfaces:**
- Consumes: Task 2's `inspectModels()`; `FilterInvoker::invoke(ElementScope, ?ValueInterface)`,
  `IneffectiveFilterContract::getIneffectiveReason(Filter)`, `ConfigurationContext`.
- Produces: `inspect()`'s scope closure:
  `fn (array $filters): ListSpec => $this->listSpecBuilderFactory->create(driver: (string) $listModel->type, model:
  $listModel, source: "{$listModel::getTable()}.{$listModel->id}")` with each filter `addFilter($filter, $alias)`
  before `->build()`.

Scope filters: the built, published rows, the first per alias (sorting order) only. Call `$buildScope($filters)`:
`\Exception` → no scope, nothing reported for it; `\Error` → debug: rethrow, else `counts[ProgrammingError]++`
(no row) + critical log; no scope. With a scope: check each `$spec->filters` entry, and each built row whose source
is not among the spec filters' sources (unpublished rows, later duplicates), with `new ElementScope($spec, $filter,
new ConfigurationContext())` and value `$filter->control !== null ? null : $filter->value`: `AbortFilteringException`
→ nothing; `\Exception` → `Broken` (reason via `FilterFailure::getReason()`); `\Error` → as in Task 2. Then, for each
checked filter (and, without a scope, for each built row): if its element implements `IneffectiveFilterContract`,
`getIneffectiveReason($filter)?->trans($this->translator)` → `Ineffective` (an `\Exception` there is ignored, an
`\Error` → `ProgrammingError`). A filter's row: its `source` `tl_flare_filter.<id>`; filters without a row (driver/
listener) only count, published. One issue per kind per row.

- [ ] **Step 1: Write the tests** — port the cases of today's `BrokenFilterInspectorTest` and
  `IneffectiveFilterInspectorTest` to `inspectModels()` (models + a `$buildScope` that builds a `ListSpec` with the
  given filters on `tl_test`/`tl_news`), plus:

```php
testAFilterFailingWithItsDefaultValueIsBroken        // fixed filter of `throws` → Broken, reason 'broken'
testControlledFilterIsCheckedWithoutItsInitialValue  // value-sensitive element with control + value → no issue
testAnAbortingFilterIsNotBroken
testAnUnexpectedExceptionIsBroken                    // \RuntimeException('raw') → Broken, reason 'raw'
testAConditionErrorIsAProgrammingError               // buildContext throws \Error('bug'), debug false → ProgrammingError
testDescribesAnArchiveWithoutControlAndValue         // ArchiveFilterElement row without control/value → Ineffective with the translated reason
testFiltersOfTheDriverCountWithoutARow               // $buildScope adds an inline `throws` filter → count(Broken) === 1, no row issue
testOnlyTheFirstOfADuplicateGroupEntersTheScope      // published dup pair → $buildScope receives one filter for 'dup'
testWithoutAListScopeRowIssuesAreStillReported       // $buildScope throws FlareException; rows: 'gone' (Broken), dup pair (DuplicateAlias), archive (Ineffective) all reported; no condition check
testAScopeErrorIsAListLevelProgrammingError          // $buildScope throws \Error, debug false → count(ProgrammingError) === 1, no row issue
testScopeErrorsEscapeInDebugMode
```

- [ ] **Step 2: Run, verify fail** — the new tests fail (no scope part yet).
- [ ] **Step 3: Implement** the scope part. If `FilterInspector` approaches 20 methods, move the per-filter checks
  into a private helper class in the same namespace (`FilterChecker`, `@internal`, not a service).
- [ ] **Step 4: Run** — `make test`, `make phpstan`, `mago lint` clean.
- [ ] **Step 5: Commit** — `git commit -m "feat: check the conditions and the effect of each filter in the backend inspection"`

---

### Task 4: Backend wiring and removal of the old inspectors

**Files:**
- Modify: `src/EventListener/DataContainer/FlareFilter/ListCallbacks.php`,
  `templates/backend/filter_child_record/_base.html.twig`, `src/EventListener/Contao/ElementDcaListener.php`,
  `src/Model/FilterModel.php` (remove `findDuplicateFormNames()`), `tests/Model/FilterModelTest.php` (remove
  `testDuplicateFormNamesAreFoundAfterNormalization`)
- Delete: `src/DataContainer/IneffectiveFilterInspector.php`, `src/DataContainer/BrokenFilterInspector.php`,
  `src/DataContainer/FilterDiagnosis.php`, `tests/DataContainer/IneffectiveFilterInspectorTest.php`,
  `tests/DataContainer/BrokenFilterInspectorTest.php`
- Test: `tests/EventListener/Contao/ElementDcaListenerTest.php` (stays green)

**Interfaces:**
- Consumes: `FilterInspector::inspect()`, `ListInspection::forRow()/count()/$blockingAliases`,
  `FilterIssue::describe()`.

- [ ] **Step 1: Row** — `ListCallbacks` injects `FilterInspector` (drops the two old inspectors and the
  `$GLOBALS['FLARE']['duplicate_filter_aliases']` read); passes `'notices' => [kind value => describe() text]` for
  the row's issues (list model via `ListModel::findByPk((int) $row['pid'])`; none → `[]`). Template: replace the
  skull, engine and bone-fracture blocks with four blocks in the enum's order — `notices.programming_error` (`bug`,
  `#dc3545`), `notices.duplicate_alias` (`copy-x`, `#dc3545`), `notices.broken` (`bone-fracture`, `#dc3545`),
  `notices.ineffective` (`engine`, `#e0a800`) — each `<span style="color: …; opacity:
  var(--flare-filter-notice-opacity)">`, SVG `1rem`, class `lucide-<name>-icon`, `<title>` = the text. SVG paths:
  the spec's "Display" section (bug, copy-x) and today's template (bone-fracture, engine). Remove
  `filter_alias`'s duplicate lookup from the template.
- [ ] **Step 2: Overview and edit form** — `ElementDcaListener` injects `FilterInspector` instead of the two old
  inspectors; `checkDuplicateFilterAliases()` and the `$GLOBALS` write go; `reportFilterStates()` (overview, child
  view of `tl_flare_filter`, no `act`, GET only — keep `reportsFilterStates()`) adds, in enum order and only if
  `count(kind) > 0`:
  - `ProgrammingError` → `addError(trans('list.info.programming_errors', ['%count%' => n]))`
  - `DuplicateAlias` → `addError(trans('list.info.duplicate_filter_alias', ['%alias%' => quoted, comma-joined, escaped $blockingAliases]))`
  - `Broken` → `addError(trans('list.info.broken_filters', ['%count%' => n]))`
  - `Ineffective` → `addNew(trans('list.info.ineffective_filters', ['%count%' => n]))`
  `reportFilterState()` (edit form, GET only): per issue of the row, `Ineffective` → `addNew`, others → `addError`,
  text `StringUtil::specialchars($issue->describe($translator))`. The duplicate message now appears on the filter
  overview only, no longer on the list's own edit form (spec: "the overview shows the duplicate message").
- [ ] **Step 3: Remove** the old inspectors, `FilterDiagnosis`, `findDuplicateFormNames()` and their tests.
  `grep -rn "IneffectiveFilterInspector\|BrokenFilterInspector\|FilterDiagnosis\|duplicate_filter_aliases\|findDuplicateFormNames" src tests templates`
  → no output.
- [ ] **Step 4: Verify** — `make test`, `make phpstan`, `mago lint` clean. Manual (user's Contao instance): the five
  duplicate/publish combinations of the spec, an element throwing `\Error` (bug icon, red message) in non-debug
  mode, a broken and an ineffective filter; saving a fixed filter shows no stale message.
- [ ] **Step 5: Commit** — `git commit -m "feat!: report every filter issue through one backend inspector"`

---

### Task 5: Docs

**Files:**
- Modify: `AGENTS.md` (the `src/Contract/FilterElement/` bullet: one `DataContainer/Inspection/FilterInspector`,
  four issue kinds, `\Error` caught outside debug mode in the backend only), `dev/superpowers/specs/2026-10-14-filter-forms-design.md` §8.1 backend
  paragraph
- Modify (`docs/` worktree): `docs/spec/filtering.md` ("Backend" paragraph: four kinds and icons, duplicate rules,
  published-only messages), `docs/dev/filter-elements/index.md` (marks paragraph), `docs/migrating-from-v0.1.md`
  (backend marks line)

- [ ] **Step 1: Edit** the files.
- [ ] **Step 2: Build** — `cd docs && npm run build` → success.
- [ ] **Step 3: Commit both** — bundle: `git commit -m "docs: one backend inspector for filter issues"`;
  `docs/`: `git -C docs commit -am "docs: one backend inspector for filter issues"`.
