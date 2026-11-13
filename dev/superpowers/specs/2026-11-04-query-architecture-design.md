# Design: query architecture — list drivers own their query building blocks

Status: agreed in conversation 2026-11-04, builder-checked and independently reviewed twice, awaiting the user's
review. Rebased onto `v0.2` after PR #54 (entries instead of models in the engine views) on 2026-11-13 and revised in
conversation the same day: an explicit column list replaces `main.*` and the column mapping (§3), the list alone
defines its entries (§2), lookups by ids and the expansion follow PR #54. A small follow-up to PR #54 goes first on
`v0.2` and is assumed here: no entry becomes a Contao model any more (`EntryModelFactory` removed; the schema.org
listeners and `flare_related()` use Contao's finders) and the reader's auto-item lookup drops its `LIMIT 1`. Branch:
`v0.2-query` off `v0.2` (the unreleased major version; breaking changes are fine, no BC shims, no `class_alias`) —
Claude's call.

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
- **Entries and Count never disagree**, and **related rows never multiply entries.** The legacy multilingual
  integration and today's Events count show how a scattered query produces counts that do not match the entries;
  today's to-many joins (Codefog tags) need `GROUP BY main.id` against duplicates, which clashes with computed
  columns under `ONLY_FULL_GROUP_BY`, and make two tag filters of one list share one joined row
  (`tags.id IN (A…) AND tags.id IN (B…)` — almost always empty).
- **Every key of a row is defined** (user, 2026-11-13): the projection is an explicit list "row key → SQL
  expression", pre-filled with the columns of the list's table; the driver and integrations redefine single columns
  (`IFNULL(…)`, an inner SELECT, a fixed value) or add keys. Whether a key comes from the table itself, from an
  expression or from a join makes no difference. `main.*` goes, and so does every link between the query and Contao
  models: rows become entries (PR #54), nothing else.
- **The list defines its entries, the intent their presentation** (user, 2026-11-13): an intent decides how a list is
  executed and embedded — the order and the section shown —, never what constitutes the list's entities.
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
2. A **driver query hook**, called once per query build, that contributes the driver's per-query building blocks:
   base conditions and the values of its parameters. Entries and Count are both derived from it.
3. **One row per entry**: only outer-joinable (single-valued up to `main`) aliases are joined into the outer query; conditions on multiplying
   aliases are compiled per filter into correlated `EXISTS` subqueries (user's decision).
4. An explicit **column list**: every key of a row has one definition, pre-filled with the columns of the list's
   table and redefined or extended by the driver and by integrations (`main.title` ⇒
   `IFNULL(translation.title, main.title)`); a definition applies wherever the list query resolves the column —
   projection, identity, filters, sorting.
5. **Parameters**: a join condition or a column definition may contain named parameters; whoever declares them fills
   them when the query is built — the driver in its hook, an integration through an event. The driver need not know.
6. **Targeting for every element**, applied by `FilterConditionsBuilder::column()`; multi-alias predicates via
   `column($col, for: $alias)`; bundled elements follow their target's table.
7. **Framework assembly** in a fixed order, without listener priorities; **Count counts the Entries query** (wrapping),
   so the two cannot disagree.
8. **Entry expansion** as a driver capability: a driver whose entries are produced in PHP from rows (Events:
   occurrences) declares so; the framework paginates and counts the expanded entries generically.
9. **Sorting as a sequence of sort keys** with a resolution step that today knows one source (columns of the
   catalog) and is ready for the second (sort hints of elements) and for the Sortinator; sorting never changes the
   rows and is deterministic.
10. Removal of `ModifyListQueryStructEvent`, `QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`, the
    Calendar modifier listeners and loaders/projectors, `ListQueryConfig::$attributes`/`$isCounting`/`$onlyId` and
    `BuildQueryContract` in its current form.
11. The bundled lists (generic DC, News, Events, a single Codefog Tags filter) return the same entries, counts and
    order as before; the SQL text may change. Accepted exceptions, named in the docs' migration notes:
    - counts that today exceed the entries (Events with a to-many join) now match them;
    - a list with **several** filters on the same to-many alias (e.g. two tag filters) now ANDs them correctly instead
      of matching (almost) nothing;
    - a `flare_equation_simple` with `IS NULL` targeting a to-many alias (e.g. tags) no longer matches entries without
      related rows (existential semantics of the `EXISTS` compilation, §1);
    - ties: rows that compare equal under the configured sort are now ordered by `main.id` (today undefined; on
      MySQL 5.7 and MariaDB implicitly `main.id` through `GROUP BY`); occurrences of Events with equal start and end time follow
      `main.id` instead of a stored, hidden `sortSettings`.

## Non-goals

- Porting the DC_Multilingual / Changelanguage integration (`src/Integration/Terminal42Languages/` stays untouched,
  unloaded and excluded from PHPStan). Its needs are covered by an executable proof in the tests instead (see
  Testing).
- Implementing sort hints, the hint declaration contract, the Sortinator, derivations (inner SELECTs) or any other
  new `FilterEffect` channel — they are prepared for (see "Prepared for the next PR"), not built.
- "Lacks a related row" semantics (`NOT EXISTS`) for elements: a negating predicate on a to-many alias inside the
  filter's `EXISTS` means "has a related row that is not X" — the same meaning as today. Offering `NOT EXISTS` is an
  outlook item (§7). The one accepted semantic change of the `EXISTS` compilation (`IS NULL` on a to-many alias) is
  listed in goal 11.
- Bounding the Events occurrence expansion by the filter's date window (today a recurring event lists all its
  occurrences, also those outside the filtered range). Recorded as a follow-up idea in
  `dev/superpowers/ideas/2026-11-04-events-expansion-window.md` (user's decision).
- Offering columns of aliases other than `main` in the `sortSettings` backend widget (it stays `main`-only as today);
  the sort-key resolution supports any single-valued catalog alias already (Claude's call).
- Integrations contributing anything but aliases, joins, column definitions and the values of their own parameters
  (user's decisions: "vorderhand nur Aliasse und Joins" on 2026-11-04, column definitions added on 2026-11-13). No
  conditions or ordering by events.
- The follow-up to PR #54 (removing `EntryModelFactory`, native model lookups in the schema.org listeners and
  `flare_related()`, the auto-item lookup without `LIMIT 1`): its own PR on `v0.2` before this one (user's
  decision).
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
- Entries and Count computed from different rows — through joins activated for one of them only, inputs that reach
  only one of them, or a driver shaping them differently.
- A whole list that depends on the intent — a driver block, a join or a column definition read from it. An intent
  only narrows the list to a section.
- Duplicate entries from related rows, and grouping as the means against them.
- A sort order (later: chosen by a visitor) that changes which rows are entries.
- BC shims or deprecated aliases for the removed events/contracts.
- An interface shape that forces the sort-hint/Sortinator PR to rebuild the catalog, the driver hook, the filter
  invocation or the assembly.
- Behavior differences for existing lists beyond SQL text and the exceptions in goal 11.

## Constraints

- Project conventions (`AGENTS.md`): vocabulary *Compound / Effect / Context / Intent* kept distinct; lifecycle
  taxonomy (`configure*` declarative and memoizable, `build*` per invocation; hook parameter order "subject first,
  context supplementary"); event naming `{Component}{Hook}Event` / `flare.{component}.{type}.{suffix}`; the bundle
  catches `\Exception`, not `\Throwable`; programming errors are `\Error`s that escape unwrapped
  (`UndefinedConfigKeyError`, `ValueClassMismatchError`); `FilterException` is the public failure of a filter.
- Naming taste (user): short, sober nouns with the concrete term last; no "Setup", "Prepared*", "Assembly",
  "Environment", "Frame", "Scope", "Config" (taken by `elm_`/`ctl_` config). The names below are working names;
  the builder may improve them in that style.
- Query safety: identifiers quoted through the connection, values bound as parameters (also in join conditions and
  inside subqueries/derived tables), SQL names validated (`Str::isValidSqlName()`). Doctrine DBAL `QueryBuilder`
  remains the final product.
- MySQL 5.7/8 and MariaDB, with and without `ONLY_FULL_GROUP_BY` (Contao appends `TRADITIONAL` to the server's
  `sql_mode`, so a server default with `ONLY_FULL_GROUP_BY` stays on; MariaDB cannot use primary-key functional
  dependency). PHP ^8.2.
- Migrations never delete or drop (memory `migrations-never-delete`).
- Testable through public API (memory `testable-without-private-access`).
- Every row of an Entries query on a DC table carries the entry's identity as `id` (today `main.id AS id`, appended
  last by `ListExecutionContextFactory`). The entries engine (PR #54) relies on it: `EntryCollection::forTable()`
  throws `MissingEntryIdError` for a row without one, and `EntryLookup::fetch()` indexes the fetched rows by
  `(int) $row['id']` *before* that check, so a row without `id` would silently be a lookup miss. The new build
  guarantees the column: `id` is a key of the column list of `main` and cannot be removed (§3), so the lookup needs
  no check of its own. `meta.modelRef` no longer follows `row['id']`: it names the physical `main` record (§3), and
  the lookup indexes by it (§2).

## Design

### Overview

```
ListCompoundBuilder::build()                              — once per list (frontend and backend)
  driver buildList() → ListDriverBuildListEvent → config → schema       (unchanged)
  driver buildTableCatalog(TableCatalogBuilder, …)          — value-free: aliases, joins, column definitions
  → ListDriverBuildTableCatalogEvent                        — integrations add aliases, joins, column definitions
  ⇒ ListCompound::$tables (immutable TableCatalog)
  every filter's target (and declared for: aliases) checked against the catalog → FilterFailure if unknown

ListQueryDirector — per list × filter values (the intent adds only order and section)
  1. fail if the list recorded failures                     (unchanged)
  2. driver buildQuery(ListQueryBuilder, ListQueryContext)  — base conditions, parameter values; once, no intent
     → ListDriverBuildQueryEvent                            — integrations fill their parameters
  3. filters → FilterInvoker → invocation results (effect, conditions per alias, used aliases)
     backstop: every alias used is in the catalog (filters added later via withFilter(), overrides) → \Error
  4. WHERE = base conditions ∧ per filter: its propositions on outer-joinable aliases
                                        ∧ one correlated EXISTS (…) per connected to-many component it used
                                        (∧ physical main.id IN (:ids) for a lookup by ids)
  5. outer joins (outer-joinable only): mandatory ∪ used by filters, EXISTS anchors, the definitions they resolve,
     identity, base conditions — closed over from-alias and `requires`, topologically sorted;
     every parameter of an active join or a used definition (outer or inside an EXISTS) must be bound
  6. Entries: the column list of main (schema columns through their definitions + added keys; `id` = identity),
     WHERE, no grouping, ORDER BY sort keys + main.id tie-breaker, paging (none for the reader's queries);
     joins used only by projected definitions or by sort keys added as LEFT
     Count:   SELECT COUNT(*) FROM (SELECT 1 <Entries' FROM, outer joins, WHERE; no ORDER BY/LIMIT/OFFSET,
              no projection-only or sort-only joins>) t
  7. → Doctrine QueryBuilder → rows → EntryCollector (PR #54); meta.modelRef from the physical main.id

Expanding drivers (§2a): Entries fetched once per request without paging and with ORDER BY main.id → driver expands
the rows into occurrence rows → framework paginates and counts them in PHP; the page goes through EntryCollector
like any page.
```

Steps 3–7 are framework code in one place, in this order. Which internal structs carry the intermediate state
(today `SqlQueryStruct`, `SqlJoinStruct`, `TableAliasRegistry`) is the builder's call; nothing public mutates them.

### 1. The table catalog

**What it holds** (per alias): the table; for a non-main alias its join (type, from-alias, condition, `requires`);
the declared parameter names of the join condition; its **cardinality** relative to its from-alias — *single-valued*
(at most one row per row of its from-alias: News/Events archives, the multilingual translation, the Codefog tags table
relative to the relation table) or *multiplying* (the Codefog relation table relative to `main`); the flags
*mandatory* (always joined; outer-joinable aliases only) vs. *on demand*, and *hidden* (not offered to editors as a
target or sort column, still usable by code — the Codefog relation table); the alias' column definitions (§3). `main` is
always present and is the list's data container (`ListCompound::$dc`). Cardinality is declared by whoever declares the
alias; the framework trusts it.

**Effective cardinality** (Claude's call after review): an alias is *outer-joinable* only if it and every alias on its
from-alias / `requires` closure up to `main` are single-valued. Every other alias — a multiplying one, or a
single-valued one hanging off a multiplying one (the Codefog tags table) — is *to-many* and never joined into the
outer query. "Multiplying alias" in the rest of this document means *to-many* in this sense.

**Who declares it:** the driver, in a new value-free hook (working name
`buildTableCatalog(TableCatalogBuilderInterface $builder, …)`; the second parameter gives the list's resolved config
and dc — the exact context type is the builder's call, it must not need an intent). It replaces
`buildTableRegistry()`. Then `ListDriverBuildTableCatalogEvent` (named dispatch
`flare.list_driver.{type}.table_catalog` — suffix is the builder's call within the naming rule) lets integrations add
aliases and joins, with parameters and cardinality, and column definitions (§3), for any list (Codefog: any list whose
table has a tags field). The event's builder exposes declaration only — no removal or replacement of another
contribution. Duplicate aliases, a second definition of a column and a mandatory multiplying alias are programming
errors.

**Where it lives** (Claude's call): built once in the `ListCompound` build lifecycle, after the config is resolved,
and carried immutably as a property of the `ListCompound`. So every consumer that already holds the list — elements
via `ElementContext::$list`, `DcaContext`, `FilterInspector`, the director — reads the same catalog without a
factory, and the backend gets it without running a query. If the builder finds a reason to build it elsewhere, the
requirement is: value-free, built once per list, same result in backend and frontend, no query executed.

**Multiplying aliases and `EXISTS`** (user's decision): a multiplying alias is never joined into the outer query.
Each **filter** that uses multiplying aliases gets correlated subqueries instead:

```sql
EXISTS (SELECT 1 FROM tl_cfg_tag_news codefog_tags_news_join
        INNER JOIN tl_cfg_tag codefog_tags_news ON codefog_tags_news.id = codefog_tags_news_join.cfg_tag_id
        WHERE codefog_tags_news_join.news_id = main.id AND <the proposition's SQL>)
```

- **The compilation unit is the whole proposition** (one predicate call, i.e. one `FilterConditionsBuilder`'s
  product) — never parts of its SQL, which is an opaque string. A proposition that references any multiplying alias
  goes **entirely** into an `EXISTS`; one that references none stays in the outer `WHERE`.
- **Per filter, one `EXISTS` per connected component**: the propositions of one filter that reach to-many roots
  sharing a join path (e.g. one proposition on the relation table, another on the tags table) share one subquery, so
  they talk about the same related row. Propositions of *different* filters never share one, so two tag filters are
  ANDed correctly.
- **Inside the subquery** only the aliases actually used plus their from-alias / `requires` closure are joined (adding
  the whole path's INNER joins would drop dangling relation rows that a relation-only condition matches today); the
  to-many root's join condition becomes the correlation in `WHERE`; every join below the root keeps its declared
  type (user's decision after the 4th review). Turning LEFT into INNER would drop related rows whose optional
  descendant is missing before a proposition that does not reject NULLs (`relation.x OR tags.y`, `IS NULL`,
  `COALESCE`) is evaluated; for NULL-rejecting propositions — all bundled ones — both give the same rows, and MySQL
  and MariaDB simplify such a LEFT join to INNER themselves.
  A proposition that reaches two independent to-many branches (e.g. tags and categories, both from `main`) gets both
  joined into one subquery.
- **No renaming** (Claude's call after review): to-many aliases never appear in the outer query, and each `EXISTS` is
  its own scope, so the catalog alias names are reused inside every subquery — valid SQL, and the catalog can keep
  join conditions as strings with the alias names in them (`QueryHelperTrait::makeJoinOn()`).
- **Anchors**: the single-valued aliases a subquery's correlation or `requires` reference (e.g. a to-many alias hanging
  off `news_archive`) are added to the outer activation set.
- A conditions builder whose SQL is empty yields no `EXISTS` (an empty `EXISTS` would restrict to "has any related
  row", which nothing asks for today) — but its anchor still activates, as an empty-SQL proposition's target does.
- **Existential semantics, stated** (Claude's call after review): a condition on a to-many alias means "there is a
  related row for which the condition holds". Today the LEFT join chain also yields one NULL row for entries without
  related rows, so the two agree for every NULL-rejecting condition — all bundled predicates on tags except one: a
  `flare_equation_simple` with `IS NULL` on a tags alias matches untagged entries today and afterwards only entries
  with a related row for which it holds (for `tags.id IS NULL`: a relation row whose tag no longer exists, since the
  tags join keeps its LEFT type). This is an accepted change (goal 11, migration notes). Predicates that want other semantics ("title matches OR has tag",
  "has no tag X") will use an explicit builder helper (outlook, §7); the framework's automatic wrapping covers only
  propositions that place no subquery themselves.
- Column definitions (and with them projection and identity), base conditions and sort keys may only reference
  outer-joinable aliases in the outer query; referencing a to-many one is a programming error (definitions and base
  conditions, from code) or a skipped key (sorting, fail-open). An inner SELECT inside a definition is the
  definition's own business.
- Since the outer query then has exactly one row per entry, there is **no grouping** at all in this PR (see §2).

**Outer join activation** (one set, identical for Entries and Count): the mandatory aliases ∪ the outer-joinable
target alias of every proposition (also when its SQL is empty — as `ConditionsModifierListener` does today; with INNER
archive joins this decides whether orphan rows are entries) ∪ every outer-joinable alias resolved through
`column(…, for:)` ∪ the anchors of every `EXISTS` ∪ the aliases referenced by the column definitions these resolve,
by the identity and by base conditions. The closure follows each join's from-alias **and** its `requires`
(`TableAliasRegistry::resolveActiveJoins()` follows both today). Neither sort keys (§6) nor the projection add to this
set: an alias that only a projected definition references is joined as LEFT, even if declared INNER, in Entries and
not at all in Count — showing a column never changes which rows are entries (user's decision, 2026-11-13).

**Backend consumers** move to the catalog: `Contao/DcaContext::getExecutionContext()`/`getTables()`/
`getTargetTable()`, `Contao/Hook/ElementDcaHook::createExecutionContext()`,
`Contao/Callback/FlareFilter/FieldsOptionsCallbacks` (`getFieldOptions_fieldGeneric`, `getOptions_targetAlias`),
`Integration/CodefogTags/FilterCallback/TargetAliasCallback`, `CodefogTagsChoiceFilterElement::buildChoices()`/
`getOptions()`, `Contao/Callback/FlareList/FieldsOptionsCallbacks::getFieldOptions_sortSettings()` (keeps offering
`main` only), `contao/dca/tl_flare_filter.php` (`TableAliasRegistry::ALIAS_MAIN` as the `targetAlias` default),
`Sort/Factory/SortOrderSequenceFactory`. `ListExecutionContext` and `ListExecutionContextFactory` are removed.

**Bundled declarations:** News and Events declare their archive joins (`news_archive`, `events_archive`; INNER,
single-valued) on demand, as today. Codefog's `RegisterTagsTablesListener` moves from `QueryBaseInitializedEvent` to
the catalog event (relation table hidden, both aliases multiplying, on demand; its `CfgTagsJoinsRegistry` side
channel may stay or be replaced by catalog metadata — builder's call).

### 2. The driver query hook

Per query build, the driver receives a collect-only builder and a context (working names
`buildQuery(ListQueryBuilderInterface $builder, ListQueryContext $context)`; *Context* in the project's sense — the
pre-filled handover). The context carries the `ListCompound` — **no purpose and no intent**: the hook runs once, and
the framework derives Entries, Count and the reader's queries from its result, so a driver cannot shape them
differently (Claude's call after review; the legacy count-only `langPid = 0` is exactly that mistake), and it cannot
make the entries depend on the intent (the rule below; Claude's call). It replaces
`buildBaseQuery()`; `BuildQueryContract` is replaced by a contract carrying both hooks (working name kept or renamed,
builder's call), with no-op defaults in `AbstractListDriver`.

**Purpose** (working name `QueryPurpose`, an enum) stays a framework-internal notion on `ListQueryConfig`: `Entries`
(rows, sorted, paged) and `Count` (the number of entries). The reader's queries are `Entries` without paging under a
`ValidationIntent`, in two shapes since PR #54: **by auto-item**, `ValidationLoader::fetchEntryByAutoItem()` adds a
`SimpleEquationFilterElement` on the auto-item field through `withFilter()` and takes, among the rows the database's
collation matches, the one whose value is strictly equal (the follow-up to PR #54 drops the `LIMIT 1` that could
fetch the wrong one of two aliases differing only by case); **by ids**, `fetchEntriesByIds()` (behind `EntryLookup`:
`flare_reader.lookup()`, phase 1 of the entries event) today appends `main.id IN (:flare_lookup_ids)` to the finished
query builder and resets its paging. The id list becomes a framework-owned input of the query (working name
`ListQueryConfig::$ids`), compiled in the assembly as a condition on the *physical* `main.id` with a framework-scoped
parameter, and nothing modifies the Doctrine query builder after the assembly (Claude's call after PR #54). The ids a
lookup gets are record references — stored relation values (`flare_reader.lookup(entry.related_id)`),
`meta.modelRef.id` —, so they address the record, not a redefined `id`; `EntryLookup` indexes its rows by
`meta.modelRef.id` accordingly (Claude's call after the builder check; the same value as `row['id']` unless the
identity is redefined). Reader queries run without paging whatever the intent's paginator says: if the follow-up to
PR #54 leaves `ValidationIntent` a `PaginatedIntentInterface` with a page size of 1, this PR removes that (builder's
call how). `onlyId` has no caller and is dropped (Claude's call). The name must not collide with *Intent*: the intent
says what the list is rendered for, the purpose says what one query returns.

Each block has a framework default, so a driver that contributes nothing gets today's entries:

| Block | Default | Who needs to change it |
|---|---|---|
| Base conditions (AND-ed with the filters; physical columns) | none | multilingual: `main.langPid = 0` |
| Values of the driver's own parameters (joins, column definitions) | — | multilingual: `:lang` |

Projection and identity are no blocks of this hook: they follow from the column list (§3), which is declared
value-free with the catalog.

**No grouping block in this PR** (Claude's call after review): the outer query has one row per entry by construction,
and a driver-chosen `GROUP BY` other than exactly `main.id` breaks the framework's own parts under
`ONLY_FULL_GROUP_BY` (the projected columns, the `main.id` tie-breaker, the count's inner projection) —
MariaDB has no functional-dependency detection at all. Grouping returns with derivations in the next PR, together with
the rules it needs; until then the public driver API does not offer it, so that PR does not inherit a broken contract.

The framework always selects the identity `AS id` and never overrides a redefined identity (today's "re-ensure
`main.id AS id`" silently would). Rows carry the defined values under the plain column names (the legacy behavior),
one select part per key (§3). Removing today's
`GROUP BY main.id` changes no rows (no to-many join remains in the outer query); the order it implied on MySQL 5.7 and
MariaDB is kept by the `main.id` tie-breaker (§6). It also fixes a latent error: `SELECT main.* … GROUP BY main.id`
fails on MariaDB with `ONLY_FULL_GROUP_BY` today (migration notes).

**No Contao model in the query** (user's decision, 2026-11-13). Since PR #54 the rows become entries
(`EntryCollector`), and after its follow-up no entry becomes a model: Contao APIs that need one load it through the
finder of the table's model class by `meta.modelRef`. The query therefore owes nothing to the model registry — no
complete `main` rows, no "projection status" of a row (the entries design had planned one). What it owes the entries
is `meta.modelRef` (§3). Defined columns in `WHERE` (e.g. `IFNULL(…) = :alias`) cannot use indexes — a note for the
port.

**Count** (user's decision): `SELECT COUNT(*) FROM (SELECT 1 <the Entries query's FROM, outer joins and WHERE (incl.
the EXISTS subqueries); no ORDER BY, LIMIT, OFFSET, no sort-only joins>) t`. One rule for every driver; when grouping
and HAVING return with derivations, the inner projection adds exactly the expressions a HAVING references. Entries and
Count always agree. The inner projection never uses the column list, the identity or `main.id` (all would constrain
later grouping under `ONLY_FULL_GROUP_BY`). Parameters bind across the derived table (DBAL converts named parameters to
positional ones, repeated names included). The cost is a derived table per count. The legacy "COUNT(DISTINCT main.id)
and drop GROUP BY" special-casing goes.

**The list defines the entries, the intent their presentation** (user's decision, 2026-11-13). An intent decides how
a list is executed and embedded: the order, the section shown and what the output is for. It never decides what
constitutes the list's entities. The entities are the **whole list** — what a `ValidationIntent` sees, which is
interested in nothing else. An interactive or aggregation intent shows a **section** of it: a page, the visitor's
filter values, and an element's non-limiting defaults (`flare_calendar_current` without `is_limited` drops its date
window under a `ValidationIntent` — its code: "unlimited defaults only shape the initial display"). Whatever defines
the whole list — a base condition, a join, a column definition, a display mode such as the multilingual "only
translated entries" — is part of the list: its config, a filter the driver adds in `buildList()`, or, for one content
element, a modification of that element's list the way engine mods make it (`SimpleEquationMod` → `withFilter()`).
Consequences:

- `buildQuery()` gets no intent (above), and neither do the catalog hooks. Filter elements keep theirs
  (`ElementContext::$intent`, unchanged): they need it to tell the section from the whole list, and they only narrow.
- Entries, Count and the lookup of an interactive list run on the same `ListCompound` (`InteractiveProjector` hands
  one list to all three; the lookup's `ValidationIntent` from `ValidationIntentFactory::createFromIntent()` carries
  only the reader page and the auto-item field). Entries and Count show the same section; the lookup and the reader
  address the whole list (the reader's rules: fixed filter values, no visitor values, no display defaults).
- The result of `buildQuery()` depends on nothing but the list, so one result serves every query on that list (the
  reader's auto-item query runs on a list with one more filter, so it is a build of its own). The entries run under a
  different intent object (`$intent->with(paginatorConfig: …)`) than the count (a fresh `AggregationIntent` from
  `Engine/Intent/Factory/AggregationIntentFactory::createFromIntent()`); the interactive count must nevertheless come
  from the same query build as its entries — the same list *as modified by engine mods*, the same filter values, the
  same section. How (e.g. the interactive projector counting through the same query build) is the builder's call;
  the plan must state it. The expressibility test checks Entries-vs-Count equality.

**What the driver cannot do** (anti-goal): reorder or rewrite the filters' conditions, the `EXISTS` compilation, the
ORDER BY, the joins' activation or the count; those are framework steps.

### 2a. Entry expansion (Events)

An events list does not list rows but **occurrences**: `GroupsEntriesTrait` expands each row into every day of a
multi-day event and every repetition of a recurring event, ordered by date and start time. Paging and counting are
over occurrences; the order is the expansion's (`EventsListDriver::buildDca()` removes `sortSettings` from the
palette). Today this needs SQL paging switched off, a count that really fetches rows, and the Events loaders and
projectors. The user agreed to model it as a driver capability instead (replacing per-purpose deviations, which a
review showed would apply to every query of an Events list — a count returning rows to the generic
`AggregationLoader`, a reader lookup losing its `LIMIT 1`):

- A driver may implement an expansion contract (working name `ExpandsRowsContract::expandRows(array $rows,
  ListQueryContext $context): array`; since PR #54 *entry* names the `Entry` the collector makes from a row, so the
  contract speaks of rows). It returns the occurrence rows in their final order and may annotate them in the row (as
  today's `_flare_event_group`, which `InteractiveEventsView::getEntriesGrouped()` reads from `entry.row`).
- For an expanding driver under an interactive or aggregation intent the framework runs Entries **once per request**
  without SQL paging and with `ORDER BY main.id` only (deterministic input for the expansion's stable sort; a stored,
  hidden `sortSettings` is ignored), expands the rows **once**, and serves both the count (the number of expanded
  entries) and the page (the intent's paginator) from that one result, in PHP. Today the count fetches 5 columns and
  the page all columns — one full fetch replaces both. Count therefore always returns a number, through the generic
  loaders.
- The expansion runs eagerly inside the projector's guarded section (not lazily from Twig), so its failures become
  `list.failed` (§9). The page of expanded rows then goes through `EntryCollector` like any page (lazily,
  `flare_list.entries`): one `Entry` per occurrence, several with the same `meta.modelRef`, one entries event per page.
  `EntryCollection` stays a flat list (PR #54: a nested occurrence iterator for Events comes later, on top of it).
- Under a `ValidationIntent` (reader by auto-item, `EntryLookup` by ids) nothing is expanded: a normal SQL query, one
  entry per record.
- An expanding driver owns its order: no `sortSettings`, and in the next PR no Sortinator, for such lists.
- `EventsListDriver` implements the contract with today's `GroupsEntriesTrait` logic. Removed:
  `EventsInteractiveLoader`, `EventsAggregationLoader`, `EventsAggregationProjector`, `DoNotPaginateModifierListener`,
  `CountEventsModifierListener`, the `ContaoCalendar_*` attributes. `EventsInteractiveProjector` keeps only
  `createView()` for `InteractiveEventsView` (`getEntriesGrouped()`); its `createLoader()` goes (or the view is
  created elsewhere — builder's call).
- Where the generic paging/counting and the per-request reuse of expanded entries live (the engine loaders or a shared
  executor) is the builder's call; it must not be duplicated per driver. Expanding lists fetch all matching rows, as
  today.

### 3. The column list

A column of an alias has a **definition**, an SQL expression; by default it is the column itself. The definitions of
`main` form the **column list**: the projection, whose keys are the keys of `row`. `main.*` goes (user's decision,
2026-11-13); the "column mapping" of the earlier drafts is the same thing seen from the filters. Definitions are
declared with the catalog (value-free; Claude's call — they depend only on the table, and the backend/inspector can
see them).

- **Pre-filled from the schema.** The column list of `main` holds the columns its table has in the database, in the
  table's order — read through DBAL's schema manager —, each defined as itself (`main.title` ⇒ `main.title`) unless
  redefined, followed by the added keys. A driver that defines nothing gets the rows `main.*` returned. The catalog
  stays value-free and runs no query: it stores only the definitions that deviate, for any alias. The schema is read
  when a query needs the projection, once per table and request; any cache beyond the request is the builder's call
  and must not survive a schema change. Other aliases need no schema: their columns are resolved by name as today,
  through a definition where one exists. Sort keys keep their own, DCA-based notion of an alias' columns (§6, D15,
  D34): a database column without a DCA field is a key of `row` but no sort key (Claude's call after the builder
  check).
- **Redefined and extended.** The driver (in `buildTableCatalog()`) and integrations (in the catalog event) may
  redefine single columns and add keys the table does not have: `IFNULL(translation.<f>, main.<f>)` for a
  translatable field, an inner SELECT, any other SQL expression, a fixed value, a column of a joined alias
  (`main.archiveTitle` ⇒ `news_archive.title`). All are the same kind of entry; a key of `row` does not tell where
  its value comes from (user: "volle Flexibilität bei der Fassung von späteren `row`-Inhalten").
- **One definition per column** (Claude's call): on top of the schema default a column is defined at most once. A
  second definition — two integrations, or an integration and the driver — is a programming error like a duplicate
  alias, not "the last one wins": that would be the priority chain through the back door (anti-goal).
- **Applied wherever the list query resolves a column** (user's decision, proposed by Claude): the projection, the
  identity, `FilterConditionsBuilder::column()` (all filters, including the reader's auto-item filter — so the
  multilingual auto-item lookup hits the translated alias *and* keeps untranslated entries, the reason the user chose
  this over retargeting; the value the reader then compares strictly is the one it filtered on) and sort-key
  resolution. A redefinition by an integration therefore also changes what the filters on that column match: what a
  row shows is what the list filters and sorts by.
- **`id`.** The definition of `main.id` is the entry's identity in `row`: the framework always selects it `AS id` and
  never drops it; the column list cannot be built without it.
- **`meta.modelRef` names the physical record** (user's decision, proposed by Claude): the table of `main` and the
  *physical* `main.id`, whatever the identity is defined as — the id Contao's finders, child records
  (`flare_content()`) and cache tags address. Under the multilingual definitions that is the fallback record, while
  `row['id']` may be the translation's (§8) — which is why a lookup by ids restricts on the physical id and indexes
  by the reference (§2). With the default identity both are the same value. When the identity is
  redefined, the framework selects the physical id next to the row and hands it to `EntryCollector`; it is no key of
  `row` (how it travels, also through an expansion, is the builder's call). `EntryCollection::forTable()` stops
  deriving the reference from `row['id']`.
- **What a definition may contain.** Definitions are authored by code, never by editors, and built through the
  builder's helpers, never from stored strings: identifiers quoted, values bound — a fixed value is a parameter, not
  a literal in the SQL. A definition may carry named parameters, declared and filled like join parameters (§4). It
  declares the outer aliases it references (outer-joinable ones only); using the column activates them — as LEFT
  joins if only the projection uses it (§1).
- It does **not** apply to the raw table lookups elements make for choices and options
  (`FieldValueChoiceFilterElement::buildChoices()`, `DcaSelectFieldFilterElement`): under redefined columns their
  keys come from physical values. Out of scope here; a note for the multilingual port.
- Code needs **physical** access next to it: the driver's base condition `main.langPid = 0` and the definitions
  themselves must address the real column. The builder offers an explicit way (e.g. a `physical()` helper or
  `column(…, physical: true)` — builder's call); elements get the defined column by default.

### 4. Join parameters

- A join declared in the catalog may contain named parameters (`… AND translation.language = :lang`) and declares
  their names.
- Parameter names are scoped per join alias by the framework so they cannot collide with the filters' parameters
  (which are prefixed per conditions builder today) or with other joins' (user confirmed); a join whose condition
  moves into several `EXISTS` subqueries binds the same value in each. The exact scheme is the builder's call.
- Whoever declared a join fills its parameters per query: the driver in `buildQuery()`; an integration in
  `ListDriverBuildQueryEvent` (named dispatch `flare.list_driver.{type}.build_query`), dispatched by the framework
  right after `buildQuery()`. Its builder exposes only setting values of the listener's own parameters (user's
  decision: the driver need not know). Filling a parameter of a join one did not declare is not offered (builder's
  call how ownership is enforced — at least by alias).
- A declared parameter of an **active** join (outer or inside an `EXISTS`) that has no value at assembly time is a
  **programming error** (user confirmed): an `\Error` subclass, escaping unwrapped like `UndefinedConfigKeyError`,
  never a `FilterFailure`. Parameters of inactive joins need no value.
- Column definitions (§3) carry named parameters under the same rules: declared with the definition, scoped so they
  cannot collide, filled by whoever declared the definition (the driver in `buildQuery()`, an integration in
  `ListDriverBuildQueryEvent`), and a parameter of a *used* definition without a value is the same programming
  error. A definition that only the projection uses binds its parameters in Entries only (Count selects none).

### 5. Targeting

Confirmed by the user:

- **Every element** has a target: any visible catalog alias — single-valued or multiplying — default `main`. The
  backend shows the `targetAlias` field for every element type (options: the visible aliases of the catalog);
  `AsFilterElement::$isTargeted`, `FilterCompound::$targetingForced`, `withTargetAlias(…, forced)` and the stopgap
  `FilterEffectBuilderFactory::resolveTargetAlias()` are removed; `AddTargetAliasFieldCallback` adds the field for all
  types (or is folded into `ElementDcaHook::buildFrameworkDca()` — builder's call). `FilterCompound::$targetAlias`
  and the stored `tl_flare_filter.targetAlias` stay. Hidden aliases are never offered to editors but are valid for
  code (programmatic filters, `for:`).
- **Opt-out** (§15 of the filter-forms spec): an element may address aliases itself instead of taking an editor-chosen
  target; it then gets no target field, and its **effective target is always `main`** — a stored `targetAlias` is
  ignored and not validated (it survives type changes, `alwaysSave`; otherwise switching a `flare_bool` targeting
  `events_archive` to `flare_calendar_current` would resolve `column('startTime')` to `events_archive.startTime`).
  The mechanism (an attribute flag, a contract) is the builder's call.
- **Eager validation**: in the `ListCompound` build, right after the catalog exists, every filter whose effective
  target is not in the catalog records a `FilterFailure` (the list fails closed; the `FilterInspector` reports the
  filter as *broken*). A check inside `column()` alone would miss filters that add no predicate for a null value
  (e.g. `flare_bool`): the value would be dropped by the retry and the inspector, which checks with the default value,
  would never see it. `for:` aliases that derive from config are declared by the element so they can be checked at
  the same point (mechanism builder's call). The check is **one reusable per-filter validator** that both the build
  and the `FilterInspector` call — the inspector also checks rows outside the list (unpublished rows, later
  duplicates) "with the list as theirs", and an unpublished filter with an invalid target must be reported before it
  is published.
- **Backstop** at query time: every alias a filter actually uses (target, `for:`, a per-predicate override) is checked
  against the catalog. This covers filters added after the build through `ListCompound::withFilter()`
  (`Engine/Loader/ValidationLoader`, `Engine/Mod/SimpleEquationMod`). Such aliases come from code, so an unknown one
  is a **programming error** (`\Error`), not a `FilterException` — a `FilterException` would be retried with null for
  a controlled filter and only logged, i.e. fail open, contradicting D6.
- `FilterConditionsBuilder::column($col)` resolves against the filter's target alias and applies the column's
  definition (§3).
- A predicate that can or must address several aliases **declares that as an option** and uses
  `$builder->column($col, for: $alias)` (user's words). The element supplies the alias in the predicate options
  (from its config or its own knowledge, e.g. Codefog's relation alias).
- Join activation by use: see §1 (outer joins for outer-joinable aliases, per-filter `EXISTS` for to-many ones).
  Conditions that build raw SQL with other alias strings are not tracked — the docs say so.
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
  - in `Contao/Callback/FlareFilter/FieldsOptionsCallbacks`: `getFieldOptions_fieldGeneric()`,
    `getFieldOptions_fieldDatim()` (`elm_fieldStart`/`elm_fieldStop`), `getFieldOptions_fieldPid()` /
    `getFieldOptions_fieldPtable()` (today `DcaHelper::getFieldOptions($dc)` resolves the list's dc), and the
    defaults in `Contao/Callback/FlareFilter/FieldsLoadAndSaveCallbacks` (published/start/stop/pid/ptable);
  - `Filter/Factory/FilterEffectFactory`, which validates only the list's dc as an SQL name: it validates the target
    table too.

  Elements already targeted (`flare_bool`, `flare_equation_simple`, `flare_search_keywords`, the Codefog elements)
  keep working as they do. Opt-out: elements bound to fixed columns of a specific table —
  `CalendarCurrentFilterElement` (event columns) — and `PublishedFilterElement` (Claude's call after review):
  `NewsListDriver::buildList()` and `EventsListDriver::buildList()` add their own `main` published filter only if the
  list has no `PublishedFilterElement` yet, so a published filter an editor targeted at `news_archive` would suppress
  the driver's and expose unpublished entries. Always `main` keeps that check sound.
- **Stale stored targets** (user's decision): rows of formerly untargeted types may hold a non-`main` value that the
  stopgap ignored and that would now apply. A run-once migration in `src/Migration/` resets `targetAlias` to `main`
  on the rows of a **frozen list of the bundled formerly untargeted types** — `flare_archive`,
  `flare_relation_belongsTo`, `flare_dcaSelectField`, `flare_fieldValueChoice`, `flare_published`, `flare_dateRange`,
  `flare_calendar_current` (checked against v0.1 and `support/0.0.34`; the attribute is removed, so the migration must
  not read it). Unknown (userland) types are left alone — they may have been registered with `isTargeted: true` and
  hold deliberate targets; the migration notes name this (Claude's call after review: reset-list, not keep-list).
  It must run exactly once and never silently skip: a target an editor sets after the refactor must never be reset.
  The marker must survive `contao:migrate --schema-only` (which runs the schema diff before any migration) and a later
  schema diff (which drops columns not in the DCA). Required shape (Claude's call after review): the marker is a DCA
  field with SQL default `'0'`, so the schema diff creates it with `'0'` for existing rows and never drops it; every
  save of a filter row in the backend sets it to `'1'` (a save callback), as does the migration for each row it
  processes; the migration handles only `'0'` rows. A row an editor saves before the migration ran is skipped — its
  target field was visible to the editor then. It updates values only, never drops or deletes; SQL for MySQL and
  MariaDB. Between deploying the code and running `contao:migrate`, stale targets apply — the migration notes say so.

### 6. Sorting, prepared for hints and the Sortinator

Abstraction (user: "man könnte die basic Spalten auch als SortHints betrachten"): a sort order is a sequence of
**sort keys** with directions. A key comes from one of two sources:

1. **columns of the catalog** (`alias.column`, resolved through `column()`, i.e. through its definition) — this PR;
2. **sort hints declared by filter elements** — the next PR.

This PR:

- generalizes `Sort/SortOrder` (today a fixed `alias`/`column` pair) so a key is not necessarily a column; the
  `SortOrderSequence` from the intent stays the single input of the ORDER BY step;
- resolves keys in the framework assembly, after the filters' invocation results exist, so effect-provided hints can
  be resolved there later;
- **sorting never changes the rows**: an outer-joinable alias that is active only because of a sort key is joined as a
  LEFT join, even if declared INNER (an INNER archive join used only for sorting would drop orphan rows); Count
  ignores sort keys altogether. So a visitor's sort choice (later) provably cannot change entries or count;
- **deterministic**: `main.id` is always appended as the last ORDER BY key for Entries (stable paging; keeps the order
  MySQL 5.7 and MariaDB derived from today's `GROUP BY main.id` for unsorted lists);
- **fails open**: an unresolvable key (unknown alias/column, a multiplying alias; later an ineffective hint) is
  skipped with a warning, the remaining keys still sort (user's decision: "ordering sollte relativ fail-open sein");
- keeps the stored `sortSettings` format readable; the key format must leave room for a hint namespace without a
  migration (e.g. column keys never contain a character a hint key uses — builder's call).

**Resolvable keys** (Claude's call): the catalog does not store column lists. The columns of an alias are the DCA
fields **with an `sql` definition** of its table (what the `sortSettings` widget offers today via
`DcaHelper::getFieldOptions()`), plus the columns defined for that alias (§3). A column key is
resolvable when its alias is in the catalog and outer-joinable and its column is such a column. A skipped key is
logged at warning level, so an order change (e.g. a stored key on a database column without a DCA field, which sorts
today) is visible. `FilterConditionsBuilder::column()` does **not** check column existence
(unchanged behavior: an unknown column is an SQL error, as today); it checks only the SQL name and the alias.

**Resolvable is not allowed.** What a visitor may choose (the Sortinator, next PR) is a separate, list-configured set
— a whitelist plus the default sort — never "every resolvable key": that would open an ordering side channel on
non-public fields (e.g. `tl_member.password`) and unindexed sorts. This PR only keeps the two apart; it adds no
whitelist yet.

### 7. Prepared for the next PR (outlook, not built)

So the next PR can add sort hints, derivations and the Sortinator without rebuilding this one:

- **Filter invocation result**: `Query/Executor/FilterInvoker::invoke()` returns, per filter, a result holding the
  `FilterEffect`, its conditions per alias and the aliases it used (today it returns only `FilterConditionsBuilder`s
  and drops the effect; the retry in `ListQueryDirector::invokeFilters()` works on those). The assembly reads these
  results, so hints and derivations can reach it later.
- **Alias resolution against a scope**: `column()` and `for:` resolve against the catalog ∪ aliases local to the
  invocation. The per-filter `EXISTS` scoping already introduces filter-local aliases; a derivation alias like
  `{$ovl}.score` (filter-scope spec §8) becomes one more kind of local alias.
- **Join assembly generic over join definitions**: the catalog is one source of joins; derivations will be another. A
  derivation grouped by its key is single-valued and can be joined into the outer query; otherwise it belongs into an
  `EXISTS` like a multiplying path.
- **Projection open to effects**: a column computed by a derivation (e.g. showing the score) will need a way into the
  column list of `main`; it must be able to accept keys from invocation results later. The Count wrapper keeps the
  columns a HAVING references.
- **Explicit subqueries for predicates**: a helper on the conditions builder (e.g. `$builder->exists($alias, fn
  (FilterConditionsBuilder $sub) => …)` and a `notExists()` counterpart) lets a predicate place its own subquery —
  "title matches OR has tag X", "has no tag X", `IS NULL` semantics. Propositions that place their own subquery are
  left alone by the automatic wrapping. Not built here; the per-proposition compilation unit keeps room for it.
- **Hint declaration** (proposed in conversation, answered to the user's question; not yet decided): a capability
  contract in `src/Contract/FilterElement/` (working name `SortHintContract::buildSortHints(SortHintsBuilder,
  ElementContext)`), value-free like `ChoiceSourceContract::buildChoices()`, listing the keys an element *can* offer
  with labels — read by the list configuration (default sort over hints) and the Sortinator's whitelist.
- **Hint realization** in `buildEffect()` (`$builder->sortHint('overlap', …)`), because a hint usually references the
  derivation the element builds for its restriction anyway (filter-scope spec §8, tag-overlap example); a declared but
  unrealized hint is ineffective and skipped. Hint-only derivations follow the sort rule: they never change the rows.
- **`FilterEffect`** today carries only `$formula`; new channels are added as further properties and builder methods
  without changing `buildEffect()`'s signature.
- **Sortinator**: like the paginator, it turns a request parameter into the intent's `SortOrderSequence`, restricted to
  the list's whitelist. The query layer needs nothing beyond the sort step.
- **Stable events**: the fixed assembly order is the place where later events (e.g. "query assembled") can be offered
  without priorities. None are added beyond the two integration events.

### 8. What the multilingual driver would look like (expressibility check)

Not built; it is the yardstick for the interfaces (and for the test fixture driver):

- catalog: alias `translation` on the same table, LEFT JOIN `main.id = translation.<langPid> AND
  translation.<language> = :lang`, single-valued, *mandatory*; column definitions for `id`, the language/pid columns
  and every translatable field (`IFNULL(translation.<f>, main.<f>)`); the other columns keep their schema default;
- `buildQuery()`: `:lang` from the current language (in the fallback language the join finds no row and every
  `IFNULL` falls back to `main` — so the catalog stays static and a `translation` target always exists); base
  condition `main.langPid = 0` (physical); no grouping (none exists);
- the display mode ("localized": only translated entries) restricts on `translation.<langPid>` and comes from the
  list — its config, or for one content element a modification of that element's list —, never from the intent
  (§2); so Entries, Count, the lookup and the reader apply it alike. Whether the driver adds it as a filter in
  `buildList()` or as a base condition is the port's call;
- a Codefog tags filter on such a list works unchanged: it becomes an `EXISTS` and adds no rows;
- reader by auto-item: nothing to do — the auto-item filter's `column('alias')` resolves to the definition, the
  strict comparison in `ValidationLoader` compares the defined value, and `ReaderUrlGenerator` reads the auto-item
  from `entry.row`, the defined value too: the translated alias round-trips;
- lookup by ids (`flare_reader.lookup()`, phase 1 of the entries event): by the physical `main.id`, the fallback
  record's id — the value stored relations and `meta.modelRef` hold (§2);
- `meta.modelRef` is the fallback record (§3) — the id DC_Multilingual's own finders take: a model class using its
  `MultilingualTrait` overrides `buildFindQuery()`, searches among the fallback rows and returns the record
  translated for the current language, so a listener that loads `Model::getClassFromTable($ref->table)::
  findById($ref->id)` gets the translated model natively (read from DC_Multilingual's `master` and Contao 5.3 on
  2026-11-13). Notes for the port: a multilingual list may sit on any table, `tl_news` included, so the bundled
  listeners keyed on `modelRef.table` fire there too; whether `flare_content()` and the cache tags of
  `EntryCacheTagger` (`contao.db.<table>.<id>`) are right with the fallback record alone is the port's to check;
- the Changelanguage "same entry in language X" query is out of scope (it may later run the list's query with another
  `:lang`; nothing here may preclude that).

### 9. Failure semantics

- `buildTableCatalog()` and `ListDriverBuildTableCatalogEvent` listeners run inside the `ListCompound` build: a
  `FlareException` from them fails the list the way an unresolvable list driver does today (frontend: `list.failed`,
  reader: HTTP 500). Backend option callbacks that build the list without a try/catch today (e.g.
  `FlareFilter/FieldsOptionsCallbacks::getFieldOptions_fieldGeneric()` / `getOptions_targetAlias()`) get one, so a
  broken catalog does not crash every filter edit form.
- `buildQuery()`, `ListDriverBuildQueryEvent` listeners and `expandRows()` run per request: a `FlareException` from
  them is a **list-level failure** and must end in `list.failed` (reader: HTTP 500) like a failed filter — not escape
  to `ListViewController`'s error response. Today `InteractiveProjector` catches only `FilterException` around the
  count (`InteractiveProjector.php:103`); either these failures are wrapped as a list-level `FilterException` or
  the projector's guard is widened (builder's call). For expanding drivers the expansion runs inside that guard (§2a).
  The reader and the breadcrumb need no new guard: `ValidationLoader` wraps every `\Exception` into a
  `FlareException`, which `Contao/Reader/ReaderResolver` stores as the resolution's failure (reader: 5xx; breadcrumb:
  items unchanged). From a lookup (`flare_reader.lookup()`, a phase-1 loader) the `FlareException` reaches its
  caller, as PR #54 defines for lookups.
- Programming errors — duplicate aliases, a second definition of a column, a mandatory multiplying alias, an unbound
  parameter of an active join or of a used column definition, a column definition or driver block referencing an
  unknown or to-many alias, an unknown alias used by a filter at query time
  (backstop, §5) — are `\Error`s and escape unwrapped (the
  request fails), consistent with `UndefinedConfigKeyError`. Since they come from code, they are acceptable in the
  backend too.
- `abort()` keeps meaning "legitimately matches nothing".

### 10. Removals and moves

Removed: `Event/ModifyListQueryStructEvent`, `Event/QueryBaseInitializedEvent`, `EventListener/QueryStructModifier/*`,
`Integration/ContaoCalendar/EventListener/{DoNotPaginate,CountEvents}ModifierListener`,
`Integration/ContaoCalendar/Loader/*`, `Integration/ContaoCalendar/Projector/EventsAggregationProjector` (and
`EventsInteractiveProjector`'s `createLoader()`, §2a), `Query/ListExecutionContext`,
`Query/Factory/ListExecutionContextFactory`, the post-build `andWhere()` and paging reset in
`ValidationLoader::fetchEntriesByIds()` (replaced by the id input, §2), the `main.*` select, the `ModelRef` derived
from `row['id']` in `EntryCollection::forTable()` (§3), `ListQueryConfig::$attributes`, `$isCounting`, `$onlyId`
(replaced by `$purpose`), `Contract/ListDriver/BuildQueryContract` in its current form,
`AsFilterElement::$isTargeted` (and its pass-through in
`DependencyInjection/Compiler/RegisterFilterElementsPass`), `FilterCompound::$targetingForced` (and
the `$targetingForced` parameter of `Filter/Factory/FilterCompoundFactory`, its part of `FilterCompound::fingerprint()`),
the targeting stopgap; tests follow (incl. `tests/Registry/FilterElementRegistryTest.php`,
`tests/Contao/Inspection/BuildsFilterInspectorTrait.php`). `FilterCompound::fingerprint()` uses the *effective*
target (so an ignored stored target of an opt-out element does not change `ListCompound::hash()`);
`ListCompound::withFilter()`/`withFilters()`/`withFailure()` carry the catalog, and `hash()` covers it if it can
differ between lists of equal config (builder's call). Whether `SqlQueryStruct`, `SqlJoinStruct`,
`TableAliasRegistry`, `QueryBuilderFactory`, `FilterQueryBuilderFactory` survive as internals, are renamed or are
replaced is the builder's call (the nomenclature spec only said they keep their names *there*).

Documentation: `AGENTS.md` (the Intent line of the vocabulary — it decides order and section, never the entries —,
architecture flow, the "query events … left as they are until the query architecture is reworked" sentence, Notable
subsystems — incl. `EntryMeta` (`modelRef` = the physical record), `EntryCollection::forTable()`, `ValidationLoader`
and `EntryLookup` —, Query safety, Event system); the docs worktree (`docs/`, branch `docs/main`, committed
separately): `docs/docs/spec/query-struct.md`, `spec/filtering.md`, `spec/projection.md`,
`dev/contracts/build-query-contract.md`, `dev/events.md`, `dev/list-types/index.md`, `dev/entries.md` and
`templating.mdx` (`entry.meta.modelRef`, the keys of `entry.row`), `removed-in-v0.2.md`, `migrating-from-v0.1.md` —
describing the step from v0.1 to the final v0.2. The migration notes cover: the accepted behavior changes of goal 11,
the stale-target reset (and the window before `contao:migrate`), and a replacement for each former use of the removed
events by userland code — conditions → a programmatic filter via `ListDriverBuildListEvent`; joins → the catalog
event; projection → column definitions (driver or catalog event); order → a driver (subclass); grouping → no longer
possible until derivations.

## Decisions

| # | Decision | By |
|---|---|---|
| D1 | Driver provides building blocks, the framework assembles in a fixed order (no priority chain) | user |
| D2 | Integrations contribute by event: aliases and joins, since 2026-11-13 also column definitions (D37); no conditions or ordering | user |
| D3 | A join's declarer fills its parameters when the query is built; integrations through a build-time event, without the driver knowing | user |
| D4 | Unfilled parameter of an active join = programming error; parameter names scoped per join alias | user (proposed by Claude) |
| D5 | Every element targetable (any catalog alias, default `main`), `column()` applies it, `column($col, for: $alias)` for multi-alias predicates declared as an option | user |
| D6 | Opt-out for elements that address aliases themselves; unknown alias = broken filter (fail closed, inspector); used aliases activate joins | user (proposed by Claude) |
| D7 | A column may stand for an SQL expression wherever the list query resolves it (agreed as the driver's "column mapping"; since 2026-11-13 the column list, D37) | user |
| D8 | DC_Multilingual only made possible, ported later | user |
| D9 | Sort hints and Sortinator prepared here, built in the next PR; columns are one source of sort keys; unresolvable keys skipped (fail-open); Sortinator restricted to list-allowed keys | user |
| D10 | Hint declaration via a value-free contract, realization in `buildEffect()` | Claude's proposal, answered to the user's question; final in the next PR |
| D11 | Catalog built once into the `ListCompound`; column definitions declared with the catalog | Claude |
| D12 | `buildQuery()` once per query build, no purpose and no intent in its context; Entries and Count derived; purposes `Entries`/`Count` internal; reader queries are `Entries` without paging under `ValidationIntent`, by auto-item and by ids (D35); `onlyId` dropped | Claude (after review; no intent: follows D40) |
| D13 | `sortSettings` widget stays `main`-only | Claude |
| D14 | Branch `v0.2-query` | Claude |
| D15 | Sort-key columns of an alias = DCA fields of its table + defined columns, not stored in the catalog; sort keys checked against them (single-valued aliases only), filter columns not; resolvable ≠ allowed | Claude (after review) |
| D16 | Count wraps the Entries query (`SELECT COUNT(*) FROM (SELECT 1 …) t`) | user (inner projection: Claude after review) |
| D17 | Bundled elements that read the list's table follow their target's table; only fixed-column elements opt out | user |
| D18 | Run-once migration resets stale `targetAlias` of formerly untargeted types to `main`, guarded by a marker (D31) | user (marker: Claude after review) |
| D19 | Events modelled as an expanding driver (occurrences paged and counted generically in PHP, fetched and expanded once per request); Events loaders/projectors and the per-purpose deviations go | user (once per request: Claude after review) |
| D20 | The date-window bug of the expansion is a follow-up idea, not fixed here | user |
| D21 | Join cardinality in the catalog; one outer join activation set for both purposes | Claude (after review) |
| D22 | Target alias validated eagerly at list build (`FilterFailure`), plus a query-time backstop | Claude (after review) |
| D23 | Parent relation for Archive/BelongsTo: list config for `main`, element config or target-table inference otherwise | Claude (after review) |
| D24 | `FilterInvoker` returns invocation results (effect, conditions, used aliases) | Claude (after review) |
| D25 | To-many aliases never in the outer query; per filter one correlated `EXISTS` per connected component, whole propositions, no renaming, anchors activated, joins below the root keep their declared type; existential semantics (`IS NULL` on to-many accepted change) | user (details: Claude after review; join types: user after 4th review) |
| D26 | Sort-only joins become LEFT; Count ignores sort keys; `main.id` tie-breaker | Claude (after review) |
| D27 | Opt-out elements always target `main`, stored value ignored | Claude (after review) |
| D28 | New-hook failures are list-level failures (`list.failed`; reader and breadcrumb through `ReaderResolver`'s failure); the query owes nothing to Contao models — no projection status | Claude (after review; models: user, 2026-11-13) |
| D29 | Effective cardinality: outer-joinable only if single-valued all the way to `main` | Claude (after 3rd review) |
| D30 | No grouping block in the public driver API in this PR | Claude (after 3rd review) |
| D31 | Migration resets a frozen list of bundled formerly untargeted types; marker is a DCA field default `'0'`, set to `'1'` on every backend save and by the migration | Claude (after 3rd review) |
| D32 | `PublishedFilterElement` opts out (always `main`) | Claude (after 3rd review) |
| D33 | One per-filter alias validator shared by build and inspector; query-time backstop is an `\Error` | Claude (after 3rd review) |
| D34 | Resolvable sort columns need an `sql` definition; skipped keys logged as warnings | Claude (after 3rd review) |
| D35 | A lookup by ids is a framework-owned query input, compiled as a condition on the physical `main.id`, without paging, and indexed by `meta.modelRef.id`; nothing modifies the query builder after the assembly | Claude (after PR #54 and the builder check) |
| D36 | The expansion contract speaks of rows (`expandRows()`); occurrences become entries through `EntryCollector`, the collection stays flat | Claude (after PR #54; flat collection: user in the entries design) |
| D37 | An explicit column list replaces `main.*`: pre-filled from the schema, single definitions redefined or added by the driver and by integrations with any SQL; a definition applies to projection, identity, filters and sorting | user (2026-11-13; applies everywhere: proposed by Claude) |
| D38 | One definition per column on top of the schema default; a second one is a programming error | Claude |
| D39 | Joins used only by the projection become LEFT and are absent from Count | user (proposed by Claude) |
| D40 | The list defines its entries (the whole list, what a `ValidationIntent` sees); the intent decides only order, section and embedding. `buildQuery()` and the catalog hooks get no intent; filter elements keep theirs to tell the section from the whole list | user (2026-11-13; no intent in the context, elements unchanged: Claude) |
| D41 | `meta.modelRef` names the physical `main` record, independent of the identity | user (proposed by Claude) |
| D42 | The follow-up to PR #54 (no entry becomes a model, auto-item lookup without `LIMIT 1`) is its own PR before this one | user |

## Left to the builder

Final names (in the user's naming style), the exact builder interfaces and their methods, the internal structs, the
parameter-scoping scheme, the opt-out mechanism, the physical-column API, how column definitions and fixed values are
written, where the schema's columns are read and memoized, how the physical id reaches `meta.modelRef`, the sort-key
format, how ownership of parameters is enforced, how config-derived `for:` aliases are declared, where expanded
entries are paged, counted and reused, how the interactive count shares the entries' query build (the key must be
stated in the plan: list as modified by engine mods, filter values), how new-hook failures reach `list.failed`, the
order of commits. Anything marked "builder's call" above.

## Testing

- **Before the refactor**: capture today's generated SQL and parameters for a matrix of the bundled lists (generic
  DC, News with and without an archive filter, Events with a current-events filter, a Codefog tags filter, sorted and
  unsorted, Entries and Count) as reference snapshots, and, where SQLite can express it, today's ids, counts and order
  through the in-memory SQLite pipeline of PR #54 (`tests/Engine/EnginePipelineTrait.php`) as executable tests that
  stay green afterwards (except the accepted changes of goal 11). They document the old shape; the new SQL may differ.
- Unit tests through public API for: catalog declaration (driver + event, duplicates, cardinality, mandatory
  multiplying rejected, hidden/mandatory), the column list (schema pre-fill in the table's order, a redefined column,
  an added key, a fixed value bound as a parameter, a second definition → `\Error`, `id` never dropped, one
  definition seen by projection, filter and sort key) and physical access, `meta.modelRef` from the physical id under
  a redefined identity, parameter scoping of joins and definitions (incl. inside
  `EXISTS` and the derived count table) and the unbound-parameter `\Error`, the `EXISTS` compilation (one per filter
  and connected component, whole propositions, two tag filters ANDed, relation-only conditions keep dangling relation
  rows, declared join types kept below the root (a proposition over the relation and an optional descendant keeps
  relation rows without that descendant), two to-many branches in one proposition, anchors activated, `IS NULL` on a
  to-many alias, path joins and
  correlation, empty conditions → no `EXISTS`), the marker surviving `--schema-only` and later schema diffs, targeting (`column()`,
  `for:`, eager validation → `FilterFailure`, query-time backstop for `withFilter()`-added filters, opt-out ignores a
  stored target, outer join activation incl. empty-SQL propositions, from-alias and `requires` closure, identical
  activation for Entries and Count), table-bound elements offering and computing choices from their target's table
  (incl. the parent relation for non-`main` targets), the stale-target migration (rows with marker `'0'` processed
  and marked; runs once; leaves later edits and rows saved in the backend alone; formerly targeted types untouched),
  Count wrapping, projection-only and sort-only joins as LEFT (and absent from Count), the `main.id` tie-breaker,
  the lookup by ids (restricted on the physical `main.id`, indexed by `meta.modelRef.id`, no paging), entry
  expansion (fetched and expanded once, paged and counted over expanded rows, no expansion under `ValidationIntent`,
  failure → `list.failed`), sort-key resolution and fail-open skipping (incl. multiplying aliases), identity never
  overridden.
- Generated SQL for the bundled drivers: generic DC (no `GROUP BY`), News (archive join only when used), Events
  (expansion query without paging, `ORDER BY main.id`), a Codefog tags filter (an `EXISTS` over relation and tags
  table, no outer join).
- **Expressibility proof**: a test fixture driver (in `tests/Fixture/`) that does what §8 describes — mandatory
  parameterized single-valued join, redefined columns, physical base condition, redefined identity, a display mode
  taken from the list — plus a fixture integration that adds a parameterized join and a column definition and fills
  their parameters through the build event, plus a tags-like multiplying path. It must not need anything outside the
  public API. It asserts that Entries and Count are built from the same driver result and agree, that the lookup and
  the reader find exactly the whole list, that a lookup by ids finds an entry by its fallback id and the reader by
  its translated alias (round trip through `ReaderUrlGenerator`), and that `meta.modelRef` names the fallback record.
- **The SQLite pipeline follows**: `EnginePipelineTrait` registers the five `QueryStructModifier` listeners by hand and
  counts queries through a `ModifyListQueryStructEvent` listener; both go. It builds the new assembly instead and
  counts executed statements on the connection (e.g. a DBAL middleware); `EntryLookupTest`, `ReaderResolverTest` and
  `BreadcrumbHookTest` assert query counts with it. The behavior checks of this PR run on it, too: the column list
  from the schema, the `EXISTS` compilation (two tag filters ANDed), Count = Entries, projection-only and sort-only
  LEFT joins, the tie-breaker, the lookup by ids under a redefined identity.
- **Manual acceptance** (CI has no MySQL or MariaDB; SQLite cannot check `ONLY_FULL_GROUP_BY`, collations or
  MariaDB's behavior): run the reference matrix against MySQL 8 and MariaDB with sample data, with
  `ONLY_FULL_GROUP_BY` on, and compare ids, counts and order before/after (expected differences: goal 11); check the
  explicit column list against a table with many columns and array parameters inside `EXISTS` and the derived count
  table. A small scripted local matrix for this is worth adding to the plan (builder's call).
- Existing tests follow (`ListQueryDirectorTest`, `FilterInvokerTest`, `FilterEffectFactoryTest`, `FormulaBuilderTest`,
  `CodefogTagsChoiceFilterElementTest`, `FieldsOptionsCallbacksTest`, `ElementDcaHookTest`,
  `InteractiveProjectorTest`, `FilterCompoundTest`, and from PR #54 `ValidationLoaderTest`, `EntryLookupTest`,
  `InteractiveViewTest`, `ReaderResolverTest`, `BreadcrumbHookTest`, `ReaderControllerTest`); `make test` and
  `make phpstan` green; Mago runs in CI only.
