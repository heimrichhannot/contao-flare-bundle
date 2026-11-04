# Design: query architecture — list drivers own their query building blocks

Status: agreed in conversation 2026-11-04, builder-checked, awaiting the user's review. Branch: `v0.2-query`
off `v0.2` (the unreleased major version; breaking changes are fine, no BC shims, no `class_alias`) — Claude's call.

## Intent

Filter elements have a clean structure: an element receives a collect-only builder and a value-free context, adds
abstract building blocks (`buildEffect()`), and the framework turns them into SQL. List drivers have nothing
comparable. A list's query is assembled today by a chain of mutations:

1. `Query/Factory/ListExecutionContextFactory` creates a mutable `SqlQueryStruct` (`main.*`, `GROUP BY main.id`) and a
   `TableAliasRegistry`; the driver mutates both through `BuildQueryContract::buildTableRegistry()` /
   `buildBaseQuery()`; `QueryBaseInitializedEvent` lets anyone mutate them further (Codefog Tags registers its joins
   there); the factory then re-adds `main.id AS id`.
2. `Query/Executor/ListQueryDirector` builds every filter's conditions and dispatches `ModifyListQueryStructEvent`.
   The actual query semantics live in listeners ordered only by priority: `SelectModifierListener` (490),
   `ConditionsModifierListener` (470, also activates join aliases), `PageModifierListener` (430),
   `OrderModifierListener` (420), the Calendar listeners `DoNotPaginateModifierListener` (200) and
   `CountEventsModifierListener` (100), `JoinModifierListener` (−450).
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
  target automatically (today only `main` works, through a stopgap in `FilterEffectBuilderFactory`).
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

1. A **table catalog** per list: every alias the list's query can use, with its table, its join and its flags,
   declared value-free by the driver and extended by integrations through one event. It replaces
   `ListExecutionContext` / `TableAliasRegistry` as the source of truth, in the frontend and in the backend.
2. A **driver query hook**, called per query, that contributes the driver's building blocks: projection, identity,
   base conditions, grouping, the values of its join parameters, and per-purpose deviations from the defaults.
3. A **column mapping** declared by the driver: a column of an alias may stand for an SQL expression
   (`main.title` ⇒ `IFNULL(translation.title, main.title)`), applied wherever a column is resolved.
4. **Join parameters**: a join condition may contain named parameters; whoever declares a join fills its parameters
   when the query is built — the driver in its hook, an integration through an event. The driver need not know.
5. **Targeting for every element**, applied by `FilterConditionsBuilder::column()`; multi-alias predicates via
   `column($col, for: $alias)`; joins activated by use.
6. **Framework assembly** in a fixed order, without listener priorities. `ModifyListQueryStructEvent`,
   `QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`, the Calendar modifier listeners,
   `ListQueryConfig::$attributes`/`$isCounting`/`$onlyId` and `BuildQueryContract` are removed.
7. **Sorting as a sequence of sort keys** with a resolution step that today knows one source (columns of the
   catalog) and is ready for the second (sort hints of elements) and for the Sortinator.
8. The bundled lists (generic DC, News, Events, Codefog Tags filters) return the same entries, counts and order as
   before. The SQL text may change.

## Non-goals

- Porting the DC_Multilingual / Changelanguage integration (`src/Integration/Terminal42Languages/` stays untouched,
  unloaded and excluded from PHPStan). Its needs are covered by an executable proof in the tests instead (see
  Testing).
- Implementing sort hints, the hint declaration contract, the Sortinator, derivations (inner SELECTs) or any other
  new `FilterEffect` channel — they are prepared for (see "Prepared for the next PR"), not built.
- Offering columns of aliases other than `main` in the `sortSettings` backend widget (it stays `main`-only as today);
  the sort-key resolution supports any catalog alias already (Claude's call).
- Integrations contributing anything but aliases, joins and the values of their own join parameters (user's
  decision: "vorderhand nur Aliasse und Joins"). No projection, conditions, mapping or ordering by events.
- Changing `FilterConditionsBuilder`'s condition API, the predicates' SQL, the filter failure semantics
  (`FilterFailure`, retry with `null`, `abort()`), the engine/projector/view layering, or the Calendar loaders'
  PHP-side grouping and pagination.
- The `ListModel` exception (`ListCompound`, `ListCompoundBuilder`, `ListCompoundFactory`, `BaseListOptions` keep
  taking a `ListModel`).

## Anti-goals

- A new priority chain through the back door: no ordered listeners, no "run me after X", no event that may change
  what another contribution produced.
- Stringly channels between components (`$attributes`-style keys) — deviations are typed builder calls.
- Raw, unbound runtime values in SQL (the legacy language interpolation).
- BC shims or deprecated aliases for the removed events/contracts.
- An interface shape that forces the sort-hint/Sortinator PR to rebuild the catalog, the driver hook or the assembly.
- Behavior differences for existing lists beyond SQL text (entries, counts, order, reader lookup, backend options).

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
- MySQL 5.7/8 and MariaDB. PHP ^8.2.
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

ListQueryDirector::createQueryBuilder(ListQueryConfig {list, intent, filterValues, purpose})
  1. fail if the list recorded failures                     (unchanged)
  2. driver buildQuery(ListQueryBuilder, ListQueryContext)  — building blocks of this query
     → ListDriverBuildQueryEvent                            — integrations fill their join parameters
  3. filters → effects → conditions (FilterInvoker)         — column() applies target + mapping, records aliases
  4. sort keys → ORDER BY (purpose Entries)                 — resolved via catalog; unresolvable key skipped
  5. paging (purpose Entries, unless the driver opted out)
  6. projection by purpose: Entries → driver projection + identity AS id; Count → COUNT(DISTINCT identity)
     unless the driver replaced it
  7. joins: mandatory ∪ used (filters, sort keys, mapping, projection, identity), closed over `requires`,
     topologically sorted; every parameter of an active join must be bound
  8. → Doctrine QueryBuilder
```

Steps 3–8 are framework code in one place, in this order. Which internal structs carry the intermediate state
(today `SqlQueryStruct`, `SqlJoinStruct`, `TableAliasRegistry`) is the builder's call; nothing public mutates them.

### 1. The table catalog

**What it holds** (per alias): the table; for a non-main alias its join (type, from-alias, condition, `requires`);
the declared parameter names of the join condition; the flags *mandatory* (always joined) vs. *on demand* (joined only
when used) and *hidden* (not offered to editors as a target or sort column, still usable by code — the Codefog
relation table); the alias' column mapping (section 3). `main` is always present and is the list's data container
(`ListCompound::$dc`).

**Who declares it:** the driver, in a new value-free hook (working name
`buildTableCatalog(TableCatalogBuilderInterface $builder, …)`; the second parameter gives the list's resolved config
and dc — the exact context type is the builder's call, it must not need an intent). It replaces
`buildTableRegistry()`. Then `ListDriverBuildTableCatalogEvent` (named dispatch
`flare.list_driver.{type}.table_catalog` — suffix is the builder's call within the naming rule) lets integrations add
aliases and joins, with parameters, for any list (Codefog: any list whose table has a tags field). The event's builder
exposes alias/join declaration only — no mapping, no removal of other contributions. Duplicate aliases are a
programming error.

**Where it lives** (Claude's call): built once in the `ListCompound` build lifecycle, after the config is resolved,
and carried immutably as a property of the `ListCompound`. So every consumer that already holds the list — elements
via `ElementContext::$list`, `DcaContext`, `FilterInspector`, the director — reads the same catalog without a
factory, and the backend gets it without running a query. If the builder finds a reason to build it elsewhere, the
requirement is: value-free, built once per list, same result in backend and frontend, no query executed.

**Backend consumers** move to the catalog: `Contao/DcaContext::getExecutionContext()`/`getTables()`/
`getTargetTable()`, `Contao/Hook/ElementDcaHook::createExecutionContext()`,
`Contao/Callback/FlareFilter/FieldsOptionsCallbacks` (`getFieldOptions_fieldGeneric`, `getOptions_targetAlias`),
`Integration/CodefogTags/FilterCallback/TargetAliasCallback`, `CodefogTagsChoiceFilterElement::buildChoices()`/
`getOptions()`, `Contao/Callback/FlareList/FieldsOptionsCallbacks::getFieldOptions_sortSettings()` (keeps offering
`main` only). `ListExecutionContext` and `ListExecutionContextFactory` are removed.

**Bundled declarations:** News and Events declare their archive joins (`news_archive`, `events_archive`) on demand,
as today. Codefog's `RegisterTagsTablesListener` moves from `QueryBaseInitializedEvent` to the catalog event (relation
table hidden, tags table on demand; its `CfgTagsJoinsRegistry` side channel may stay or be replaced by catalog
metadata — builder's call).

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

**Building blocks the driver contributes** (each has a framework default, so a driver that contributes nothing gets
today's query):

| Block | Default | Who needs to change it |
|---|---|---|
| Projection | `main.*` | multilingual: explicit list with mapped columns |
| Identity — the expression that is the entry's `id` | `main.id` through the column mapping | multilingual: `IFNULL(translation.id, main.id)` (falls out of the mapping) |
| Base conditions (AND-ed with the filters; physical columns) | none | multilingual: `main.langPid = 0` |
| Grouping | `GROUP BY` identity | multilingual: none |
| Values of the driver's own join parameters | — | multilingual: `:lang` |
| Per-purpose deviations | Entries: SQL paging from the intent's paginator; Count: `COUNT(DISTINCT identity)` | Calendar: no SQL paging for Entries; Count selects `id, startTime, endTime, repeatEach, repeatEnd` instead of `COUNT(...)` (its loader groups in PHP) |

The framework always selects the identity `AS id` and never overrides the driver's identity (today's "re-ensure
`main.id AS id`" silently would). Rows must carry mapped values under the plain column names (the legacy behavior);
how the projection achieves that with `main.*` plus mapped columns is the builder's call.

**Calendar** after this refactor: `EventsListDriver::buildQuery()` declares the two deviations by purpose;
`DoNotPaginateModifierListener`, `CountEventsModifierListener` and the `ContaoCalendar_*` attributes are removed;
the Events loaders/projectors keep their PHP grouping/pagination and simply run the Entries / Count purpose.
The replaced Count projection keeps the default grouping by identity (Claude's call): today the Events count query
has neither `GROUP BY` nor `DISTINCT`, so with a multiplying join (e.g. a Codefog filter on several tags) the count
exceeds the entries, which are grouped by `main.id`. Grouping fixes that; counts change only in that buggy case
(an exception to goal 8, named in the docs' migration notes). Today
`CountEventsModifierListener` throws `AbortFilteringException` when no valid column remains — with typed builder calls
that case disappears or becomes a programming error (builder's call).

**What the driver cannot do** (anti-goal): reorder or rewrite the filters' conditions, the ORDER BY or the joins'
activation; those are framework steps.

### 3. Column mapping

The driver may declare that a column of an alias stands for an SQL expression, e.g. for every translatable field
`main.<f>` ⇒ `IFNULL(translation.<f>, main.<f>)`. Declared with the catalog (value-free; Claude's call — it depends
only on the table's DCA, and the backend/inspector can see it), by the driver only.

- Applied wherever a column is resolved: `FilterConditionsBuilder::column()` (all filters, including the reader's
  lookup filters — so the multilingual auto-item lookup hits the translated alias *and* keeps untranslated entries,
  the reason the user chose mapping over retargeting), sort-key resolution, identity, projection.
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
- **Bundled elements follow their target's table** (user's decision): every element that reads the list's table today
  reads its target's table instead — for backend field options and for choices/lookups at runtime.
  `DcaContext::getTargetTable()` exists in the backend; `ElementContext` gets the runtime counterpart (the target
  alias' table from the catalog; name is the builder's call). Affected: `DcaSelectFieldFilterElement` (field options,
  `getOptions()`), `FieldValueChoiceFilterElement::buildChoices()`, `ArchiveFilterElement` and
  `BelongsToRelationFilterElement` (`PtableInferrer` on the target table, including the cache key in
  `ArchiveFilterElement`), and the generic field options in `FieldsOptionsCallbacks`. Elements already targeted
  (`flare_bool`, `flare_equation_simple`, `flare_search_keywords`, the Codefog elements) keep working as they do.
  Only elements bound to fixed columns of a specific table opt out: `CalendarCurrentFilterElement` (event columns);
  `PublishedFilterElement` too unless its configurable field names make a target sensible (builder's call).
- **Stale stored targets** (user's decision): `targetAlias` is `alwaysSave` and survives a change of the element type,
  so rows of formerly untargeted types may hold a non-`main` value that the stopgap ignored and that would now apply.
  A run-once migration in `src/Migration/` resets `targetAlias` to `main` on every row whose type was **not** targeted
  in v0.1/early v0.2 — the hard-coded list of formerly targeted types is `flare_bool`, `flare_equation_simple`,
  `flare_search_keywords`, `cfg_tags_choice`, `cfg_tags_search` (the attribute is removed, so the migration must not
  read it). It must run only once: a target an editor sets after the refactor must never be reset (a marker like
  `BackfillFilterValuesMigration`'s `legacyBackfilled`, or an installation-wide one — builder's call). It updates
  values only, never drops or deletes; SQL for MySQL and MariaDB.
- `FilterConditionsBuilder::column($col)` resolves against the filter's target alias and applies the column mapping.
- A predicate that can or must address several aliases **declares that as an option** and uses
  `$builder->column($col, for: $alias)` (user's words). The element supplies the alias in the predicate options
  (from its config or its own knowledge, e.g. Codefog's relation alias).
- Every alias resolved through `column()` (target or `for:`) is recorded; the assembly activates exactly the used
  aliases plus the mandatory ones (plus `requires`). This replaces `FilterConditions::$targetAlias` and the
  activation in `ConditionsModifierListener`. Conditions that bypass `column()` with raw alias strings are not
  tracked — the docs say so.
- An alias that is not in the catalog (target or `for:`) is a `FilterException`: the filter is broken, the list fails
  closed, the backend `FilterInspector` reports it as *broken* (it builds conditions through the shared
  `FilterInvoker`, which now also validates aliases).
- `FilterEffectBuilderInterface::addPredicate(…, ?string $targetAlias)` keeps or loses its per-predicate override —
  builder's call (it overlaps with `for:`; keep it only if a bundled element needs it).

### 6. Sorting, prepared for hints and the Sortinator

Abstraction (user: "man könnte die basic Spalten auch als SortHints betrachten"): a sort order is a sequence of
**sort keys** with directions. A key comes from one of two sources:

1. **columns of the catalog** (`alias.column`, resolved through `column()` — mapped, join activated) — this PR;
2. **sort hints declared by filter elements** — the next PR.

This PR:

- generalizes `Sort/SortOrder` (today a fixed `alias`/`column` pair) so a key is not necessarily a column; the
  `SortOrderSequence` from the intent stays the single input of the ORDER BY step;
- resolves keys in the framework assembly (step 4), after the filters' effects are built, so effect-provided hints can
  be resolved there later;
- **fails open**: an unresolvable key (unknown alias/column today; an ineffective hint later) is skipped with a
  warning, the remaining keys still sort (user's decision: "ordering sollte relativ fail-open sein");
- keeps the stored `sortSettings` format readable; the key format must leave room for a hint namespace without a
  migration (e.g. column keys never contain a character a hint key uses — builder's call).

`OrderModifierListener`'s behavior (no ORDER BY for Count, sequence from `SortableIntentInterface`) is kept.

**Columns of an alias** (Claude's call): the catalog does not store column lists. The columns of an alias are the
DCA fields of its table (what the `sortSettings` widget offers today via `DcaHelper::getFieldOptions()`), plus the
columns the driver's mapping declares for that alias. A column sort key is resolvable when its alias is in the
catalog and its column is such a column; otherwise it is skipped (fail-open). The same column source feeds the
Sortinator's allowed keys in the next PR, so the catalog interface need not change for it. `FilterConditionsBuilder::column()`
does **not** check column existence (unchanged behavior: an unknown column is an SQL error, as today); it checks only
the SQL name and the alias.

### 7. Prepared for the next PR (outlook, not built)

So the next PR can add sort hints and the Sortinator without rebuilding this one:

- **Hint declaration** (proposed in conversation, user asked; not yet decided): a capability contract in
  `src/Contract/FilterElement/` (working name `SortHintContract::buildSortHints(SortHintsBuilder, ElementContext)`),
  value-free like `ChoiceSourceContract::buildChoices()`, listing the keys an element *can* offer with labels — read
  by the list configuration (default sort over hints) and the Sortinator (allowed keys).
- **Hint realization** in `buildEffect()` (`$builder->sortHint('overlap', …)`), because a hint usually references the
  derivation the element builds for its restriction anyway (filter-scope spec §8, tag-overlap example); a declared but
  unrealized hint is ineffective and skipped.
- **`FilterEffect`** today carries only `$formula`; new channels (sort hints, derivations) are added as further
  properties and builder methods without changing `buildEffect()`'s signature. The join assembly must accept joins
  that do not come from the catalog (derivations) — keep the activated-joins step generic over join definitions.
- **Sortinator**: like the paginator, it turns a request parameter into the intent's `SortOrderSequence`, restricted to
  the keys the list allows (catalog columns + declared hints). The query layer needs nothing beyond step 4.
- **Stable events**: the fixed assembly order is the place where later events (e.g. "query assembled") can be offered
  without priorities. None are added beyond the two integration events.

### 8. What the multilingual driver would look like (expressibility check)

Not built; it is the yardstick for the interfaces (and for the test fixture driver):

- catalog: alias `translation` on the same table, LEFT JOIN `main.id = translation.<langPid> AND
  translation.<language> = :lang`, *mandatory*; column mapping for `id`, the language/pid columns and every
  translatable field;
- `buildQuery()`: `:lang` from the current language (in the fallback language the join finds no row and every
  `IFNULL` falls back to `main` — so the catalog stays static and a `translation` target always exists); base
  condition `main.langPid = 0` (physical); no grouping; projection of regular fields plus mapped fields; a
  content-element display mode ("localized": only translated entries) as a base condition on `translation.<langPid>`
  — read from the intent (how it reaches the intent is the later port's business);
- reader lookup by auto-item: nothing to do — the lookup filter's `column('alias')` is mapped;
- the Changelanguage "same entry in language X" query is out of scope (it may later run the list's query with another
  `:lang`; nothing here may preclude that).

### 9. Removals and moves

Removed: `Event/ModifyListQueryStructEvent`, `Event/QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`,
`Integration/ContaoCalendar/EventListener/{DoNotPaginate,CountEvents}ModifierListener`,
`Query/ListExecutionContext`, `Query/Factory/ListExecutionContextFactory`, `ListQueryConfig::$attributes`,
`$isCounting`, `$onlyId` (replaced by `$purpose`), `Contract/ListDriver/BuildQueryContract` in its current form,
`AsFilterElement::$isTargeted`, `FilterCompound::$targetingForced`, the targeting stopgap. Whether `SqlQueryStruct`,
`SqlJoinStruct`, `TableAliasRegistry`, `QueryBuilderFactory`, `FilterQueryBuilderFactory` survive as internals,
are renamed or are replaced is the builder's call (the nomenclature spec only said they keep their names *there*).

Documentation: `AGENTS.md` (architecture flow, the "query events … left as they are until the query architecture is
reworked" sentence, Notable subsystems, Query safety, Event system); the docs worktree (`docs/`, branch `docs/main`,
committed separately): `docs/docs/spec/query-struct.md`, `spec/filtering.md`, `spec/projection.md`,
`dev/contracts/build-query-contract.md`, `dev/events.md`, `dev/list-types/index.md`, `migrating-from-v0.1.md` —
describing the step from v0.1 to the final v0.2.

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
| D12 | Purposes `Entries` and `Count`; reader lookups are `Entries` under `ValidationIntent`; `onlyId` dropped | Claude |
| D13 | `sortSettings` widget stays `main`-only | Claude |
| D14 | Branch `v0.2-query` | Claude |
| D15 | Columns of an alias = DCA fields of its table + mapped columns, not stored in the catalog; sort keys checked against them, filter columns not | Claude |
| D16 | Events Count keeps grouping by identity (fixes count > entries with multiplying joins) | Claude |
| D17 | Bundled elements that read the list's table follow their target's table; only fixed-column elements opt out | user |
| D18 | Run-once migration resets stale `targetAlias` of formerly untargeted types to `main` | user |

## Left to the builder

Final names (in the user's naming style), the exact builder interfaces and their methods, the internal structs, the
parameter-scoping scheme, the opt-out mechanism, the physical-column API, the sort-key format, how ownership of join
parameters is enforced, the order of commits. Anything marked "builder's call" above.

## Testing

- Unit tests through public API for: catalog declaration (driver + event, duplicates, hidden/mandatory), column
  mapping and physical access, join parameter scoping and the unbound-parameter `\Error`, targeting (`column()`,
  `for:`, unknown alias → `FilterException`, join activation by use, `requires` closure; table-bound elements
  offering and computing choices from their target's table), the stale-target migration (resets only formerly
  untargeted types, runs once, leaves later edits alone), purpose defaults and driver
  deviations, sort-key resolution and fail-open skipping, identity never overridden.
- Generated SQL for the bundled drivers: generic DC, News (archive join only when used), Events (no SQL paging,
  count projection), a Codefog tags filter (relation joins activated by the target).
- **Expressibility proof**: a test fixture driver (in `tests/Fixture/`) that does what section 8 describes — mandatory
  parameterized join, mapping, physical base condition, mapped identity, no grouping — plus a fixture integration
  that adds a parameterized join and fills it through the build event. It must not need anything outside the public
  API.
- Existing tests follow (`ListQueryDirectorTest`, `FilterEffectFactoryTest`, `CodefogTagsChoiceFilterElementTest`,
  `FieldsOptionsCallbacksTest`, `ElementDcaHookTest`, `InteractiveProjectorTest`, `FilterCompoundTest`); `make test`
  and `make phpstan` green; Mago runs in CI only.
