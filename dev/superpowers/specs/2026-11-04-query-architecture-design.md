# Design: query architecture — list drivers own their query building blocks

Status: agreed in conversation 2026-11-04, builder-checked and independently reviewed, awaiting the user's review.
Branch: `v0.2-query` off `v0.2` (the unreleased major version; breaking changes are fine, no BC shims, no
`class_alias`) — Claude's call.

## Intent

Filter elements have a clean structure: an element receives a collect-only builder and a value-free context, adds
abstract building blocks (`buildEffect()`), and the framework turns them into SQL. List drivers have nothing
comparable. A list's query is assembled today by a chain of mutations:

1. `Query/Factory/ListExecutionContextFactory` creates a mutable `SqlQueryStruct` (`main.*`, `GROUP BY main.id`) and a
   `TableAliasRegistry`; the driver mutates both through `BuildQueryContract::buildTableRegistry()` /
   `buildBaseQuery()`; `QueryBaseInitializedEvent` lets anyone mutate them further (Codefog Tags registers its joins
   there); the factory then re-adds `main.id AS id`.
2. `Query/Executor/ListQueryDirector` builds every filter's conditions and dispatches `ModifyListQueryStructEvent`.
   The actual query semantics live in listeners ordered only by priority: `SelectModifierListener` (490, also drops
   `GROUP BY` for counting), `ConditionsModifierListener` (470, also activates each proposition's target alias),
   `PageModifierListener` (430), `OrderModifierListener` (420), the Calendar listeners `DoNotPaginateModifierListener`
   (200) and `CountEventsModifierListener` (100), `JoinModifierListener` (−450).
3. The Calendar loaders steer "their" listeners through stringly `ListQueryConfig::$attributes`
   (`ContaoCalendar_doNotPaginate`, `ContaoCalendar_overrideSelect`); counting and id-only selection are booleans on
   `ListQueryConfig` (`isCounting`, `onlyId` — the latter has no caller).

What the user wants instead, and why:

- **The priority chain and the Calendar attributes go.** Nobody can read the query's shape from the code; any
  listener can undo any other; integrations talk to each other through string keys.
- **The list driver provides building blocks, the framework assembles** — the same split as element/effect. The
  assembly order is fixed in framework code, so that events can be offered at stable points.
- **Targeting is finished** (deferred since `2026-10-14-filter-forms-design.md` §15, "to be redesigned before the v0.2
  release"): every element can target any table/join alias the list offers, and `$builder->column(...)` applies the
  target automatically. Today only types with `isTargeted` honor their stored target; all others are forced to `main`
  by a stopgap in `Filter/Factory/FilterEffectBuilderFactory::resolveTargetAlias()`.
- **Entries and Count never disagree.** The legacy multilingual integration and today's Events count both show how a
  scattered query produces counts that do not match the entries.
- **The architecture must carry what is known to come**, without being rebuilt:
  - a DC_Multilingual driver (legacy in `src/Integration/Terminal42Languages/`, full v0.0.x integration on branch
    `support/0.0.34`: `src/EventListener/Integration/Terminal42ChangelanguageListener.php`,
    `src/List/ListQueryBuilder.php`, `src/Manager/ListQueryManager.php`). It is **not ported here**; it must only be
    expressible (user's decision);
  - sort hints from filter elements, a list-configured default sort over them, and a visitor-facing *Sortinator*
    (analogous to the paginator). They come **in the PR directly after this one** (user's decision); this PR must
    think them through so that PR does not have to rebuild the interfaces. Small adjustments there are acceptable if
    necessary (user's words).

What the legacy multilingual integration needed from the query, as derived in conversation (and confirmed by the
user) — every point must be expressible by a driver after this refactor:

| Need | Legacy mechanism |
|---|---|
| Replace the projection with computed columns, `IFNULL(translation.x, main.x) AS x`, including `id` | `addRawSelect()` in three fetch events |
| A join that is always present, regardless of filters | `setTableAliasMandatory('translation')` |
| A join condition with a runtime value (the current language) | the language interpolated raw into `ON` — must become a bound parameter |
| No `GROUP BY` | `setGroupBy([])` |
| A fixed base condition: only fallback rows (`langPid = 0`) are entries | added only in the count event — so entries and count disagreed (a bug born of the scattered events) |
| Filters and the reader's auto-item lookup hit the translated value | the auto-item filter retargeted to `translation` (loses untranslated entries) |
| Runtime knowledge: current language, the content element's display mode | globals, the content model in the event |
| One behavior for entries, count and auto-item lookup | three events repeating the same `applyMlQueries()` |

## Goals

1. A **table catalog** per list: every alias the list's query can use, with its table, its join, its cardinality and
   its flags, declared value-free by the driver and extended by integrations through one event. It replaces
   `ListExecutionContext` / `TableAliasRegistry` as the source of truth, in the frontend and in the backend.
2. A **driver query hook**, called per query, that contributes the driver's building blocks: projection, identity,
   base conditions, grouping and the values of its join parameters.
3. A **column mapping** declared by the driver: a column of an alias may stand for an SQL expression
   (`main.title` ⇒ `IFNULL(translation.title, main.title)`), applied wherever the list query resolves a column.
4. **Join parameters**: a join condition may contain named parameters; whoever declares a join fills its parameters
   when the query is built — the driver in its hook, an integration through an event. The driver need not know.
5. **Targeting for every element**, applied by `FilterConditionsBuilder::column()`; multi-alias predicates via
   `column($col, for: $alias)`; bundled elements follow their target's table.
6. **Framework assembly** in a fixed order, without listener priorities; **Count counts the Entries query** (wrapping),
   so the two cannot disagree.
7. **Entry expansion** as a driver capability: a driver whose entries are produced in PHP from rows (Events:
   occurrences) declares so; the framework paginates and counts the expanded entries generically.
8. **Sorting as a sequence of sort keys** with a resolution step that today knows one source (columns of the
   catalog) and is ready for the second (sort hints of elements) and for the Sortinator.
9. Removal of `ModifyListQueryStructEvent`, `QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`, the
   Calendar modifier listeners and loaders/projectors, `ListQueryConfig::$attributes`/`$isCounting`/`$onlyId` and
   `BuildQueryContract` in its current form.
10. The bundled lists (generic DC, News, Events, Codefog Tags filters) return the same entries, counts and order as
    before; the SQL text may change. Exception: counts that today exceed the entries (Events with a multiplying join)
    now match them.

## Non-goals

- Porting the DC_Multilingual / Changelanguage integration (`src/Integration/Terminal42Languages/` stays untouched,
  unloaded and excluded from PHPStan). Its needs are covered by an executable proof in the tests instead (see
  Testing).
- Implementing sort hints, the hint declaration contract, the Sortinator, derivations (inner SELECTs) or any other
  new `FilterEffect` channel — they are prepared for (see "Prepared for the next PR"), not built.
- Bounding the Events occurrence expansion by the filter's date window (today a recurring event lists all its
  occurrences, also those outside the filtered range). Recorded as a follow-up idea in
  `dev/superpowers/ideas/2026-11-04-events-expansion-window.md` (user's decision).
- Offering columns of aliases other than `main` in the `sortSettings` backend widget (it stays `main`-only as today);
  the sort-key resolution supports any single-valued catalog alias already (Claude's call).
- Integrations contributing anything but aliases, joins and the values of their own join parameters (user's
  decision: "vorderhand nur Aliasse und Joins"). No projection, conditions, mapping or ordering by events.
- Changing `FilterConditionsBuilder`'s condition API, the predicates' SQL, the filter failure semantics
  (`FilterFailure`, retry with `null`, `abort()`), or the engine's projector/view layering.
- The `ListModel` exception (`ListCompound`, `ListCompoundBuilder`, `ListCompoundFactory`, `BaseListOptions` keep
  taking a `ListModel`).
- A database service in CI.

## Anti-goals

- A new priority chain through the back door: no ordered listeners, no "run me after X", no event that may change
  what another contribution produced.
- Stringly channels between components (`$attributes`-style keys) — deviations are typed.
- Raw, unbound runtime values in SQL (the legacy language interpolation).
- Entries and Count computed from different sets of rows — through joins activated for one purpose only, inputs that
  reach only one of them, or driver-specific count shapes.
- BC shims or deprecated aliases for the removed events/contracts.
- An interface shape that forces the sort-hint/Sortinator PR to rebuild the catalog, the driver hook, the filter
  invocation or the assembly.
- Behavior differences for existing lists beyond SQL text and the count fix in goal 10.

## Constraints

- Project conventions (`AGENTS.md`): vocabulary *Compound / Effect / Context / Intent* kept distinct; lifecycle
  taxonomy (`configure*` declarative and memoizable, `build*` per invocation; hook parameter order "subject first,
  context supplementary"); event naming `{Component}{Hook}Event` / `flare.{component}.{type}.{suffix}`; the bundle
  catches `\Exception`, not `\Throwable`; programming errors are `\Error`s that escape unwrapped
  (`UndefinedConfigKeyError`, `ValueClassMismatchError`); `FilterException` is the public failure of a filter.
- Naming taste (user): short, sober nouns with the concrete term last; no "Setup", "Prepared*", "Assembly",
  "Environment", "Frame", "Scope", "Config" (taken by `elm_`/`ctl_` config). The names below are working names;
  the builder may improve them in that style.
- Query safety: identifiers quoted through the connection, values bound as parameters (also in join conditions),
  SQL names validated (`Str::isValidSqlName()`). Doctrine DBAL `QueryBuilder` remains the final product.
- MySQL 5.7/8 and MariaDB, including `ONLY_FULL_GROUP_BY` (on by default, kept by Contao's TRADITIONAL mode). PHP ^8.2.
- Migrations never delete or drop (memory `migrations-never-delete`).
- Testable through public API (memory `testable-without-private-access`).

## Design

### Overview

```
ListCompoundBuilder::build()                              — once per list (frontend and backend)
  driver buildList() → ListDriverBuildListEvent → config → schema       (unchanged)
  driver buildTableCatalog(TableCatalogBuilder, …)          — value-free declaration
  → ListDriverBuildTableCatalogEvent                        — integrations add aliases + joins (+ their params)
  ⇒ ListCompound::$tables (immutable TableCatalog)
  every filter's target alias checked against the catalog → FilterFailure if unknown

ListQueryDirector::createQueryBuilder(ListQueryConfig {list, intent, filterValues, purpose})
  1. fail if the list recorded failures                     (unchanged)
  2. driver buildQuery(ListQueryBuilder, ListQueryContext)  — building blocks of this query
     → ListDriverBuildQueryEvent                            — integrations fill their join parameters
  3. filters → FilterInvoker → invocation results (effect, conditions, used aliases)
  4. sort keys resolved (both purposes, for join activation; ORDER BY emitted for Entries only)
  5. joins: one activation set for both purposes (see §1), closed over from-alias and `requires`,
     topologically sorted; every parameter of an active join must be bound
  6. Entries: projection + identity AS id, base conditions ∧ filter conditions, grouping, ORDER BY, paging
     Count:   SELECT COUNT(*) FROM (<the Entries query, projection = identity only, no ORDER BY/LIMIT>) t
  7. → Doctrine QueryBuilder

Expanding drivers (§2a): Entries fetched without paging/ORDER BY → driver expands rows → framework sorts nothing,
paginates and counts the expanded entries in PHP.
```

Steps 3–7 are framework code in one place, in this order. Which internal structs carry the intermediate state
(today `SqlQueryStruct`, `SqlJoinStruct`, `TableAliasRegistry`) is the builder's call; nothing public mutates them.

### 1. The table catalog

**What it holds** (per alias): the table; for a non-main alias its join (type, from-alias, condition, `requires`);
the declared parameter names of the join condition; its **cardinality** — *single-valued* (at most one row per
`main` row: News/Events archives, the multilingual translation) or *multiplying* (Codefog relation and tags tables);
the flags *mandatory* (always joined) vs. *on demand* (joined only when used) and *hidden* (not offered to editors as
a target or sort column, still usable by code — the Codefog relation table); the alias' column mapping (§3). `main`
is always present and is the list's data container (`ListCompound::$dc`).

**Who declares it:** the driver, in a new value-free hook (working name
`buildTableCatalog(TableCatalogBuilderInterface $builder, …)`; the second parameter gives the list's resolved config
and dc — the exact context type is the builder's call, it must not need an intent). It replaces
`buildTableRegistry()`. Then `ListDriverBuildTableCatalogEvent` (named dispatch
`flare.list_driver.{type}.table_catalog` — suffix is the builder's call within the naming rule) lets integrations add
aliases and joins, with parameters and cardinality, for any list (Codefog: any list whose table has a tags field). The
event's builder exposes alias/join declaration only — no mapping, no removal of other contributions. Duplicate aliases
are a programming error.

**Where it lives** (Claude's call): built once in the `ListCompound` build lifecycle, after the config is resolved,
and carried immutably as a property of the `ListCompound`. So every consumer that already holds the list — elements
via `ElementContext::$list`, `DcaContext`, `FilterInspector`, the director — reads the same catalog without a
factory, and the backend gets it without running a query. If the builder finds a reason to build it elsewhere, the
requirement is: value-free, built once per list, same result in backend and frontend, no query executed.

**Join activation** (one set, identical for Entries and Count): the mandatory aliases ∪ the target alias of every
proposition (also when its SQL is empty — as `ConditionsModifierListener` does today; with INNER archive joins this
decides whether orphan rows are entries) ∪ every alias resolved through `column(…, for:)` ∪ the aliases referenced
by used mapping expressions, the projection, the identity, base conditions and grouping ∪ the aliases of resolved
sort keys. The closure follows each join's from-alias **and** its `requires` (Codefog's tags join starts from the
hidden relation alias and declares no `requires`; `TableAliasRegistry::resolveActiveJoins()` follows both today).
Sort keys are resolved for activation in Count too, so the sort order chosen (later: by a visitor) can never change
the rows; since sort keys may use single-valued aliases only (§6), this adds no multiplication.

**Backend consumers** move to the catalog: `Contao/DcaContext::getExecutionContext()`/`getTables()`/
`getTargetTable()`, `Contao/Hook/ElementDcaHook::createExecutionContext()`,
`Contao/Callback/FlareFilter/FieldsOptionsCallbacks` (`getFieldOptions_fieldGeneric`, `getOptions_targetAlias`),
`Integration/CodefogTags/FilterCallback/TargetAliasCallback`, `CodefogTagsChoiceFilterElement::buildChoices()`/
`getOptions()`, `Contao/Callback/FlareList/FieldsOptionsCallbacks::getFieldOptions_sortSettings()` (keeps offering
`main` only), `contao/dca/tl_flare_filter.php` (`TableAliasRegistry::ALIAS_MAIN` as the `targetAlias` default),
`Sort/Factory/SortOrderSequenceFactory`. `ListExecutionContext` and `ListExecutionContextFactory` are removed.

**Bundled declarations:** News and Events declare their archive joins (`news_archive`, `events_archive`; INNER,
single-valued) on demand, as today. Codefog's `RegisterTagsTablesListener` moves from `QueryBaseInitializedEvent` to
the catalog event (relation table hidden, both multiplying, tags table on demand; its `CfgTagsJoinsRegistry` side
channel may stay or be replaced by catalog metadata — builder's call).

### 2. The driver query hook

Per query, the driver receives a collect-only builder and a context (working names
`buildQuery(ListQueryBuilderInterface $builder, ListQueryContext $context)`; *Context* in the project's sense — the
pre-filled handover). The context carries the `ListCompound`, the `IntentInterface` and the **purpose**. It replaces
`buildBaseQuery()`; `BuildQueryContract` is replaced by a contract carrying both hooks (working name kept or renamed,
builder's call), with no-op defaults in `AbstractListDriver`.

**Purpose** (working name `QueryPurpose`, an enum): `Entries` (rows, sorted, paged) and `Count` (the number of
entries). The reader's lookups by id/auto-item are `Entries` under a `ValidationIntent` (today: `ValidationLoader`
adds a `SimpleEquationFilterElement` and a page size of 1). `onlyId` has no caller and is dropped (Claude's call). The
name must not collide with *Intent*: the intent says what the list is rendered for, the purpose says what this one
query returns (one intent may run several queries — interactive runs Entries and Count).

The driver contributes the **same building blocks for both purposes** — the purpose is in the context for
information, not to shape Count differently; the framework derives Count from Entries. Each block has a framework
default, so a driver that contributes nothing gets today's query:

| Block | Default | Who needs to change it |
|---|---|---|
| Projection | `main.*` | multilingual: explicit list with mapped columns |
| Identity — the expression that is the entry's `id` | `main.id` through the column mapping | multilingual: `IFNULL(translation.id, main.id)` (falls out of the mapping) |
| Base conditions (AND-ed with the filters; physical columns) | none | multilingual: `main.langPid = 0` |
| Grouping | physical `main.id` (not the mapped identity — `GROUP BY IFNULL(…)` with `main.*` fails under `ONLY_FULL_GROUP_BY`; `main.id` works through the primary-key dependency) | multilingual without multiplying joins: none |
| Values of the driver's own join parameters | — | multilingual: `:lang` |

The framework always selects the identity `AS id` and never overrides the driver's identity (today's "re-ensure
`main.id AS id`" silently would). Rows must carry mapped values under the plain column names (the legacy behavior);
how the projection achieves that with `main.*` plus mapped columns is the builder's call. A driver that drops the
grouping is responsible for not activating multiplying joins; the framework does not re-add grouping.

**Count** (user's decision): `SELECT COUNT(*) FROM (<the Entries query: same FROM, joins, WHERE, GROUP BY, HAVING;
projection reduced to the identity; no ORDER BY, LIMIT, OFFSET>) t`. One rule for every driver, every grouping and
later derivations or HAVING; Entries and Count always agree. The cost is a derived table per count. The legacy
"COUNT(DISTINCT main.id) and drop GROUP BY" special-casing goes.

**Inputs that shape the query reach Count, too.** Today `Engine/Intent/Factory/AggregationIntentFactory::
createFromIntent()` returns a bare `AggregationIntent`, which the interactive projector uses for its count — anything a
driver reads from the intent (e.g. the multilingual display mode) would shape Entries but not Count. The interactive
count must run with the same query-shaping inputs as its entries: either the aggregation intent carries them from the
interactive one or they reach `ListQueryContext` another way (builder's call). The expressibility test checks
Entries-vs-Count equality.

**What the driver cannot do** (anti-goal): reorder or rewrite the filters' conditions, the ORDER BY, the joins'
activation or the count; those are framework steps.

### 2a. Entry expansion (Events)

An events list does not list rows but **occurrences**: `GroupsEntriesTrait` expands each row into every day of a
multi-day event and every repetition of a recurring event, ordered by date and start time. Paging and counting are
over occurrences; the order is the expansion's (`EventsListDriver::buildDca()` removes `sortSettings` from the
palette). Today this needs SQL paging switched off, a count that really fetches rows, and the Events loaders and
projectors. The user agreed to model it as a driver capability instead (replacing the earlier per-purpose deviations,
which a review showed would apply to every query of an Events list — a count returning rows to the generic
`AggregationLoader`, a reader lookup losing its `LIMIT 1`):

- A driver may implement an expansion contract (working name `ExpandsEntriesContract::expandEntries(array $rows,
  ListQueryContext $context): array`). It returns the entries in their final order and may annotate them (as today's
  `_flare_event_group`).
- For an expanding driver under an interactive or aggregation intent the framework runs Entries **without** SQL paging
  and ORDER BY, expands the rows, and then paginates (the intent's paginator) and counts (the number of expanded
  entries) generically, in PHP. Count therefore always returns a number, through the generic loaders.
- Under a `ValidationIntent` (reader lookups) nothing is expanded: a normal SQL query with its `LIMIT 1`.
- An expanding driver owns its order: no `sortSettings`, and in the next PR no Sortinator, for such lists.
- `EventsListDriver` implements the contract with today's `GroupsEntriesTrait` logic. Removed:
  `EventsInteractiveLoader`, `EventsAggregationLoader`, `EventsAggregationProjector`, `DoNotPaginateModifierListener`,
  `CountEventsModifierListener`, the `ContaoCalendar_*` attributes. `EventsInteractiveProjector` survives only if it is
  still needed to create `InteractiveEventsView` (template grouping by `_flare_event_group`) — builder's call.
- Where the generic paging/counting of expanded entries lives (the engine loaders or a shared executor) is the
  builder's call; it must not be duplicated per driver. Expanding lists fetch all matching rows, as today.

### 3. Column mapping

The driver may declare that a column of an alias stands for an SQL expression, e.g. for every translatable field
`main.<f>` ⇒ `IFNULL(translation.<f>, main.<f>)`. Declared with the catalog (value-free; Claude's call — it depends
only on the table's DCA, and the backend/inspector can see it), by the driver only.

- Applied wherever the **list query** resolves a column: `FilterConditionsBuilder::column()` (all filters, including
  the reader's lookup filters — so the multilingual auto-item lookup hits the translated alias *and* keeps
  untranslated entries, the reason the user chose mapping over retargeting), sort-key resolution, identity,
  projection.
- It does **not** apply to the raw table lookups elements make for choices and options
  (`FieldValueChoiceFilterElement::buildChoices()`, `DcaSelectFieldFilterElement`): under a mapping driver their keys
  come from physical values. Out of scope here; a note for the multilingual port.
- A mapping expression declares the aliases it references; using a mapped column activates them.
- The driver needs **physical** access next to it: its base condition `main.langPid = 0` and the mapping expressions
  themselves must address the real column. The builder offers an explicit way (e.g. a `physical()` helper or
  `column(…, mapped: false)` — builder's call); elements get the mapped column by default.
- Mapping expressions are authored by driver code, not by editors; they are built from quoted identifiers through
  the builder's helpers, never from stored strings.

### 4. Join parameters

- A join declared in the catalog may contain named parameters (`… AND translation.language = :lang`) and declares
  their names.
- Parameter names are scoped per join alias by the framework so they cannot collide with the filters' parameters
  (which are prefixed per conditions builder today) or with other joins' (user confirmed). The exact scheme is the
  builder's call.
- Whoever declared a join fills its parameters per query: the driver in `buildQuery()`; an integration in
  `ListDriverBuildQueryEvent` (named dispatch `flare.list_driver.{type}.build_query`), dispatched by the framework
  right after `buildQuery()`. Its builder exposes only setting values of join parameters (user's decision: the driver
  need not know). Filling a parameter of a join one did not declare is not offered (builder's call how ownership is
  enforced — at least by alias).
- A declared parameter of an **active** join that has no value at assembly time is a **programming error** (user
  confirmed): an `\Error` subclass, escaping unwrapped like `UndefinedConfigKeyError`, never a `FilterFailure`.
  Parameters of inactive joins need no value.

### 5. Targeting

Confirmed by the user:

- **Every element** has a target: any visible catalog alias, default `main`. The backend shows the `targetAlias`
  field for every element type (options from the catalog: visible aliases); `AsFilterElement::$isTargeted`,
  `FilterCompound::$targetingForced`, `withTargetAlias(…, forced)` and the stopgap
  `FilterEffectBuilderFactory::resolveTargetAlias()` are removed; `AddTargetAliasFieldCallback` adds the field for all
  types (or is folded into `ElementDcaHook::buildFrameworkDca()` — builder's call). `FilterCompound::$targetAlias`
  and the stored `tl_flare_filter.targetAlias` stay.
- **Opt-out** (§15 of the filter-forms spec): an element may address aliases itself instead of taking an editor-chosen
  target; it then gets no target field. The mechanism (an attribute flag, a contract) is the builder's call.
- **The target is validated eagerly**: in the `ListCompound` build, right after the catalog exists, every filter
  whose target alias is not in the catalog records a `FilterFailure` (the list fails closed; the `FilterInspector`
  reports the filter as *broken*). A check inside `column()` alone would miss filters that add no predicate for a
  null value (e.g. `flare_bool`): the value would be dropped by the retry and the inspector, which checks with the
  default value, would never see it. Aliases passed to `column(…, for:)` are validated in `column()` (→
  `FilterException`).
- `FilterConditionsBuilder::column($col)` resolves against the filter's target alias and applies the column mapping.
- A predicate that can or must address several aliases **declares that as an option** and uses
  `$builder->column($col, for: $alias)` (user's words). The element supplies the alias in the predicate options
  (from its config or its own knowledge, e.g. Codefog's relation alias).
- Join activation by use: see §1 (each proposition's target, plus `for:` aliases). Conditions that build raw SQL
  with other alias strings are not tracked — the docs say so.
- `FilterEffectBuilderInterface::addPredicate(…, ?string $targetAlias)` keeps or loses its per-predicate override —
  builder's call (it overlaps with `for:`; `tests/Filter/FormulaBuilderTest.php` covers it today).
- **Bundled elements follow their target's table** (user's decision): every element that reads the list's table
  today reads its target's table instead — for backend field options and for choices/lookups at runtime.
  `DcaContext::getTargetTable()` exists in the backend; `ElementContext` gets the runtime counterpart (the target
  alias' table from the catalog; name is the builder's call). Affected:
  - `DcaSelectFieldFilterElement` (`buildDca()` field options, `getOptions()`, choices);
  - `FieldValueChoiceFilterElement::buildChoices()`;
  - `ArchiveFilterElement` (`buildDca()`, `buildAllChoices()`, the cached `PtableInferrer` and its cache key) and
    `BelongsToRelationFilterElement` (`buildDca()`, runtime inferrer), plus
    `Contao/Callback/FlareFilter/FieldsOptionsCallbacks::getOptions_whitelistParents()` and
    `Contao/Callback/FlareFilter/WhichPtableCheckCallback`. **Where the parent relation comes from**: the list's
    config (`fieldPid`, `whichPtable`, `fieldPtable`, `tablePtable`) describes the parent of `main` only, so it
    applies only when the target is `main`; for any other target the element's own `elm_` relation config (where it
    has one, as `BelongsToRelationFilterElement` does in the backend) or auto-inference from the target table's DCA
    (`ptable`, field `pid`) applies — Claude's call;
  - the generic field options in `FieldsOptionsCallbacks::getFieldOptions_fieldGeneric()`;
  - `Filter/Factory/FilterEffectFactory`, which validates only the list's dc as an SQL name: it validates the target
    table too.

  Elements already targeted (`flare_bool`, `flare_equation_simple`, `flare_search_keywords`, the Codefog elements)
  keep working as they do. Only elements bound to fixed columns of a specific table opt out:
  `CalendarCurrentFilterElement` (event columns); `PublishedFilterElement` too unless its configurable field names
  make a target sensible (builder's call).
- **Stale stored targets** (user's decision): `targetAlias` is `alwaysSave` and survives a change of the element type,
  so rows of formerly untargeted types may hold a non-`main` value that the stopgap ignored and that would now apply.
  A run-once migration in `src/Migration/` resets `targetAlias` to `main` on every row whose type was **not** targeted
  — the hard-coded list of formerly targeted types is `flare_bool`, `flare_equation_simple`, `flare_search_keywords`,
  `cfg_tags_choice`, `cfg_tags_search` (checked against v0.1 and `support/0.0.34`; the attribute is removed, so the
  migration must not read it). It must run only once: a target an editor sets after the refactor must never be reset.
  A marker kept out of the DCA (as `BackfillFilterValuesMigration`'s `legacyBackfilled`) is proposed for dropping by
  the schema diff, and a reset has no data guard to survive that — so the marker is **declared in the DCA** (e.g. SQL
  default '1' for new rows; the migration processes the '0' rows) or a guard independent of any droppable column is
  used (builder's call). It updates values only, never drops or deletes; SQL for MySQL and MariaDB.

### 6. Sorting, prepared for hints and the Sortinator

Abstraction (user: "man könnte die basic Spalten auch als SortHints betrachten"): a sort order is a sequence of
**sort keys** with directions. A key comes from one of two sources:

1. **columns of the catalog** (`alias.column`, resolved through `column()` — mapped, join activated) — this PR;
2. **sort hints declared by filter elements** — the next PR.

This PR:

- generalizes `Sort/SortOrder` (today a fixed `alias`/`column` pair) so a key is not necessarily a column; the
  `SortOrderSequence` from the intent stays the single input of the ORDER BY step;
- resolves keys in the framework assembly (step 4), after the filters' invocation results exist, so effect-provided
  hints can be resolved there later;
- **fails open**: an unresolvable key (unknown alias/column, an alias that is multiplying; later an ineffective hint)
  is skipped with a warning, the remaining keys still sort (user's decision: "ordering sollte relativ fail-open
  sein");
- keeps the stored `sortSettings` format readable; the key format must leave room for a hint namespace without a
  migration (e.g. column keys never contain a character a hint key uses — builder's call).

`OrderModifierListener`'s behavior (no ORDER BY for Count, sequence from `SortableIntentInterface`) is kept.

**Resolvable keys** (Claude's call): the catalog does not store column lists. The columns of an alias are the DCA
fields of its table (what the `sortSettings` widget offers today via `DcaHelper::getFieldOptions()`), plus the columns
the driver's mapping declares for that alias. A column key is resolvable when its alias is in the catalog and
single-valued (sorting by a multiplying alias under grouping is non-deterministic or an error) and its column is such
a column. `FilterConditionsBuilder::column()` does **not** check column existence (unchanged behavior: an unknown
column is an SQL error, as today); it checks only the SQL name and the alias.

**Resolvable is not allowed.** What a visitor may choose (the Sortinator, next PR) is a separate, list-configured set
— a whitelist plus the default sort — never "every resolvable key": that would open an ordering side channel on
non-public fields (e.g. `tl_member.password`) and unindexed sorts. This PR only keeps the two apart; it adds no
whitelist yet.

### 7. Prepared for the next PR (outlook, not built)

So the next PR can add sort hints, derivations and the Sortinator without rebuilding this one:

- **Filter invocation result**: `Query/Executor/FilterInvoker::invoke()` returns, per filter, a result holding the
  `FilterEffect`, its conditions and the aliases it used (today it returns only `FilterConditionsBuilder`s and drops
  the effect; the retry in `ListQueryDirector::invokeFilters()` works on those). Steps 4 and 5 read these results, so
  hints and derivations can reach them later.
- **Alias resolution against a scope**: `column()` and `for:` resolve against the catalog ∪ aliases local to the
  effect (none today), so a derivation alias like `{$ovl}.score` (filter-scope spec §8) becomes resolvable later;
  derived aliases will need scoping per filter.
- **Join assembly generic over join definitions**: the catalog is one source of joins; derivations will be another.
- **Projection open to effects**: a column computed by a derivation (e.g. showing the score) will need a way into the
  projection; the projection step must be able to accept columns from invocation results later.
- **Hint declaration** (proposed in conversation, answered to the user's question; not yet decided): a capability
  contract in `src/Contract/FilterElement/` (working name `SortHintContract::buildSortHints(SortHintsBuilder,
  ElementContext)`), value-free like `ChoiceSourceContract::buildChoices()`, listing the keys an element *can* offer
  with labels — read by the list configuration (default sort over hints) and the Sortinator's whitelist.
- **Hint realization** in `buildEffect()` (`$builder->sortHint('overlap', …)`), because a hint usually references the
  derivation the element builds for its restriction anyway (filter-scope spec §8, tag-overlap example); a declared but
  unrealized hint is ineffective and skipped.
- **`FilterEffect`** today carries only `$formula`; new channels are added as further properties and builder methods
  without changing `buildEffect()`'s signature.
- **Sortinator**: like the paginator, it turns a request parameter into the intent's `SortOrderSequence`, restricted to
  the list's whitelist. The query layer needs nothing beyond step 4.
- **Stable events**: the fixed assembly order is the place where later events (e.g. "query assembled") can be offered
  without priorities. None are added beyond the two integration events.

### 8. What the multilingual driver would look like (expressibility check)

Not built; it is the yardstick for the interfaces (and for the test fixture driver):

- catalog: alias `translation` on the same table, LEFT JOIN `main.id = translation.<langPid> AND
  translation.<language> = :lang`, single-valued, *mandatory*; column mapping for `id`, the language/pid columns and
  every translatable field;
- `buildQuery()`: `:lang` from the current language (in the fallback language the join finds no row and every
  `IFNULL` falls back to `main` — so the catalog stays static and a `translation` target always exists); base
  condition `main.langPid = 0` (physical); grouping kept (`main.id`) or dropped; projection of regular fields plus
  mapped fields; a content-element display mode ("localized": only translated entries) as a base condition on
  `translation.<langPid>` — read from the intent, and therefore reaching Count as well (§2);
- reader lookup by auto-item: nothing to do — the lookup filter's `column('alias')` is mapped;
- the Changelanguage "same entry in language X" query is out of scope (it may later run the list's query with another
  `:lang`; nothing here may preclude that).

### 9. Failure semantics

- `buildTableCatalog()` and `ListDriverBuildTableCatalogEvent` listeners run inside the `ListCompound` build: a
  `FlareException` from them fails the list the way an unresolvable list driver does today (frontend: `list.failed`,
  reader: HTTP 500; backend option callbacks that build the list must keep their current tolerance — those that
  build it without a try/catch today, e.g. `FlareFilter/FieldsOptionsCallbacks::getFieldOptions_fieldGeneric()` /
  `getOptions_targetAlias()`, get one, so a broken catalog does not crash every filter edit form).
- `buildQuery()` and `ListDriverBuildQueryEvent` listeners run per query: a `FlareException` fails the query like a
  filter failure without a control (the list fails closed).
- Programming errors — duplicate aliases, an unbound parameter of an active join, a mapping referencing an unknown
  alias — are `\Error`s and escape unwrapped (the request fails), consistent with `UndefinedConfigKeyError`. Since
  they come from code, they are acceptable in the backend too.
- `expandEntries()`: a `FlareException` fails the list; `\Error`s escape.
- `abort()` keeps meaning "legitimately matches nothing".

### 10. Removals and moves

Removed: `Event/ModifyListQueryStructEvent`, `Event/QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`,
`Integration/ContaoCalendar/EventListener/{DoNotPaginate,CountEvents}ModifierListener`,
`Integration/ContaoCalendar/Loader/*`, `Integration/ContaoCalendar/Projector/EventsAggregationProjector` (and
`EventsInteractiveProjector` unless needed for the view, §2a), `Query/ListExecutionContext`,
`Query/Factory/ListExecutionContextFactory`, `ListQueryConfig::$attributes`, `$isCounting`, `$onlyId` (replaced by
`$purpose`), `Contract/ListDriver/BuildQueryContract` in its current form, `AsFilterElement::$isTargeted`,
`FilterCompound::$targetingForced`, the targeting stopgap. Whether `SqlQueryStruct`, `SqlJoinStruct`,
`TableAliasRegistry`, `QueryBuilderFactory`, `FilterQueryBuilderFactory` survive as internals, are renamed or are
replaced is the builder's call (the nomenclature spec only said they keep their names *there*).

Documentation: `AGENTS.md` (architecture flow, the "query events … left as they are until the query architecture is
reworked" sentence, Notable subsystems, Query safety, Event system); the docs worktree (`docs/`, branch `docs/main`,
committed separately): `docs/docs/spec/query-struct.md`, `spec/filtering.md`, `spec/projection.md`,
`dev/contracts/build-query-contract.md`, `dev/events.md`, `dev/list-types/index.md`, `migrating-from-v0.1.md` (incl.
the count fix and the reset of stale targets) — describing the step from v0.1 to the final v0.2.

## Decisions

| # | Decision | By |
|---|---|---|
| D1 | Driver provides building blocks, the framework assembles in a fixed order (no priority chain) | user |
| D2 | Integrations contribute by event, for now only aliases and joins | user |
| D3 | A join's declarer fills its parameters when the query is built; integrations through a build-time event, without the driver knowing | user |
| D4 | Unfilled parameter of an active join = programming error; parameter names scoped per join alias | user (proposed by Claude) |
| D5 | Every element targetable (any catalog alias, default `main`), `column()` applies it, `column($col, for: $alias)` for multi-alias predicates declared as an option | user |
| D6 | Opt-out for elements that address aliases themselves; unknown alias = broken filter (fail closed, inspector); used aliases activate joins | user (proposed by Claude) |
| D7 | Column mapping by the driver is supported | user |
| D8 | DC_Multilingual only made possible, ported later | user |
| D9 | Sort hints and Sortinator prepared here, built in the next PR; columns are one source of sort keys; unresolvable keys skipped (fail-open); Sortinator restricted to list-allowed keys | user |
| D10 | Hint declaration via a value-free contract, realization in `buildEffect()` | Claude's proposal, answered to the user's question; final in the next PR |
| D11 | Catalog built once into the `ListCompound`; column mapping declared with the catalog | Claude |
| D12 | Purposes `Entries` and `Count`; reader lookups are `Entries` under `ValidationIntent`; `onlyId` dropped; drivers contribute the same blocks for both purposes | Claude |
| D13 | `sortSettings` widget stays `main`-only | Claude |
| D14 | Branch `v0.2-query` | Claude |
| D15 | Columns of an alias = DCA fields of its table + mapped columns, not stored in the catalog; sort keys checked against them (single-valued aliases only), filter columns not; resolvable ≠ allowed | Claude (after review) |
| D16 | Count wraps the Entries query (`SELECT COUNT(*) FROM (…) t`) | user |
| D17 | Bundled elements that read the list's table follow their target's table; only fixed-column elements opt out | user |
| D18 | Run-once migration resets stale `targetAlias` of formerly untargeted types to `main`; marker survives the schema diff | user (marker: Claude after review) |
| D19 | Events modelled as an expanding driver (occurrences paged and counted generically in PHP); Events loaders/projectors and the per-purpose deviations go | user |
| D20 | The date-window bug of the expansion is a follow-up idea, not fixed here | user |
| D21 | Join cardinality in the catalog; one join activation set for both purposes; default grouping physical `main.id` | Claude (after review) |
| D22 | Target alias validated eagerly at list build (`FilterFailure`) | Claude (after review) |
| D23 | Parent relation for Archive/BelongsTo: list config for `main`, element config or target-table inference otherwise | Claude (after review) |
| D24 | `FilterInvoker` returns invocation results (effect, conditions, used aliases) | Claude (after review) |

## Left to the builder

Final names (in the user's naming style), the exact builder interfaces and their methods, the internal structs, the
parameter-scoping scheme, the opt-out mechanism, the physical-column API, the sort-key format, how ownership of join
parameters is enforced, where expanded entries are paged and counted, how query-shaping inputs reach Count, the order
of commits. Anything marked "builder's call" above.

## Testing

- **Before the refactor**: capture today's generated SQL and parameters for a matrix of the bundled lists (generic
  DC, News with and without an archive filter, Events with a current-events filter, a Codefog tags filter, sorted and
  unsorted, Entries and Count) as reference snapshots. They document the old shape; the new SQL may differ.
- Unit tests through public API for: catalog declaration (driver + event, duplicates, cardinality, hidden/mandatory),
  column mapping and physical access, join parameter scoping and the unbound-parameter `\Error`, targeting (`column()`,
  `for:`, eager validation → `FilterFailure`, join activation incl. empty-SQL propositions, from-alias and `requires`
  closure, identical activation for Entries and Count), table-bound elements offering and computing choices from their
  target's table (incl. the parent relation for non-`main` targets), the stale-target migration (resets only formerly
  untargeted types, runs once, survives a dropped-marker scenario, leaves later edits alone), Count wrapping, entry
  expansion (paged and counted over expanded entries, no expansion under `ValidationIntent`), sort-key resolution
  and fail-open skipping (incl. multiplying aliases), identity never overridden.
- Generated SQL for the bundled drivers: generic DC, News (archive join only when used), Events (no SQL paging or
  ORDER BY when expanding), a Codefog tags filter (relation joins activated by the target, grouping by `main.id`).
- **Expressibility proof**: a test fixture driver (in `tests/Fixture/`) that does what §8 describes — mandatory
  parameterized single-valued join, mapping, physical base condition, mapped identity, intent-driven base condition —
  plus a fixture integration that adds a parameterized join and fills it through the build event. It must not need
  anything outside the public API. It asserts that Entries and Count are built from the same rows.
- **Manual acceptance** (CI has no database): run the reference matrix against MySQL 8 and MariaDB with sample data
  and compare ids, counts and order before/after; check `ONLY_FULL_GROUP_BY`, duplicate column names from `main.*`
  plus mapped columns, and array parameters in ON clauses.
- Existing tests follow (`ListQueryDirectorTest`, `FilterInvokerTest`, `FilterEffectFactoryTest`, `FormulaBuilderTest`,
  `CodefogTagsChoiceFilterElementTest`, `FieldsOptionsCallbacksTest`, `ElementDcaHookTest`,
  `InteractiveProjectorTest`, `FilterCompoundTest`); `make test` and `make phpstan` green; Mago runs in CI only.
