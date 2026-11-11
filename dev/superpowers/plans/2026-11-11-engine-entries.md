# Entries instead of models — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The engine views hand out `Entry` objects instead of Contao models; a model is made only at the Contao
boundary; reader and breadcrumb share one request-scoped resolution.

**Architecture:** A new Contao-free namespace `src/Entry/` holds the wrapper (`Entry`, `EntryMeta`, `ModelRef`,
`EntryCollection`) and two ports (`EntryLookupInterface`, `EntryTaggerInterface`). The engine wraps loaded rows
through `Engine\Entry\EntryCollector`, which dispatches the entries event and seals the collection;
`Engine\Entry\ListEntryLookup` proves membership by the reader's rules in one query. Contao-side services turn
entries into models (`Contao\Entry\*`) and resolve the reader once per request (`Contao\Reader\*`). The order is
additive first, then the list side, then the reader side, then Twig, then docs; every task ends green.

**Tech Stack:** PHP 8.2, Symfony 5.4–7, Contao 4.13/5 (the suite runs Contao 5.3), Twig 3, DBAL ≥ 3.6, PHPUnit 9.6
with `contao/test-case`, PHPStan level 5, Mago. Commands: `make test [path]`, `make phpstan` (Docker),
`mago lint --minimum-fail-level note` (local binary).

**Spec:** `dev/superpowers/specs/2026-11-11-engine-entries-design.md` — the authority for behavior. This plan fixes
names, files, order and tests. Each task names the spec sections to read first.

## Names (for the user's review before execution)

The spec leaves all names to the builder. Proposed, in the user's style (short nouns, the concrete term last); the
plan uses them throughout, so a rename is a search-and-replace in this file before execution.

| Spec placeholder / concept | Name | Location |
|---|---|---|
| wrapper, meta, model reference, collection | `Entry`, `EntryMeta`, `ModelRef`, `EntryCollection` | `src/Entry/` |
| `EntryLookup` (phase 1's second argument) | `EntryLookupInterface` | `src/Entry/` |
| port for cache tags | `EntryTaggerInterface` | `src/Entry/` |
| `ListEntriesLoadedEvent` | `ListDriverEntriesEvent`, named `flare.list_driver.{type}.entries` | `src/Event/` |
| its named dispatch | `ListDriverEntriesListener` | `src/EventListener/NamedDispatch/` |
| builds a sealed collection + dispatches | `EntryCollector` (not a service) | `src/Engine/Entry/` |
| the lookup | `ListEntryLookup` (not a service) | `src/Engine/Entry/` |
| creates both | `EntryCollectorFactory` (service) | `src/Engine/Factory/` |
| boundary service | `EntryModelFactory` | `src/Contao/Entry/` |
| `flare_related()` backend | `EntryRelationResolver` | `src/Contao/Entry/` |
| cache tags | `EntryCacheTagger` | `src/Contao/Entry/` |
| reader resolver / its value | `ReaderResolver` / `ReaderResolution` (DTO) | `src/Contao/Reader/` / `src/Contao/` |
| discovery / auto-item | `ReaderElementFinder` / `AutoItemReader` | `src/Contao/Reader/` |
| `\Error` subclasses | `EntryNameCollisionError`, `SealedEntriesError`, `UndefinedEntryKeyError`, `ReadonlyEntryError`, `MissingEntryIdError`, `InvalidLookupIdError`, `MissingModelRefError`, `UndefinedRelationError` | `src/Exception/` |

## Global Constraints

- Branch `v0.2-entries`. Commits: **no `Co-Authored-By` trailer, never push.** Message style
  `feat(entries): …`, `refactor(reader): …`, `docs: …`.
- v0.2 is unreleased: break the API, no BC shims, no `class_alias`, no deprecations.
- Contao-free: `src/Entry/`, `src/Engine/Entry/` and `src/Event/ListDriverEntriesEvent.php` import nothing from
  `Contao\`. Check before each commit: `grep -rn 'use Contao\\' src/Entry src/Engine/Entry src/Event/ListDriverEntriesEvent.php`
  → no output.
- Programming errors are `final` `\Error` subclasses in `src/Exception/`, built like `UndefinedConfigKeyError` (the
  constructor builds the message). The bundle catches `\Exception`, never `\Throwable` or `\Error`.
- Production code is tested through its public API, no reflection. Contao statics that a test must control go
  through `ContaoFramework` adapters (`getAdapter()`, `createInstance()`); tests use `mockContaoFramework()`,
  `mockConfiguredAdapter()`, `mockClassWithProperties()` from `Contao\TestCase\ContaoTestCase`.
- PHPUnit 9.6 cannot double `final` or `readonly` classes. Tests construct such collaborators for real (see the
  fixtures in Tasks 3, 4 and 8) instead of mocking them.
- APIs used must exist in Contao 4.13 and 5: `Input::get($key, false, true)`, `StringUtil::revertInputEncoding()`
  (used by `HtmlDecoder` since 4.13 — confirm against the 4.13 tag), `Controller::isVisibleElement()`,
  `ContentModel::findPublishedByPidAndTable()`, `DcaExtractor::getInstance()->getFields()/getRelations()`.
  `Input::encodeInput()` and `InputEncodingMode` are 5.x only: tests may use them, production code must not.
- `declare(strict_types=1)`, PSR-12/PER, 120 characters. After staging, before each commit (also catches new files):
  `git diff --cached --name-only --diff-filter=d | grep '\.php$' | xargs -r awk 'length > 120 {print FILENAME":"FNR}'`
  → no output.
- PHPStan analyses `src/` only, excludes `src/Integration/Terminal42Languages` (check it with
  `docker compose run --rm php -l <file>`; `make php -l` does not work, make takes `-l` as its own option) and
  ignores `class.notFound` in `src/Integration/` (`NewsModel`, `CalendarEventsModel` do not exist in the suite).
- `make test <path>` passes **one** path to PHPUnit 9 (further paths are ignored): run one call per path a step
  names.
- Every task ends with `make test` → `OK`, `make phpstan` → `[OK] No errors`, `mago lint --minimum-fail-level note`
  → no issues, then one commit.
- `AGENTS.md` (`CLAUDE.md` is a symlink) and the docs worktree change in Task 11 only. `docs/` is its own worktree on
  `docs/main`: commit there with `git -C docs`, never push it.
- `tests/DependencyInjection/ServicesConfigTest.php` pins service registration: every task adds its new services to
  `provideServices()` and its new runtime objects (DTOs, entries, collectors, lookups) to `provideNonServices()`, as
  each task lists. Errors, interfaces, events and event listeners are not pinned.

## Review Focus

1. **Where the page comes from.** On Contao 5 the main request carries `pageModel` as a `PageModel`, a fragment
   sub-request as an id; Contao 4.13 has neither and sets `$GLOBALS['objPage']`. Discovery reads the main request,
   accepts both forms and falls back to `$GLOBALS['objPage']`; no page → no reader. Pinned in Task 7.
2. **Empty lookups.** `flare_reader.lookup([])` (an empty picker field after deserializing) returns an empty
   collection without a query and without an entries event. Pinned in Task 3.
3. **A reader page without an auto-item** (`/reader` opened directly): the resolution has no entry, the list is not
   queried, the breadcrumb stays unchanged. Pinned in Task 8.
4. **Long-running workers.** `ReaderResolver::reset()` empties the memo; the next main request with another auto-item
   resolves anew. Pinned in Task 8.
5. **Rows that do not share keys** (another source, a `ModifyListQueryStructEvent` select): `column()` skips rows
   without the key; a collection-level `attach()` collides with a column of *any* row. Pinned in Task 1.

Known limits, documented in Task 11 and not fixed (outside the spec's round-trip character list): an auto-item value
containing `/` or a literal `%2F` cannot round-trip (Symfony's router keeps `%2F` as a path separator, Contao splits
the parameters at `/`); on Contao 4.13 also `%` followed by a sprintf letter (`%s`, `%d`, `%b`, …), which 4.13's
legacy URL logic decodes. Such a link ends in a 404, never in another entry. The breadcrumb ignores Contao's preview
mode (invisible readers are not found there).

---

## File Structure

| Path | Change | Responsibility |
|---|---|---|
| `src/Entry/Entry.php`, `EntryMeta.php`, `ModelRef.php`, `EntryCollection.php` | create (T1) | the wrapper |
| `src/Entry/EntryLookupInterface.php`, `EntryTaggerInterface.php` | create (T2) | ports for listeners and cache tags |
| `src/Exception/{EntryNameCollision,SealedEntries,UndefinedEntryKey,ReadonlyEntry,MissingEntryId}Error.php` | create (T1) | |
| `src/Exception/InvalidLookupIdError.php` (T3), `MissingModelRefError.php` (T6), `UndefinedRelationError.php` (T10) | create | |
| `src/Event/ListDriverEntriesEvent.php`, `src/EventListener/NamedDispatch/ListDriverEntriesListener.php` | create (T2) | entries event |
| `src/Engine/Entry/EntryCollector.php` (T2), `ListEntryLookup.php` (T3) | create | rows → sealed collection; membership lookup |
| `src/Engine/Factory/EntryCollectorFactory.php` | create (T4) | wires collectors and lookups |
| `src/Engine/Loader/ValidationLoader{,Interface}.php` | modify (T3, T4) | `fetchEntriesByIds()`; no `fetchEntryById()`, no cache |
| `src/Util/EntryCache.php`, `src/Engine/View/LinksToReaderTrait.php` | delete (T4) | |
| `src/Engine/View/HandlesModelsTrait.php` | trim (T4), delete (T8) | |
| `src/Engine/View/InteractiveView.php`, `src/Engine/Projector/{Abstract,Interactive}Projector.php` | modify (T4) | list on entries |
| `src/Engine/View/ValidationView.php`, `src/Engine/Projector/ValidationProjector.php` | modify (T4, T8) | reader on entries |
| `src/Engine/Intent/Factory/ValidationIntentFactory.php` | modify (T4) | `createFromIntent()` |
| `src/Reader/{ReaderUrlGeneratorInterface,ReaderUrlGenerator,NullReaderUrlGenerator}.php`, `src/Event/ReaderUrlGeneratedEvent.php` | modify (T4, T5) | links from entries, one encoding layer |
| `src/Integration/ContaoCalendar/{View/InteractiveEventsView,Projector/EventsInteractiveProjector}.php` | modify (T4) | |
| `src/Contao/Entry/EntryCacheTagger.php` (T3), `EntryModelFactory.php` (T6), `EntryRelationResolver.php` (T10) | create | Contao side of entries |
| `src/Contao/Reader/ReaderElementFinder.php`, `AutoItemReader.php` (T7), `ReaderResolver.php` (T8); `src/Contao/ReaderResolution.php` (T8) | create | request-scoped reader |
| `src/Reader/ReaderRequestAttribute.php`, `src/Reader/Factory/ReaderRequestAttributeFactory.php`, `src/Reader/Resolver/ReaderRequestAttributeResolver.php` | delete (T8) | |
| `src/Event/Reader{PageMeta,Render}Event.php`, `src/EventListener/Reader/*`, `src/Integration/{ContaoNews,ContaoCalendar}/EventListener/*PageMetaListener.php`, `src/Integration/ContaoComments/EventListener/ContaoCommentsListener.php` | modify (T8) | reader events carry `Entry` |
| `src/Controller/ContentElement/ReaderController.php`, `src/Contao/Hook/BreadcrumbHook.php` | modify (T8) | use the resolver |
| `src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php` | modify (T8) | type switch only |
| `src/Twig/Runtime/FlareRuntime.php`, `src/Twig/Extension/FlareExtension.php`, `src/Event/ReaderSchemaOrgEvent.php`, `src/Integration/*/EventListener/*SchemaOrgListener.php` | modify (T9, T10) | |
| `contao/templates/content_element/flare_listview.html.twig` (T4), `flare_reader.html.twig` (T8) | modify | |
| `config/services.yaml` | modify (T1, T2, T3) | excludes, tagger alias |
| `tests/Engine/EnginePipelineTrait.php` | create (T3), extend (T4, T8) | SQLite-backed engine for tests |
| `tests/Fixture/TestModelFixture.php` (T6), `tests/Fixture/ContentModelFixture.php` (T8) | create | |
| `AGENTS.md`, `docs/docs/**` | modify (T11) | |

---

### Task 1: `Entry`, `EntryMeta`, `ModelRef`, `EntryCollection`

Read the spec sections "What it looks like", "`Entry`", "`EntryMeta` and `ModelRef`" and "`EntryCollection`".

**Files:**
- Create: `src/Entry/Entry.php`, `src/Entry/EntryMeta.php`, `src/Entry/ModelRef.php`, `src/Entry/EntryCollection.php`
- Create: `src/Exception/EntryNameCollisionError.php`, `src/Exception/SealedEntriesError.php`,
  `src/Exception/UndefinedEntryKeyError.php`, `src/Exception/ReadonlyEntryError.php`,
  `src/Exception/MissingEntryIdError.php`
- Modify: `config/services.yaml` — add `Entry` to the root `exclude` braces (`../src/{Config,Contao,…,Entry,…}`)
- Test: `tests/Entry/EntryTest.php`, `tests/Entry/EntryCollectionTest.php`, `tests/Entry/EntryTwigTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Produces:**

```php
namespace HeimrichHannot\FlareBundle\Entry;

final readonly class ModelRef { public function __construct(public string $table, public int|string $id) {} }
final readonly class EntryMeta { public function __construct(public ?ModelRef $modelRef = null) {} }

final class Entry
{
    public const RESERVED = ['to', 'row', 'meta'];

    /** @param array<string, mixed> $row  @internal entries are created by their EntryCollection */
    public function __construct(
        public readonly array     $row,
        public readonly EntryMeta $meta = new EntryMeta(),
        private readonly ?EntryCollection $collection = null,
    ) {}

    public function to(): ?string;                         // the collection's linker, memoized per entry
    public function attach(string $name, mixed $value): void; // a \Closure(Entry): mixed is lazy, run once
    public function __get(string $name): mixed;
    public function __isset(string $name): bool;
    /** Both throw ReadonlyEntryError: an entry is written only through attach() (no dynamic properties). */
    public function __set(string $name, mixed $value): void;
    public function __unset(string $name): void;
    /** @internal */ public function ownsAttachment(string $name): bool;
}

final class EntryCollection implements \IteratorAggregate, \Countable
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param ?\Closure(Entry): ?string $linker
     * @param ?\Closure(array<string, mixed>): EntryMeta $meta  default: an empty EntryMeta
     */
    public function __construct(array $rows = [], ?\Closure $linker = null, ?\Closure $meta = null);
    /** One ModelRef($table, $row['id']) per row; MissingEntryIdError for a row without a non-empty `id`. */
    public static function forTable(string $table, array $rows, ?\Closure $linker = null): self;
    /** @return \Iterator<int, Entry> */ public function getIterator(): \Iterator; // a fresh iterator per call
    public function count(): int;
    public function first(): ?Entry;
    /** @return list<mixed> distinct values of a row column, skipping rows without it and empty() values */
    public function column(string $name): array;
    /** @param \Closure(EntryCollection): \Closure(Entry): mixed $load */
    public function attach(string $name, \Closure $load): void;
    public function seal(): void;
    public function isSealed(): bool;
    /** @internal */ public function provides(string $name): bool;
    /** @internal runs phase 1 once per name, returns the picker's value for $entry */
    public function resolveFor(string $name, Entry $entry): mixed;
    /** @internal */ public function linkFor(Entry $entry): ?string;
}
```

Rules the signatures leave open (all from the spec):
- `Entry::__get()` order: `to` → `to()`; a row column (`array_key_exists`, so `null` values count) → own
  attachment → `$collection->provides()` → else `UndefinedEntryKeyError`. `row` and `meta` are declared properties
  and never reach `__get()`.
- `Entry::__isset()`: true for `to`, for every existing column (also `null`), own attachment, or collection name.
- `Entry::attach()` throws `EntryNameCollisionError` for a reserved name, a column of this row, a name already
  attached to this entry, or one its collection provides; `SealedEntriesError` once its collection is sealed.
- `EntryCollection::attach()` throws `EntryNameCollisionError` for a reserved name, a column of any row (the union
  of all row keys), a name any entry owns, or one already attached to the collection; `SealedEntriesError` after
  `seal()`. Phase 1 runs on the first `resolveFor()` of that name; the entry memoizes the picker's result.
- Internal code reads `$entry->row` directly, never `__get()`. `Entry` implements no `Traversable`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Entry/EntryTest.php
private static function entries(array $rows, ?\Closure $linker = null): EntryCollection
{
    return new EntryCollection($rows, $linker ?? static fn (Entry $e): string => '/reader/' . $e->row['id']);
}

public function testToIsTheLinkAndRowToTheColumn(): void
{
    $entry = self::entries([['id' => 1, 'to' => 'column']])->first();

    self::assertSame('/reader/1', $entry->to);
    self::assertSame('/reader/1', $entry->to());
    self::assertSame('column', $entry->row['to']);
}

public function testLinkIsAskedOncePerEntry(): void
{
    $calls = 0;
    $entry = self::entries([['id' => 1]], static function () use (&$calls): string { ++$calls; return '/x'; })->first();
    $entry->to; $entry->to();
    self::assertSame(1, $calls);
}

public function testNullColumnIsSetAndReadable(): void
{
    $entry = self::entries([['id' => 1, 'subtitle' => null]])->first();
    self::assertTrue(isset($entry->subtitle));
    self::assertNull($entry->subtitle);
}

public function testUnknownNameThrows(): void
{
    $this->expectException(UndefinedEntryKeyError::class);
    self::entries([['id' => 1]])->first()->missing;
}

public function testLazyValueRunsOnce(): void
{
    $calls = 0;
    $entry = self::entries([['id' => 1]])->first();
    $entry->attach('isNew', static function () use (&$calls): bool { ++$calls; return true; });
    self::assertTrue($entry->isNew);
    self::assertTrue($entry->isNew);
    self::assertSame(1, $calls);
}

/** @dataProvider provideCollisions */
public function testEntryAttachCollisionsThrow(string $name): void
{
    $entries = self::entries([['id' => 1, 'title' => 'T']]);
    $entries->attach('archive', static fn (): \Closure => static fn (): null => null);
    $entry = $entries->first();
    $entry->attach('isNew', true);

    $this->expectException(EntryNameCollisionError::class);
    $entry->attach($name, 'x');
}
// provideCollisions: ['to'], ['row'], ['meta'], ['title' (column)], ['isNew' (twice)], ['archive' (collection)]

public function testEntryIsNotIterable(): void
{
    self::assertNotInstanceOf(\Traversable::class, self::entries([['id' => 1]])->first());
}

public function testWritingOrUnsettingAPropertyThrows(): void
{
    $entry = self::entries([['id' => 1]])->first();
    try { $entry->archive = 'x'; self::fail(); } catch (ReadonlyEntryError) {}
    $this->expectException(ReadonlyEntryError::class);
    unset($entry->id);
}
```

```php
// tests/Entry/EntryCollectionTest.php
public function testCollectionLoaderRunsOncePerCollectionAndOnlyWhenRead(): void
{
    $phase1 = 0; $picks = 0;
    $entries = new EntryCollection([['id' => 1, 'pid' => 10], ['id' => 2, 'pid' => 20]]);
    $entries->attach('archive', function (EntryCollection $all) use (&$phase1, &$picks): \Closure {
        ++$phase1;
        self::assertSame([10, 20], $all->column('pid'));
        return static function (Entry $e) use (&$picks): string { ++$picks; return 'A' . $e->row['pid']; };
    });
    self::assertSame(0, $phase1);

    [$a, $b] = \iterator_to_array($entries);
    self::assertSame('A10', $a->archive);
    self::assertSame('A10', $a->archive);
    self::assertSame('A20', $b->archive);
    self::assertSame([1, 2], [$phase1, $picks]);
}

/** @dataProvider provideCollectionCollisions */
public function testCollectionAttachCollisionsThrow(string $name): void
{
    // row 2 alone has `extra`: rows may differ (Review Focus 5)
    $entries = new EntryCollection([['id' => 1], ['id' => 2, 'extra' => 'x']]);
    $entries->first()->attach('own', 1);
    $entries->attach('twice', static fn (): \Closure => static fn (): null => null);

    $this->expectException(EntryNameCollisionError::class);
    $entries->attach($name, static fn (): \Closure => static fn (): null => null);
}
// provideCollectionCollisions: ['to'], ['row'], ['meta'], ['extra'], ['own'], ['twice']

public function testAttachAfterSealThrowsOnBothLevels(): void
{
    $entries = new EntryCollection([['id' => 1]]);
    $entries->seal();
    self::assertTrue($entries->isSealed());

    try { $entries->first()->attach('a', 1); self::fail(); } catch (SealedEntriesError) {}
    $this->expectException(SealedEntriesError::class);
    $entries->attach('b', static fn (): \Closure => static fn (): null => null);
}

public function testForTableSetsAModelRefPerRow(): void
{
    $entry = EntryCollection::forTable('tl_news', [['id' => '5']])->first();
    self::assertEquals(new ModelRef('tl_news', '5'), $entry->meta->modelRef);
}

public function testForTableRejectsARowWithoutId(): void
{
    $this->expectException(MissingEntryIdError::class);
    EntryCollection::forTable('tl_news', [['title' => 'x']]);
}

public function testColumnSkipsMissingAndEmptyValues(): void
{
    $entries = new EntryCollection([['pid' => '1'], ['pid' => '2'], ['pid' => '1'], ['pid' => null], ['pid' => ''],
        ['pid' => '0'], ['other' => 'x']]);
    self::assertSame(['1', '2'], $entries->column('pid'));
}

public function testIsAListWithFreshIteratorsAndRepeatedIds(): void
{
    $entries = new EntryCollection([['id' => 7], ['id' => 7], ['id' => 8]]);
    self::assertSame([0, 1, 2], \array_keys(\iterator_to_array($entries)));
    self::assertCount(3, \iterator_to_array($entries));   // second pass sees all entries again
    self::assertCount(3, $entries);
}
```

```php
// tests/Entry/EntryTwigTest.php — a real Twig\Environment over an ArrayLoader
private function render(string $template, array $context, bool $strict = true): string
{
    $twig = new Environment(new ArrayLoader(['t' => $template]), ['strict_variables' => $strict]);
    return $twig->render('t', $context);
}

public function testCollectionWorksWithEmptyLengthAndLoopLast(): void
{
    $entries = new EntryCollection([['id' => 1], ['id' => 2]]);
    self::assertSame('2', $this->render('{% if e is empty %}x{% endif %}{{ e|length }}', ['e' => $entries]));
    self::assertSame('1,2', $this->render('{% for x in e %}{{ x.row.id }}{% if not loop.last %},{% endif %}{% endfor %}', ['e' => $entries]));
    self::assertSame('1212', $this->render('{% for x in e %}{{ x.id }}{% endfor %}{% for x in e %}{{ x.id }}{% endfor %}', ['e' => $entries]));
    self::assertSame('empty', $this->render('{% if e is empty %}empty{% endif %}', ['e' => new EntryCollection()]));
}

public function testToAndRowTo(): void
{
    $entry = (new EntryCollection([['id' => 1, 'to' => 'column']], static fn (): string => '/reader/1'))->first();
    self::assertSame('/reader/1|column', $this->render('{{ entry.to }}|{{ entry.row.to }}', ['entry' => $entry]));
}

public function testNullColumnUnderStrictVariables(): void
{
    $entry = (new EntryCollection([['id' => 1, 'subtitle' => null]]))->first();
    self::assertSame('null', $this->render('{{ entry.subtitle is null ? "null" : "set" }}', ['entry' => $entry]));
}

public function testUnknownNameFollowsTwigsRules(): void
{
    $entry = (new EntryCollection([['id' => 1]]))->first();
    self::assertSame('', $this->render('{{ entry.missing }}', ['entry' => $entry], strict: false));
    $this->expectException(\Twig\Error\RuntimeError::class);
    $this->render('{{ entry.missing }}', ['entry' => $entry]);
}
```

`ServicesConfigTest::provideNonServices()`: add `Entry`, `EntryMeta`, `ModelRef`, `EntryCollection`.

- [ ] **Step 2: Run the tests — expect failure**

Run: `make test tests/Entry` and `make test tests/DependencyInjection/ServicesConfigTest.php`
Expected: errors `Class "HeimrichHannot\FlareBundle\Entry\EntryCollection" not found`.

- [ ] **Step 3: Implement the classes and errors listed above; add `Entry` to the services exclude**

- [ ] **Step 4: Run the tests — expect pass**

Run: the same two calls → `OK`.

- [ ] **Step 5: Full checks and commit**

`make test`, `make phpstan`, `mago lint --minimum-fail-level note`, the Contao-free grep;
`git add src/Entry src/Exception config/services.yaml tests/Entry tests/DependencyInjection`, the staged
120-character check, `git commit -m "feat(entries): add Entry, EntryMeta, ModelRef and EntryCollection"`

---

### Task 2: Entries event and `EntryCollector`

Read the spec sections "Entries event" and "Views" (first bullet).

**Files:**
- Create: `src/Entry/EntryLookupInterface.php`, `src/Entry/EntryTaggerInterface.php`
- Create: `src/Event/ListDriverEntriesEvent.php`, `src/EventListener/NamedDispatch/ListDriverEntriesListener.php`
- Create: `src/Engine/Entry/EntryCollector.php`
- Modify: `config/services.yaml` — add `../src/Engine/Entry` to the `Engine\` block's `exclude`
- Test: `tests/Engine/Entry/EntryCollectorTest.php`,
  `tests/EventListener/NamedDispatch/ListDriverEntriesListenerTest.php` (pattern:
  `ListDriverBuildListListenerTest`), `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Task 1. **Produces:**

```php
interface EntryLookupInterface
{
    /**
     * `mixed`, not `int|string|array`: Twig calls methods without strict_types, so a typed parameter would coerce
     * `true` to 1 and turn `null` into a TypeError before the implementation can reject it with its own message.
     *
     * @param int|string|list<int|string> $ids ids of the list's own table; anything else throws InvalidLookupIdError
     * @return ($ids is array ? EntryCollection : ?Entry)
     */
    public function lookup(mixed $ids): Entry|EntryCollection|null;
}

interface EntryTaggerInterface { public function tag(Entry ...$entries): void; }

class ListDriverEntriesEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public function __construct(
        public readonly ListCompound    $list,
        public readonly IntentInterface $intent,
        public readonly EntryCollection $entries,
        private readonly EntryLookupInterface $lookup,
    ) {}

    /** Proxies $entries->attach(); phase 1 gets the lookup as second argument.
     *  @param \Closure(EntryCollection, EntryLookupInterface): \Closure(Entry): mixed $load */
    public function attach(string $name, \Closure $load): void;
}

final readonly class EntryCollector
{
    /** @param ?\Closure(Entry): ?string $linker */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ListCompound             $list,
        private IntentInterface          $intent,
        private ?\Closure                $linker = null,
    ) {}

    /** EntryCollection::forTable($list->dc, …); the event only if non-empty; always sealed.
     *  @param list<array<string, mixed>> $rows */
    public function collect(array $rows, EntryLookupInterface $lookup): EntryCollection;
}
```

`ListDriverEntriesListener`: `#[AsEventListener(priority: -200)]`, re-dispatches under
`"flare.list_driver.{$event->list->type}.entries"` when the type is a non-empty string.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Engine/Entry/EntryCollectorTest.php — list(): new ListCompound(driver: <anonymous ListDriverInterface,
// as in BuildsFilterEffectsTrait::buildElementContext()>, type: 'test_list', dc: 'tl_test')
public function testNonEmptyRowsDispatchOnceAndAreSealed(): void
{
    $events = [];
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static function (ListDriverEntriesEvent $e) use (&$events): void { $events[] = $e; });
    $list = $this->list();          // ListCompound type 'test_list', dc 'tl_test'
    $intent = new ValidationIntent();

    $entries = (new EntryCollector($dispatcher, $list, $intent, static fn (Entry $e): string => '/r/' . $e->row['id']))
        ->collect([['id' => 1], ['id' => 2]], $this->createMock(EntryLookupInterface::class));

    self::assertCount(1, $events);
    self::assertSame([$list, $intent, $entries], [$events[0]->list, $events[0]->intent, $events[0]->entries]);
    self::assertTrue($entries->isSealed());
    self::assertEquals(new ModelRef('tl_test', 1), $entries->first()->meta->modelRef);
    self::assertSame('/r/1', $entries->first()->to);
}

public function testEmptyRowsDispatchNothing(): void
{
    // same setup, collect([]) → no event, an empty sealed collection
}

public function testEventAttachHandsPhaseOneTheLookup(): void
{
    $lookup = $this->createMock(EntryLookupInterface::class);
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static fn (ListDriverEntriesEvent $e) => $e->attach(
        'seen',
        static fn (EntryCollection $all, EntryLookupInterface $l): \Closure => static fn (): bool => $l === $lookup,
    ));

    $entry = (new EntryCollector($dispatcher, $this->list(), new ValidationIntent()))->collect([['id' => 1]], $lookup)->first();

    self::assertTrue($entry->seen);
}
```

`ListDriverEntriesListenerTest::testDispatchesUnderTheDriverType`: a listener on
`flare.list_driver.test_list.entries` receives the same event object. `ServicesConfigTest`: `EntryCollector`,
`ListDriverEntriesEvent` → non-services.

- [ ] **Step 2: Run — expect failure.** `make test tests/Engine/Entry`, `make test tests/EventListener/NamedDispatch`,
  `make test tests/DependencyInjection/ServicesConfigTest.php` → class not found.
- [ ] **Step 3: Implement the interfaces, the event, the listener and the collector; add the services exclude.**
- [ ] **Step 4: Run — expect pass.** The same three calls → `OK`.
- [ ] **Step 5: Full checks and commit.** `feat(entries): add the entries event and EntryCollector`

---

### Task 3: The lookup (`ListEntryLookup`), `fetchEntriesByIds()`, `EntryCacheTagger`

Read the spec section "Lookup" and "Non-goals" (cache invalidation).

**Files:**
- Create: `tests/Engine/EnginePipelineTrait.php` (test support, see below)
- Modify: `src/Engine/Loader/ValidationLoaderInterface.php`, `src/Engine/Loader/ValidationLoader.php` — add
  `fetchEntriesByIds()` (the rest stays until Task 4)
- Create: `src/Engine/Entry/ListEntryLookup.php`, `src/Exception/InvalidLookupIdError.php`
- Create: `src/Contao/Entry/EntryCacheTagger.php`
- Modify: `config/services.yaml` — alias
  `HeimrichHannot\FlareBundle\Entry\EntryTaggerInterface: '@HeimrichHannot\FlareBundle\Contao\Entry\EntryCacheTagger'`
- Test: `tests/Engine/Entry/ListEntryLookupTest.php`, `tests/Contao/Entry/EntryCacheTaggerTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Tasks 1–2. **Produces:**

```php
interface ValidationLoaderInterface
{
    /** One query `main.id IN (:ids)` over the list's query by the reader's rules, without LIMIT/OFFSET; rows
     *  in database order. Wraps any \Exception into FlareException (like the other loader methods).
     *  @param list<int> $ids  @return list<array<string, mixed>> */
    public function fetchEntriesByIds(array $ids): array;
    // fetchEntryById(), fetchEntryByAutoItem() unchanged in this task
}

final class ListEntryLookup implements EntryLookupInterface
{
    public function __construct(
        private readonly ValidationLoaderInterface $loader,
        private readonly EntryCollector            $collector,
        private readonly EntryTaggerInterface      $tagger,
    ) {}
    public function lookup(mixed $ids): Entry|EntryCollection|null;
}

final readonly class EntryCacheTagger implements EntryTaggerInterface
{
    /** FOS registers the tagger only when cache tagging is enabled; Contao injects it optionally too. */
    public function __construct(private ?SymfonyResponseTagger $responseTagger = null) {}
    /** Adds "contao.db.<table>.<id>" for every entry with a modelRef; skips the others; no-op without a tagger. */
    public function tag(Entry ...$entries): void;
}
```

`fetchEntriesByIds()` builds its query with `ListQueryDirector::createQueryBuilder()` (intent and filter values from
the loader config, as `executeQuery()` does), then
`andWhere($qb->expr()->in('main.id', ':flare_lookup_ids'))`, `setParameter(…, $ids, ArrayParameterType::INTEGER)`,
`setFirstResult(0)`, `setMaxResults(null)`; a `null` query builder (aborted filter) yields `[]`.

`ListEntryLookup::lookup()`:
1. Validate before anything is coerced: a non-array input is a single id. Each id must be an `int` or a string
   matching `/^\d+$/`; anything else — `null`, `''`, `true`, a float, a serialized string — throws
   `InvalidLookupIdError` (message: ids of the list's own table — an int, a numeric string or a list of those;
   deserialize a stored value first). Cast to `int`, keep the first occurrence of duplicates.
2. An empty list → an empty, sealed `EntryCollection` (no query, no event).
3. Memo key `implode(',', $ids)`: identical normalized lookups query once.
4. `fetchEntriesByIds()`, reorder the rows to the given id order (rows indexed by `(int) $row['id']`), then
   `$collector->collect($rows, $this)`, then `$tagger->tag(...$collection)`.
5. A single id → `$collection->first()` (`null` for a non-member); a list → the collection.

**Test support — `tests/Engine/EnginePipelineTrait.php`** (uses `BuildsFilterEffectsTrait`; construction patterns
from `InteractiveProjectorTest::createProjector()` and `ListQueryDirectorTest::createDirector()`):

```php
trait EnginePipelineTrait
{
    use BuildsFilterEffectsTrait;

    /** Number of list queries built (ModifyListQueryStructEvent dispatches). */
    protected int $queries = 0;

    /** SQLite in memory; table tl_test(id INTEGER PRIMARY KEY, pid INTEGER DEFAULT 0, alias TEXT DEFAULT '',
     *  title TEXT DEFAULT '', published INTEGER DEFAULT 0) filled with $rows. */
    protected function createConnection(array $rows): Connection;

    /** The real director over $connection; registers the bundle's Select (490), Conditions (470), Page (430),
     *  Order (420) and Join (-450) modifier listeners and the query counter on $dispatcher. */
    protected function createListQueryDirector(Connection $connection, EventDispatcher $dispatcher): ListQueryDirector;

    /** A LoaderFactory whose FilterCompoundFactory knows SimpleEquationFilterElement::TYPE. */
    protected function createLoaderFactory(Connection $connection, EventDispatcher $dispatcher): LoaderFactory;

    /** ListCompound of type 'test_list' on 'tl_test' with the given filters (keyed by alias). */
    protected function createList(FilterCompound ...$filters): ListCompound;

    /** A fixed filter `published = 1` (SimpleEquationFilterElement, alias 'published'). */
    protected static function publishedOnly(): FilterCompound;
}
```

`pdo_sqlite` is in the Docker image and in `shivammathur/setup-php`'s default extensions; no workflow change.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Engine/Entry/ListEntryLookupTest.php — rows: 1..3 published, 4 unpublished.
// SpyTagger (in the test file) implements EntryTaggerInterface; $tagged lists "<table>.<id>" per tagged entry.
// ids(EntryCollection): list<int> of the entries' row ids.
private function lookup(?EventDispatcher $dispatcher = null, ?SpyTagger &$tagger = null): ListEntryLookup
{
    $dispatcher ??= new EventDispatcher();
    $connection = $this->createConnection([
        ['id' => 1, 'published' => 1], ['id' => 2, 'published' => 1], ['id' => 3, 'published' => 1],
        ['id' => 4, 'published' => 0],
    ]);
    $list = $this->createList(self::publishedOnly());
    $intent = new ValidationIntent();
    $loader = $this->createLoaderFactory($connection, $dispatcher)
        ->createValidationLoader(new ValidationLoaderConfig($list, $intent, 'id'));
    $tagger = new SpyTagger();     // records tagged entries

    return new ListEntryLookup($loader, new EntryCollector($dispatcher, $list, $intent,
        static fn (Entry $e): string => '/r/' . $e->row['id']), $tagger);
}

public function testMembersComeBackInTheGivenOrderInOneQuery(): void
{
    $result = $this->lookup()->lookup([3, '1', 2]);
    self::assertSame([3, 1, 2], \array_map(static fn (Entry $e): int => (int) $e->row['id'], \iterator_to_array($result)));
    self::assertSame(1, $this->queries);
}

public function testNonMembersAreMissing(): void
{
    $lookup = $this->lookup();
    self::assertSame([1], self::ids($lookup->lookup([4, 1])));
    self::assertNull($lookup->lookup(4));
}

public function testDuplicatesCollapseAndIdenticalLookupsQueryOnce(): void
{
    $lookup = $this->lookup();
    self::assertSame([2], self::ids($lookup->lookup([2, '2', 2])));
    $lookup->lookup([2]);
    self::assertSame(1, $this->queries);
}

/** @dataProvider provideInvalidIds */
public function testInvalidIdsThrow(mixed $ids): void
{
    $this->expectException(InvalidLookupIdError::class);
    $this->lookup()->lookup($ids);
}
// provideInvalidIds: ['a:2:{i:0;s:1:"1";i:1;s:1:"2";}'], [['1', 'x']], ['1.5'], [1.5], [null], [''], [true], [[null]]

/** @dataProvider provideInvalidTwigIds  through a real Twig\Environment, which calls methods without strict_types */
public function testInvalidIdsThrowThroughTwig(mixed $ids): void
{
    $twig = new Environment(new ArrayLoader(['t' => '{% set r = l.lookup(v) %}']), ['strict_variables' => true]);
    try {
        $twig->render('t', ['l' => $this->lookup(), 'v' => $ids]);
        self::fail('No error thrown.');
    } catch (\Twig\Error\RuntimeError $e) {
        self::assertInstanceOf(InvalidLookupIdError::class, $e->getPrevious());
    }
}
// provideInvalidTwigIds: [true], [null], ['a:1:{i:0;s:1:"1";}']

public function testEmptyLookupQueriesNothing(): void   // Review Focus 2
{
    $events = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static function () use (&$events): void { ++$events; });
    $result = $this->lookup($dispatcher)->lookup([]);
    self::assertCount(0, $result);
    self::assertSame([0, 0], [$this->queries, $events]);
}

public function testMembersHaveLinksRunTheEventAndAreTagged(): void
{
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static fn (ListDriverEntriesEvent $e) =>
        $e->entries->first()?->attach('flag', true));
    $entry = $this->lookup($dispatcher, $tagger)->lookup(1);

    self::assertSame('/r/1', $entry->to);
    self::assertTrue($entry->flag);
    self::assertSame(['tl_test.1'], $tagger->tagged);   // "<table>.<id>" of each tagged entry
}

public function testLookupInPhaseOneDoesNotRecurseAndChainsOnlyWhenRead(): void
{
    // the same listener serves every collection, the lookup's result included; the batch lookup runs in phase 1
    $phase1 = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static fn (ListDriverEntriesEvent $e) => $e->attach(
        'similar',
        static function (EntryCollection $all, EntryLookupInterface $l) use (&$phase1): \Closure {
            ++$phase1;
            $next = static fn (mixed $id): int => ((int) $id % 3) + 1;
            $similar = $l->lookup(\array_map($next, $all->column('id')));
            return static function (Entry $x) use ($similar, $next): ?Entry {
                foreach ($similar as $s) { if ((int) $s->row['id'] === $next($x->row['id'])) { return $s; } }
                return null;
            };
        },
    ));
    $entry = $this->lookup($dispatcher)->lookup(1);
    self::assertSame([1, 0], [$this->queries, $phase1]);   // dispatching ran no phase 1

    self::assertSame(2, (int) $entry->similar->row['id']);
    self::assertSame([2, 1], [$this->queries, $phase1]);   // the result's own event registered lazily
    self::assertSame(3, (int) $entry->similar->similar->row['id']);
    self::assertSame([3, 2], [$this->queries, $phase1]);
}
```

```php
// tests/Contao/Entry/EntryCacheTaggerTest.php
public function testTagsEntriesWithAModelRef(): void
{
    $responseTagger = new SymfonyResponseTagger();
    $entries = EntryCollection::forTable('tl_news', [['id' => 5], ['id' => 6]]);
    (new EntryCacheTagger($responseTagger))->tag(...$entries, ...new EntryCollection([['id' => 7]]));
    self::assertSame('contao.db.tl_news.5,contao.db.tl_news.6', $responseTagger->getTagsHeaderValue());
}

public function testWithoutAResponseTaggerNothingHappens(): void
{
    (new EntryCacheTagger())->tag(...EntryCollection::forTable('tl_news', [['id' => 5]]));
    $this->addToAssertionCount(1);
}
```

`ServicesConfigTest`: `ListEntryLookup` → non-service; `EntryCacheTagger` → service; new test
`testEntryTaggerIsAliasedToTheCacheTagger` (`getAlias(EntryTaggerInterface::class)` → `EntryCacheTagger::class`).

- [ ] **Step 2: Run — expect failure.** `make test tests/Engine/Entry`, `make test tests/Contao/Entry`,
  `make test tests/DependencyInjection/ServicesConfigTest.php` → class not found.
- [ ] **Step 3: Implement the trait, `fetchEntriesByIds()`, `ListEntryLookup`, `InvalidLookupIdError`, `EntryCacheTagger`, the alias.**
- [ ] **Step 4: Run — expect pass.** The same three calls → `OK`.
- [ ] **Step 5: Full checks and commit.** `feat(entries): look up member entries of a list in one query`

---

### Task 4: The list view on entries

Read the spec sections "Views", "Reader URL" (first and last bullets), "Events integration", "Twig" (last bullet,
list template) and "Loader".

**Files:**
- Create: `src/Engine/Factory/EntryCollectorFactory.php`
- Modify: `src/Engine/View/InteractiveView.php` (entries; no models, no `to()`, no `$table`, no generator)
- Modify: `src/Engine/Projector/AbstractProjector.php` (subscribe `EntryCollectorFactory`, getter),
  `src/Engine/Projector/InteractiveProjector.php`
- Modify: `src/Engine/Intent/Factory/ValidationIntentFactory.php` — add `createFromIntent()`
- Modify: `src/Engine/View/ValidationView.php`, `src/Engine/Projector/ValidationProjector.php` — drop `to()`,
  `getEntry()`, `getModel()`, the reader-model/generator methods and the generator argument; keep
  `getEntryByAutoItem(): ?array` and `getModelByAutoItem()` for the controller until Task 8
- Modify: `src/Engine/View/HandlesModelsTrait.php` — drop `createModelsFromEntries()`
- Delete: `src/Engine/View/LinksToReaderTrait.php`, `src/Util/EntryCache.php`
- Modify: `src/Engine/Loader/ValidationLoader{,Interface}.php` — drop `fetchEntryById()` and the cache;
  `fetchEntryByAutoItem()` returns `null` on a miss
- Modify: `src/Reader/ReaderUrlGeneratorInterface.php`, `ReaderUrlGenerator.php`, `NullReaderUrlGenerator.php`,
  `src/Event/ReaderUrlGeneratedEvent.php` — `Entry` instead of `Model` (encoding unchanged until Task 5)
- Modify: `src/Integration/ContaoCalendar/View/InteractiveEventsView.php`,
  `src/Integration/ContaoCalendar/Projector/EventsInteractiveProjector.php`
- Modify: `contao/templates/content_element/flare_listview.html.twig`
- Modify: `tests/Engine/EnginePipelineTrait.php` — add `createProjectorRegistry()`
- Test: `tests/Engine/Projector/InteractiveProjectorTest.php` (update), `tests/Engine/View/InteractiveViewTest.php`,
  `tests/Integration/ContaoCalendar/InteractiveEventsViewTest.php`, `tests/Reader/ReaderUrlGeneratorTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Tasks 1–3. **Produces:**

```php
final readonly class EntryCollectorFactory
{
    public function __construct(
        private EventDispatcherInterface  $eventDispatcher,
        private LoaderFactory             $loaderFactory,
        private ReaderUrlGeneratorFactory $readerUrlGeneratorFactory,
        private EntryTaggerInterface      $entryTagger,
    ) {}
    /** linker = $readerUrlGenerator->generate(...) */
    public function create(ListCompound $list, IntentInterface $intent, ReaderUrlGeneratorInterface $readerUrlGenerator): EntryCollector;
    /** Membership by the reader's rules: a ValidationLoader for ($list, $intent, the reader auto-item field), the
     *  generator from $intent->createReaderUrlConfig(), a collector for $intent. */
    public function createLookup(ListCompound $list, ValidationIntent $intent): EntryLookupInterface;
}

// ValidationIntentFactory
/** The reader's rules for links out of a list: its reader page and auto-item field, no filter values. */
public function createFromIntent(InteractiveIntent $intent): ValidationIntent;

class InteractiveView implements ViewInterface
{
    public function __construct(
        private readonly InteractiveLoaderInterface $loader,
        private readonly FormInterface              $form,
        private readonly Paginator                  $paginator,
        private readonly EntryCollector             $collector,
        private readonly EntryLookupInterface       $lookup,
        private readonly int                        $totalItems,
        private readonly bool                       $failed = false,
    ) {}
    public function getEntries(): EntryCollection;   // lazy, memoized: $collector->collect($loader->fetchEntries(), $lookup)
    // isFailed(), getForm(), getPaginator(), getCount(), issetEntries() unchanged
}

// InteractiveProjector: constructor gains ValidationIntentFactory $validationIntentFactory (last parameter)
protected function createView(
    InteractiveLoaderInterface $loader, FormInterface $form, Paginator $paginator,
    EntryCollector $collector, EntryLookupInterface $lookup, int $totalItems, bool $failed = false,
): InteractiveView;

interface ReaderUrlGeneratorInterface { public function generate(Entry $entry): ?string; }
// ReaderUrlGenerator reads (string) ($entry->row[$autoItemField] ?? ''); '' → null.
// ReaderUrlGeneratedEvent(Entry $entry, string $autoItem, PageModel $page, string $url)

class InteractiveEventsView extends InteractiveView
{
    /** @return array<string, list<Entry>> grouped by $entry->row['_flare_event_group'] ?? '' */
    public function getEntriesGrouped(): array;
}
```

The projector builds the success path's collector with the paged intent it hands the loader, the failed and
invalid paths' collectors with the incoming intent (their collections are empty, so no event fires).

`flare_listview.html.twig` debug block: link `entry.to`, heading `entry.meta.modelRef.table`, the `dl` iterates
`entry.row` (keys except `password`); no `flare_list.to()`, no `flare_list.model()`.

`EnginePipelineTrait::createProjectorRegistry(Connection $connection, EventDispatcher $dispatcher, array $query = [],
bool $debug = false): ProjectorRegistry` — Interactive, Aggregation and Validation projectors over the trait's
director and loader factory, each with a `ServiceLocator` that serves `AbstractProjector::getSubscribedServices()`
(wiring as in `InteractiveProjectorTest::createProjector()`), an `EntryCollectorFactory` with a spy tagger, and a
`RequestStack` whose current request carries `$query` as its query parameters (a submission of form `f` is
`['f' => ['q' => '2']]`).

- [ ] **Step 1: Write the failing tests**

```php
// tests/Engine/View/InteractiveViewTest.php — rows 1..3 published, 4 unpublished; list: publishedOnly()
public function testEntriesCarryModelRefsAndRunTheEventOnce(): void
{
    $events = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ListDriverEntriesEvent::class, static function () use (&$events): void { ++$events; });
    $view = $this->project($dispatcher);   // InteractiveIntent(new PaginatorConfig(itemsPerPage: 2), formName: 'f')

    $entries = $view->getEntries();
    self::assertSame($entries, $view->getEntries());
    self::assertCount(2, $entries);           // the page
    self::assertSame(3, $view->getCount());   // the total
    self::assertTrue($entries->isSealed());
    self::assertEquals(new ModelRef('tl_test', 1), $entries->first()->meta->modelRef);
    self::assertSame(1, $events);
}

/** @dataProvider provideEmptyViews */
public function testEmptyViewsDispatchNothing(string $case): void
{
    // 'failed': the list carries a FilterFailure; 'invalid': a controlled filter rejects the submitted value;
    // 'no rows': all rows unpublished, so the published filter matches nothing. Listener counter as above.
    self::assertCount(0, $view->getEntries());
    self::assertSame(0, $events);
}

public function testLookupInPhaseOneIgnoresFormValues(): void
{
    // list: a controlled filter (KeywordsFilterControl, alias 'q') whose element adds `id = <keywords>` for a
    // non-null value; submitted q=2. A listener attaches 'other' = $lookup->lookup(1) in phase 1.
    $entries = $view->getEntries();
    self::assertSame([2], self::ids($entries));
    self::assertSame(1, (int) $entries->first()->other->row['id']);
}
```

```php
// tests/Integration/ContaoCalendar/InteractiveEventsViewTest.php
public function testGroupsEntriesWithRepeatedIdsByEventGroup(): void
{
    // loader stub returns [['id' => 7, '_flare_event_group' => '2026-11-11'], ['id' => 8, '_flare_event_group' => '2026-11-11'],
    //                       ['id' => 7, '_flare_event_group' => '2026-11-12']]
    $grouped = $view->getEntriesGrouped();
    self::assertSame(['2026-11-11', '2026-11-12'], \array_keys($grouped));
    self::assertSame([7, 8], \array_map(static fn (Entry $e): int => $e->row['id'], $grouped['2026-11-11']));
    self::assertContainsOnlyInstancesOf(Entry::class, $grouped['2026-11-12']);
}
```

```php
// tests/Reader/ReaderUrlGeneratorTest.php
public function testReadsTheAutoItemFromTheRowAndDispatchesTheEntry(): void
{
    $page = $this->createMock(PageModel::class);
    $page->method('getAbsoluteUrl')->with('/my-alias')->willReturn('https://example.org/reader/my-alias');
    $seen = null;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(ReaderUrlGeneratedEvent::class, static function (ReaderUrlGeneratedEvent $e) use (&$seen): void { $seen = $e; });
    $entry = (new EntryCollection([['id' => 1, 'alias' => 'my-alias']]))->first();

    $url = (new ReaderUrlGenerator($dispatcher, new ReaderUrlConfig($page, 'alias')))->generate($entry);

    self::assertSame('https://example.org/reader/my-alias', $url);
    self::assertSame([$entry, 'my-alias'], [$seen->entry, $seen->autoItem]);
}
// testEmptyAutoItemHasNoLink: alias '' → null, no event. testNullGeneratorHasNoLink.
```

`InteractiveProjectorTest`: pass `new ValidationIntentFactory(...)` to the constructor, serve
`EntryCollectorFactory` from the locator, replace `assertSame([], $view->getEntries())` by
`assertCount(0, $view->getEntries())`. `ServicesConfigTest`: `EntryCollectorFactory` → service.

- [ ] **Step 2: Run — expect failure.** `make test tests/Engine`, `make test tests/Integration/ContaoCalendar`,
  `make test tests/Reader`.
- [ ] **Step 3: Implement the changes listed under Files.**
- [ ] **Step 4: Run — expect pass.** The same three calls → `OK`. Then
  `grep -rn "LinksToReaderTrait\|EntryCache\b\|fetchEntryById\|createModelsFromEntries\|flare_list\.to\|flare_list\.model" src contao tests config`
  → no output.
- [ ] **Step 5: Full checks and commit.** `refactor(engine): hand out entries in the list view`

---

### Task 5: Reader URL round trip

Read the spec section "Reader URL" (round trip) and "Intent" bug 5.

**Files:**
- Modify: `src/Reader/ReaderUrlGenerator.php`, `src/Event/ReaderUrlGeneratedEvent.php` (doc comment of `$autoItem`)
- Test: `tests/Reader/ReaderUrlRoundTripTest.php`, `tests/Reader/ReaderUrlGeneratorTest.php`

**Interfaces — Consumes:** Task 4's `ReaderUrlGenerator`. Produces nothing new.

The generator passes `'/' . StringUtil::revertInputEncoding($stored)` to `getAbsoluteUrl()` — the router owns
percent-encoding; `Str::urlEncodePath()` is no longer called here. `revertInputEncoding()` (exists since Contao 4.13)
is the inverse of how `Input::get('auto_item', false, true)` encodes the routed value, so stored value → URL →
auto-item is the identity. `ReaderUrlGeneratedEvent::$autoItem` is that URL text — the reverted value, never
percent-encoded; a listener that builds another URL from it passes it to the router as is. (The dormant
`ChangelanguageListener` still calls `Str::urlEncodePath()` on it; it stays unchanged, see the spec's non-goals.)
`ReaderUrlGeneratorTest::testReadsTheAutoItemFromTheRowAndDispatchesTheEntry` gains a case with a stored
`a&#35;b`: `getAbsoluteUrl('/a#b')`, `$event->autoItem === 'a#b'`.

Out of scope (Review Focus, known limits): values with `/` or a literal `%2F`, and on Contao 4.13 `%` followed by a
sprintf letter. No test pins them.

- [ ] **Step 1: Write the failing test**

```php
/** @dataProvider provideValues */
public function testLinkRoutesBackToTheSameStoredValue(string $raw): void
{
    $stored = Input::encodeInput($raw, InputEncodingMode::encodeAll);   // how Contao stores backend input
    $routes = new RouteCollection();
    $routes->add('reader', new Route('/reader{!parameters}', ['parameters' => ''], ['parameters' => '(/.+?)?']));
    $context = new RequestContext('', 'GET', 'example.org', 'https');
    $page = $this->createMock(PageModel::class);
    $page->method('getAbsoluteUrl')->willReturnCallback(static fn (string $p): string =>
        (new UrlGenerator($routes, $context))->generate('reader', ['parameters' => $p], UrlGeneratorInterface::ABSOLUTE_URL));
    $entry = (new EntryCollection([['id' => 1, 'alias' => $stored]]))->first();

    $url = (new ReaderUrlGenerator(new EventDispatcher(), new ReaderUrlConfig($page, 'alias')))->generate($entry);
    $match = (new UrlMatcher($routes, $context))->match((string) \parse_url($url, \PHP_URL_PATH));
    $autoItem = \substr($match['parameters'], 1);                        // Contao's InputEnhancer

    self::assertSame($stored, Input::encodeInput($autoItem, InputEncodingMode::encodeAll)); // = Input::get(…, false, true)

    if (!\str_contains($raw, '%')) {
        self::assertStringNotContainsString('%25', $url);   // no double encoding
    }
}
// provideValues: 'space' => ['a b'], 'unicode' => ['Grüße'], 'quotes' => ["it's \"x\""], 'hash' => ['a#b'],
//                'percent' => ['100%'], 'ampersand' => ['a&b']
```

- [ ] **Step 2: Run — expect failure.** `make test tests/Reader` → all six values fail (`%2520`, `%25C3…`,
  `%2526…`), and the new `a&#35;b` case of `ReaderUrlGeneratorTest`.
- [ ] **Step 3: Implement the generator change and the doc comment.**
- [ ] **Step 4: Run — expect pass.** `make test tests/Reader` → `OK`.
- [ ] **Step 5: Full checks and commit.** `fix(reader): let reader URLs round-trip special characters`

---

### Task 6: Contao boundary service (entry → model)

Read the spec section "Contao boundary service" and "Tests" (seams).

**Files:**
- Create: `src/Contao/Entry/EntryModelFactory.php`, `src/Exception/MissingModelRefError.php`
- Create: `tests/Fixture/TestModelFixture.php`
- Test: `tests/Contao/Entry/EntryModelFactoryTest.php`, `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Task 1. **Produces:**

```php
final readonly class EntryModelFactory
{
    public function __construct(private ContaoFramework $framework) {}
    /** MissingModelRefError without modelRef; null without a model class for the table; the registry's instance
     *  for (table, id) if any; else createInstance($class, [$row restricted to the table's columns]). */
    public function create(Entry $entry): ?Model;
}

// tests/Fixture/TestModelFixture.php — not final, so tests can mock getRelated() (Task 10)
class TestModelFixture extends Model
{
    protected static $strTable = 'tl_test';
    /** @param array<string, mixed> $row */
    public function __construct(array $row = []) { $this->arrData = $row; }
    /** No-ops: Contao's versions read the DCA (unique fields) through the container; Task 8 registers fixtures
     *  in the real Model\Registry. */
    public function onRegister(Registry $registry): void {}
    public function onUnregister(Registry $registry): void {}
}
```

`create()` in this order; all Contao access goes through adapters:
1. The model class, resolved in one guarded step: the `Model` adapter's `getClassFromTable()` throws a
   `\RuntimeException` on Contao 5 when `$GLOBALS['TL_MODELS']` has no entry for the table (4.13 returns a derived
   name instead) — catch it → `null`. A class that does not exist or is no `Model` subclass → `null`.
2. Only then the registry (`Model\Registry::getInstance()->fetch($table, $id)` — `fetch()` itself calls
   `getClassFromTable()`, hence step 1 first).
3. Else the row restricted to the keys of `DcaExtractor::getInstance($table)->getFields()`, always keeping `id`
   (a table without `sql` fields would otherwise lose its primary key), and
   `$framework->createInstance($class, [$row])` — Contao's constructor registers the new model.

The test builds its framework with `createMock(ContaoFramework::class)` and stubs `getAdapter()` and
`createInstance()` itself (`mockContaoFramework()`'s `$instances` closures receive no arguments). The
`createInstance()` stub builds a `TestModelFixture` from the given row and puts it into the array behind the
registry mock's `fetch()`, as Contao's constructor would register it.

- [ ] **Step 1: Write the failing tests** (`EntryModelFactoryTest extends ContaoTestCase`)

```php
public function testWithoutModelRefThrows(): void
{
    $this->expectException(MissingModelRefError::class);
    $this->factory()->create(new Entry(['id' => 1]));
}

public function testWithoutRegisteredModelClassReturnsNull(): void
{
    // Model adapter: getClassFromTable('tl_test') throws \RuntimeException (Contao 5); registry never asked
    self::assertNull($this->factory(classLookupThrows: true)->create($this->entry()));
}

public function testARegisteredButMissingModelClassReturnsNull(): void
{
    // Model adapter: getClassFromTable('tl_test') → 'Contao\NoSuchModel' (4.13, or a stale TL_MODELS entry)
    self::assertNull($this->factory(class: 'Contao\NoSuchModel')->create($this->entry()));
}

public function testReturnsTheRegistryInstance(): void
{
    $registered = new TestModelFixture(['id' => 5]);
    // Registry adapter getInstance() → Registry mock, fetch('tl_test', 5) → $registered; createInstance never called
    self::assertSame($registered, $this->factory(registered: $registered)->create($this->entry()));
}

public function testCreatesAndRegistersAModelFromTheTableColumnsOnly(): void
{
    // DcaExtractor mock getFields() → ['id' => …, 'title' => …]; createInstance stubbed as described above
    $factory = $this->factory();
    $entry = $this->entry(['id' => 5, 'title' => 'T', '_flare_event_group' => 'x', 'a__b' => 1]);

    $model = $factory->create($entry);
    self::assertInstanceOf(TestModelFixture::class, $model);
    self::assertSame(['id' => 5, 'title' => 'T'], $model->row());
    self::assertSame($model, $factory->create($entry));   // the registered instance; createInstance ran once
    self::assertSame(1, $this->instancesCreated);
}

public function testTheIdSurvivesATableWithoutSqlFields(): void
{
    // getFields() → []
    self::assertSame(['id' => 5], $this->factory(fields: [])->create($this->entry(['id' => 5, 'title' => 'T']))?->row());
}
// entry(): EntryCollection::forTable('tl_test', [$row])->first()
```

`ServicesConfigTest`: `EntryModelFactory` → service.

- [ ] **Step 2: Run — expect failure.** `make test tests/Contao/Entry/EntryModelFactoryTest.php`,
  `make test tests/DependencyInjection/ServicesConfigTest.php`
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run — expect pass.** The same two calls → `OK`.
- [ ] **Step 5: Full checks and commit.** `feat(contao): turn entries into models at the Contao boundary`

---

### Task 7: Reader discovery and auto-item reading

Read the spec section "Reader resolver" (discovery, auto-item) and "Anti-goals" (last bullet).

**Files:**
- Create: `src/Contao/Reader/ReaderElementFinder.php`, `src/Contao/Reader/AutoItemReader.php`
- Modify: `tests/Engine/EnginePipelineTrait.php` — add
  `createReaderTables(Connection $connection, array $articles, array $elements): void` (the SQLite tables below,
  filled with the given rows; defaults: published/visible, `start`/`stop` `''`, `tstamp` 1, `ptable` `tl_article`)
- Test: `tests/Contao/Reader/ReaderElementFinderTest.php`, `tests/Contao/Reader/AutoItemReaderTest.php`,
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Produces:**

```php
final readonly class AutoItemReader
{
    public function __construct(private ContaoFramework $framework) {}
    /** Input adapter get('auto_item', false, true): input-encoded, not marked as used; '' and non-strings → null. */
    public function read(): ?string;
}

final readonly class ReaderElementFinder
{
    public function __construct(
        private Connection      $connection,
        private ContaoFramework $framework,
        private RequestStack    $requestStack,
    ) {}
    /** The single visible top-level flare_reader element of the current page; null for none or several. */
    public function find(): ?ContentModel;
}
```

`find()`:
1. Page id: the **main** request's `pageModel` attribute — a `PageModel` (its `id`) or a numeric id — else
   `$GLOBALS['objPage']->id` if set; none → `null`.
2. One DBAL query: `tl_content` (`type = 'flare_reader'`; top level only: `ptable = 'tl_article'` or, as Contao 4.13
   stores article content, `ptable = ''`) joined to `tl_article` on `pid`, `tl_article.pid = :page`; article
   `published = 1` and start/stop; element `invisible = 0`, `tstamp != 0` (unsaved elements, as Contao's finders
   skip them) and start/stop — written like `ContentModel::findPublishedByPidAndTable()`:
   `(start = '' OR start <= :time) AND (stop = '' OR stop > :time)` with `:time = Date::floorToMinute()`; ordered by
   article `sorting`, then element `sorting`; selects both ids. Preview mode is not honored (known limit).
3. Per candidate: load `ContentModel` and `ArticleModel` via adapters (`findByPk`), keep it if the Controller
   adapter's `isVisibleElement()` is true for both (protected/groups; guests on 4.13; the hook).
4. Exactly one left → that `ContentModel`; otherwise `null`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Contao/Reader/ReaderElementFinderTest.php (ContaoTestCase) — SQLite tables tl_article(id, pid, sorting,
// published, start, stop) and tl_content(id, pid, ptable, type, sorting, invisible, start, stop, tstamp); page id 7.
public function testFindsTheOneVisibleReader(): void { self::assertSame(20, (int) $this->finder()->find()?->id); }
public function testHiddenElementsAndArticlesAreSkipped(): void
{
    // reader 20 invisible=1; reader 21 in an unpublished article; reader 22 with start in the future → null
}
public function testTwoVisibleReadersFindNothing(): void {}
public function testAnUnsavedReaderIsIgnored(): void {}      // 20 saved, 21 with tstamp 0 → 20
public function testALegacyEmptyPtableCounts(): void {}      // 20 with ptable '' (Contao 4.13) → 20
public function testANestedReaderIsIgnored(): void {}        // ptable 'tl_content'
public function testAProtectedReaderIsSkipped(): void {}     // isVisibleElement false for 20, 21 visible → 21
public function testThePageMayBeAnIdOnTheMainRequest(): void {}   // Review Focus 1: attribute pageModel = 7
public function testThePageFallsBackToObjPage(): void {}          // Review Focus 1: no attribute, $GLOBALS['objPage'] id 7
public function testWithoutAPageNothingIsQueried(): void {}       // no attribute, no objPage → null
```

```php
// tests/Contao/Reader/AutoItemReaderTest.php
public function testReadsTheAutoItemWithoutMarkingItUsed(): void
{
    $input = $this->mockAdapter(['get']);
    $input->expects(self::once())->method('get')->with('auto_item', false, true)->willReturn('a&#35;b');
    self::assertSame('a&#35;b', (new AutoItemReader($this->mockContaoFramework([Input::class => $input])))->read());
}
// testMissingAutoItemIsNull: get → null and '' → null
```

`ServicesConfigTest`: both → services. The `tearDown()` of the finder test unsets `$GLOBALS['objPage']`.

- [ ] **Step 2: Run — expect failure.** `make test tests/Contao/Reader`,
  `make test tests/DependencyInjection/ServicesConfigTest.php`
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run — expect pass.** The same two calls → `OK`.
- [ ] **Step 5: Full checks and commit.** `feat(reader): find the page's reader element and read the auto-item`

---

### Task 8: Reader resolver and the reader path

Read the spec sections "Views" (`ValidationView`), "Reader resolver", "Reader events and bundled listeners", "Twig"
(reader template) and "Non-goals" (Terminal42Languages).

**Files:**
- Create: `src/Contao/ReaderResolution.php`, `src/Contao/Reader/ReaderResolver.php`
- Modify: `src/Engine/View/ValidationView.php`, `src/Engine/Projector/ValidationProjector.php`
- Delete: `src/Engine/View/HandlesModelsTrait.php`, `src/Reader/ReaderRequestAttribute.php`,
  `src/Reader/Factory/ReaderRequestAttributeFactory.php`, `src/Reader/Resolver/ReaderRequestAttributeResolver.php`
- Modify: `src/Event/ReaderPageMetaEvent.php`, `src/Event/ReaderRenderEvent.php`
- Modify: `src/EventListener/Reader/GenericReaderPageMetaListener.php`,
  `src/EventListener/Reader/ReaderPageMetaTitleListener.php`,
  `src/Integration/ContaoNews/EventListener/NewsReaderPageMetaListener.php`,
  `src/Integration/ContaoCalendar/EventListener/EventsReaderPageMetaListener.php`,
  `src/Integration/ContaoComments/EventListener/ContaoCommentsListener.php`
- Modify: `src/Controller/ContentElement/ReaderController.php`, `src/Contao/Hook/BreadcrumbHook.php`
- Modify: `src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php` (type switch only)
- Modify: `contao/templates/content_element/flare_reader.html.twig`
- Create: `tests/Fixture/ContentModelFixture.php`; extend `tests/Engine/EnginePipelineTrait.php`
- Test: `tests/Contao/Reader/ReaderResolverTest.php`, `tests/Contao/Hook/BreadcrumbHookTest.php`,
  `tests/EventListener/Reader/ReaderPageMetaTitleListenerTest.php`, `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Tasks 1–7. **Produces:**

```php
class ValidationView implements ViewInterface
{
    public function __construct(
        private readonly ValidationLoaderInterface $loader,
        private readonly EntryCollector            $collector,
        private readonly EntryLookupInterface      $lookup,
        private readonly ?BackLink                 $backLink = null,
    ) {}
    public function getBackLink(): ?BackLink;
    /** A one-entry collection with the linker, memoized per auto-item (misses too). */
    public function getEntryByAutoItem(string $autoItem): ?Entry;
    /** Delegates to the lookup; `mixed` for the reason given at EntryLookupInterface.
     *  @param int|string|list<int|string> $ids */
    public function lookup(mixed $ids): Entry|EntryCollection|null;
}

final class ReaderResolution
{
    /** @param ?\Closure(): ReaderPageMeta $pageMeta */
    public function __construct(
        public readonly ContentModel      $contentModel,
        public readonly ?ListCompound     $list = null,
        public readonly ?ValidationIntent $intent = null,
        public readonly ?Engine           $engine = null,
        public readonly ?ValidationView   $view = null,
        public readonly ?Entry            $entry = null,
        public readonly ?FlareException   $failure = null,
        private ?\Closure                 $pageMeta = null,
    ) {}
    /** Dispatches ReaderPageMetaEvent once, on the first call; null without an entry. */
    public function getPageMeta(): ?ReaderPageMeta;
}

final class ReaderResolver implements ResetInterface
{
    public function __construct(
        private readonly AutoItemReader             $autoItemReader,
        private readonly EngineFactory              $engineFactory,
        private readonly EventDispatcherInterface   $eventDispatcher,
        private readonly ListCompoundBuilderFactory $listFactory,
        private readonly ReaderElementFinder        $elementFinder,
        private readonly ValidationIntentFactory    $validationIntentFactory,
    ) {}
    public function resolve(ContentModel $reader): ReaderResolution;
    /** null without an auto-item (nothing is built, no finder query); else $elementFinder->find() → resolve() or null */
    public function resolveForPage(): ?ReaderResolution;
    /** Empties the resolution memo and the memoized auto-item. */
    public function reset(): void;
}

class ReaderPageMetaEvent { public function __construct(ContentModel $contentModel, Entry $entry, ListCompound $list, ?ReaderPageMeta $pageMeta = null) }
class ReaderRenderEvent extends Event { public function __construct(ContentModel $contentModel, IntentInterface $intent, Entry $entry, ListCompound $list, Template $template) }
```

`resolve()`:
1. The auto-item is read **once per request**: `$autoItemReader->read()` on the first call, memoized including
   `null`. Without an auto-item → `new ReaderResolution($reader)` (nothing built, nothing queried). Memo key
   `"{$reader->id}|{$autoItem}"`; a hit returns the same object.
2. Inside one `try`: the list model via `$reader->getRelated(ContentContainer::FIELD_LIST)` (an `\Exception` there or
   a non-`ListModel` becomes `FlareException('No list model found.')`), `listFactory->create()->build()`,
   `validationIntentFactory->createFromContent()`, `engineFactory->createEngine()->createView()` (not a
   `ValidationView` → `ViewException`), then `getEntryByAutoItem($autoItem)`.
3. `catch (FlareException $e)` → a resolution with `failure` (and whatever was built). Other exceptions propagate
   unmemoized; `\Error`s are never caught.
4. `pageMeta` closure: `$eventDispatcher->dispatch(new ReaderPageMetaEvent($reader, $entry, $list))->pageMeta`.
5. Never touches `Model\Registry`.

Callers:
- `ReaderController::getFrontendResponse()`: `Input::get('auto_item')` (marks it used) or 404; `resolve()`; a failure
  → log + `InternalServerErrorHttpException`; no entry → 404; `EntryTaggerInterface::tag($entry)`;
  `ReaderRenderEvent`; template data `flare` (engine), `flare_reader` (view), `entry`, `content_model`, `headline`;
  `applyPageMeta($resolution->getPageMeta())`. Constructor: drop `EngineFactory`, `EntityCacheTags`,
  `KernelInterface` (only `getErrorResponse()` read it), `ListCompoundBuilderFactory`, the attribute
  factory/resolver and `ValidationIntentFactory`; add `ReaderResolver`, `EntryTaggerInterface`. Remove
  `getErrorResponse()`.
- `BreadcrumbHook(ReaderResolver $readerResolver)`: `resolveForPage()` inside `try … catch (\Exception)` (items
  unchanged, `@mago-expect lint:no-empty-catch-clause` as today); no resolution, a failure, no entry or an empty
  page meta title → items unchanged. Only on success with a non-empty title: on Contao 4.13 (`Env::isContao4()`)
  drop the second-to-last item when the last two share their page id (today's handling), then set the last item's
  `title` and `link` to that title. Its SQL, `Connection`, `ProjectorRegistry` and `tryGetReaderPageId()` go.
- Listeners: Generic reads tokens from `$event->entry->row`; Title falls back over the row's `headline`
  (`Str::formatHeadline`), `title`, `question`, `name`, `alias`, `id`; News/Events page meta return unless
  `$event->entry->meta->modelRef?->table === NewsModel::getTable()` / `CalendarEventsModel::getTable()` and read the
  row; Comments: its existing `class_exists` guard stays **first** (the comments integration loads without the news
  bundle), then the same table check with `NewsModel::getTable()`, archive
  `NewsArchiveModel::findByPk($row['pid'])`, author `$row['author']`, `strSource: NewsModel::getTable()`,
  `intParent: (int) $row['id']`.
- `ChangelanguageListener`: constructor `ReaderResolver $readerResolver` instead of the attribute resolver; in
  `onChangeLanguageNavigation()` take `resolveForPage()`, return without an entry, `$table` from
  `$entry->meta->modelRef?->table`, `$listModel` = `$resolution->list`. Nothing else. Check with
  `docker compose run --rm php -l src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php`.
- `flare_reader.html.twig`: `{# @var \HeimrichHannot\FlareBundle\Entry\Entry entry #}`, debug heading
  `entry.meta.modelRef.table`, `dl` over `entry.row`.

Test support additions:

```php
// tests/Fixture/ContentModelFixture.php
final class ContentModelFixture extends ContentModel
{
    public function __construct(array $row, private readonly ?ListModel $list = null) { $this->arrData = $row; }
    public function getRelated($strKey, array $arrOptions = []): ?ListModel { return $this->list; }
}

// EnginePipelineTrait
/** Number of lists built (ListDriverBuildListEvent dispatches). */
protected int $builds = 0;

/** A ReaderResolver over the real list builder (ListCompoundBuilderFactory as in ListCompoundBuilderFactoryTest,
 *  driver 'test_list' on 'tl_test'; $filters/$failures added by a ListDriverBuildListEvent listener that also
 *  counts $builds), the real ValidationIntentFactory (with a ValidatorInterface stub returning an empty
 *  ConstraintViolationList) and EngineFactory (createProjectorRegistry()), an AutoItemReader on a mocked Input
 *  adapter returning $autoItem (a list: consecutive reads) and counting its calls in $autoItemReads, and a
 *  ReaderElementFinder over $connection (tables of Task 7, page 7 on the main request) whose ContentModel adapter
 *  returns $content. Uses mockContaoFramework(): tests using it extend ContaoTestCase. */
protected int $autoItemReads = 0;
protected function createReaderResolver(Connection $connection, EventDispatcher $dispatcher, string|array|null $autoItem,
    array $filters = [], array $failures = [], ?ContentModelFixture $content = null): ReaderResolver;
```

`ListCompound::getAutoItemField()` and `ValidationIntentFactory::createFromContent()` call
`Controller::loadDataContainer()`: the resolver test uses the existing
`Tests\Filter\Element\StubsContaoDcaTrait` and stubs `tl_test` with the fields `id`, `alias`, `title`, `published`,
so the auto-item field is `alias`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Contao/Reader/ReaderResolverTest.php (ContaoTestCase + EnginePipelineTrait + StubsContaoDcaTrait)
// rows: 1 'visible' published, 2 'hidden' unpublished; content id 5 → ListModelFixture(id 0, type test_list).
// Registry set-up (the state in which the old shortcut fired): $GLOBALS['TL_MODELS']['tl_test'] =
// TestModelFixture::class (Registry::fetch() needs it); register the fixture and registerAlias($m, 'alias', $value)
// (its onRegister() is a no-op); tearDown(): unregisterAlias(), unregister(), restore TL_MODELS.

/** @dataProvider provideRegistryKeys  ['by alias' => ['alias', 'hidden']], ['by id' => ['id', '2']] */
public function testAnEntryOutsideTheListIsNotFoundEvenIfTheRegistryHoldsIt(string $autoItemField, string $autoItem): void
{
    // the DCA stub has an `alias` field only for 'alias', so the auto-item field becomes `id` for 'id'
    $registered = $this->register(new TestModelFixture(['id' => 2, 'alias' => 'hidden', 'published' => '1']));
    self::assertSame($registered, Registry::getInstance()->fetch('tl_test', 2));                // precondition
    self::assertSame($registered, Registry::getInstance()->fetch('tl_test', 'hidden', 'alias'));

    $resolver = $this->resolver(autoItem: $autoItem, filters: [self::publishedOnly()], content: $this->content());
    self::assertNull($resolver->resolveForPage()?->entry);                                     // breadcrumb path
    $resolution = $resolver->resolve($this->content());                                         // reader path
    self::assertNull($resolution->entry);
    self::assertNull($resolution->failure);
}

public function testARegisteredUnpublishedInstanceDoesNotCrashTheReader(): void
{
    // bug 2: the old code built a second model for a registered id and threw "already contains an instance"
    $this->register(new TestModelFixture(['id' => 1, 'alias' => 'visible', 'published' => '']));
    $entry = $this->resolver(autoItem: 'visible')->resolve($this->content())->entry;   // no published filter
    self::assertSame(1, (int) $entry?->row['id']);
}

public function testAnEntryInsideTheListIsResolved(): void
{
    $entry = $this->resolver(autoItem: 'visible', filters: [self::publishedOnly()])->resolve($this->content())->entry;
    self::assertSame('visible', $entry->row['alias']);
    self::assertEquals(new ModelRef('tl_test', 1), $entry->meta->modelRef);
}

public function testAControlThatCannotBuildItsFormDoesNotFailTheReader(): void
{
    // filters: publishedOnly() + a controlled filter whose control's buildForm() throws
    self::assertNotNull($resolution->entry);
    self::assertNull($resolution->failure);
}

public function testARecordedFailureFailsTheReaderAndIsMemoized(): void
{
    // failures: [new FilterFailure('tl_flare_filter.3', 'broken')]; count ListDriverBuildListEvent dispatches
    $resolver = $this->resolver(autoItem: 'visible', failures: [...]);
    $first = $resolver->resolve($this->content());
    self::assertInstanceOf(FilterException::class, $first->failure);
    self::assertNull($first->entry);
    self::assertSame($first, $resolver->resolve($this->content()));
    self::assertSame(1, $this->builds);
}

public function testTheSameElementTwiceQueriesOnce(): void
{
    $resolver = $this->resolver(autoItem: 'visible');
    $resolver->resolve($this->content());
    $resolver->resolve($this->content());
    self::assertSame(1, $this->queries);
}

public function testBreadcrumbThenReaderShareTheEntryAndReadTheAutoItemOnce(): void
{
    $resolver = $this->resolver(autoItem: 'visible', content: $this->content());   // finder finds content 5
    self::assertSame($resolver->resolveForPage()?->entry, $resolver->resolve($this->content())->entry);
    self::assertSame(1, $this->autoItemReads);
}

public function testPageMetaIsDispatchedOnce(): void
{
    // a ReaderPageMetaEvent listener sets the title 'T' and counts
    self::assertSame('T', $resolution->getPageMeta()?->getTitle());
    $resolution->getPageMeta();
    self::assertSame(1, $dispatches);
}

public function testWithoutAutoItemNothingIsBuiltOrQueried(): void   // Review Focus 3
{
    $resolver = $this->resolver(autoItem: null, content: $this->content());
    self::assertNull($resolver->resolveForPage());
    self::assertNull($resolver->resolve($this->content())->entry);
    self::assertSame([0, 0], [$this->queries, $this->builds]);
}

public function testResetEmptiesTheMemoAndTheAutoItem(): void   // Review Focus 4
{
    $resolver = $this->resolver(autoItem: ['visible', 'hidden']);   // consecutive reads: two main requests
    $first = $resolver->resolve($this->content());
    $resolver->reset();
    $second = $resolver->resolve($this->content());
    self::assertSame(['visible', 'hidden'], [$first->entry?->row['alias'], $second->entry?->row['alias']]);
    self::assertSame(2, $this->autoItemReads);
}
```

```php
// tests/Contao/Hook/BreadcrumbHookTest.php
public function testTheLastItemGetsTheReaderTitle(): void
{
    $items = [['title' => 'Home', 'link' => 'Home'], ['title' => 'Reader', 'link' => 'Reader', 'data' => ['id' => 7]]];
    $result = (new BreadcrumbHook($resolverWithEntryAndPageMetaTitle))($items, $this->createMock(Module::class));
    self::assertSame(['T', 'T'], [$result[1]['title'], $result[1]['link']]);
}
// testItemsStayUnchanged — no reader on the page; a failed list; no entry (auto-item of an unpublished row)
```

```php
// tests/EventListener/Reader/ReaderPageMetaTitleListenerTest.php — HtmlDecoder mock returns its argument
public function testTitleFromTheHeadlineOfTheRow(): void
{
    // row ['id' => 1, 'headline' => serialize(['unit' => 'h1', 'value' => 'Hello'])] → 'Hello'
}
public function testTitleFallsBackToTheTitleColumn(): void {}   // row ['id' => 1, 'title' => 'Fallback']
```

`ServicesConfigTest`: `ReaderResolver` → service, `ReaderResolution` → non-service; drop the deleted classes from
any list; `BreadcrumbHook` stays a service.

- [ ] **Step 2: Run — expect failure.** `make test tests/Contao`, `make test tests/EventListener/Reader`,
  `make test tests/DependencyInjection/ServicesConfigTest.php`
- [ ] **Step 3: Implement everything listed under Files.**
- [ ] **Step 4: Run — expect pass.** The same three calls → `OK`. Then
  `grep -rn "getModelByAutoItem\|displayModel\|ReaderRequestAttribute\|HandlesModelsTrait" src contao tests config`
  → no output; `grep -n "pageMeta" src/Event/ReaderRenderEvent.php` → no output;
  `docker compose run --rm php -l src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php`
  → `No syntax errors detected`.
- [ ] **Step 5: Full checks and commit.** `refactor(reader): resolve the reader once per request, on entries`

---

### Task 9: Twig functions and schema.org on entries

Read the spec section "Twig" and "Reader events and bundled listeners" (schema.org).

**Files:**
- Modify: `src/Twig/Runtime/FlareRuntime.php`, `src/Twig/Extension/FlareExtension.php`
- Modify: `src/Event/ReaderSchemaOrgEvent.php`,
  `src/Integration/ContaoNews/EventListener/NewsReaderSchemaOrgListener.php`,
  `src/Integration/ContaoCalendar/EventListener/EventsSchemaOrgListener.php`
- Test: `tests/Twig/FlareRuntimeTest.php`, `tests/Twig/FlareExtensionTest.php`

**Interfaces — Consumes:** Tasks 1, 6, 8. **Produces:**

```php
readonly class FlareRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ContaoFramework          $framework,
        private EventDispatcherInterface $eventDispatcher,
        private ProjectorRegistry        $projectorRegistry,
    ) {}
    public function project(ListCompound $list, IntentInterface $intent): ViewInterface;  // unchanged
    /** MissingModelRefError without modelRef; ContentModel adapter findPublishedByPidAndTable($ref->id, $ref->table),
     *  Controller adapter getContentElement(); lazy as today (CallableWrapper + once). */
    public function getTlContent(Entry $entry): ?callable;
    public function getEnclosure(Entry|array $entity, ?string $field = null): array;     // Entry → $entity->row
    /** Context fallback (`entry`, list of `flare`) only when $context has `flare_reader`. */
    public function getSchemaOrg(array $context, ?Entry $entry = null, ?ListCompound $list = null): ?array;
}

class ReaderSchemaOrgEvent extends Event { public function __construct(ListCompound $list, Entry $entry, array $data = []) }
```

`getEnclosureFiles()` and the `flare_enclosure_files` registration are deleted. Schema.org listeners take
`EntryModelFactory` in their constructor, return unless `modelRef?->table` is `NewsModel::getTable()` /
`CalendarEventsModel::getTable()`, then `create()` and `instanceof` before `News::getSchemaOrgData()` /
`Events::getSchemaOrgData()`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Twig/FlareRuntimeTest.php (ContaoTestCase)
public function testContentNeedsAModelRef(): void
{
    $this->expectException(MissingModelRefError::class);
    $this->runtime()->getTlContent(new Entry(['id' => 1]));
}

public function testContentRendersTheElementsOfTheEntrysRecord(): void
{
    $content = $this->mockAdapter(['findPublishedByPidAndTable']);
    $content->expects(self::once())->method('findPublishedByPidAndTable')->with(5, 'tl_test')->willReturn(null);
    $callable = $this->runtime([ContentModel::class => $content])
        ->getTlContent(EntryCollection::forTable('tl_test', [['id' => 5]])->first());
    self::assertSame('', $callable());
}

public function testSchemaOrgFallsBackToTheContextOnlyInTheReader(): void
{
    // listener on ReaderSchemaOrgEvent sets data ['@type' => 'Thing'] and records the event
    $context = ['entry' => $entry, 'flare' => $engine];
    self::assertNull($this->runtime()->getSchemaOrg($context));                         // a list loop's `entry`
    self::assertSame(['@type' => 'Thing'], $this->runtime()->getSchemaOrg($context + ['flare_reader' => $view]));
    self::assertSame($entry, $seen->entry);
}
```

```php
// tests/Twig/FlareExtensionTest.php
public function testEnclosureFilesIsGone(): void
{
    $names = \array_map(static fn (TwigFunction $f): string => $f->getName(), (new FlareExtension())->getFunctions());
    self::assertNotContains('flare_enclosure_files', $names);
}
```

- [ ] **Step 2: Run — expect failure.** `make test tests/Twig`
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run — expect pass.** `make test tests/Twig` → `OK`;
  `grep -rn "flare_enclosure_files\|getEnclosureFiles" src contao` → no output (the negative assertion in
  `FlareExtensionTest` is the only reference left in `tests/`).
- [ ] **Step 5: Full checks and commit.** `refactor(twig): render content, enclosures and schema.org from entries`

---

### Task 10: `flare_related(entry, 'column')`

Read the spec section "`flare_related(entry, 'column')`".

**Files:**
- Create: `src/Contao/Entry/EntryRelationResolver.php`, `src/Exception/UndefinedRelationError.php`
- Modify: `src/Twig/Runtime/FlareRuntime.php` (constructor: `EntryRelationResolver $relationResolver` appended as
  the last parameter; `getRelated()`), `src/Twig/Extension/FlareExtension.php`
- Test: `tests/Contao/Entry/EntryRelationResolverTest.php`, `tests/Twig/FlareExtensionTest.php`,
  `tests/Twig/FlareRuntimeTest.php` (its `runtime()` helper passes the new argument),
  `tests/DependencyInjection/ServicesConfigTest.php`

**Interfaces — Consumes:** Tasks 1, 6. **Produces:**

```php
final readonly class EntryRelationResolver
{
    public function __construct(private ContaoFramework $framework, private EntryModelFactory $modelFactory) {}
    /** Like Model::getRelated(): ?Model for to-one, ?Collection for to-many; any target table, the list's own
     *  included, no membership check. MissingModelRefError without modelRef; UndefinedRelationError when
     *  DcaExtractor::getInstance($table)->getRelations() lacks $column or the table has no model class.
     *  Memoized per entry (WeakMap) and column. */
    public function resolve(Entry $entry, string $column): Model|Collection|null;
}
// FlareRuntime::getRelated(Entry $entry, string $column): Model|Collection|null → resolver
// FlareExtension: new TwigFunction('flare_related', [FlareRuntime::class, 'getRelated'])
```

- [ ] **Step 1: Write the failing tests** (`EntryRelationResolverTest extends ContaoTestCase`; the model is a
  `createMock(TestModelFixture::class)` served by the mocked registry, relations from a mocked `DcaExtractor`)

```php
public function testToOneIsTheModelGetRelatedReturns(): void
{
    $archive = new TestModelFixture(['id' => 10]);
    $model->expects(self::once())->method('getRelated')->with('pid')->willReturn($archive);
    self::assertSame($archive, $resolver->resolve($entry, 'pid'));
    self::assertSame($archive, $resolver->resolve($entry, 'pid'));   // memoized: getRelated once
}

public function testToManyIsTheCollectionGetRelatedReturns(): void
{
    $tags = new Collection([new TestModelFixture(['id' => 3])], 'tl_test');
    // getRelated('tags') → $tags
    self::assertSame($tags, $resolver->resolve($entry, 'tags'));
}

public function testARelationIntoTheListsOwnTableIsResolved(): void
{
    // relations ['parent' => ['type' => 'belongsTo', 'table' => 'tl_test', 'field' => 'id']]; getRelated('parent') → model
}

public function testWithoutModelRefThrows(): void { /* new Entry(['id' => 1]) → MissingModelRefError */ }
public function testAColumnWithoutRelationThrows(): void { /* 'title' → UndefinedRelationError */ }
```

`FlareExtensionTest::testRelatedIsRegistered` (`flare_related` in the names). `ServicesConfigTest`:
`EntryRelationResolver` → service.

- [ ] **Step 2: Run — expect failure.** `make test tests/Contao/Entry`, `make test tests/Twig`,
  `make test tests/DependencyInjection/ServicesConfigTest.php`
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run — expect pass.** The same three calls → `OK`.
- [ ] **Step 5: Full checks and commit.** `feat(twig): add flare_related() for Contao relations of an entry`

---

### Task 11: `AGENTS.md` and the docs worktree

Read the spec section "Documentation".

**Files:**
- Modify: `AGENTS.md`
- Modify (worktree `docs/`, branch `docs/main`): `docs/docs/templating.mdx`, `docs/docs/reference/twig.md`,
  `docs/docs/dev/events.md`, `docs/docs/integrations/index.md`, `docs/docs/removed-in-v0.2.md`,
  `docs/docs/preflight-v0.2.md`, `docs/docs/spec/filtering.md`, `docs/docs/migrating-from-v0.1.md`,
  `docs/docs/dev/filter-controls.md`
- Create: `docs/docs/dev/entries.md`

Content (decisions, not prose):
- `AGENTS.md`: the execution flow ends in "→ View (entries for Twig: `src/Entry/`)"; notable subsystems get
  `src/Entry/` (wrapper, ports), `src/Engine/Entry/` (collector, lookup), `src/Contao/Entry/` (model factory,
  relations, cache tags), `src/Contao/Reader/` + `ReaderResolution` (request-scoped reader, discovery, auto-item);
  the event list gets `flare.list_driver.{type}.entries` (`ListDriverEntriesEvent`); `src/Reader/` loses the
  request attribute. The failure paragraph is split, not just shortened: the two failures only the form harness
  detects — `buildForm()` throws, or a `FilterControlBuildFormEvent` listener throws a `FlareException` — fail the
  list view only (the reader builds no form). An alias that is no valid form name is recorded by
  `FilterCollector` as a `FilterFailure` on `ListCompound::$failures` and keeps failing the reader with HTTP 500,
  like every other recorded failure.
- `templating.mdx`: list loop over `flare_list.entries` with `entry.to`, `entry.row.<column>`,
  `entry.meta.modelRef.table`, reserved names `to`/`row`/`meta`, `is empty`/`|length`; delete the
  `flare_list.to/model/models` passages (around lines 104, 139–174); reader variable `entry` (234–264); back-button
  text `flare_reader.to(id)` → `flare_reader.lookup(id).to` (299); Twig functions list (523–530): `flare_content(entry)`,
  `flare_enclosure(entry|array)`, `flare_schema_org(entry?)`, `flare_related(entry, column)`, `flare_reader.lookup()`
  (membership in *this* list; the reader on the jump-to page must use the same list; ids only — deserialize stored
  lists first). The known limits of reader URLs from the plan's Review Focus (`/`, literal `%2F`, on 4.13 `%` before
  a sprintf letter).
- `reference/twig.md`: the same function rows.
- `dev/events.md`: a section for `ListDriverEntriesEvent` / `flare.list_driver.{type}.entries`; reader events carry
  `entry`; `ReaderRenderEvent` has no `pageMeta` (change it in `ReaderPageMetaEvent`); `ReaderUrlGeneratedEvent::$entry`,
  and `$autoItem` is the URL text (decoded, never percent-encoded — pass it to the router as is).
- `dev/entries.md` (new guide): the spec's listener example (to-one), a to-many example, computed similarity with
  the `EntryLookupInterface` in phase 1, cheap per-entry values with `$entry->attach()`, tagging loaded data with
  `EntityCacheTags`, `flare_related()` returns the raw relation (no membership — use `lookup()` for that), the
  boundary service for Contao APIs that need a model. Request context: the reader's entries event and its lazy
  values may run while the breadcrumb renders, in the main request, before the reader's fragment sub-request
  exists — listeners must not depend on the fragment request.
- `integrations/index.md`: `getEntriesGrouped()` returns entries.
- `removed-in-v0.2.md`: `flare_list.to()`, `flare_list.model()`, `flare_list.models`, `flare_reader.to()`, the
  reader variable `model`, `InteractiveView::getModels()/getModel()`, `ValidationView::getModel()/
  getModelByAutoItem()/getEntry()`, `HandlesModelsTrait`, `LinksToReaderTrait`, `ReaderRequestAttribute*`,
  `EntryCache`, `ValidationLoader::fetchEntryById()`, `ReaderRenderEvent::$pageMeta`, the `Model` properties of
  the reader events and `ReaderUrlGeneratedEvent`; `flare_enclosure_files()` stays listed.
- `preflight-v0.2.md`: tick "`flare_enclosure_files` Twig function".
- `spec/filtering.md` (row "A control cannot build its form"), `migrating-from-v0.1.md` (~165) and
  `dev/filter-controls.md` (~245): split like `AGENTS.md` — `buildForm()` and the `FilterControlBuildFormEvent`
  listener fail the list, the reader is unaffected; an invalid alias still fails the reader with HTTP 500.

- [ ] **Step 1: Edit `AGENTS.md`.**
- [ ] **Step 2: Edit the docs pages.**
- [ ] **Step 3: Audit.** `grep -rn "flare_list\.to\|flare_list\.model\|flare_reader\.to(\|displayModel\|getModelByAutoItem" docs/docs`
  → only `removed-in-v0.2.md`. Reread `AGENTS.md`'s failure paragraph and the three docs pages: only `buildForm()`
  and the `FilterControlBuildFormEvent` listener are exempt from "HTTP 500 in the reader"; the invalid alias is not.
- [ ] **Step 4: Commit both trees.**
  `git add AGENTS.md && git commit -m "docs: describe entries, the reader resolver and the lookup in AGENTS.md"`;
  `git -C docs add -A docs && git -C docs commit -m "docs: entries instead of models in templates, events and reader"`
  (do not push `docs/main`).

---

## Self-review notes

- Spec coverage: Goals 1–11 → Tasks 4/8/9 (1), 1 (2), 2 (3), 8 (4), 6 (5), 3/8 (6), 10 (7), 4/5/8 (8), 4 (9),
  9 (10), every task + 11 (11). Every bullet of the spec's "Tests" section has a named test above.
- Not tested in the suite, by design: Contao 4.13 branches (`Env::isContao4()` in the breadcrumb, 4.13's `Input`
  and `guests`), News/Events listeners (their bundles are no dev dependencies), the default templates.
- Reviewed 2026-11-12 by Codex (gpt-6-astra, max) and Claude (Opus 5.5, xhigh), independently; all findings applied
  (Contao 5's throwing `getClassFromTable()`, optional response tagger, `lookup(mixed)`, discovery SQL with `tstamp`
  and the 4.13 empty `ptable`, a registry test that reproduces the old shortcut by id and alias, the auto-item read
  once per request, the narrow failure correction in the docs, and smaller ones). The URL limits for `/`, `%2F`
  and 4.13's sprintf placeholders are documented, not fixed.
