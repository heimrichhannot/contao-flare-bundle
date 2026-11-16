# Entries follow-up — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** No entry becomes a Contao model any more (`EntryModelFactory` is removed), the schema.org listeners load
their model through the table's finder, `flare_related()` resolves relations natively from the entry's row, and the
reader finds the strictly equal auto-item among all rows the database's collation matches.

**Architecture:** First the dev dependencies (`contao/news-bundle`, `contao/calendar-bundle`) so the listener tests use
the real model classes. Then the two users of `EntryModelFactory` move off it — the listeners to
`Model::getClassFromTable()` + `findById()`, `EntryRelationResolver` to Contao's lazy relation path with the related
table's finders — and the factory is deleted once nothing uses it. The auto-item fix in `ValidationLoader` is
independent; the docs worktree comes last. Every task ends green.

**Tech Stack:** PHP 8.2 (Docker), Contao 4.13/5 (the dev install resolves Contao 5.3.48), Doctrine DBAL ≥ 3.6,
PHPUnit 9.6 with `contao/test-case`, SQLite (`pdo_sqlite`) for database-backed tests, PHPStan level 5, Mago 1.47.4.
Commands: `make test [path]`, `make phpstan`, `docker compose run --rm composer …`,
`mago lint --minimum-fail-level note` (local binary, same version as CI).

**Spec:** `dev/superpowers/specs/2026-11-16-entries-followup-design.md` — the authority for behavior; M1–M11 and
C1–C10 are settled. This plan fixes files, order, names left to the builder and tests. Each task names the spec
sections to read first.

## Global Constraints

- Branch `v0.2-entries-followup` (checked out, head = the spec commit). One commit per task, conventional-commit style
  (`chore(deps): …`, `refactor(entries): …`, `fix(reader): …`, `docs(entries): …`). **No `Co-Authored-By` trailer or
  any other attribution line. Never push** — neither this branch nor `docs/main`.
- v0.2 is unreleased: breaking changes without BC shims, no `class_alias`, no deprecations. No replacement service or
  helper for "entry → model", no event for relations, no membership check (spec, Anti-goals).
- Contao 4.13 and 5.x, PHP 8.2+, Symfony 5.4–7. The suite runs Contao 5.3.48 only: 4.13 behavior (`findById()` via
  `__callStatic()`, `getClassFromTable()` returning a guessed, maybe missing class) is covered by adapter-mocked unit
  tests, not by a 4.13 run.
- Programming errors are `\Error`s (`MissingModelRefError`, `UndefinedRelationError`); the bundle catches `\Exception`,
  never `\Throwable`/`\Error`. Parameterized SQL only (`FilterConditionsBuilder`, bound finder values).
- Tests drive production code through its public API. Contao statics a test must control go through
  `ContaoFramework` adapters (`mockContaoFramework()`, `mockAdapter()`, `mockConfiguredAdapter()`,
  `mockClassWithProperties()` of `ContaoTestCase`). No reflection on the bundle's private members, no test-only hooks
  in `src/` (the reflection inside `tests/Filter/Element/StubsContaoDcaTrait.php` targets Contao's loaders and is
  existing infrastructure).
- `declare(strict_types=1)`, PSR-12/PER, 120 characters. After staging, before each commit, check the added lines
  only: `git diff --cached -U0 -- '*.php' | grep '^+[^+]' | awk 'length > 121'` → no output (121 = 120 plus the
  diff's `+`). Lines that are too long already stay as they are
  (`tests/DependencyInjection/ServicesConfigTest.php:195`).
- Mago fails on notes. Two rules this plan's code runs into: string interpolation is braced (`"{$field} IN("`, not
  `"$field IN("`), and `empty()` is an error (`no-empty`; nothing in `src/` uses it).
- PHPUnit's `->with($value)` compares loosely (`'7'` equals `7`, any equal object): where the type or the instance is
  the point of a test, write `->with(self::identicalTo($value))`.
- `make test <path>` passes **one** path to PHPUnit; run one call per path a step names. `make composer`/`make php`
  cannot carry arguments that start with `-` (make takes them as its own options) or contain spaces: call
  `docker compose run --rm composer …` directly for those.
- Every task ends with `make test` → `OK`, `make phpstan` → `[OK] No errors`, `mago lint --minimum-fail-level note`
  → no issues, the 120-character check, then one commit.
- `composer.lock` is gitignored (`.gitignore:12`): only `composer.json` is committed.
- `AGENTS.md` (`CLAUDE.md` is a symlink) changes with the code it describes (Tasks 3, 4, 5). The `docs/` worktree
  (branch `docs/main`) changes only in Task 6, committed there with `git -C docs`.

## Review Focus

1. **A list whose query already orders** (a driver or a `ModifyListQueryStructEvent` listener sets `ORDER BY`): among
   strictly equal auto-item rows that order decides, `main.id` only breaks ties. Pinned in Task 5
   (`testTheListsOrderGoesBeforeTheIdTieBreaker`).
2. **Ids as strings.** On MySQL, PDO delivers `id` as a string, so `meta.modelRef.id` is `'7'`: the listeners pass it
   to `findById()` unchanged. Pinned in Task 2 (`testAStringIdIsPassedUnchanged`).
3. **CSV relation values with spaces** (`'3, 1'` with `eval.csv = ','`): the values are trimmed as Contao's
   `StringUtil::trimsplit()` does. Pinned in Task 3 (provider case `with delimiter and spaces`).
4. **A to-many value that is a plain scalar** (`'5'` in a column without delimiter): `deserialize($value, true)`
   makes it `['5']`, one bound value, as Contao queries it. Pinned in Task 3 (provider case `a plain value`).
5. **An auto-item that matches neither variant strictly** (`FOO` when `Foo` and `foo` are stored): all matches are read,
   none is equal → 404 (`null`). Pinned in Task 5 (`testEachCaseVariantFindsItsOwnRow`, third assertion).

---

## File Structure

| Path | Change | Responsibility |
|---|---|---|
| `composer.json` | modify (T1) | `require-dev`: news and calendar bundles |
| `src/Integration/ContaoNews/EventListener/NewsReaderSchemaOrgListener.php` | modify (T2) | finder by `modelRef` |
| `src/Integration/ContaoCalendar/EventListener/EventsSchemaOrgListener.php` | modify (T2) | finder by `modelRef` |
| `src/Contao/ListEntry/EntryRelationResolver.php` | modify (T3) | native relation resolution |
| `src/Exception/UndefinedRelationError.php`, `src/Twig/Runtime/FlareRuntime.php` | modify (T3) | docblocks |
| `src/Contao/ListEntry/EntryModelFactory.php`, `tests/Contao/ListEntry/EntryModelFactoryTest.php` | delete (T4) | |
| `src/Exception/MissingModelRefError.php`, `src/List/Entry/ModelRef.php` | modify (T4) | message, `modelRef` rule |
| `src/Engine/Loader/ValidationLoader.php`, `ValidationLoaderInterface.php` | modify (T5) | strict auto-item lookup |
| `tests/Integration/ContaoNews/EventListener/NewsReaderSchemaOrgListenerTest.php` | create (T2) | |
| `tests/Integration/ContaoCalendar/EventListener/EventsSchemaOrgListenerTest.php` | create (T2) | |
| `tests/Contao/ListEntry/EntryRelationResolverTest.php` | rewrite (T3) | finder call per case |
| `tests/Contao/ListEntry/EntryRelationResolverDatabaseTest.php` | create (T3) | equality with `getRelated()` |
| `tests/Fixture/Relation{Source,Target}ModelFixture.php` | create (T3) | real models for the DB test |
| `tests/Twig/FlareRuntimeTest.php` | modify (T3, T4) | resolver without factory; message |
| `tests/DependencyInjection/ServicesConfigTest.php` | modify (T4) | factory entry removed |
| `tests/Engine/Loader/ValidationLoaderTest.php` | modify (T5) | collation cases |
| `AGENTS.md` | modify (T3, T4, T5) | spec §6 |
| `docs/docs/dev/entries.md`, `removed-in-v0.2.md`, `reference/twig.md`, `templating.mdx` | modify (T6) | |

---

### Task 1: News and calendar bundles as dev dependencies

Read the spec: Decisions M9; Testing, the `composer.json` bullet.

**Files:**
- Modify: `composer.json` (`require-dev`)
- Modify only if PHPStan reports new errors once the classes exist: files under `src/Integration/ContaoNews/` and
  `src/Integration/ContaoCalendar/`

**Interfaces:**
- Consumes: nothing.
- Produces: `Contao\NewsModel`, `Contao\News`, `Contao\CalendarEventsModel`, `Contao\Events` loadable in the suite
  (Tasks 2 and 3 rely on them).

- [ ] **Step 1: Baseline.** `make test` → `OK (N tests, …)`; note N. `make phpstan` → `[OK] No errors`.
  `mago lint --minimum-fail-level note` → `No issues found.`

- [ ] **Step 2: Require the bundles** (not via `make`: `--dev` and the spaces of the constraint do not survive it):

```bash
docker compose run --rm composer require --dev --no-progress \
  "contao/news-bundle:^4.13 || ^5.0" "contao/calendar-bundle:^4.13 || ^5.0"
```

Expected: `composer.json` gains both entries in `require-dev` (where Composer writes them); the lock only adds the two
bundles and dependencies they bring. If Composer wants to update or downgrade `contao/core-bundle` or any other locked
package, stop: do not add `-W`, report the conflict.

- [ ] **Step 3: Verify the install.**
  `docker compose run --rm composer show | grep -E '^contao/(core|news|calendar)-bundle '` → three lines with the
  same version (5.3.48 at the time of writing; `show` takes one package, so the list is filtered).
  `docker compose run --rm composer validate --strict` →
  `./composer.json is valid`.

- [ ] **Step 4: Verify resolution like the compatibility workflow**, on a copy outside the repository (the
  workflow turns off advisory blocking for 4.13's `spomky-labs/otphp`; that must not reach the real `composer.json`):

```bash
tmp=$(mktemp -d) && cp composer.json "$tmp"/
docker compose run --rm -v "$tmp:/work" -w /work composer config --no-plugins policy.advisories.block false
for v in '4.13.*' '5.3.*'; do
  docker compose run --rm -v "$tmp:/work" -w /work composer update --dry-run --no-progress --prefer-dist \
    --with "contao/core-bundle:$v" || echo "FAIL $v"
done
rm -rf "$tmp"
```

Expected: both dry-runs end without `FAIL`. PHP 8.3–8.5 and Contao 5.7 are checked by `compatibility.yaml` in CI
only (the Docker image is PHP 8.2; the workflow excludes 8.2 × 5.7).

- [ ] **Step 5: Suite and static analysis.** `make test` → `OK`, still N tests (881 when the plan was reviewed). No
  test loads `HeimrichHannotFlareExtension` (`ServicesConfigTest` reads `config/services.yaml` directly), so the
  integration configs the extension loads once the bundles exist (`Env::hasContaoNews()`, `Env::hasContaoCalendar()`)
  are not exercised by the suite and cannot change it. `make phpstan`: PHPStan now sees the
  real news/calendar classes in `src/Integration/`. The `class.notFound` ignore for `src/Integration/` stays matched
  (CodefogTags, `Contao\Comments`), so no "unmatched ignore" error is expected. If new errors appear in
  `src/Integration/ContaoNews` or `src/Integration/ContaoCalendar`, fix them in place with the narrowest type
  correction; do not add `ignoreErrors` entries. If an error can only be silenced (a wrong Contao signature), add one
  entry scoped by `path` and `message` with a comment, and name it in the task report.

- [ ] **Step 6: Lint and commit.** `mago lint --minimum-fail-level note` → no issues.

```bash
git add composer.json   # plus any file fixed in Step 5
git commit -m "chore(deps): require contao/news-bundle and contao/calendar-bundle for dev"
```

---

### Task 2: Schema.org listeners load their model through the table's finder

Read the spec: §2, Decisions M2, M8, C1, C2, C3, C10; Testing, the listener bullet.

**Files:**
- Modify: `src/Integration/ContaoNews/EventListener/NewsReaderSchemaOrgListener.php`,
  `src/Integration/ContaoCalendar/EventListener/EventsSchemaOrgListener.php`
- Test (create): `tests/Integration/ContaoNews/EventListener/NewsReaderSchemaOrgListenerTest.php`,
  `tests/Integration/ContaoCalendar/EventListener/EventsSchemaOrgListenerTest.php`

**Interfaces:**
- Consumes: Task 1 (real `NewsModel`, `News`, `CalendarEventsModel`, `Events`).
- Produces (both listeners keep `#[AsEventListener(priority: 200)]` and `__invoke(ReaderSchemaOrgEvent $event): void`;
  only the constructor changes):

```php
public function __construct(private readonly ContaoFramework $framework) {}
```

The body is the spec's §2 code block with every static through an adapter: `getAdapter(Model::class)
->getClassFromTable($ref->table)` (not caught, C10), `getAdapter($class)->findById($ref->id)` (the id passed
unchanged; on 4.13 the adapter's call reaches `__callStatic()`), `instanceof NewsModel` /
`CalendarEventsModel` guard (C2), `getAdapter(News::class)->getSchemaOrgData($model)` /
`getAdapter(Events::class)->getSchemaOrgData($model)`. The table guard stays `NewsModel::getTable()` /
`CalendarEventsModel::getTable()` (a static property read, no container needed).

- [ ] **Step 1: Write the failing tests** (`extends ContaoTestCase`; the event is
  `new ReaderSchemaOrgEvent($list, $entry, ['kept' => true])`, the list
  `new ListCompound(driver: $this->createStub(ListDriverInterface::class), type: 'test_list', dc: 'tl_news')`, the
  entry `EntryCollection::forTable($table, [$row])->first()`). News:

```php
public function testAnEntryOfAnotherTableIsLeftAlone(): void
{
    // entry of 'tl_test'; framework = createMock(ContaoFramework::class), expects(never())->method('getAdapter')
    self::assertSame(['kept' => true], $event->data);
}

public function testAnEntryWithoutModelRefIsLeftAlone(): void { /* new Entry(['id' => 7]); getAdapter never */ }

public function testTheRecordIsLoadedThroughTheFinderOfTheRegisteredClass(): void
{
    $model = $this->createMock(NewsModel::class);
    // a subclass of NewsModel, as a multilingual class would be: proves the finder of the *named* class is used
    $class = $model::class;
    // adapters: Model::class → getClassFromTable with('tl_news') → $class
    //           $class       → findById with(self::identicalTo(7)), once → $model
    //           News::class  → getSchemaOrgData with(self::identicalTo($model)), once → ['@type' => 'NewsArticle']
    // (no adapter for NewsModel::class: mockContaoFramework returns null for it)
    self::assertSame(['@type' => 'NewsArticle'], $event->data);
}

public function testAStringIdIsPassedUnchanged(): void
{
    // row ['id' => '7'] → findById with(self::identicalTo('7')): a cast to int must fail this test
}

public function testAMissingRecordYieldsNoData(): void
{
    // findById → null; News adapter: getSchemaOrgData expects never
    self::assertSame(['kept' => true], $event->data);
}

public function testAModelOfAnotherClassYieldsNoData(): void
{
    // findById → $this->createMock(TestModelFixture::class); getSchemaOrgData never
    self::assertSame(['kept' => true], $event->data);
}

public function testABrokenModelRegistrationFailsTheRequest(): void
{
    // getClassFromTable → willThrowException(new \RuntimeException('no model'))
    $this->expectException(\RuntimeException::class);
}
```

The events test is the same with `tl_calendar_events`, `CalendarEventsModel`, `Events` and
`['@type' => 'Event']`.

- [ ] **Step 2: Run — expect failure.** `make test tests/Integration/ContaoNews`,
  `make test tests/Integration/ContaoCalendar` → errors (the constructors still take `EntryModelFactory`).

- [ ] **Step 3: Implement** both listeners as in Interfaces. They no longer import `EntryModelFactory`.

- [ ] **Step 4: Run — expect pass.** The same two calls → `OK`.

- [ ] **Step 5: Full checks and commit.**
  `git commit -m "refactor(integration): load the schema.org listeners' model through the table's finder"`

---

### Task 3: `flare_related()` resolves natively

Read the spec: §3, Decisions M4, M10, M11, C4, C5, C6; Testing, the first two bullets; §6 (resolver part).

**Files:**
- Modify: `src/Contao/ListEntry/EntryRelationResolver.php`, `src/Exception/UndefinedRelationError.php` (docblock:
  "a related table without a model class, or an unknown relation type"), `src/Twig/Runtime/FlareRuntime.php`
  (`getRelated()` docblock: `@throws UndefinedRelationError for a column without relation, a related table without
  a model class or an unknown relation type`), `AGENTS.md` (the `EntryRelationResolver` clause of the
  `src/Contao/ListEntry/` bullet, lines 185–187)
- Test: rewrite `tests/Contao/ListEntry/EntryRelationResolverTest.php`; create
  `tests/Contao/ListEntry/EntryRelationResolverDatabaseTest.php`, `tests/Fixture/RelationSourceModelFixture.php`,
  `tests/Fixture/RelationTargetModelFixture.php`; modify `tests/Twig/FlareRuntimeTest.php:47`
  (`new EntryRelationResolver($framework)`)

**Interfaces:**
- Consumes: nothing from earlier tasks (the factory still exists; after this task only the deletion in Task 4
  is left).
- Produces:

```php
final readonly class EntryRelationResolver
{
    public function __construct(private ContaoFramework $framework) {}

    /** Signature, memo (WeakMap<Entry, ArrayObject>, null memoized) and MissingModelRefError unchanged. */
    public function resolve(Entry $entry, string $column): Model|Collection|null;
}
```

Spec §3 steps 1–6 in that order. Adapters: `DcaExtractor::class` (`getInstance($ref->table)->getRelations()`),
`Model::class` (`getClassFromTable()`), `Database::class` (`quoteIdentifier()`), `FilesModel::class`
(`findMultipleByUuids()`), the related class (`findOneBy()`, `findBy()`). `StringUtil::trimsplit()` /
`StringUtil::deserialize()` are called directly (pure functions, no framework state). Pinned values:

- related class accepted only if `\is_string($class) && \class_exists($class) && \is_subclass_of($class, Model::class)`;
  a `\RuntimeException` from `getClassFromTable()` (5.x) counts as none. None →
  `new UndefinedRelationError($ref->table, $column, 'relates to a table without a model class')`.
- unknown type → `new UndefinedRelationError($ref->table, $column, \sprintf('has the unsupported relation type "%s"',
  $relation['type']))`.
- "no value" (spec step 2, Contao's `empty()`), written without `empty()`:
  `\in_array($value, [null, false, 0, 0.0, '', '0', []], true)`.
- to-one: `$adapter->findOneBy($relation['field'], $value)` (the value as the row holds it, no cast).
- to-many: `$values = \array_values(isset($relation['delimiter']) ? trimsplit(…) : deserialize($value, true))`;
  `[] → null`; `tl_files` + `uuid` → `getAdapter(FilesModel::class)->findMultipleByUuids($values)`; otherwise
  `$field = $relation['table'] . '.' . quoteIdentifier($relation['field'])` and
  `$adapter->findBy(["{$field} IN(" . \implode(',', \array_fill(0, \count($values), '?')) . ')'],
  [...$values, \implode(',', $values)], ['order' => "FIND_IN_SET({$field}, ?)"])`.

Split left to the builder (spec): private helpers `relatedClass(string $table, string $column, string $ownTable):
string` and `toManyValues(array $relation, mixed $value): array` are suggested, not required.

- [ ] **Step 1: Write the failing unit tests** (`EntryRelationResolverTest extends ContaoTestCase`). The entry is
  `EntryCollection::forTable('tl_test', [$row])->first()`; relations come from a mocked `DcaExtractor`; the related
  class is `TestModelFixture::class` (existing, a real `Model` subclass), its finder a `mockAdapter(['findOneBy',
  'findBy'])` keyed by that class; `Database` is `mockAdapter(['quoteIdentifier'])` returning
  `` fn (string $f) => "`$f`" ``.

```php
private const RELATIONS = [
    'pid' => ['type' => 'belongsTo', 'table' => 'tl_archive', 'field' => 'id'],
    'author' => ['type' => 'hasOne', 'table' => 'tl_user', 'field' => 'id'],
    'parent' => ['type' => 'belongsTo', 'table' => 'tl_test', 'field' => 'id'],
    'category' => ['type' => 'belongsTo', 'table' => 'tl_archive', 'field' => 'alias'],
    'tags' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'id'],
    'children' => ['type' => 'hasMany', 'table' => 'tl_tag', 'field' => 'id'],
    'csv' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'id', 'delimiter' => ','],
    'ordered' => ['type' => 'belongsToMany', 'table' => 'tl_tag', 'field' => 'order'],
    'files' => ['type' => 'belongsToMany', 'table' => 'tl_files', 'field' => 'uuid'],
    'image' => ['type' => 'belongsTo', 'table' => 'tl_nomodel', 'field' => 'id'],
    'odd' => ['type' => 'manyToMany', 'table' => 'tl_tag', 'field' => 'id'],
    'oddNoModel' => ['type' => 'manyToMany', 'table' => 'tl_nomodel', 'field' => 'id'],
];

/** @dataProvider provideToOne — 'belongsTo' (pid, tl_archive, id, 7), 'hasOne' (author, tl_user, id, 3),
 *  'into the list's own table' (parent, tl_test, id, 2), 'a target field other than id' (category, tl_archive,
 *  alias, 'news') */
public function testToOneFindsTheRelatedRecordByItsField(
    string $column, string $table, string $field, mixed $value,
): void {
    // getClassFromTable with($table); findOneBy with($field, self::identicalTo($value)), once → $related
    self::assertSame($related, $resolver->resolve($this->entry(['id' => 5, $column => $value]), $column));
}

/** @dataProvider provideToMany — stored value → expected bound values:
 *  'belongsToMany serialized' tags serialize(['3', '1']) → ['3', '1'];
 *  'hasMany' children serialize(['3', '1']) → ['3', '1'];
 *  'serialized with string keys' tags serialize(['x' => '3', 'y' => '1']) → ['3', '1'];
 *  'with delimiter and spaces' csv '3, 1' → ['3', '1']                                   (Review Focus 3)
 *  'a plain value' tags '5' → ['5']                                                       (Review Focus 4)
 *  'a quote and a backslash' tags serialize(["o'k", 'a\\b']) → ["o'k", 'a\\b'] */
public function testToManyFindsTheRecordsInTheStoredOrder(string $column, string $stored, array $values): void
{
    $field = 'tl_tag.`id`';
    $placeholders = \implode(',', \array_fill(0, \count($values), '?'));
    // findBy with(["$field IN($placeholders)"], [...$values, \implode(',', $values)],
    //             ['order' => "FIND_IN_SET($field, ?)"]), once → $collection
    self::assertSame($collection, $resolver->resolve($this->entry(['id' => 5, $column => $stored]), $column));
}

public function testATargetFieldThatNeedsQuotingIsQuotedLikeContao(): void
{
    // 'ordered' = serialize(['5']); quoteIdentifier with('order'); findBy with(['tl_tag.`order` IN(?)'],
    // ['5', '5'], ['order' => 'FIND_IN_SET(tl_tag.`order`, ?)'])
}

/** @dataProvider provideUuids — 'string' ['9e474bae-ce18-11ef-8f8b-0242ac120002'],
 *  'binary' [\hex2bin('9e474baece1811ef8f8b0242ac120002')] */
public function testFileUuidsGoToFilesModelWhateverClassIsRegistered(array $uuids): void
{
    // getClassFromTable('tl_files') → TestModelFixture::class; FilesModel adapter: findMultipleByUuids with($uuids),
    // once → $collection; the TestModelFixture adapter's findBy expects never
}

/** @dataProvider provideEmptyValues — 0, '0', '', null and a missing column, each for 'pid' and 'tags' */
public function testAnEmptyValueIsNullWithoutAQuery(string $column, array $row): void
{
    // getClassFromTable, findOneBy, findBy expect never
    self::assertNull($resolver->resolve($this->entry($row), $column));
}

public function testAnEmptySerializedArrayIsNullWithoutAQuery(): void { /* tags 'a:0:{}': findBy never → null */ }

public function testAnEmptyValueIsNullBeforeTheRelatedClassOrTypeIsChecked(): void
{
    // 'image' = '' with getClassFromTable throwing for tl_nomodel → null; 'odd' = 0 → null
}

public function testEachEntryAndColumnIsResolvedOnce(): void
{
    // findOneBy expects exactly(3), returns null: (first, pid) twice, (first, parent), (second, pid) twice
}

public function testWithoutModelRefThrows(): void { /* new Entry(['id' => 1, 'pid' => 7]) → MissingModelRefError */ }

public function testAColumnWithoutRelationThrows(): void { /* 'title' → UndefinedRelationError, message 'title' */ }

/** @dataProvider provideMissingClasses — 'Contao 5 throws' (\RuntimeException), 'Contao 4.13 guesses'
 *  ('Contao\NoSuchModel'), 'no model' (\stdClass::class) */
public function testARelatedTableWithoutModelClassThrows(mixed $lookup): void
{
    // 'image' = 4 → UndefinedRelationError, expectExceptionMessage('without a model class')
}

public function testAnUnknownRelationTypeThrows(): void { /* 'odd' = 4 → message contains 'manyToMany' */ }

public function testTheRelatedClassIsCheckedBeforeTheType(): void
{
    // 'oddNoModel' = 4, getClassFromTable throws for tl_nomodel → message 'without a model class' (spec steps 3, 6)
}

public function testARelationOfATableWithoutModelClassResolves(): void
{
    // entry of 'tl_nomodel' with ['id' => 5, 'pid' => 7], relations of tl_nomodel = RELATIONS;
    // getClassFromTable throws for 'tl_nomodel', returns TestModelFixture::class for 'tl_archive' → the model
}
```

- [ ] **Step 2: Write the failing database test** (`EntryRelationResolverDatabaseTest extends ContaoTestCase`,
  `use StubsContaoDcaTrait`). Fixtures: `RelationSourceModelFixture extends Contao\Model` with
  `protected static $strTable = 'tl_flare_rel_source'` and `RelationTargetModelFixture` for
  `'tl_flare_rel_target'` — nothing else overridden, so Contao's real constructor, registry and `find()` run.
  Setup, in this order except step 6 (checked against Contao 5.3.48 in the Docker image on 2026-11-16 — Contao's
  own `getRelated()` and the resolver's bound `findBy()` both return `[3 => 'c', 1 => 'a']` for the data below):
  1. SQLite in memory via `DriverManager`; `$connection->getNativeConnection()->sqliteCreateFunction('FIND_IN_SET',
     …, 2)` (1-based position of the needle in the comma list, 0 if absent).
  2. `CREATE TABLE tl_flare_rel_source (id INTEGER PRIMARY KEY, pid INTEGER, tags TEXT)`,
     `CREATE TABLE tl_flare_rel_target (id INTEGER PRIMARY KEY, title TEXT)`; targets `1 a`, `2 b`, `3 c`; source
     `id 5, pid 2, tags serialize(['3', '1'])`.
  3. `stubDca()` for both tables: `config.dataContainer = DC_Table::class`; source fields
     `pid.relation = ['type' => 'belongsTo', 'load' => 'lazy', 'table' => 'tl_flare_rel_target']`,
     `tags.relation = ['type' => 'belongsToMany', 'load' => 'lazy', 'table' => 'tl_flare_rel_target']`; target
     field `title`.
  4. Then `System::setContainer()` with a container holding `Config::class` (a mock), `database_connection`, the
     parameters `kernel.cache_dir` (a path that does not exist: no cached extract) and `kernel.debug` = true. The
     trait's `@after` hook restores the container saved before step 3.
  5. `$GLOBALS['TL_MODELS']` for both tables → the fixtures; `$GLOBALS['TL_LANG']['MSC']` non-empty (else
     `DcaExtractor` loads the default language file). Both restored in `tearDown()`.
  6. Before step 1 in `setUp()`, and again in `tearDown()`: Contao's static state, with the test case's own helper —
     `$this->resetStaticProperties([Database::class, DcaExtractor::class, Registry::class,
     [Model::class, ['arrColumnCastTypes']]])`. `Contao\Database` is a singleton that keeps the connection of the
     first test that touched it: without the reset the second test method reads the first one's database, and
     equal fixture data hides that. If a later test still reads stale state, add the class that holds it.
  7. The resolver gets a `ContaoFramework` mock whose `getAdapter()` returns `new Adapter($class)` for every class.

```php
public function testToOneEqualsGetRelated(): void
{
    $expected = RelationSourceModelFixture::findById(5)->getRelated('pid');
    Registry::getInstance()->reset();
    $row = $this->connection->fetchAssociative('SELECT * FROM tl_flare_rel_source WHERE id = 5');
    $actual = $this->resolver->resolve(EntryCollection::forTable('tl_flare_rel_source', [$row])->first(), 'pid');

    self::assertInstanceOf(RelationTargetModelFixture::class, $actual);
    self::assertSame($expected->row(), $actual->row());
}

public function testToManyEqualsGetRelatedInTheStoredOrder(): void
{
    // same for 'tags'; both Collections; the rows of $expected->getModels() equal those of $actual;
    // array_column($rows, 'id') === [3, 1]
}
```

- [ ] **Step 3: Run — expect failure.** `make test tests/Contao/ListEntry` (unit tests fail: the resolver still asks the
  factory; the database test fails for the same reason or on the constructor).

- [ ] **Step 4: Implement** the resolver as in Interfaces; update the two docblocks; `FlareRuntimeTest:47` →
  `new EntryRelationResolver($framework)`, and remove that file's `use …\EntryModelFactory;` (line 9). `tests/Twig`
  is green before and after (PHP ignores the surplus constructor argument); it only must not break. In `AGENTS.md`
  replace the resolver clause with: `EntryRelationResolver`
  (`flare_related(entry, column)`: Contao's lazy relation resolution without a model of the entry — relation from
  `DcaExtractor`, value from `entry.row`, the related table's finders, to-many values bound as parameters,
  `tl_files`.`uuid` through `FilesModel::findMultipleByUuids()`; memoized per entry and column, no membership check;
  `UndefinedRelationError` for a column without relation, a related table without model class or an unknown
  relation type).

- [ ] **Step 5: Run — expect pass.** `make test tests/Contao/ListEntry`, `make test tests/Twig` → `OK`.

- [ ] **Step 6: Full checks and commit.**
  `git commit -m "refactor(entries): resolve flare_related() natively from the entry's row"`

---

### Task 4: Remove `EntryModelFactory`

Read the spec: §1, §4, §6 (lines 39–41 and the `src/Contao/ListEntry/` bullet), Decisions M1, M3, C9.

**Files:**
- Delete: `src/Contao/ListEntry/EntryModelFactory.php`, `tests/Contao/ListEntry/EntryModelFactoryTest.php`
- Modify: `src/Exception/MissingModelRefError.php` (message and docblock), `src/List/Entry/ModelRef.php` (docblock),
  `tests/DependencyInjection/ServicesConfigTest.php` (import and the entry at line 113),
  `tests/Twig/FlareRuntimeTest.php` (`testContentNeedsAModelRef`), `AGENTS.md`
- `config/services.yaml`: nothing (the class was registered by the `Contao\` resource glob).

**Interfaces:**
- Consumes: Tasks 2 and 3 (no `src/` user of the factory is left).
- Produces (wording decided here):
  - `MissingModelRefError` message: `'The entry has no modelRef, so it names no record of a DC table. Entries of a DC
    table are built with EntryCollection::forTable().'`; docblock: a record was requested for an entry without a
    model reference — a programming error; only entries built by `EntryCollection::forTable()` name a record.
  - `ModelRef` docblock: names the physical record of the list's main table the entry was built from
    (`EntryCollection::forTable()` takes `row['id']`, which is `main.id` while the query selects `main.*`); carries no
    Contao dependency.

- [ ] **Step 1: Write the failing test.** In `FlareRuntimeTest::testContentNeedsAModelRef` add
  `$this->expectExceptionMessage('names no record');`.

- [ ] **Step 2: Run — expect failure.** `make test tests/Twig/FlareRuntimeTest.php` → the message assertion fails.

- [ ] **Step 3: Implement.** Delete the two files; drop `EntryModelFactory::class` from `provideServices()` and its
  import; reword `MissingModelRefError` and the `ModelRef` docblock. `AGENTS.md`:
  - lines 39–41: the views hand out `Entry` objects, never Contao models; no entry becomes a model — Contao APIs that
    need one load it by `meta.modelRef` through the table's model class
    (`Model::getClassFromTable($ref->table)::findById($ref->id)`).
  - the `src/Contao/ListEntry/` bullet: drop the `EntryModelFactory` clause; the schema.org listeners load their
    model by `meta.modelRef` through the table's finder (one query unless the registry holds the record); add the
    rule: `meta.modelRef` names the physical record of the list's main table. Keep Task 3's resolver clause and the
    `EntryCacheTagger` clause.

- [ ] **Step 4: Run — expect pass.** `make test tests/Twig/FlareRuntimeTest.php` → `OK`;
  `grep -rn 'EntryModelFactory' src tests config contao AGENTS.md` → no output.

- [ ] **Step 5: Full checks and commit.** `git commit -m "refactor(entries): remove EntryModelFactory"`
  (`git add -A src tests AGENTS.md` picks up the deletions).

---

### Task 5: The reader finds the strictly equal auto-item among all matches

Read the spec: §5, Decisions M5, M7, C7, C8; Testing, the `ValidationLoaderTest` bullet; §6 (reader bullet).

**Files:**
- Modify: `src/Engine/Loader/ValidationLoader.php` (`fetchEntryByAutoItem()`),
  `src/Engine/Loader/ValidationLoaderInterface.php` (docblock), `AGENTS.md` (reader bullet, line 193)
- Test: `tests/Engine/Loader/ValidationLoaderTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `fetchEntryByAutoItem(string $autoItem): ?array` unchanged in signature. Docblock: the member row whose
  auto-item field holds exactly `$autoItem`, by the reader's rules; all rows the database matches, without
  LIMIT/OFFSET, in the list's order and then `main.id`; the first strictly equal one, `null` if none.

Implementation pinned by the spec: after `createQueryBuilder()`,
`$qb->setFirstResult(0)->setMaxResults(null)->addOrderBy(TableAliasRegistry::ALIAS_MAIN . '.id')` (ascending, behind
any order the struct set); read with `fetchAssociative()` in a loop, return the first row with
`(string) ($row[$field] ?? '') === $autoItem`; `$result->free()` in a `finally`; no cap. `ValidationIntent` keeps
its page size 1.

- [ ] **Step 1: Write the failing tests.** Add a helper `collatedConnection(string $collation, array $rows):
  Connection` — `CREATE TABLE tl_test (id INTEGER, alias TEXT COLLATE <collation>, title TEXT DEFAULT '',
  published INTEGER DEFAULT 0)` — and give `loader()` an optional `?EventDispatcher $dispatcher = null` parameter.
  Two facts of the harness decide what these tests can show (checked on 2026-11-16):
  - `EnginePipelineTrait` registers the real `PageModifierListener`, so the query carries today's `LIMIT 1`.
  - The query groups by `main.id` (`ListExecutionContextFactory`), and SQLite then returns the rows in id order
    whatever the insertion order. So the tie-breaker of M7 is only observable where the grouping is gone: the
    equal-rows test drops it with a listener, and `id` is deliberately **not** `PRIMARY KEY`, so that an ungrouped,
    unordered scan returns insertion order.

```php
/** @dataProvider provideCaseVariants — 'Foo first' [[1, 'Foo'], [2, 'foo']], 'foo first' [[2, 'foo'], [1, 'Foo']] */
public function testEachCaseVariantFindsItsOwnRow(array $rows): void
{
    $loader = $this->loader([], connection: $this->collatedConnection('NOCASE', $rows));
    self::assertSame(1, (int) ($loader->fetchEntryByAutoItem('Foo')['id'] ?? 0));
    self::assertSame(2, (int) ($loader->fetchEntryByAutoItem('foo')['id'] ?? 0));
    self::assertNull($loader->fetchEntryByAutoItem('FOO'));                       // Review Focus 5
}

/** @dataProvider provideSpaceVariants — [[1, 'a'], [2, 'a ']] and [[2, 'a '], [1, 'a']] */
public function testEachTrailingSpaceVariantFindsItsOwnRow(array $rows): void
{
    // collation 'RTRIM': 'a' → id 1, 'a ' → id 2
}

/** @dataProvider provideEqualRows — [[2, 'x'], [1, 'x']] and [[1, 'x'], [2, 'x']] */
public function testOfStrictlyEqualRowsTheLowestIdWins(array $rows): void
{
    // collation 'BINARY'; a dispatcher with a listener (priority 0) on ModifyListQueryStructEvent:
    // $event->queryStruct->setGroupBy(null) — without ORDER BY main.id the first inserted row would win
    // 'x' → id 1
}

public function testTheListsOrderGoesBeforeTheIdTieBreaker(): void
{
    // rows (id 1, alias 'x', title 'a'), (id 2, alias 'x', title 'b'); a dispatcher with a listener (priority 0) on
    // ModifyListQueryStructEvent: $event->queryStruct->setOrderBy([['main.title', 'DESC']]) → 'x' → id 2  (RF 1)
}
```

Two more, for the paging reset and the missing cap (spec §5: the reset overrides any limit a listener sets):

```php
public function testALimitAndOffsetSetByAListenerAreReset(): void
{
    // NOCASE, rows (1, 'Foo'), (2, 'foo'); a listener (priority 0, so after PageModifierListener) on
    // ModifyListQueryStructEvent: $event->queryStruct->setLimit(1)->setOffset(1)
    // 'Foo' → id 1 (the offset would skip it), 'foo' → id 2
}

public function testAStrictMatchBehindSeveralCollationMatchesIsFound(): void
{
    // NOCASE, rows (1, 'FOO'), (2, 'Foo'), (3, 'foo'): 'foo' → id 3
}
```

The existing tests (`'0'`, empty auto-item, non-canonical ids, other case) stay as they are.

- [ ] **Step 2: Run — expect failure.** `make test tests/Engine/Loader/ValidationLoaderTest.php` → the first two
  fail in both insertion orders, on the `foo` and the `'a '` assertion (`LIMIT 1` returns id 1, which is not strictly
  equal); the third fails for the order `[[2, 'x'], [1, 'x']]` (id 2 is returned). The fourth passes already: it
  guards that the tie-breaker is appended behind the list's order, not put before it. The two reset tests fail too
  (the offset skips the row; `LIMIT 1` never reaches id 3). If the third does not fail before the fix, the test does
  not prove the tie-breaker: stop and report instead of going on.

- [ ] **Step 3: Implement** as pinned above; update the interface docblock. `AGENTS.md` reader bullet: "Only the stored
  value itself finds the entry: `ValidationLoader` reads every row the database matches (no LIMIT, ordered by the
  list's order and then `main.id`) and takes the first strictly equal one — numeric casts, collations and trailing
  spaces of the database never pick another row; of strictly equal rows the lowest id wins; none → 404".

- [ ] **Step 4: Run — expect pass.** `make test tests/Engine/Loader/ValidationLoaderTest.php` → `OK`;
  `make test tests/Engine` and `make test tests/Contao` → `OK` (reader resolver and lookup tests unaffected).

- [ ] **Step 5: Full checks and commit.**
  `git commit -m "fix(reader): find the strictly equal auto-item among all collation matches"`

---

### Task 6: Documentation worktree

Read the spec: "Documentation", §2 (registry, lifecycle, cost), §3 ("Where the result differs"), §4.

**Files** (worktree `docs/`, branch `docs/main`; paths from the repository root):
- Modify: `docs/docs/dev/entries.md` (§6 at line 222, §8 at line 260, line 88),
  `docs/docs/removed-in-v0.2.md` (line 76; the list around line 113), `docs/docs/reference/twig.md` (line 10),
  `docs/docs/templating.mdx` (lines 156, 599, 607–613)

**Interfaces:** Consumes Tasks 2–5 (behavior described as built). Produces nothing for code.

Content (decisions, not prose):
- `dev/entries.md` §8 "Contao APIs That Need a Model" (keep the heading: `removed-in-v0.2.md` links to its anchor):
  the finder of the table's class by `meta.modelRef`; the listener example as built in Task 2, without
  `EntryModelFactory`; C1's registry note (a record registered earlier in the request is returned as it is, whichever
  class registered it); the model is the database record — mapped values and columns of the row that are not the
  table's are not in it; the finder's lifecycle (core model registered and saveable, DC_Multilingual model detached
  and not saveable); one query per call unless the registry holds the record; `null` for a deleted record, unless
  the registry still holds an instance of it; check `meta->modelRef` yourself. §6 `flare_related()`: native
  resolution from `entry.row` with the four differences of spec §3 (no parent
  model, `a:0:{}` without a query, errors for unknown type / related table without model class, unchanged comma and
  duplicate limits of `FIND_IN_SET`). Line 88: `meta->modelRef` is the physical record of the list's main table.
- `removed-in-v0.2.md` line 76: replacement is the table's finder (`Model::getClassFromTable($ref->table)::findById(
  $ref->id)`), linking §8; add `EntryModelFactory` under `## Classes` (line 22) with the label "(v0.2 development
  only)" that the removed building events carry under `## Events`.
- `reference/twig.md` and `templating.mdx`: "a table without a model class" → "a related table without a model
  class"; `flare_related()` resolves from the entry's row; `entry.meta.modelRef` (line 156) is the physical record of
  the list's main table.

- [ ] **Step 1: Edit the four pages.**
- [ ] **Step 2: Audit.** `grep -rn 'EntryModelFactory' docs/docs` → only `removed-in-v0.2.md`;
  `grep -rn 'a table without a model class' docs/docs` → no output.
- [ ] **Step 3: Commit in the worktree** (do not push `docs/main`):

```bash
git -C docs add docs/dev/entries.md docs/removed-in-v0.2.md docs/reference/twig.md docs/templating.mdx
git -C docs commit -m "docs(entries): models by modelRef through the table's finder, native flare_related()"
```

---

## Self-review notes

- **Spec coverage.** §1 → Tasks 2, 3, 4 (every row of its table: listeners T2, resolver + `FlareRuntimeTest` T3,
  `ServicesConfigTest` + `AGENTS.md` T4, services/Twig nothing). §2 → T2 (C1–C3, C10; M3 docblock in T4). §3 → T3
  (steps 1–6, C4–C6, M11; equality M10 in the database test). §4 → T6 (docs only, no code). §5 → T5 (M5, M7, C7, C8).
  §6 → T3, T4, T5. Testing → T1 (`composer.json`, resolution), T2 (listener bullet), T3 (both resolver bullets), T5
  (`ValidationLoaderTest`), T3/T4 (`FlareRuntimeTest`, `ServicesConfigTest`). Documentation → T6. Notes for later PRs
  → nothing (later PRs).
- **Left to the builder, decided here:** listeners take only `ContaoFramework` and reach `Model`, the found class
  and `News`/`Events` through adapters; the resolver uses adapters for `DcaExtractor`, `Model`, `Database`,
  `FilesModel` and the related class, and calls the pure `StringUtil` functions directly; the error reasons and the
  `MissingModelRefError`/`ModelRef` wording above; the database comparison is its own test class with two real
  fixture models; the dev dependencies are their own task and commit, so the suite is proven green on them before any
  task uses them.
- **Order keeps every commit green:** T2 and T3 stop using the factory while it still exists; T4 deletes it when
  `grep` finds no user; T5 is independent.
- **Reviewed 2026-11-16 against the code,** first by Claude, then by two independent reviewers (Opus 5.5, GPT-6
  Astra) who built throwaway versions of Tasks 1, 2, 3 and 5 outside the repository. Worked in: Task 5's equal-rows
  test drops the query's `GROUP BY main.id` (SQLite returns id order under it, so the test passed without the
  tie-breaker), plus tests for the paging reset and the missing cap; `identicalTo()` where a type is the point; the
  Mago rules the pinned code ran into; the static-state reset of the database test; the `composer show` command;
  the 120-character check on added lines only; the `## Classes` section and three missing points in the docs task.
- **Not pinned by a test, checked by reading in Task 5's review:** that the rows are read one by one, that reading
  stops at the first strictly equal row, and that the result is freed in a `finally`. None of the three changes what
  the loader returns; pinning them needs an instrumented DBAL result.
- **Not covered by the suite, by design:** Contao 4.13 (the dev install is 5.3.48), MySQL/MariaDB collations (SQLite's
  `NOCASE`/`RTRIM` stand in), DC_Multilingual (the port's PR), and the loading of the integration configs by the
  bundle's extension (no test loads it).
- **CI, to read with care:** the PHPUnit and PHPStan workflows run `composer update` without a lock, so they test
  the newest Contao that resolves (5.3.54 when reviewed, not the local 5.3.48). `compatibility.yaml` runs its
  matrix with `continue-on-error: true`: a green run does not show that every PHP × Contao pair resolved — look at
  the jobs.
