# Design: entries instead of models — the engine renders rows, models only at the Contao boundary

Status: agreed in conversation 2026-11-11, builder-checked, awaiting the user's review. Goes in **its own PR on
`v0.2`, before `v0.2-query`** (user's decision). Breaking the view, template and event API is fine: v0.2 is
unreleased (user's decision).

Origin: `dev/superpowers/ideas/2026-11-11-engine-rows-only.md` (committed on `v0.2-query`). This design replaces
its "Direction" and answers its open questions; it does not need the idea note to be read.

## Intent

Today the engine views turn rows into Contao models (`Engine/View/HandlesModelsTrait`) and read Contao's
`Model\Registry` first. That causes:

1. **The reader shows entries that are not in its list.** `fetchModel()` returns a model that is already in the
   registry (by id, or by alias through `Model::onRegister()`) if only its `published` field is set — without
   running the list's lookup query. Anything that loaded the record earlier in the request (a news module, an insert
   tag, a FLARE list, which registers its models itself) makes the reader show an entry its filters exclude, or one
   outside `start`/`stop`. Expected: 404. `Contao/Hook/BreadcrumbHook` has the same bypass.
2. **A crash in the same method.** If the registry holds an *unpublished* instance and the list does not filter on
   `published`, the shortcut is skipped, the query finds the row, and `new $modelClass($row)` throws a
   `\RuntimeException` ("registry already contains an instance"). It is no `FlareException`, so the reader does not
   catch it.
3. **A gap for the query refactor.** Both registry reads would replace a mapped row (a translation) with the cached
   physical model.
4. **Breadcrumb and reader can evaluate different things.** The reader reads `Input::get('auto_item')` (encoded),
   the breadcrumb `Input::findGet()` (raw). The breadcrumb picks the first article by `id` and its first
   `flare_reader` element without visibility checks. They run through two code paths (engine vs. projector
   registry), and the second lookup is only free today because of the registry shortcut.

The user's direction: templates use `entry` from `flare_list.entries`; **a Contao model is not accessible from
templates at all**. Whatever a template needs beyond the row — related records, computed values — a userland event
listener attaches to the entries; it knows the list and the query. A model is made only at the Contao boundary,
where a Contao API demands one (`News::getSchemaOrgData()`).

## Goals

1. No Contao model of the list's table in the engine views, in templates or in reader events. Removed:
   `HandlesModelsTrait`, `LinksToReaderTrait`, `InteractiveView::getModels()`/`getModel()`,
   `ValidationView::getModel()`/`getModelByAutoItem()`, `flare_list.to()`, `flare_list.model()`, the reader
   template variable `model`.
2. Entries: a generic wrapper `Entry` and a collection `EntryCollection` (below).
3. An entries event through which listeners attach data to the entries of a list page or the reader's entry.
4. A request-scoped reader resolver shared by reader and breadcrumb: the same list, the same entry and the same page
   meta, computed once per request.
5. A Contao boundary service that turns an entry into a model, for Contao APIs that require one.
6. A membership-checked lookup of other entries of the same list (`flare_reader.lookup()`, `$event->lookup()`),
   replacing `flare_reader.to(id)`.
7. `flare_related(entry, 'column')`: a Contao relation of an entry, as Contao model(s) of the related table.
8. The bugs above are fixed, plus the `ValidationLoader` cache key (see "Loader").
7. PHPUnit and PHPStan green; docs worktree and `AGENTS.md` updated.

## Non-goals

- **Projections from the query refactor.** This PR adds no projection status. The query refactor adds it later as a
  field of `EntryMeta` (adding to `meta` is non-breaking) together with the boundary service's rule for mapped rows
  (a detached model with `preventSaving`, never registered). After this PR lands, `v0.2-query`'s spec D28 / §2 is
  reduced to "the query result carries its projection status" (follow-up on that branch, not here).
- **The Events driver's occurrences.** Events will build its own nested iterator on top of `EntryCollection` later;
  `EntryCollection` stays a flat list of rows (user's decision).
- **More than one reader per page.** We assume exactly one reader element per page (user's decision). If that
  changes, the code gets extended then.
- Moving the remaining Contao uses out of `src/Engine/` (intent factories with `ContentModel`,
  `ReaderUrlConfigCreatorTrait`/`LazyPage` with `PageModel`): a later step.
- Porting `Integration/Terminal42Languages` (excluded from PHPStan, half-ported). Only its reader lookup is switched
  to the resolver (see "Reader resolver"), so the service still wires.
- A generic relation loader that attaches related records to entries automatically. Related records come from
  listeners, or from `flare_related()` on demand.
- **`flare_reader.prev` / `flare_reader.next` and the position of an entry in its list** — a separate follow-up
  (user's decision). They depend on the list's order and filters and cost a query in the reader. Recommendation for
  that follow-up: the position as `flare_reader.pos(entry)`, not as `entry.meta.pos` — `meta` holds facts about the
  record from its source (cheap, the same everywhere), and the same sealed `Entry` is shared between breadcrumb,
  reader and lookup collections.
- Links between different lists (an event linking to its speaker in a speakers list), language versions
  (Changelanguage), insert tags and sitemaps: covered individually by userland listeners (user's decision). The
  lookup sits behind a service so that such cases can reuse it later.

## Anti-goals

- A template or a reader event that receives a list entry as a Contao model. (`flare_related()` returns *related*
  records as models; see there.)
- Any lookup of a list entry that skips the list's query.
- A name collision that does not throw.
- A reader that answers 404 or the entry although membership could not be decided — a failed list stays 5xx.
- A breadcrumb that guesses between several reader elements, or that marks `auto_item` as used (that would suppress
  Contao's 404 for unused route parameters on pages where no reader consumes it).

## Design

All class, event and service names below are placeholders; final names follow the user's naming style
(see "Left to the builder").

### What it looks like (template and listener)

```twig
{# list #}
{% if flare_list.entries is empty %}<p>No entries.</p>{% endif %}
{% for entry in flare_list.entries %}
    <h2><a href="{{ entry.to }}">{{ entry.headline }}</a></h2>
    {% if entry.archive %}<p>{{ entry.archive.title }}</p>{% endif %}
    {% for tag in entry.tags %}<span>{{ tag.name }}</span>{% endfor %}
    {% if not loop.last %}<hr>{% endif %}
{% endfor %}

{# reader: the same attachments, the variable is `entry` #}
<h1>{{ entry.headline }}</h1>
<p>{{ entry.archive.title }}</p>
{{ flare_content(entry) }}
```

```php
#[AsEventListener('flare.list_driver.flare_news.entries')]
final readonly class NewsArchiveListener
{
    public function __construct(private Connection $connection) {}

    public function __invoke(ListEntriesLoadedEvent $event): void
    {
        // phase 1 runs once per collection, on the first access to `archive` on any entry
        $event->attach('archive', function (EntryCollection $entries): \Closure {
            $archives = $this->connection->fetchAllAssociativeIndexed(
                'SELECT id, title, jumpTo FROM tl_news_archive WHERE id IN (?)',
                [$entries->column('pid')],
                [ArrayParameterType::INTEGER],
            );

            // phase 2 picks one entry's value; memoized per entry
            return static fn (Entry $entry): ?array => $archives[$entry->pid] ?? null;
        });
    }
}
```

A cheap per-entry value is attached on the entry itself:
`foreach ($event->entries as $entry) { $entry->attach('isNew', $entry->date > \strtotime('-7 days')); }`.

Computed similarity ("more like this": same tags, same category) is a listener too: it determines the ids by its own
query, checks them with `$event->lookup()` and attaches the result, e.g. as `entry.similar` — an `EntryCollection`
or an array, as the listener prefers (user's decision).

### `Entry`

A generic wrapper, independent of the data source (a DC table today; config or an API later).

- **State:** `array $row` (the data as delivered by the source), `EntryMeta $meta`, `?EntryCollection $collection`
  (set by the collection that creates the entry; `readonly`, so an entry belongs to at most one collection), its own
  attachments, and a memo of resolved lazy values.
- **Reserved names:** `to`, `row`, `meta` (a class constant). They are never resolved from the row. A column with a
  reserved name is **not** an error; it is reachable as `entry.row.<name>`. The list is fixed: adding a name later
  would hide columns in existing templates. New metadata goes into `meta` instead, which is non-breaking.
  - `to` → `to()`: the reader URL from the collection's linker; `null` without a linker (no reader page
    configured), without a collection, or when the auto-item value is empty. `entry.to`, `entry.to()` and
    `$entry->to()` all return the link.
  - `row` → the row array. `meta` → the `EntryMeta`.
- **`__get($name)` order:** reserved name → row column → own attachment → collection → otherwise an `\Error`
  subclass (a programming error, like `UndefinedConfigKeyError`).
- **`__isset($name)`:** true for reserved names and for every name that exists, *even if its value is `null`*.
  Twig asks `isset()` before reading a property; `false` for a null column would make Twig fall through to methods
  and fail under `strict_variables` (debug).
- **`attach(string $name, mixed $value)`:** a value, or a `\Closure(Entry): mixed` that runs once on the first
  access and is memoized. A `\Closure` always means "lazy"; to attach a closure as a value, wrap it. Throws an
  `\Error` subclass at registration when the name is reserved, a column of this row, already attached to this entry,
  or provided by its collection, or when the entry is sealed.
- **Not iterable.** Iterating the columns goes through `entry.row` (one way only).
- Internal code (the linker, `EntryCollection::column()`) reads `row` directly, never through `__get()`, so a
  reserved column name breaks nothing internally.

### `EntryMeta` and `ModelRef`

```php
final readonly class EntryMeta
{
    public function __construct(
        public ?ModelRef $modelRef = null,
    ) {}
}

final readonly class ModelRef
{
    public function __construct(
        public string     $table,
        public int|string $id,
    ) {}
}
```

- `modelRef` is set for **every record of a DC table** (table + the row's `id`), whether or not a model class exists
  for that table; it is `null` for entries from other sources. There is no `meta.table` and no `meta.id`
  (user's decision): `meta.modelRef.table` is the table.
- Callers that need a Contao model check `$entry->meta->modelRef instanceof ModelRef` (and usually the table), then
  ask the boundary service.
- In Twig: `entry.meta.modelRef.table` (camelCase; Twig does not translate snake_case).
- These types carry no Contao dependency; the projector sets `modelRef` from `$list->dc` and the row's `id` without
  asking Contao anything.

### `EntryCollection`

- **`IteratorAggregate` + `Countable`**, not a bare generator (`getIterator()` may `yield` internally). Twig's
  `is empty` and `|length` call `iterator_count()` on a non-`Countable` `Traversable` and would consume a generator;
  `loop.last` needs `Countable`. Each loop gets a fresh iterator.
- A **list** (keys `0..n-1`), not keyed by id: ids are not guaranteed for every source.
- It **creates its entries** from the rows, passing itself, the entry's `EntryMeta` and nothing else; plus an
  optional linker `\Closure(Entry): ?string` (the reader URL generator; `to()` results are memoized per entry).
- **`column(string $name): list<mixed>`:** the distinct, non-empty values of a row column across the collection.
- **`attach(string $name, \Closure(EntryCollection): \Closure(Entry): mixed $load)`:** two phases. Phase 1 runs
  once per collection, on the first access to `$name` on any of its entries; it returns a picker. Phase 2 (the
  picker) maps one entry to its value; the result is memoized on the entry. Throws an `\Error` subclass at
  registration when the name is reserved, a column of any row (the union of all row keys — rows of an API may
  differ), attached by any entry itself, already attached to the collection, or when the collection is sealed.
- **`seal()`:** after the entries event. Afterwards any `attach()` — on the collection or on one of its entries —
  throws. Templates see a fixed set; breadcrumb and reader can share the same `Entry` objects safely.

### Entries event

- **Name (placeholder):** `ListEntriesLoadedEvent`, with the named dispatch `flare.list_driver.{type}.entries`
  (`{type}` = the list's driver type, like the other named events in `src/EventListener/NamedDispatch/`).
- **When:** once per collection, when a view loads its rows — the list page on the first access to
  `flare_list.entries` (lazy, as `fetchEntries()` today), the reader's entry as a one-entry collection when it is
  looked up. Not for the count (`AggregationView`). After dispatch the view seals the collection.
- **Carries:** `ListCompound $list` (type, `dc`, config, `source` — a listener can target a single list by
  `source`), `IntentInterface $intent` (list or reader), `EntryCollection $entries`.
- **`$event->attach(...)`** proxies `$event->entries->attach(...)`.
- **`$event->lookup(...)`**: the lookup below, for the event's list (also in the list view).
- One listener serves the list and the reader, because both dispatch the same event.

### Views

- `InteractiveView::getEntries(): EntryCollection` — lazily: loader → collection (with `modelRef`s and the linker)
  → entries event → seal. `getCount()` stays the total (pagination); `entries|length` is the page's count.
- `ValidationView::getEntryByAutoItem(string): ?Entry` — the entry comes from a one-entry collection with the
  linker, so `entry.to` is the canonical reader URL. A miss is `null` (not `[]`).
- `ValidationView::lookup(int|string|array $ids)` — see "Lookup". It replaces `getEntry(int $id)` and
  `flare_reader.to(id)`.
- The views no longer need `HandlesModelsTrait`/`LinksToReaderTrait`. They need the event dispatcher.

### Lookup (other entries of the same list)

`flare_reader.lookup(id)` / `flare_reader.lookup([ids])` in templates (e.g. editorially linked entries, the back
button), `$event->lookup(...)` in listeners. Both reach the same service.

- **Input:** ids of the list's own table (the `id` column), not of a parent table — `entry.pid` of a news entry is
  an archive id and belongs to `flare_related()`.
- **Membership by the reader's rules:** the list's filters with their fixed values, no submitted form values — the
  links lead to the reader, and the reader must show every linked entry. In the list view, a lookup can therefore
  return entries the current form filter hides; that is correct.
- **One id → `?Entry`; a list of ids → `EntryCollection`** with only the members, in the order of the given ids.
  One query for a list of ids (`id IN (...)`).
- **The result is its own collection:** linker, entries event, sealed — `.to` and attachments work on it. The event
  of a lookup collection triggers the same listeners again, but `attach()` is lazy, so `entry.similar.similar` only
  queries if a template reads it; there is no eager recursion.
- Repeated lookups of the same ids within a view should not query twice (builder: memo per view or per service).

### `flare_related(entry, 'column')`

Resolves a Contao relation of an entry's column, as Contao defines it in the DCA (`foreignKey` + `relation`), with
the same result as Contao's `Model::getRelated()`: a `?Model` for a to-one relation, a `?Collection` for a to-many
relation. Example: `flare_related(entry, 'pid').title` is the news archive's title.

- Memoized per entry and column.
- An `\Error` subclass without `meta.modelRef`, and for a column without a relation in the DCA.
- **It does not check the list.** For a relation into another table that is right (an archive has nothing to do
  with the list's filters). For the exception — a field pointing into the list's *own* table (tree tables,
  variants) — it returns a model of that table regardless of the list's filters. The docs say so and point to
  `flare_reader.lookup()` for links and membership (Claude's decision; such self-references are rare).
- Built on the boundary service's model (`getRelated()`) or directly on Contao's relation data (`DcaExtractor`) —
  builder's choice, as long as the result equals `getRelated()`.

### Reader URL

- `ReaderUrlGeneratorInterface::generate(Entry $entry): ?string` reads the auto-item field from `$entry->row`.
  `NullReaderUrlGenerator` follows.
- `ReaderUrlGeneratedEvent` carries `Entry $entry` instead of `Model $model` (public API; the bundled
  `ChangelanguageListener` does not read it).

### Contao boundary service (entry → model)

In `src/Contao/` (e.g. `Contao/Factory/`). `create(Entry $entry): ?Model`:

| Case | Result |
|---|---|
| no `meta.modelRef` | `\Error` subclass — the caller had to check |
| no model class registered for `modelRef.table` | `null` |
| otherwise | the registry's instance for that id if there is one, else a new model from the row (Contao's constructor registers it) |

Reusing the registry instance is safe now: the list's query has already confirmed membership, and Contao's identity
map stays consistent (`getRelated()`, other modules see the same instance). The rule for mapped rows comes with the
query refactor (non-goal).

Used only where a Contao API requires a model: the News/Events schema.org listeners
(`News::getSchemaOrgData()`, `Events::getSchemaOrgData()`); userland listeners may use it too.

### Reader resolver (request cache)

A Contao-side service (placeholder `ReaderResolver`) implementing `ResetInterface`. It is the only path from a reader
element and the auto-item to the result.

- `resolve(ContentModel $reader): ReaderResolution` — used by `ReaderController`.
- `resolveForPage(int $pageId): ?ReaderResolution` — used by `BreadcrumbHook` and the `ChangelanguageListener`.
  Discovery: the page's published articles in sorting order, their visible `flare_reader` elements — the same
  visibility Contao applies when rendering (`invisible`, start/stop, guests/protected; see
  `ContentModel::findPublishedByPidAndTable()`, `Controller::isVisibleElement()`). **Exactly one → `resolve()` it;
  none or several → `null`.**
- **Memo key:** `tl_content.id` + auto-item. **Value** (`ReaderResolution`): the list, the intent, the engine and the
  view (the template still gets `flare` and `flare_reader`), `?Entry` (`null` → 404), `?FlareException` failure, and
  the page meta (`ReaderPageMetaEvent` dispatched lazily, once — so the breadcrumb title equals the `<title>`).
- **One code path:** list → intent → engine → view → entry, the way `ReaderController` does it today.
- **Failures are memoized.** A `FlareException` while building is stored, not rethrown by the resolver: the reader
  turns it into 5xx (as today), the breadcrumb leaves its items unchanged. `\Error`s are not caught (bundle rule).
  Other exceptions keep today's behavior per caller.
- **The auto-item is read only by the resolver,** once, from the **main** request (the reader runs as a fragment in
  a sub-request; `Input` reads the *current* request), **encoded the way `Input::get()` encodes it** (Contao stores
  backend input encoded; this is what the reader uses today) and **without marking it as used**. `ReaderController`
  keeps marking it as used (`Input::get('auto_item')`).
- **The cache lives in the service, not in request attributes:** attributes of the fragment's sub-request never
  reach the main request. `ResetInterface` clears it between main requests (long-running workers).
- **Order does not matter:** the validation path reads nothing from the (sub-)request, so whoever comes first
  computes, the other gets the same result and the same sealed `Entry`. The breadcrumb renders first in practice.

Consequences:

- `ReaderController`: `resolve()` → failure → 5xx; no entry → 404; else the cache tag `contao.db.<table>.<id>` from
  `meta.modelRef` (when set), the page meta from the resolution, `ReaderRenderEvent`, template data `entry` (replaces
  `model`), `flare`, `flare_reader`, `content_model`, `headline`.
- `BreadcrumbHook`: `resolveForPage()`; no resolution, a failure or no entry → items unchanged; else the title from
  the resolution's page meta. Its own SQL, discovery and `ProjectorRegistry` path go.
- `ReaderRequestAttribute`, `ReaderRequestAttributeFactory` and `ReaderRequestAttributeResolver` are removed. They
  stored a model on the fragment's sub-request (so main-request readers likely never saw it) and reloaded it with
  `findByPk()` (a registry read). The `ChangelanguageListener` asks `resolveForPage()` for the current page instead
  (`$resolution->list->dc`, `$resolution->list->getAutoItemField()`).

### Reader events and bundled listeners

- `ReaderPageMetaEvent`, `ReaderRenderEvent`: `Entry $entry` replaces `Model $displayModel`.
- `ReaderRenderEvent` no longer carries `pageMeta`. Page meta is changed only through `ReaderPageMetaEvent`, which
  the resolution dispatches once; a render-time change would make the breadcrumb (rendered earlier) and the
  `<title>` differ. No bundled listener writes it today.
- `ReaderSchemaOrgEvent`: `Entry $entry` replaces `Model $model`.
- `GenericReaderPageMetaListener`, `ReaderPageMetaTitleListener`: read `$event->entry->row`.
- News/Events page meta listeners: check `$entry->meta->modelRef?->table` against `NewsModel::getTable()` /
  `CalendarEventsModel::getTable()` instead of `instanceof`; read the row.
- News/Events schema.org listeners: table check, then the boundary service.
- `ContaoCommentsListener`: table check; the archive from `NewsArchiveModel::findByPk($entry->row['pid'])`, the
  author from the row — it needs no `NewsModel`.

### Twig

- `flare_content(entry)`: needs `meta.modelRef` (table + id; content elements hang on it via `ptable`/`pid`); an
  `\Error` without.
- `flare_enclosure(entry|array, field)`: reads `entry.row`. `flare_enclosure_files` (deprecated): `Model` → `Entry`.
- `flare_schema_org(entry?, list?)`: falls back to the context variable `entry` (instead of `model`) and the engine
  in `flare`; dispatches `ReaderSchemaOrgEvent(list, entry)`.
- `content_element/flare_listview.html.twig`: `entry.to`; the debug block iterates `entry.row` and shows
  `entry.meta.modelRef.table`. `content_element/flare_reader.html.twig`: `entry` instead of `model`.

### Loader

`ValidationLoader::fetchEntryById()` checks `has($id)` but stores under `'id:' . $id`, so it never hits;
`fetchEntryByAutoItem()` caches a miss as `[]` and re-queries it. Fix it or drop the loader cache (the view and the
resolver memoize) — builder's choice. A miss is `null`.

## Tests

No test needs the registry's private state; populate it through Contao's public API (`new $class($row)`, `attach()`).

- Reader and breadcrumb with a populated registry (by id and by alias): an entry outside the list's filters → 404 /
  breadcrumb unchanged; inside → the entry is rendered.
- `Entry`: `entry.to` is the link and `entry.row.to` the column for a row with a `to` column, without an error;
  collisions throw on both levels (reserved name, column, attached twice, collection vs. entry); a lazy closure runs
  once; a collection loader runs once per collection and only when its name is read; `attach()` after sealing throws;
  `__isset()` is true for a null column; an unknown name throws.
- Twig rendering against a real environment: `is empty`, `|length`, `loop.last`, two loops over the same
  collection, `entry.to`, `entry.row.to`, a null column under `strict_variables`.
- Resolver: the same element twice → one query; a failure is memoized; breadcrumb then reader get the same `Entry`
  instance; discovery skips a hidden element and returns `null` for two visible ones; an auto-item with special
  characters resolves the same for both; the breadcrumb path does not mark `auto_item` as used.
- Boundary service: returns the registry instance when present, creates and registers otherwise, `null` without a
  model class, throws without `modelRef`.
- Lookup: only members are returned (an id outside the list's filters → `null` / missing), in the given order; a
  list of ids runs one query; form values do not affect membership; the result's entries have `.to` and run the
  entries event.
- `flare_related()`: to-one returns the model, to-many the collection, both equal to `getRelated()`; throws without
  `modelRef` and for a column without a relation.

## Documentation

- Docs worktree (`docs/`, branch `docs/main`, committed separately): templates (`flare_list.entries`, `entry.to`,
  `entry.row`, `entry.meta`, reserved names), a guide for enriching entries with a listener (the example above,
  to-one and to-many, computed similarity with `$event->lookup()`), the changed reader events and Twig functions,
  `flare_reader.lookup()` and `flare_related()`. `docs/docs/templating.mdx` today documents `flare_list.to(id)`,
  `flare_reader.to(id)` ("similar entries", also in the back-button section) and the reader variable `model`.
- `AGENTS.md`: the execution flow ends in entries, not models; the new subsystems (entries, reader resolver,
  boundary service).

## Constraints

- PHP ^8.2, Contao 4.13 and 5, Symfony 5.4–7, Twig 3, DBAL 3 (`ArrayParameterType` in the example needs
  DBAL ≥ 3.6; examples only). The auto-item encoding must work on Contao 4.13 and 5.
- Bundle conventions: `declare(strict_types=1)`, Mago, PHPStan level 5; programming errors are `\Error` subclasses;
  the bundle catches `\Exception`, never `\Throwable`.
- `Entry`, `EntryCollection`, `EntryMeta`, `ModelRef` and the entries event have no Contao dependency. The resolver,
  the boundary service and the Twig runtime are Contao-side.
- Production code is testable through its public API (no reflection into private state).

## Who decided what

The user decided: rows-only engine in its own PR on `v0.2` first; breaking API is fine; no model in templates;
enrichment by userland listeners; the `Entry` wrapper with row + attachments (value or callback) resolved by magic
`__get()`; nullable/source-independent entries; `entry.to` on the entry; `$event->attach()` as a proxy; the
entry ↔ collection link instead of installing batch closures; collisions always throw; reserved `to`, `row`, `meta`;
`meta.modelRef` only (no `meta.table`, no `meta.id`); Events builds its own iterator; a request cache; exactly one
reader per page; `flare_reader.lookup(id | [ids])` for membership; `flare_related(entry, 'column')` for Contao
relations; computed similarity via listeners; `prev`/`next`/position as a separate follow-up; links between lists,
language versions and similar cases via userland listeners.

Claude decided (please check):

- The collection-level `attach()` is two-phase (load for all → pick per entry) and lazy; the picker needs no id.
- `EntryCollection` is `IteratorAggregate` + `Countable` and a list, not a bare generator and not keyed by id.
- `__isset()` means "exists" (true for null values); unknown names throw; `Entry` is not iterable.
- `modelRef` is set for every DC-table record, with or without a model class; the boundary service returns `null`
  when the class is missing.
- `row` rather than `raw`.
- Sealing after the entries event, on both levels.
- The resolver keys by `tl_content.id` + auto-item, memoizes failures and the page meta, and owns the discovery;
  discovery requires exactly one visible reader element.
- The auto-item is read from the main request, encoded like `Input::get()`, without marking it used.
- `ReaderRequestAttribute*` is removed; the `ChangelanguageListener` uses the resolver.
- No projection status in this PR; the query refactor adds it to `meta`.
- `ReaderRenderEvent` loses its writable `pageMeta`; page meta only through `ReaderPageMetaEvent`.
- Lookup: membership by the reader's rules (no form values); one id → `?Entry`, a list → `EntryCollection` in the
  given order; listeners get it as `$event->lookup()`; it replaces `getEntry(int $id)`.
- `flare_related()`: `?Model` / `?Collection` like `getRelated()`; `\Error` without `modelRef` or relation; no check
  against the list, also for the rare relation into the list's own table.
- Recommendation for the follow-up: position as `flare_reader.pos(entry)`, not `entry.meta.pos`.

## Left to the builder

- All names (classes, the event, its named-dispatch suffix, the `\Error` subclasses, the resolver and boundary
  service) in the user's naming style — the user reviews them — and their namespaces.
- How the views get the event dispatcher; whether the event is dispatched for an empty collection.
- The discovery query, within the visibility constraint above.
- Fixing or dropping the `ValidationLoader` cache; where the lookup service lives and how the list view's event
  gets the reader's membership rules (the reader page and auto-item field are on `InteractiveIntent` too).
- Typed PHP accessors next to the magic ones (e.g. `getRow()`, `getMeta()`), as long as Twig sees only the reserved
  names and the data.
