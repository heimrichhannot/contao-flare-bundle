# Exclusive filters — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A filter row can apply exclusively to ticked outputs (today: the list view); the list view runs on
`forOutput(Output::ListView)`, the reader and the lookup on `whole()`; `flare_calendar_current` follows one rule
without `is_limited`; the contexts lose their intent; the inspector gets warnings from an event; a run-once migration
keeps the calendar filters' leftover bounds, so no list becomes wider.

**Architecture:** Bottom-up. First the setting on `FilterCompound` and the derivations on `ListCompound`, then its
storage and the entry points that pick their list. Then the calendar element's pieces (`DateTimeHelper`,
`DcaFieldBuilder`), the element itself, and the removal of the contexts' intent, which the element was the last to
read. Then the inspector (kinds, icons, several issues per row), its event and the bundled warning, the migration,
and finally `AGENTS.md` and the docs worktree. Every task ends green.

**Tech Stack:** PHP 8.2 (Docker), Contao 4.13/5 (the dev install resolves 5.3), Symfony 5.4–7, Doctrine DBAL ≥ 3.6,
PHPUnit 9.6 with `contao/test-case`, SQLite for the pipeline tests, Twig, PHPStan level 5, Mago 1.47.4 (local binary).

**Spec:** `dev/superpowers/specs/2026-11-13-exclusive-filters-design.md` is the authority on behavior. E1–E30 are
settled. This plan fixes the files, the order, the names the spec leaves to the builder, and the tests. Each task
names the spec sections to read first.

## Global Constraints

- **Branch and commits:**
  - Work on branch `v0.2-exclusive-filters`; its head is the commit of this plan, and PR #56 is open against `v0.2`.
  - Make one commit per task, in conventional-commit style (`feat(filter): …`, `refactor(context): …`,
    `fix(calendar): …`, `docs(…): …`).
  - **Add no `Co-Authored-By` trailer and no other attribution line. Never push**: neither this branch nor `docs/main`.
- **No compatibility shims:** v0.2 is unreleased and has no userland code yet. Breaking changes need no BC shims, no
  `class_alias` and no deprecations.
- **Exceptions:** the bundle catches `\Exception`, never `\Throwable` or `\Error`. Programming errors are `\Error`s.
  The only exception is the `FilterInspector`, which catches `\Error` outside debug mode.
- **Migrations:**
  - Never drop or delete. Update values only; adding the migration's own marker column is the one piece of DDL, as
    in the backfill.
  - Use the DBAL `Connection`, not `Contao\Database`.
  - SQL must run on both MySQL and MariaDB.
- **Tests:**
  - Drive production code through its public API. Use no reflection on the bundle's private members and add no
    test-only hooks to `src/`.
  - Control Contao statics through `ContaoFramework` adapters.
  - PHPUnit's `->with($value)` compares loosely. Where the type or the instance is the point, use
    `self::identicalTo()` or `assertSame()`.
- **Code style and line length:**
  - `declare(strict_types=1)`, PSR-12/PER style, 120 characters per line.
  - Before each commit, check the added lines: `git diff --cached -U0 -- '*.php' '*.twig' | grep '^+[^+]' | awk
    'length > 121'` must print nothing.
- **Mago:**
  - Mago fails on notes.
  - Brace string interpolation: write `"{$x}"`, not `"$x"`.
  - Never use `empty()`.
- **Commands:**
  - `make test <path>` passes one path. Run one call per path.
  - `make` cannot take arguments that start with `-`; call `docker compose run --rm …` directly for those.
- **End of every task:**
  - `make test` prints `OK`.
  - `make phpstan` prints `[OK] No errors`.
  - `mago lint --minimum-fail-level note` reports no issues.
  - The 120-character check prints nothing.
  - Then make one commit.
- **Translations:** every new string goes into `de` and `en`. `contao/languages/{de,en}/tl_flare_filter.php` holds
  the field labels; `translations/flare.{de,en}.yaml` holds the `flare` domain.
- **`AGENTS.md`** (`CLAUDE.md` is a symlink to it) changes only in Task 12. The `docs/` worktree changes only in
  Task 13, committed there with `git -C docs`.

## Names this plan fixes (spec, "Left to the builder")

| Item | Name |
|---|---|
| Output enum | `HeimrichHannot\FlareBundle\List\Output` (`src/List/Output.php`), `enum Output: string { case ListView = 'list_view'; }` |
| Columns on `tl_flare_filter` | `exclusive` (checkbox, `['type' => 'boolean', 'default' => false]`), `outputs` (checkbox list, `['type' => 'blob', 'notnull' => false]`, a serialized list of `Output` values) |
| `FilterCompound` | `public ?array $outputs = null` (last constructor parameter; `null` = not exclusive, else `list<Output>`), `withOutputs(?array $outputs): self`, `isExclusive(): bool`, `appliesTo(Output $output): bool` |
| Exclusive filters created in code | the last parameter `?array $outputs = null` of the core `Filter\Factory\FilterCompoundFactory::create()`, and `FilterCompound::withOutputs()` |
| `ListCompound` | `whole(): self`, `forOutput(Output $output): self` |
| `DateTimeHelper` variant | `DateTimeHelper::toDateTimeImmutable(string $time_span_or_string_or_stamp): ?\DateTimeImmutable` |
| Field builder | `DcaFieldBuilderInterface::makeDatim(): self`, `DcaFieldBuilderInterface::remove(string ...$paths): self` (dot paths into the definition, e.g. `'eval.rgxp'`); `Str::dropEmptyLegends(string $palette): string`, called by `DcaBuilder::apply()` only |
| Issue kind and icon | `FilterIssueKind::Warning = 'warning'` (last case), `FilterIssueKind::defaultIcon(): string`, `FilterIssue::$icon` (`?string`) |
| Event | `HeimrichHannot\FlareBundle\Event\FilterInspectionEvent` |
| Bundled listener | `HeimrichHannot\FlareBundle\EventListener\Inspection\ExclusivePublishedFilterListener` |
| Migration | `HeimrichHannot\FlareBundle\Migration\RetireCalendarLimitMigration`, marker column `calendarLimitRetired` |
| New translation keys | `flare`: `list.info.warnings`, `filter.info.ineffective.exclusive_without_output`, `filter.info.warning.exclusive_published`; `tl_flare_filter`: `exclusive`, `outputs`, `output.list_view`. Unreadable period text reuses `filter.value.date_range.invalid` |

## Review Focus

1. **Only unknown output keys.** A row stored `exclusive = 1` with `outputs` holding only keys the enum does not know
   (a garbage value, or a later output after a downgrade). The filter applies nowhere and is reported as ineffective;
   it must not apply everywhere. → Task 3 (factory) and Task 9 (inspector).
2. **Numeric aliases.** A list whose filters are keyed by numeric aliases (row ids). `whole()` and `forOutput()` must
   keep the keys, not renumber them, as `withFilter()` already takes care to do. → Task 2.
3. **Whitespace-only text.** `strtotime('   ')` returns the current time. A configured calendar side whose text is
   only whitespace is an open side (`null`), not "now" and not a `FilterException`; the date input shows it empty.
   → Task 5 (helper), Task 6 (load callback) and Task 7 (transformer).
4. **Bound and value touching.** When the effective start equals the effective stop, the predicate is added; only a
   start strictly after the stop aborts. → Task 7.
5. **A filter added by an engine mod.** On the list view, a mod adds a filter through `withFilter()`, which is not
   exclusive. Entries apply it, and the lookup (`whole()` of that list) applies it too. → Task 4.

---

### Task 1: The setting on `FilterCompound` and the `Output` enum

Spec: §1, §2 (first two paragraphs, `with*()` paragraph), E5, E22, E23.

**Files:**
- Create: `src/List/Output.php`
- Modify: `src/Filter/FilterCompound.php`, `src/Filter/Factory/FilterCompoundFactory.php`
- Test: `tests/Filter/FilterCompoundTest.php`, `tests/Filter/FilterCompoundFactoryTest.php` (the core factory's
  test)

**Interfaces:**
- Produces:
  - `Output::ListView` (`'list_view'`).
  - `FilterCompound::$outputs` (`?list<Output>`), `withOutputs(?array): self`, `isExclusive(): bool`
    (`$outputs !== null`), `appliesTo(Output): bool` (true if not exclusive or `$output` is in `$outputs`, strict).
  - Core `create(..., ?array $outputs = null)`, passed through to the constructor.

- [ ] **Step 1: Write the failing tests** in `FilterCompoundTest`:
  - `testIsNotExclusiveByDefault`: `isExclusive()` is false and `appliesTo(Output::ListView)` is true.
  - `testExclusiveAppliesOnlyToItsOutputs`:
    - `withOutputs([Output::ListView])` gives `isExclusive()` true and `appliesTo(Output::ListView)` true.
    - `withOutputs([])` gives `isExclusive()` true and `appliesTo(Output::ListView)` false.
  - `testWithMethodsKeepTheOutputs`: on `withOutputs([Output::ListView])`, each of `withValue(null)`,
    `withControl(null)` and `withTargetAlias('t')` returns a compound whose `$outputs` is `[Output::ListView]`
    (`assertSame`).
  - In the core factory test, add `testCreatePassesTheOutputs`: `create(..., outputs: [Output::ListView])->outputs`
    is `[Output::ListView]`, and `outputs` defaults to `null`.

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/Filter/FilterCompoundTest.php` → errors (`Output`
  and `withOutputs()` are undefined).

- [ ] **Step 3: Implement.**
  - Create the enum.
  - On `FilterCompound`:
    - Add `$outputs` and document it: `null` = not exclusive; a list = exclusive to these outputs, empty = nowhere.
    - Add `withOutputs()`, `isExclusive()` and `appliesTo()`.
    - Carry `$outputs` over in `withValue()`, `withControl()`, `withTargetAlias()`.
    - Leave `fingerprint()` alone: `ListCompound::hash()` still calls it until Task 2 removes both.
  - In the core factory, add the parameter, its `@param` line and the pass-through.

- [ ] **Step 4: Run the tests until they pass.** Run `make test tests/Filter/FilterCompoundTest.php`, then
  `make test tests/Filter/FilterCompoundFactoryTest.php`. Expected: `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(filter): let a filter apply exclusively to
  outputs"`.

### Task 2: `ListCompound::whole()` / `forOutput()`, `hash()` and `fingerprint()` removed

Spec: §2 (derivations, failures, duplicate check), E6, E9, E23.

**Files:**
- Modify: `src/List/ListCompound.php`, `src/Filter/FilterCompound.php` (delete `fingerprint()`)
- Test: `tests/List/ListCompoundTest.php`, `tests/Filter/FilterCompoundTest.php`

**Interfaces:**
- Consumes: `FilterCompound::isExclusive()`, `appliesTo()` (Task 1).
- Produces:
  - `whole(): self`: the filters that are not exclusive.
  - `forOutput(Output): self`: the filters that `appliesTo($output)`.
  - Both keep config, source, failures, type, dc and driver.
  - Both return `$this` when they drop no filter.

- [ ] **Step 1: Write the failing tests:**
  - `testWholeDropsExclusiveFilters`:
    - The list has filters `a` (not exclusive), `b` (`[ListView]`) and `c` (`[]`).
    - `array_keys(whole()->filters)` is `['a']`.
  - `testForOutputKeepsTheFiltersOfThatOutput`: on the same list, `array_keys(forOutput(Output::ListView)->filters)`
    is `['a', 'b']`.
  - `testDerivedListsKeepFailuresAndConfig`:
    - The list has a failure, config `['x' => 1]` and the filters of the first test, so both derivations build a new
      list: assert `assertNotSame($list, $list->whole())` and the same for `forOutput(Output::ListView)` first.
    - `whole()->failures` and `forOutput(...)->failures` are `assertSame` to the original's.
    - Their `config` is `['x' => 1]`.
  - `testDerivedListsKeepNumericKeys` (Review Focus 2): with filters keyed `7` (exclusive `[]`) and `9` (not
    exclusive), `array_keys(whole()->filters)` is `[9]`.
  - `testWithoutExclusiveFiltersTheListIsItsOwnWhole`: `whole()` and `forOutput(Output::ListView)` are
    `assertSame($list)`.
  - `testWithFilterAddsANonExclusiveFilter`: `withFilter(f)->whole()` still contains `f`.
  - Delete the `hash()` tests (`testHashIsStableAndChangesWithContent`) and the `hash()` assertion inside
    `testFailuresSurviveWithers` (`ListCompoundTest.php:144`); keep the rest of that test.
  - Delete the `fingerprint()` tests in `FilterCompoundTest` (the block at :142–211).

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/List/ListCompoundTest.php` → undefined method.
  `testWithFilterAddsANonExclusiveFilter` fails only because `whole()` is undefined; it guards against a regression.

- [ ] **Step 3: Implement.**
  - Write `whole()` and `forOutput()` with `\array_filter` (it keeps keys) and `withFilters()`. When the count is
    unchanged, return `$this`.
  - Docblock: a list with failures fails in every derivation.
  - Delete `ListCompound::hash()` and `FilterCompound::fingerprint()`. Keep `ValueInterface::fingerprint()`.

- [ ] **Step 4: Run until it passes.** Run `make test tests/List/ListCompoundTest.php`, then
  `make test tests/Filter/FilterCompoundTest.php` → `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(list): derive the whole list and an output's
  list, drop the unused hashes"`.

### Task 3: Storage — DCA fields, translations, `Contao\Factory\FilterCompoundFactory`

Spec: §1 (palette, meaning table), §2 ("Stored output keys the enum does not know are ignored"), E2, E5.

**Files:**
- Modify: `contao/dca/tl_flare_filter.php`
- Modify: `src/Model/FilterModel.php` (or the `@property` docblock trait it uses) for `exclusive` and `outputs`
- Modify: `contao/languages/{de,en}/tl_flare_filter.php`, `src/Contao/Factory/FilterCompoundFactory.php`
- Test: `tests/Contao/Factory/FilterCompoundFactoryTest.php`, `tests/Contao/Factory/FilterCollectorTest.php`

**Interfaces:**
- Consumes: the core `create(..., outputs:)` (Task 1).
- Produces: a stored row with `exclusive = 1` gives a `FilterCompound` whose `$outputs` is the known `Output`s of
  `outputs`, deduplicated, in stored order; with `exclusive` off, `$outputs` is `null`.

- [ ] **Step 1: Write the failing tests** in `FilterCompoundFactoryTest`:
  - `testANonExclusiveRowHasNoOutputs`: with `exclusive = ''`, `outputs = serialize(['list_view'])`, `$outputs` is
    `null`.
  - `testAnExclusiveRowCarriesItsOutputs`: `exclusive = '1'`, `outputs = serialize(['list_view'])` gives
    `[Output::ListView]`.
  - `testAnExclusiveRowWithoutOutputsAppliesNowhere`: `outputs` as `null`, `''` and `serialize([])` each give `[]`.
  - `testUnknownOutputKeysAreIgnored` (Review Focus 1): `serialize(['sitemap', 'list_view', 'list_view'])` gives
    `[Output::ListView]`, and `serialize(['sitemap'])` gives `[]`.
  - In `FilterCollectorTest`, add `testCollectedFiltersCarryTheirOutputs`: a published exclusive row reaches
    `CollectedFilters::$filters` with its outputs.

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/Contao/Factory/FilterCompoundFactoryTest.php`.

- [ ] **Step 3: Implement.**
  - **DCA, the `exclusive` field:** `'exclude' => true`, inputType `checkbox`, `eval`
    `['submitOnChange' => true, 'tl_class' => 'clr']`.
  - **DCA, the `outputs` field:**
    - `'exclude' => true`, inputType `checkbox`, `eval` `['multiple' => true, 'tl_class' => 'clr']`.
    - `options` `\array_column(Output::cases(), 'value')`.
    - `reference` `&$GLOBALS['TL_LANG']['tl_flare_filter']['output']`.
  - **DCA, palettes:**
    - Add `'exclusive'` to `__selector__`.
    - Add the subpalette `'exclusive' => 'outputs'`.
    - Set `__suffix__` to `'{expert_legend:hide},alias,exclusive;{publish_legend},published'`.
  - **Labels:**
    - en: "Apply exclusively" / "Apply this filter only to the outputs ticked below, not to the whole list (the
      reader finds what it excludes)."; "Outputs"; `output.list_view` "List view".
    - de: „Exklusiv anwenden“ / „Diesen Filter nur in den unten gewählten Ausgaben anwenden, nicht auf die ganze
      Liste (der Reader findet, was er ausschließt).“; „Ausgaben“; „Listenansicht“.
  - **Factory:** pass `outputs:` to the core `create()` from a private static `readOutputs(FilterModel)`:
    - `null` unless `(bool) $model->exclusive`.
    - Otherwise `StringUtil::deserialize($model->outputs, true)`, mapped through `Output::tryFrom()` for string
      entries, without nulls, unique, then `array_values`.

- [ ] **Step 4: Run until it passes.** Run both test files → `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(filter): store the exclusive setting on
  filter rows"`.

### Task 4: Entry points pick their list

Spec: §2 (entry-point table, "Where the lists are taken", event table), E1, E3, E6.

**Files:**
- Modify: `src/Controller/ContentElement/ListViewController.php`, `src/Contao/Reader/ReaderResolver.php`
- Modify: `src/Engine/Projector/InteractiveProjector.php` (`createLookup()` at :68)
- Create: `tests/Controller/ContentElement/ListViewControllerTest.php`
- Test: `tests/Engine/View/InteractiveViewTest.php`, `tests/Contao/Reader/ReaderResolverTest.php`,
  `tests/Contao/Hook/BreadcrumbHookTest.php`

**Interfaces:**
- Consumes: `whole()`, `forOutput()` (Task 2), `withOutputs()` (Task 1).
- Produces:
  - `ListViewController` hands `build()->forOutput(Output::ListView)` to both `createFromContent()` and
    `createEngine()`.
  - `ReaderResolver::build()` hands `build()->whole()` to the intent factory and `createEngine()`, so
    `ReaderResolution::$list` is the whole list.
  - `InteractiveProjector::project()` calls `createLookup($list->whole(), …)`.

- [ ] **Step 1: Write the failing pipeline tests** (`EnginePipelineTrait`, SQLite rows as in each file). Use a fixed
  filter `id != 1`: a `SimpleEquationFilterElement` like `publishedOnly()`, with
  `->withOutputs([Output::ListView])`.
  - **`InteractiveViewTest`:**
    - `testAFilterExclusiveToTheListViewBoundsEntriesAndCount`: on `createList(publishedOnly(), excl)`, `ids` of
      `getEntries()` lack 1, and `getCount()` equals the number of published rows minus one.
    - `testTheLookupIgnoresFiltersExclusiveToTheListView`: phase 1 of an attachment (as in
      `testLookupInPhaseOneIgnoresFormValues`) gives `lookup(1)?->row['id']` === `1`.
    - `testTheLookupKeepsAFilterAnEngineModAdded` (Review Focus 5):
      - Add a non-exclusive filter `id != 2` to the projected list with `withFilter()`, as `SimpleEquationMod`
        does.
      - `lookup(2)` is `null`.
  - **`ReaderResolverTest`:** `testTheReaderFindsAnEntryAFilterExclusiveToTheListViewExcludes`:
    - The fixture's auto-item field is `alias` (its `tl_test` DCA has one, `:64`), and row 1 has alias `'visible'`.
    - `createReaderResolver(..., autoItem: 'visible', filters: [publishedOnly(), excl])`.
    - `resolve($content)->entry?->row['id']` is `1`.
    - `->list->filters` lacks the exclusive alias.
  - **`BreadcrumbHookTest`:** the same setup (`autoItem: 'visible'`). Give the `hook()` helper a `filters`
    parameter, defaulting to `[self::publishedOnly()]` (it hard-codes them today, `:115–153`). The last item gets the
    title `'T'`: the test's `ReaderPageMetaEvent` listener sets it once an entry is found (the rows have no title).
  - **New `ListViewControllerTest` (`ContaoTestCase`, `EnginePipelineTrait`):**
    - Build `ListViewController` over the trait's `EngineFactory` (as `createReaderResolver()` builds it) and
      `createListCompoundBuilderFactory()`; the stored list's filters come from a `ListDriverBuildListEvent`
      listener.
    - `ListViewController` is `final` and its template is created inside; run it through Contao's fragment
      controller, as `tests/Controller/ContentElement/ReaderControllerTest.php` does (:214–240):
      `setFragmentOptions(['template' => 'content_element/flare_listview', 'type' => 'flare_listview'])`,
      `setContainer()` with a container like its `fragmentContainer()`, a Twig `ArrayLoader` serving
      `@Contao/content_element/flare_listview.html.twig` with a minimal template, then
      `$controller($request, $content, 'main')`.
    - Pass a `ContentModelFixture` with a `ListModelFixture`, a frontend `ScopeMatcher`, mocks of
      `SymfonyResponseTagger`, `KernelInterface` and `LoggerInterface`, and a real `InteractiveIntentFactory` over
      `new SortOrderSequenceFactory()` and a validator
      (`Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()`).
    - A `ListViewRenderEvent` listener records `$event->engine->getList()` and `$event->engine->createView()`: the
      minimal template never creates a view, and `getForm()` needs one.
    - `testTheListViewRunsOnItsOutputsList`:
      - The filters are `a` (not exclusive), `b` (`[ListView]`) and `c` (`[]`, a `SearchKeywordsFilterElement`, whose
        value class is `KeywordsValue`, with a `KeywordsFilterControl`).
      - The recorded list's `array_keys(filters)` is `['a', 'b']`.
      - The recorded view's `getForm()->has('c')` is false (the control outside the list view's list is not
        mounted).
  - **Aliases:** give the exclusive `id != 1` filter its own alias (e.g. `not_first`), never `published`:
    `createList()` keys the filters by alias (`EnginePipelineTrait.php:442`) and would silently overwrite
    `publishedOnly()`, and the reader's listener would record a duplicate alias.

- [ ] **Step 2: Run them to see them fail.** Run each file with `make test <file>`. Expected to fail: the lookup
  test (`lookup(1)` is `null`), the reader and breadcrumb tests (no entry) and the controller test (`c` is in the
  list). The entries/count test and the engine-mod test already pass: they guard against regressions.

- [ ] **Step 3: Implement the three changes** listed under Interfaces. In `InteractiveProjector`, add a line in the
  docblock: the lookup runs on the whole list, like the reader.

- [ ] **Step 4: Run until it passes.** Run the four files, then the full `make test` → `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(engine): run the list view on its output's
  list, reader and lookup on the whole list"`.

### Task 5: `DateTimeHelper::toDateTimeImmutable()`

Spec: §4 ("The sides are dates, read once"), E16, E17.

**Files:**
- Modify: `src/Util/DateTimeHelper.php`
- Test: `tests/Util/DateTimeHelperTest.php` (create it if missing)

**Interfaces:**
- Produces: `toDateTimeImmutable(string): ?\DateTimeImmutable`.
  - It reads as `toTimestamp()` does: a time-span key, else a number as a timestamp, else the text through
    `strtotime()`. It reads the text itself, not through `toTimestamp()`, whose `?: null` turns the epoch (`'@0'`,
    `strtotime()` returns `0`) into "unreadable"; only `false` is unreadable.
  - The result is in `getTimeZone()`.
  - It reads the trimmed text. Text that is empty after trimming gives `null` (Review Focus 3: `strtotime('   ')`
    is the current time), and so does unreadable text.

- [ ] **Step 1: Write the failing tests:**
  - `testABareTimestampIsReadAsATimestamp`: `toDateTimeImmutable('1600432000')?->getTimestamp()` ===
    `1600432000`.
  - `testATimeSpanKeyIsReadAsItsExpression`: `'this_week'` gives the timestamp of
    `new \DateTimeImmutable('monday this week', DateTimeHelper::getTimeZone())`.
  - `testAnExpressionIsReadThroughStrtotime`: `'2026-01-01'` gives `strtotime('2026-01-01')`.
  - `testUnreadableTextIsNull`: `'next blursday'` gives `null`.
  - `testTheEpochIsReadable`: `'0'` and `'@0'` give the timestamp `0`.
  - `testBlankTextIsNull` (Review Focus 3): `''` and `'   '` give `null`.
  - `testSurroundingWhitespaceIsIgnored`: `' 1600432000 '` gives `1600432000`.
  - `testTheResultIsInContaosTimeZone`: `getTimezone()->getName()` === `DateTimeHelper::getTimeZone()->getName()`.

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/Util/DateTimeHelperTest.php`.

- [ ] **Step 3: Implement.** Return `null` for trimmed-empty text. A time-span key becomes its expression
  (`spanToTimeString()`); numeric text is the timestamp; any other text goes through `strtotime()`, and `false` gives
  `null`. Otherwise create `\DateTimeImmutable::createFromFormat('U', (string) $ts)`, return `null` if that is
  `false`, and return it `->setTimezone(self::getTimeZone())`.

- [ ] **Step 4: Run until it passes.** Run the file → `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(util): read a time expression as a
  DateTimeImmutable"`.

### Task 6: `DcaFieldBuilder::remove()` and `makeDatim()`, palettes without empty legends

Spec: §1 (the legend sentence), §4 (backend fields, the `makeDatim()` and key-removal bullets), E18, E19, E30.

**Files:**
- Modify: `src/Contao/DataContainer/Builder/DcaFieldBuilderInterface.php`
- Modify: `src/Contao/DataContainer/Builder/DcaFieldBuilder.php`, `src/Contao/DataContainer/Builder/DcaBuilder.php`
  (`apply()`), `src/Util/Str.php` (new `dropEmptyLegends()`; `mergePalettes()` unchanged)
- Test: `tests/Contao/DataContainer/Builder/DcaFieldBuilderTest.php`, `tests/Util/StrTest.php`,
  `tests/Contao/DataContainer/Builder/DcaBuilderTest.php`

**Interfaces:**
- Consumes: `DateTimeHelper::toDateTimeImmutable()` (Task 5).
- Produces:
  - `remove(string ...$paths): self`.
    - Each path is a dot path into the field definition (`'eval.rgxp'`, `'wizard'`).
    - `applyTo()` unsets those paths in the base definition before it merges the builder's own definition.
    - `remove()` also unsets them in the builder's pending definition, so a later `eval()` or `merge()` sets the key
      again.
    - Scope: the keys of the definition and of `eval()`/`merge()` only. The label, the options and the callbacks
      have their own setters, which `applyTo()` applies after the removal; `remove()` does not touch what they set.
      The docblock says so.
  - `Str::dropEmptyLegends(string $palette): string` drops every legend without fields: a `;`-group whose items
    (split at `,`, trimmed, empty items ignored) are all legends (`{…}`) is left out. A group without a legend stays.
  - `DcaBuilder::apply()` writes `Str::dropEmptyLegends(Str::mergePalettes(...))` as the type's palette, and nothing
    else calls it: `mergePalettes()` keeps empty legends, so a palette manipulator that runs before the
    `loadDataContainer` hook can still add fields under them (`{advanced_legend:hide}` in `tl_flare_list`'s suffix,
    `contao/dca/tl_flare_list.php:318`, stays in the table's `default` palette). A type that removes `alias` and
    `exclusive` from the filter suffix leaves no bare `{expert_legend:hide}`.
  - `makeDatim(): self`.
    - It sets `eval` `rgxp => 'datim'` and `datepicker => true`.
    - In `applyTo()` it appends ` wizard` to `eval.tl_class` unless that class already contains `wizard`.
    - It adds one load callback that shows a stored expression as its timestamp: a non-empty, non-numeric string
      becomes `(string) (DateTimeHelper::toDateTimeImmutable($value)?->getTimestamp() ?? '')`; a string that is
      empty after trimming becomes `''` (Review Focus 3); any other value passes unchanged.

- [ ] **Step 1: Write the failing tests** (through `applyTo()`):
  - `testRemoveDropsKeysOfTheBaseDefinition`:
    - Base: `['eval' => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50']]`.
    - Call `remove('eval.rgxp', 'eval.datepicker')`.
    - `eval` is `['tl_class' => 'w50']` (`assertSame`).
  - `testEvalAfterRemoveSetsTheKeyAgain`: `remove('eval.rgxp')->eval(['rgxp' => 'date'])` gives `eval.rgxp` ===
    `'date'`.
  - `testRemovingAMissingPathIsANoOp`.
  - `testRemoveLeavesTheOwnSettersAlone`: after `label('A')`, `options(['x'])` and `load($fn)`, a
    `remove('eval.rgxp')` keeps the label `['A', '']`, the options `['x']` and one load callback.
  - `testMakeDatimTurnsATextFieldIntoTheDateTimeInput`:
    - Base: `['eval' => ['tl_class' => 'w50']]`.
    - Afterwards `rgxp` is `'datim'`, `datepicker` is `true` and `tl_class` is `'w50 wizard'`.
    - A second `applyTo()` on a definition that already has `wizard` adds no second one.
    - On a definition without `tl_class`, `tl_class` is `'wizard'` (no leading space).
  - `testMakeDatimShowsAStoredExpressionAsItsTimestamp`:
    - The single load callback maps `'today'` to `(string) strtotime('today')`.
    - It maps `'1600432000'` to `'1600432000'`, `''` to `''` and `'   '` to `''`.
  - In `StrTest`:
    - `testDropEmptyLegends`: `Str::dropEmptyLegends('{a_legend},x;{b_legend};{c_legend:hide};{d_legend},y')` is
      `'{a_legend},x;{d_legend},y'`, and a group without a legend (`'title,type'`) stays.
    - `testMergePalettesKeepsLegendsWithoutFields`: `Str::mergePalettes('{a_legend},x', '{b_legend:hide}')` is
      `'{a_legend},x;{b_legend:hide}'` (it passes today: a regression guard).
  - In `DcaBuilderTest`:
    - `testASuffixWithoutFieldsLeavesNoLegend`: a suffix callable that removes `alias` and `exclusive` from
      `'{expert_legend:hide},alias,exclusive;{publish_legend},published'` gives a palette ending in
      `'{publish_legend},published'` without `expert_legend`.
    - `testALegendFilledBeforeApplyStays`: the table's `__suffix__` is `'{advanced_legend:hide},extra'` (as a
      palette manipulator would leave it) → the applied palette keeps `{advanced_legend:hide},extra`.

- [ ] **Step 2: Run them to see them fail.** Run `make test` once per test file named under Files.

- [ ] **Step 3: Implement.** Add both methods to the interface with docblocks, implement them as described, add
  `dropEmptyLegends()` and call it in `DcaBuilder::apply()` (docblock: why only there).

- [ ] **Step 4: Run until it passes.** Run the three files, then the full `make test` → `OK`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(dca): remove field keys, make a date-time
  input, drop empty legends when a palette is applied"`.

### Task 7: `flare_calendar_current` with one rule

Spec: §4 completely, E7, E16–E18, E20, E27.

**Files:**
- Modify: `src/Filter/Element/CalendarCurrentFilterElement.php`
- Modify: `src/Contao/Callback/FlareFilter/FieldsLoadAndSaveCallbacks.php` (delete `onLoadField_startStopAt()`)
- Modify: `contao/dca/tl_flare_filter.php`:
  - `elm_startAt` and `elm_stopAt` become plain text with `'tl_class' => 'w50'`: no `rgxp`, no `datepicker`, no
    `wizard`.
  - Delete the field `elm_isLimited`. The column stays: Contao offers to drop it among the manual deletes.
- Modify: `contao/languages/{de,en}/tl_flare_filter.php` (drop `date_limits_legend` and `elm_isLimited`), the model's
  `@property` for `elm_isLimited`
- Modify: `src/Config/ComponentConfig.php` (:26): the docblock says resolved values are "scalars, arrays, and enums
  only"; add immutable value objects such as `\DateTimeImmutable`
- Test: `tests/Filter/Element/CalendarCurrentFilterElementTest.php`

**Interfaces:**
- Consumes:
  - `DateTimeHelper::toDateTimeImmutable()` (Task 5).
  - `makeDatim()` (Task 6).
- Produces:
  - **Options:** `start_at` and `stop_at` take `allowedTypes(\DateTimeImmutable::class, 'null')`; `has_extended_events`
    stays. `is_limited`, `configure_start` and `configure_stop` leave the schema.
  - **Transformer**, per side:
    - Configured means `elm_configureStart` or `elm_configureStop` is non-empty.
    - Unconfigured gives `null`. Configured gives `trim()` of the text: `''` becomes `null`, readable text becomes the
      `\DateTimeImmutable`, unreadable text throws a `FilterException`.
    - The exception message is `$this->trans('filter.value.date_range.invalid', ['%expression%' => $text])`.
  - **`buildEffect()`**, without any intent:
    - Start from the bound: `start` is the `start_at` timestamp or `0`, `stop` is the `stop_at` timestamp or
      `DateTimeHelper::maxTimestamp()`.
    - Narrow by the value: a `from` later than `start` replaces it, a `to` earlier than `stop` replaces it.
    - If `start > stop`, call `$builder->abort()`. Otherwise add `CalendarCurrentPredicate` as today.
  - **`getDateBounds()`:** `new DateBounds($context->get('start_at'), $context->get('stop_at'))`.
  - **`buildDca()`:**
    - The palette is
      `'{date_start_legend},elm_configureStart,elm_hasExtendedEvents;{date_stop_legend},elm_configureStop;'`.
    - For each side whose stored mode (`$context->filterModel?->elm_configureStart` / `…Stop`) is `'date'`, call
      `field('elm_startAt' / 'elm_stopAt')->makeDatim()`.
    - For both fields, add `->save(...)`. It throws `\RuntimeException` with the same translated message for
      non-empty, unreadable trimmed text, and returns the value unchanged otherwise.

- [ ] **Step 1: Rewrite the tests.**
  - Delete the `ValidationIntent` and `is_limited` tests. Base config: `start_at`/`stop_at` `null`,
    `has_extended_events` false.
  - Test `buildEffect`:
    - `testTheBoundAppliesWithoutAValue`: `start_at` `@1000` and `stop_at` `@2000` (as `\DateTimeImmutable`) give
      `[1000, 2000]`.
    - `testTheValueNarrowsWithinTheBound`: bound `[1000, 2000]` with value `[1500, 3000]` gives `[1500, 2000]`.
    - With value `[500, 1800]` it gives `[1000, 1800]`.
    - `testOpenSidesWithoutBound`: no bound and no value give `[0, DateTimeHelper::maxTimestamp()]`.
    - `testAnEmptyIntersectionAborts`: bound stop `1000` with value from `2000` →
      `expectException(AbortFilteringException::class)`. Run it once with `has_extended_events` true and once with it
      false (a data provider).
    - `testTouchingBoundsDoNotAbort` (Review Focus 4): bound `[1000, 1000]` gives `[1000, 1000]`.
    - No test for "the same conditions whatever the caller": Task 8 removes the intent from the context, which makes
      it structural.
  - Test `getDateBounds`: `testDateBoundsAreTheConfiguredPeriod`, plus a variant with no bound that gives
    `new DateBounds()`.
  - Test the transformer like `ArchiveFilterElementTest::transform()` (`TransformerMap`, `ConfigBuilder`), but with
    the autoloadable `tests/Fixture/FilterModelFixture.php`: `FilterModelStub` is declared inside
    `SimpleEquationFilterElementTest.php`, and a test file run alone cannot load it. Use the fixture for the
    `buildDca` tests' `DcaContext::$filterModel` too.
    - `testATimeSpanSideReadsItsExpression`: `elm_configureStart = 'this_week'` with
      `elm_startAt = 'monday this week'` gives the expected timestamp.
    - `testAStrSideReadsTheEditorsExpression`: `'str'` with `'2026-01-01'`.
    - `testADateSideReadsTheBareTimestamp`: `'date'` with `'1600432000'` gives `getTimestamp()` `1600432000`.
    - `testAStrSideKeepsReadingATimestampLeftFromDateMode`: `'str'` with `'1600432000'`.
    - `testAnUnconfiguredSideIsOpen`: `elm_configureStop = ''` with `elm_stopAt = '+1 month'` gives `stop_at` `null`.
    - `testAnEmptyOrBlankConfiguredSideIsOpen` (Review Focus 3): `''` and `'   '` give `null`.
    - `testUnreadableTextFailsClosed`: `'str'` with `'next blursday'` → `FilterException`.
  - Test `buildDca` (set `$GLOBALS['TL_DCA']['tl_flare_filter']` up first and unset it in `tearDown()`:
    `DcaBuilder::apply()` returns early without it, `DcaBuilder.php:121–123`):
    - `testDateSidesGetTheDateTimeInput`: with `elm_configureStart = 'date'` and `elm_configureStop = 'str'`, after
      `apply()` the field `elm_startAt` has `eval.rgxp` `'datim'` and `elm_stopAt` has no `rgxp`.
    - `testTheSaveCallbackRejectsUnreadableText`: the save callback on `elm_startAt` throws `\RuntimeException` for
      `'next blursday'`. It returns `''` for `''` and `'today'` for `'today'`.

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/Filter/Element/CalendarCurrentFilterElementTest.php`.

- [ ] **Step 3: Implement** as described under Interfaces.
  - Delete the `ValidationIntent` import and `resolveFormLimits()`.
  - The predicate's options stay ints.
  - Delete `onLoadField_startStopAt()` together with its `AsCallback` attributes.

- [ ] **Step 4: Run until it passes.** Run the file, then the full `make test`. Fix every other test that reads
  `is_limited`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "fix(calendar): bound by the configured period,
  narrow by the value, read every mode"`.

### Task 8: Contexts without an intent, `ConfigurationIntent` removed

Spec: §5, E8, E24.

**Files:**
- Modify the context classes and their constructors: `src/Filter/ElementContext.php`, `src/Filter/ControlContext.php`
  (`forElement()` included).
- Modify the 6 `src/` call sites:
  - `src/Query/Executor/ListQueryDirector.php`
  - `src/Form/Factory/FormHarnessFactory.php`
  - `src/Contao/Inspection/Inspector/FilterInspector.php` (two sites)
  - `src/Contao/DataContainer/BackendElementContextFactory.php`
  - `ControlContext::forElement()`
- Modify `src/Contao/DcaContext.php`: the docblock of `getElementContext()`.
- Delete `src/Engine/Intent/ConfigurationIntent.php`.
- Tests: the 20 `tests/` call sites (`grep -rn "new ElementContext(\|new ControlContext(" tests`), and every file in
  `grep -rln ConfigurationIntent tests`.
  - `FilterEffectFactoryTest::testConfigurationIntentHasItsOwnType()` goes.
  - `ServicesConfigTest` loses the class.
  - `FlareRuntimeTest:105` takes `new ValidationIntent()`.
  - `FilterInvokerTest:112` and `ArchiveFilterElementTest:510` no longer read `$context->intent`.
  - The helpers `BuildsFilterEffectsTrait::buildFilterEffect()`/`buildElementContext()` and
    `BuildsControlContextsTrait` lose their `$intent` parameter.

**Interfaces:**
- Produces: `new ElementContext(ListCompound $list, FilterCompound $filter)` and `new ControlContext(ListCompound $list,
  FilterCompound $filter)`. The class docblocks say "the list and the filter".

- [ ] **Step 1: Remove the parameter and the class.** `make phpstan` then lists the `src/` call sites still passing
  an intent. It analyses `src/` only; `make test` finds the test call sites (PHP accepts extra positional arguments
  silently, so search for them too).

- [ ] **Step 2: Fix every call site and test** until `make phpstan` prints `[OK] No errors`, these find nothing:
  - `grep -rnE 'ConfigurationIntent|context->intent|intent: \$context' src tests`;
  - the callers of `buildFilterEffect(` and `buildElementContext(` that still pass an intent positionally: once the
    helper loses `$intent`, that argument would shift into the next parameter (`grep -rn
    'buildFilterEffect(\|buildElementContext(' tests` and read each call with four or more arguments).

  and every hit of `grep -rnE 'intent: \$intent|intent: new' src tests` is read by hand: none may construct an
  `ElementContext` or `ControlContext` (such as `FilterControlBuildFormListenerTest.php:76`). The other hits stay:
  `FormHarness`, `ListQueryConfig`, the loaders, the projectors, `Engine`, `EngineFactory`, `EntryCollectorFactory`
  and `ReaderResolver` keep their intent.

- [ ] **Step 3: Run the suite.** `make test` → `OK`. The count is the earlier one minus the deleted tests.

- [ ] **Step 4: Commit.** Run the full checks, then `git commit -m "refactor(filter): drop the intent from element and
  control contexts"`.

### Task 9: Inspector — warning kind, icons, several issues per row, exclusive without output

Spec: §3 (everything but the event), E5, E14 (the kind), E15.

**Files:**
- Modify the inspection classes:
  - `src/Contao/Inspection/FilterIssueKind.php`
  - `src/Contao/Inspection/FilterIssue.php`
  - `src/Contao/Inspection/InspectedRows.php`
  - `src/Contao/Inspection/Inspector/FilterInspector.php`
  - `src/Contao/Inspection/Reporter/FilterIssueReporter.php`
- Modify `templates/backend/filter_child_record/_base.html.twig`.
- Create one partial per icon under `templates/backend/filter_icon/`: `bug`, `copy-x`, `bone-fracture`, `engine`,
  `badge-exclamation-point`, `opacity` (`{name}.html.twig`).
- Modify `translations/flare.{de,en}.yaml`.
- Tests: `tests/Contao/Inspection/{FilterIssueTest,InspectedRowsTest,FilterInspectorTest,FilterIssueReporterTest}.php`,
  `tests/Contao/Callback/FlareFilter/ListCallbacksTest.php`, and the helper
  `tests/Contao/Inspection/BuildsFilterInspectorTrait.php` (`recordMessages()` mocks and records `addInfo` too)

**Interfaces:**
- Consumes: `FilterCompound::$outputs` (Task 1).
- Produces:
  - **`FilterIssueKind::Warning`** is the last case.
  - **`FilterIssueKind::defaultIcon(): string`**:
    - `ProgrammingError` → `bug`, `DuplicateAlias` → `copy-x`, `Broken` → `bone-fracture`.
    - `Ineffective` → `engine`, `Warning` → `badge-exclamation-point`.
  - **`FilterIssue::__construct(kind, reason, published, ?string $icon = null)`.** `describe()` returns the reason as
    given for `Warning`, as for `Ineffective`.
  - **`InspectedRows`:**
    - `addIssue(?int $id, FilterIssueKind $kind, string $reason, ?string $icon = null)` keeps every issue of a row.
      It skips only an issue equal in kind, reason and icon to one the row already has; the collector failure and
      the condition check can report the same text.
    - `issuesOf(int $id): list<FilterIssue>`.
    - `getModels(): array<int, FilterModel>`.
    - `toInspection()` orders a row's issues by kind, keeps the order within a kind, and counts a published row once
      per kind.
  - **`FilterInspector::checkFilters()`:**
    - Drops its `$found` per-kind dedupe for filters with a row.
    - Keeps it for a filter without a row (`$id === null`): such a filter passes each kind once to `addIssue()`,
      because `addIssue(null, …)` only increments the count of filters, and two issues of one kind must not count
      one filter twice.
    - Adds `checkExclusive(FilterCompound): ?array`: `$filter->outputs === []` gives
      `[FilterIssueKind::Ineffective, trans('filter.info.ineffective.exclusive_without_output')]`.
  - **`FilterIssueReporter`:**
    - `public const ICONS` lists the six partials.
    - `getRowNotices(array $row): list<array{kind: string, icon: string, text: string}>`. The icon is the issue's own
      when it is in `ICONS`, otherwise `$kind->defaultIcon()`.
    - `reportOverview()` maps `Warning` to `list.info.warnings` (`%count%`).
    - `addMessage()` maps `Warning` to `addInfo`, `Ineffective` to `addNew` and the rest to `addError`.
  - **Template:**
    - `_base.html.twig` loops over `notices`.
    - Each notice is a `<span>` coloured `#dc3545` for `programming_error`, `duplicate_alias` and `broken`, and
      `#e0a800` for `ineffective` and `warning`.
    - Each span has `opacity: var(--flare-filter-notice-opacity)` and includes
      `'@HeimrichHannotFlare/backend/filter_icon/' ~ notice.icon ~ '.html.twig'` with `{title: notice.text}`.
    - Each partial is the lucide SVG (`width/height 1rem`, `stroke="currentColor"`, the attributes the icons use
      today) with `<title>{{ title }}</title>`. Move the four existing icons verbatim; copy
      `badge-exclamation-point` and `opacity` from lucide.dev.
  - **Translations:**
    - `list.info.warnings`:
      - en: "{1} One filter has a hint; it is marked in the list below.|]1,Inf[ %count% filters have hints; they are
        marked in the list below."
      - de: equivalent.
    - `filter.info.ineffective.exclusive_without_output`:
      - en: "This filter applies exclusively, but to no output, so it restricts nothing. Tick an output or switch
        off \"Apply exclusively\"."
      - de: equivalent.

- [ ] **Step 1: Write the failing tests:**
  - `FilterIssueTest`:
    - `testAWarningDescribesItselfAsGiven`.
    - `testTheIconDefaultsPerKind` (all five kinds through `defaultIcon()`).
  - `InspectedRowsTest`:
    - `testARowKeepsSeveralIssuesOfOneKind`: two warnings with different reasons → `forRow()` has both, and
      `count(Warning)` is `1`.
    - `testAnEqualIssueIsKeptOnce`.
    - `testEqualTextWithAnotherIconIsKept`: two warnings with the same reason and the icons `opacity` and `null` →
      both stay.
    - `testIssuesAreOrderedByKind`: add a warning, then a broken issue → broken first.
  - `FilterInspectorTest`:
    - `testAnExclusiveFilterWithoutOutputIsIneffective`: a row with `exclusive = '1'` and `outputs = ''`, or with
      only unknown keys (Review Focus 1), gives one `Ineffective` issue with the translated key.
    - `testAnExclusiveFilterWithAnOutputIsFine`.
    - `testARowlessFilterWithTwoIssuesOfOneKindCountsOnce`: a filter the driver adds, whose conditions and whose
      control's form both fail → `count(FilterIssueKind::Broken)` is `1`. It passes today (one issue per kind is
      kept): a regression guard for the dedupe this task keeps.
  - `FilterIssueReporterTest` (only what the inspector can produce at this commit; warnings and custom icons have no
    producer before Task 10's event, and the reporter takes the `final` `FilterInspector`, so they are tested there):
    - `testRowNoticesListEveryIssueWithItsIcon`: a broken row and an ineffective one (an exclusive filter without
      output) → each notice has its kind's default icon.
    - `testIneffectiveStaysANewMessage`.
    - `testEveryIconHasItsPartial`: for each `ICONS` name, `templates/backend/filter_icon/{name}.html.twig` exists.
    - Update `testRowNoticesAreKeyedByIssueKind` (:132–141), which asserts the old keyed shape (`['broken' => …]`),
      to the list shape, and rename it `testRowNoticesAreAList`.
  - `ListCallbacksTest`: it mocks `Environment::render()` today (:37), so render for real in the new test: a Twig
    `Environment` over a `FilesystemLoader` with `templates/` under the namespace `HeimrichHannotFlare` and Symfony's
    `TranslationExtension` over an `IdentityTranslator`. Two notices → the row's HTML contains two `<title>`
    elements with their texts. Update the existing assertion on the old notice shape (`['broken' => …]`, :65) to
    the list shape.

- [ ] **Step 2: Run them to see them fail.** Run each file with `make test <file>`.

- [ ] **Step 3: Implement** as described under Interfaces. Update the docblocks of `FilterIssueKind`, `ListInspection`
  and `FilterInspector`: five kinds, several issues per row.

- [ ] **Step 4: Run until it passes.** Run the files, then the full `make test` → `OK`. A row whose checks fail in
  two ways now keeps both issues (E15): where an existing test of `FilterInspectorTest` or `FilterIssueReporterTest`
  asserts a single issue for such a row, update the assertion to both issues and name the test in the commit body.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(inspection): warnings, icons per issue and
  every issue of a row"`.

### Task 10: `FilterInspectionEvent` and the bundled warning

Spec: §3 ("Warnings, from an event" bullet 2), E13, E14.

**Files:**
- Create: `src/Event/FilterInspectionEvent.php`
- Create: `src/EventListener/Inspection/ExclusivePublishedFilterListener.php`
- Modify: `src/Contao/Inspection/Inspector/FilterInspector.php`, `translations/flare.{de,en}.yaml`
- Test: `tests/Contao/Inspection/FilterInspectorTest.php`, `tests/Contao/Inspection/FilterIssueReporterTest.php`,
  `tests/EventListener/Inspection/ExclusivePublishedFilterListenerTest.php`

**Interfaces:**
- Consumes: `InspectedRows::getModels()`/`issuesOf()`/`addIssue(..., $icon)` and `FilterIssueKind::Warning` (Task 9).
- Produces:
  - **`final class FilterInspectionEvent`:**
    - Readonly public properties: `FilterModel $model`, `?ListCompound $list`, `?FilterCompound $filter`, and
      `array $issues` (`list<FilterIssue>`: the issues found so far).
    - `addWarning(string $message, ?string $icon = null): void` takes an already translated message.
    - `getWarnings(): list<array{message: string, icon: ?string}>`.
  - **Dispatch in `FilterInspector::inspectModels()`:** after `checkFilters()`, once per saved row in sorting order.
    - The event gets the built list (`null` if it could not be built) and the row's effective filter: the compound
      of the built list that belongs to the row, found as `checkFilters()` maps them (`spl_object_id`, else
      `$rows->idOf($filter->source)`, `FilterInspector.php:225–240`), so a `ListDriverBuildListEvent` listener's
      replacement is what listeners see; for a row the list does not have, `$rows->getFilters()[$id] ?? null`.
      Build the row → compound map once and share it with `checkFilters()`.
    - Each warning becomes `addIssue($id, FilterIssueKind::Warning, $message, $icon)`.
    - A listener's `\Exception` becomes a `ProgrammingError` issue on the row (`self::reason($e)`). Its `\Error` goes
      through `caught()`.
    - Filters without a row get no event.
  - **`#[AsEventListener] final readonly class ExclusivePublishedFilterListener`** (constructor
    `TranslatorInterface`, `__invoke(FilterInspectionEvent): void`). It warns when all of these hold:
    - `$event->filter?->element->component instanceof PublishedFilterElement`;
    - `$event->filter->isExclusive()` and `$event->filter->outputs !== []` (exclusive to no output, the filter is
      already ineffective, spec §3);
    - `$event->list !== null`;
    - no filter of `$event->list->filters` is a non-exclusive `PublishedFilterElement`.

    Message `filter.info.warning.exclusive_published`, icon `opacity`.
    - en: "This publication filter applies exclusively, so the reader and the lookup show unpublished entries too.
      Add a publication filter that is not exclusive if they should not."
    - de: equivalent.

- [ ] **Step 1: Write the failing tests:**
  - `FilterInspectorTest`:
    - `testTheEventIsDispatchedOncePerSavedRow`: three rows (one unpublished) → three events with their models in
      sorting order.
    - `testTheEventCarriesNullsWhenNothingCouldBeBuilt`:
      - A row whose element type is unknown → `filter` is `null`.
      - A driver whose `buildList()` throws → `list` is `null` for every row.
    - `testTheEventCarriesTheIssuesFoundSoFar`: a broken row's event has its `Broken` issue.
    - `testRowlessFiltersGetNoEvent`: a filter the driver adds → no extra event.
    - `testTheEventCarriesTheFilterAsTheListHasIt`: a `ListDriverBuildListEvent` listener replaces a row's filter
      (a type the inspector's test registry has, such as `ok`; it has no `PublishedFilterElement`,
      `BuildsFilterInspectorTrait.php:101–119`) with its `withOutputs([Output::ListView])` → the event's `filter`
      is that replacement (`assertSame`).
    - `testWarningsLandOnTheRowFormAndOverview`: a listener adds two warnings with icons → `forRow()` has two
      `Warning` issues with their icons, and `count(Warning)` is `1`.
    - `testAListenerExceptionIsAProgrammingError`.
  - `FilterIssueReporterTest` (moved here from Task 9; a test listener on the inspector's dispatcher produces the
    warnings):
    - `testAnUnknownIconFallsBackToTheKindsDefault`: a warning with icon `no-such-icon` → its notice has
      `badge-exclamation-point`.
    - `testAWarningKeepsItsOwnIcon`: icon `opacity` → the notice has `opacity`.
    - `testWarningsAreInfoMessagesAndCountedInTheOverview`: `addInfo` is recorded, with the `list.info.warnings`
      text.
  - `ExclusivePublishedFilterListenerTest`:
    - `testWarnsForAnExclusivePublishedFilterWithoutANonExclusiveOne`: the message is the translated key and the
      icon is `opacity`.
    - `testSilentWithANonExclusivePublishedFilter`.
    - `testSilentForANonExclusivePublishedFilter`.
    - `testSilentForAnExclusiveFilterWithoutOutput`.
    - `testSilentForOtherElements`.
    - `testSilentWithoutAList`.

- [ ] **Step 2: Run them to see them fail.** Run the three files.

- [ ] **Step 3: Implement** as described under Interfaces.

- [ ] **Step 4: Run until it passes.** Run the three files, then the full `make test` → `OK`. Add the listener to
  `ServicesConfigTest::provideServices()` and the event to `provideNonServices()`.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(inspection): dispatch an inspection event per
  filter row, warn for an exclusive publication filter"`.

### Task 11: `RetireCalendarLimitMigration`

Spec: §6 completely, E10, E12, E25, E26, E28, E29.

**Files:**
- Create: `src/Migration/RetireCalendarLimitMigration.php`
- Create: `tests/Migration/RetireCalendarLimitMigrationTest.php`
- Create: `tests/Migration/FakeFilterTableTrait.php`, moved out of `BackfillFilterValuesMigrationTest` (:742–862) and
  generalized, so both migration tests and the sequence test run on one table:
  - rows, columns, `update()` applied to the rows, recorded statements, and the `ContaoFramework`/`Controller` mocks
    the real backfill needs, as today;
  - marker-agnostic: `fetchAllAssociative()` serves `SELECT * FROM tl_flare_filter WHERE {marker} = '0' ORDER BY id`
    for any marker column (match `/WHERE (\w+) = '0' ORDER BY id$/`); `executeStatement()` serves
    `ALTER TABLE tl_flare_filter ADD {marker} CHAR(1) NOT NULL DEFAULT '0'` (adds the column, existing rows `'0'`) and
    `ALTER TABLE tl_flare_filter ALTER {marker} SET DEFAULT '1'` for any marker; each marker column keeps its own
    default for rows added later;
  - `fetchOne()` serves the backfill's marker-less probe (`control <> '' OR value <> ''`) only, as today;
  - `fetchAssociative()` serves the backfill's `tl_flare_list` lookup, as today
    (`BackfillFilterValuesMigrationTest.php:842–846`);
  - any other statement fails the test;
  - `addRow()` defaults cover both migrations: the backfill's columns, plus `elm_isLimited`, `elm_configureStart`,
    `elm_configureStop`, `elm_startAt`, `elm_stopAt` (all `''`).

  `BackfillFilterValuesMigrationTest` uses the trait; its tests stay unchanged and pass before the new tests are
  written.
- Modify: `tests/DependencyInjection/ServicesConfigTest.php` (the migration in `provideServices()`)

**Interfaces:**
- Consumes: `BackfillFilterValuesMigration` (constructor-injected; `shouldRun()`).
- Produces: `getName()`, `shouldRun()`, `run()`.

`shouldRun()` decides in this order:

1. No table → false.
2. Any of the columns `type`, `control`, `value`, `elm_isLimited`, `elm_configureStart`, `elm_configureStop`,
   `elm_startAt`, `elm_stopAt` missing (compare lowercased) → false. This waits for the renames and the backfill's
   columns, and keeps the migration quiet once the manual deletes have dropped `elm_isLimited` and the marker:
   `contao:migrate --with-deletes` runs the migrations again after the schema diff (spec §6, Marker).
3. `$this->backfill->shouldRun()` → false: the migration waits.
4. The marker is missing → true.
5. Otherwise → whether `fetchPendingRows()` (below) returns any row.

`run()`:
1. Add the marker if it is missing: `ALTER TABLE tl_flare_filter ADD calendarLimitRetired CHAR(1) NOT NULL DEFAULT '0'`.
2. Always run `ALTER TABLE tl_flare_filter ALTER calendarLimitRetired SET DEFAULT '1'`.
3. `fetchPendingRows()`: `SELECT * FROM tl_flare_filter WHERE calendarLimitRetired = '0' ORDER BY id`. For each row of
   type `flare_calendar_current` (filtered in PHP), write `update($changes + ['calendarLimitRetired' => '1'],
   ['id' => …])` in one statement, also when `$changes` is empty.
4. Then mark every remaining pending row done: `update(['calendarLimitRetired' => '1'], ['calendarLimitRetired' =>
   '0'])`.
5. Result: `"{$name} ({$n} filters updated, {$k} calendar filters with a control or a fixed value now stay within
   their period: check them)"`, where `$n` counts rows with changes and `$k` counts the rows to check (below).

Per calendar row:
- **Changes:** for each side (`elm_configureStart`/`elm_startAt`, `elm_configureStop`/`elm_stopAt`) whose
  `configure` is `''` and whose trimmed text is non-empty, non-numeric and readable as v0.1 read it (plain
  `strtotime()` is not `false`; no time-span keys, spec §6, E28): `changes[configure] = 'str'`. Nothing else is
  written: no `exclusive`, `outputs`, `control` or `value`.
- **To check:** `elm_isLimited` is falsy, and the row has a non-empty `control` or a stored value with a set side.
  A side is set when its raw entry (`StringUtil::deserialize($value, true)`, padded to two) is an int, or a string
  that is non-empty after trimming. No value provider is involved.

Class docblock, after the backfill's:
- the baseline (v0.1) and the one rule that makes moving anything unnecessary (spec §6, E29);
- the order (renames, backfill) and why it waits for the backfill (the count needs `control` and `value`);
- the marker and why it is not D31;
- why it requires `elm_isLimited` (the delete run drops the marker and runs the migrations again);
- that saves between the schema update and the migration are not guarded.

- [ ] **Step 1: Write the failing tests** (rows as arrays, asserting `rowUpdates()` like the backfill test):
  - **The switch-on step:**
    - `testAReadableLeftoverIsSwitchedOn`: start mode `''` with `elm_startAt = 'last monday'` →
      `elm_configureStart = 'str'`; the stop side untouched.
    - `testAReadableLeftoverIsSwitchedOnWhateverTheLimit`: the same with `elm_isLimited` `'1'` and `''`.
    - `testATimeSpanKeyLeftoverStaysOff`: `this_week`, which v0.1's `strtotime()` could not read.
    - `testAnUnreadableLeftoverStaysOff` (`next blursday`).
    - `testANumericLeftoverStaysOff` (`1767225600`, `20260101`, `0900`).
    - `testAConfiguredSideIsLeftAlone`: mode `today` with an empty text, and mode `str` with unreadable text → no
      change.
    - `testNothingElseIsWritten`: a row with a control, a stored value and a leftover → the update holds only
      `elm_configureStart` and the marker (`assertSame` on the update's keys).
  - **The count:**
    - `testTheResultCountsTheRowsToCheck`: unlimited with a control, unlimited without control but with
      `serialize(['today', null])`, limited with a control, unlimited with neither, unlimited with
      `serialize([null, ''])` → the message names 2 rows to check.
    - `testShouldRunIsFalseWithoutElmIsLimited`: the column is absent, the marker too → `shouldRun()` is false.
  - **Order, marker and repeats:**
    - `testShouldRunWaitsForTheBackfill`.
    - `testShouldRunWaitsForTheRenamedColumns` (`configureStart` instead of `elm_configureStart`).
    - `testShouldRunWaitsForControlAndValue` (no `value` column).
    - `testRunAddsTheMarkerAndDefaultsItToDone` (the two exact `ALTER` statements).
    - `testOtherTypesAreMarkedDone`.
    - `testShouldRunGoesQuietAfterARun`.
    - `testTheDeleteRunLeavesItQuiet`: after a run, drop the marker and `elm_isLimited` from the fake (the schema
      diff's deletes) and switch a side off → `shouldRun()` is false.
    - `testRowsCreatedAfterwardsAreNeverPending`: the default is `'1'` after a run.
    - `testEveryRowIsUpdatedInOneStatementWithItsMarker`.
  - **The update from v0.1 in one `contao:migrate`** (spec, Testing): `testAV01RowIsBackfilledFirstAndThenChecked`.
    - Setup: one fake table, the real `BackfillFilterValuesMigration` (over the same fake) injected into the new
      migration, and a v0.1 calendar row: `intrinsic = '0'`, `control = ''`, `elm_isLimited = '0'`,
      `elm_configureStart = ''`, `elm_startAt = 'today'`.
    - The new migration's `shouldRun()` is false while the backfill's is true.
    - After the backfill's `run()`, the row has `control = 'flare_date_range'`, and the new migration's `shouldRun()`
      is true.
    - After its `run()`, the row has `elm_configureStart = 'str'`, its `control` and `value` are what the backfill
      wrote, and the message names 1 row to check.

- [ ] **Step 2: Run them to see them fail.** Run `make test tests/Migration/RetireCalendarLimitMigrationTest.php`.

- [ ] **Step 3: Implement** as described above. Write the type literal `'flare_calendar_current'` frozen in the
  migration, as the backfill does.

- [ ] **Step 4: Run until it passes.** Run the file, `tests/Migration/BackfillFilterValuesMigrationTest.php` and
  `tests/DependencyInjection/ServicesConfigTest.php`, then the full `make test` → `OK`. The bundle has no
  `bin/console`; the migration is registered as a service through `ServicesConfigTest`, and Contao tags it by its
  parent class `AbstractMigration`, as it does the backfill.

- [ ] **Step 5: Commit.** Run the full checks, then `git commit -m "feat(migration): keep the calendar filter's
  leftover bounds when is_limited goes"`.

### Task 12: `AGENTS.md`

Spec: §7 first paragraph.

**Files:**
- Modify: `AGENTS.md`

- [ ] **Step 1: Update the passages:**
  - the vocabulary's intents (:24, without `ConfigurationIntent`);
  - the filter subsystem: `FilterCompound` with `$outputs`, and `ElementContext`/`ControlContext` as "list ×
    filter";
  - `src/List/`: `whole()`/`forOutput()`, `Output`;
  - `src/Engine/ListEntry/` (`EntryLookup`) and `src/Contao/Reader/`: "the whole list" instead of "by the reader's
    rules";
  - the inspector: five kinds, several issues per row, `FilterInspectionEvent` and the bundled warning;
  - `src/Migration/`: `RetireCalendarLimitMigration` with its marker `calendarLimitRetired`;
  - the event list: `FilterInspectionEvent`.

  Keep lines at 120 characters or fewer.

- [ ] **Step 2: Commit.** Run `git commit -m "docs(agents): exclusive filters, contexts without intent, inspection
  warnings"`.

### Task 13: Docs worktree (`docs/`, branch `docs/main`)

Spec: §7 table, every row.

**Files:** the pages in the spec's §7 table, under `docs/docs/` (check each line reference against the page first;
the numbers are from 2026-11-17).

- [ ] **Step 1: Check out and update.** Run `git -C docs status`; expected: on `docs/main` and clean. Then work
  through the table row by row:
  - the expert setting and the calendar's one rule;
  - the context tables without `$context->intent`;
  - "whole list" vs. "list view's list";
  - `hasFilterInstance()` counting exclusive filters;
  - the default published filter and the warning;
  - the events' lists;
  - `makeDatim()`/`remove()` (its scope: definition keys, not label, options or callbacks) and `DcaBuilder::apply()`
    dropping legends without fields, `mergePalettes()` keeping them (`dev/dca-builder.md`);
  - the lookup and `flare_schema_org()`;
  - the removed APIs in `removed-in-v0.2.md`;
  - in `migrating-from-v0.1.md`, the accepted changes of §6 (the narrower lists of `is_limited` off first), the
    migration's count of filters to check, and the recipe for the old behavior (a control with the sides switched
    off and the period as stored value; a filter exclusive to the list view);

- [ ] **Step 2: Build the docs.** `make docs-setup` only creates a missing worktree and installs nothing. If
  `docs/node_modules` is missing, run `npm --prefix docs ci`; then `npm --prefix docs run build`. Expected: no broken
  links.

- [ ] **Step 3: Commit in the docs worktree.** Run `git -C docs commit -am "docs: exclusive filters and the calendar
  filter's one rule"`. Do not push.
