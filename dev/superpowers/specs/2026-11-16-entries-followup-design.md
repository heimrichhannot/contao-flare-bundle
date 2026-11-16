# Design: entries follow-up — no entry becomes a model, the reader finds the strictly equal auto-item

Status: written 2026-11-16 by the design sub-agent from the maintainer's decisions of 2026-11-13, checked against the
code of `v0.2` (after PR #54), Contao 5.3 (`vendor/`), Contao 4.13.56, the news/calendar bundles and DC_Multilingual
(`main`) from GitHub. Reviewed the same day by two independent reviewers (Opus 5.5, GPT-6 Astra); their factual
corrections are worked in; the maintainer decided the five points they left open (M7–M11) and confirmed the calls
C1–C10. Awaiting the user's final read. Goes in **its own PR on `v0.2`**, branch `v0.2-entries-followup`,
before the query refactor (`v0.2-query`, D42 there). Predecessor: `2026-11-11-engine-entries-design.md`.

## Intent

PR #54 made the engine views hand out entries and kept one door back to Contao models:
`Contao\ListEntry\EntryModelFactory` (the registry's instance, else a model built from the entry's row). Two things
use that door: the News/Events schema.org listeners and `EntryRelationResolver` (`flare_related()`). A model built
from a list row is wrong as soon as the row is not the record — the multilingual port maps rows (translations), and
DC_Multilingual translates only through its model class's `buildFindQuery()`. So the door closes: Contao APIs that need
a model load it through the finder of the table's model class, relations resolve through the related table's finders.

Separately, `ValidationLoader::fetchEntryByAutoItem()` reads one row (`LIMIT 1`) and then compares it strictly. When
two stored values differ only by what the collation ignores (`Foo`/`foo`, `a`/`a `), the database may return the
other one and a valid auto-item answers 404.

## Goals

- `EntryModelFactory` is gone, with its test, its service and its documentation; no entry becomes a model anywhere.
- The schema.org listeners get the same model a Contao module would get for the record, multilingual classes included.
- `flare_related(entry, column)` resolves a relation the way Contao resolves a lazy DCA relation
  (`Model::getRelated()`), from the entry's row and without a model of the entry; §3 lists where it differs on purpose.
- Every stored auto-item value finds its own row, whatever the collation folds together.

## Non-goals

- Anything of the query refactor: the column list, `meta.modelRef` taken from a physical id that differs from
  `row['id']`, removing `PaginatedIntentInterface` from `ValidationIntent`, framework-owned lookup ids (D35).
- Exclusive filters, the DC_Multilingual port, the Changelanguage queries.
- Batching `flare_related()` across a list (N+1 stays, documented in `templating.mdx`).

## Anti-goals

- No replacement service or helper for "entry → model" (userland calls the finder itself; see §4).
- No event for relations (the maintainer considered one and chose the native resolution).
- No membership check in `flare_related()` or the listeners — unchanged from #54.

## Constraints

- Contao 4.13 and 5.x, PHP 8.2+, Symfony 5.4–7; `declare(strict_types=1)`, Mago, PHPStan level 5.
- Programming errors are `\Error`s (`MissingModelRefError`, `UndefinedRelationError`); the bundle catches `\Exception`.
- Parameterized SQL only (AGENTS.md, query safety).
- v0.2 is unreleased: breaking changes without BC shims. No tag contains #54, so `EntryModelFactory` never shipped in
  a release; only `dev-v0.2` users lose it.

## Approaches

**Finder for the listeners.** (a) `findById($ref->id)` on the class from `Model::getClassFromTable($ref->table)` —
chosen (maintainer; checked below). (b) `findByPk()` — equivalent for tables whose pk is `id`, but `ModelRef` names the
`id` column, not the pk. (c) A query that bypasses the registry, e.g. `findOneBy(["$t.id=?"], …)` — rejected: Contao's
`find()` merges the fetched row into an already registered instance and returns that one anyway (`mergeRow()`), so it
mutates a shared model instead of avoiding it.

**Relations.** (a) Native resolution in the resolver: relation from `DcaExtractor`, value from `entry.row`, finders of
the related class — chosen (maintainer). (b) Load the entry's record (`findById`) and call its `getRelated()` —
rejected: an extra query per entry, needs a model class of the list's table, and takes the value from the database
record instead of the entry's row. (c) An event — rejected by the maintainer.

**Fetching all auto-item matches.** (a) Reset paging on the finished query builder, exactly as `fetchEntriesByIds()`
does — chosen (Claude's call, C7). (b) Drop `PaginatedIntentInterface` from `ValidationIntent` — the query refactor's
job (its §2 says so); doing it here touches `PageModifierListener`, `PageParamMod` and the intent API for no gain.
(c) A `ListQueryConfig` attribute honoured by `PageModifierListener` — a third mechanism the refactor would delete.

## Design

### 1. `EntryModelFactory` removed

Delete `src/Contao/ListEntry/EntryModelFactory.php` and `tests/Contao/ListEntry/EntryModelFactoryTest.php`. Its users
and what they become:

| User | Becomes |
|---|---|
| `NewsReaderSchemaOrgListener`, `EventsSchemaOrgListener` | finder of the table's class (§2) |
| `EntryRelationResolver` (+ `EntryRelationResolverTest`) | native resolution (§3) |
| `tests/Twig/FlareRuntimeTest.php:47` | constructs `EntryRelationResolver` without the factory |
| `tests/DependencyInjection/ServicesConfigTest.php:113` | entry removed |
| `AGENTS.md:39-41`, `AGENTS.md:181-187` | rewritten (§6) |
| `config/services.yaml`, Twig, other `src/` | nothing (registered by the `Contao\` glob; no template uses it) |

`MissingModelRefError` stays (thrown by `flare_content()` and `flare_related()`), its message and docblock lose
"cannot become a model": an entry without `modelRef` names no record of a DC table (C9).

### 2. Schema.org listeners load the model through the table's finder

```php
$ref = $event->entry->meta->modelRef;
if ($ref?->table !== NewsModel::getTable()) { return; }
$class = Model::getClassFromTable($ref->table);   // via ContaoFramework adapters (C3)
$model = $class::findById($ref->id);
if (!$model instanceof NewsModel) { return; }      // null, or a class that does not extend NewsModel
$event->data = News::getSchemaOrgData($model);
```

Same for `EventsSchemaOrgListener` with `CalendarEventsModel`/`Events`. What the code says about this call:

- **Exists on both majors.** Contao 5.3 declares `Model::findById()`. Contao 4.13 has no such method: `findById` goes
  through `__callStatic()` to `findBy('id', $id)`, which returns a model (not a collection) because `id` is the pk,
  and passes no `limit`. Both end in `static::find()`, which calls `static::buildFindQuery()` — the method
  `MultilingualTrait` overrides. `News::getSchemaOrgData(NewsModel)` and `Events::getSchemaOrgData
  (CalendarEventsModel)` have the same signature on 4.13 and 5.3.
- **Registry shortcut.** `find()` looks into `Model\Registry` *before* building the query whenever it is to return a
  single model and the column is the pk or a unique field (both majors); 5.x's `findById()` additionally does so
  itself when the options are empty. A record already registered for `table` + `id` in this request is returned as it
  is, and `buildFindQuery()` does not run — whichever class or language registered it. After a query, an instance
  registered meanwhile gets the fetched row merged in. Accepted (C1): it is what every Contao module gets for the
  record; #54's registry problem was membership, and membership is already settled here — the entry came from the
  list's query.
- **Lifecycle of the loaded model.** The finder's own applies, no longer the factory's. A core model is registered
  when it is loaded (`EntryModelFactory` never registered the models it built) and can be saved. DC_Multilingual's
  `MultilingualTrait::createModelFromDbResult()` detaches its models (`preventSaving(false)`): they are not
  registered, cannot be saved, and every call queries again.
- **Cost.** One `SELECT` for a record the registry does not hold (the factory built the model from the row without
  a query): one more query on a reader page. `flare_schema_org()` is the reader's function — one entry per request —
  so nothing needs batching.
- **`getClassFromTable()` is not guarded** (C10). On 5.x it throws a `\RuntimeException` for a table without a
  `TL_MODELS` entry (the factory caught that). Behind the table guard that cannot happen: the bundle that provides
  `NewsModel` registers `tl_news`. A broken registration fails the request instead of silently yielding no data.
- **Multilingual.** A model class for `tl_news` must extend `NewsModel` (with `MultilingualTrait`) to get schema.org
  data; a class extending DC_Multilingual's `Multilingual` fails the `instanceof` guard and yields none (C2).
- **The rule** (maintainer, D41 of the query design): `meta.modelRef` names the physical record of the list's main
  table. Here it is documentation only — `EntryCollection::forTable()` takes `row['id']`, which is `main.id` as long as
  the query selects `main.*`. The `ModelRef` docblock and the docs state it.

### 3. `flare_related()` resolves natively

`EntryRelationResolver::resolve(Entry, string $column): Model|Collection|null` keeps its signature, its per-entry and
per-column memo (`WeakMap` of `ArrayObject`, `null` memoized too), `MissingModelRefError` and the missing membership
check. It loses `EntryModelFactory` and follows the lazy path of `Model::getRelated()` (identical on 4.13 and 5.3
except where noted), in this order — so an empty value returns `null` before the related class or the type is looked
at, as in Contao:

1. `$relation = DcaExtractor::getInstance($ref->table)->getRelations()[$column]` — the same source Contao's model
   constructor uses (`table`, `field` defaulting to `id`, `type`, `load`, `delimiter` from `eval.csv`); none →
   `UndefinedRelationError` (unchanged).
2. `$value = $entry->row[$column] ?? null`; `empty($value)` → `null` without a query (Contao: "no reference yet",
   so `0`, `'0'`, `''`, `null`). A column missing from the row counts as no value (C6).
3. The related class: `getClassFromTable($relation['table'])`, accepted only if it is an existing `Model` subclass
   (5.x throws for an unregistered table; 4.13 returns a guessed class name that may not exist). Otherwise
   `UndefinedRelationError($ref->table, $column, 'relates to a table without a model class')` (C5). The entry's own
   table no longer needs a model class — relations of such tables now resolve.
4. `hasOne`/`belongsTo`: `$class::findOneBy($relation['field'], $value)` → model or `null`; Contao's registry
   shortcut applies as in `getRelated()` (the field is usually the pk).
5. `hasMany`/`belongsToMany`: values by `StringUtil::trimsplit($delimiter, $value)` if a delimiter is set, else
   `StringUtil::deserialize($value, true)`; then `array_values()` — a deserialized array may carry string keys, and
   5.x unpacks the finder's values into `Statement::execute()`. No values → `null` without a query.
   `tl_files`.`uuid` → `FilesModel::findMultipleByUuids($values)`, on `FilesModel` literally as Contao 5 does
   (4.13 calls the class registered for `tl_files`; M11). Otherwise, with
   `$field = "<table>." . Database::quoteIdentifier(<field>)` (Contao quotes the field too):
   `$class::findBy(["$field IN(?,…)"], [...$values, implode(',', $values)], ['order' => "FIND_IN_SET($field, ?)"])`
   → collection in the stored order, or `null`. Contao inlines the values
   into `IN('…')` unescaped and builds the order with `Database::getInstance()->findInSet()` (an instance method,
   which quotes the joined values into the SQL); the resolver binds both (C4) — the same rows and order for any value
   without a quote or backslash.
6. Any other relation type → `UndefinedRelationError` (Contao would hit an undefined array key) (C5).

Lazy vs. eager (`relation.load`) makes no difference: eager only joins the related columns when Contao itself loads
the parent record; entries never come from Contao's `find()`, so every relation loads on first use, as with the
non-registered models of #54. `UndefinedRelationError`'s docblock moves from "a table without a model class" to "a
related table without a model class".

Where the result differs from `getRelated()` on the record's model, on purpose:

- **No parent model is consulted.** #54's factory could hand out the registry's instance of the record; its relation
  value may differ from `entry.row`, it may carry an already resolved or eagerly loaded relation (`arrRelated`), and
  its class may override `getRelated()` or the property access. None of that is read any more: the row decides.
- **A serialized empty array** (`a:0:{}`) is not `empty()` for Contao, which then queries `IN('')`; the resolver
  returns `null` without a query.
- **Errors** for an unknown type or a related table without a model class (C5) instead of PHP's.
- Unchanged from Contao, to be documented rather than fixed: `FIND_IN_SET` cannot order values that contain a comma,
  and a value listed twice yields its record once.

### 4. What userland loses

`EntryModelFactory::create()` (an injectable service and public PHP API, `dev-v0.2` only). Replacement: the finder of
the table's class by the reference — `Model::getClassFromTable($ref->table)::findById($ref->id)`, or the concrete
class (`NewsModel::findById($ref->id)`). Differences a caller must know:

- the model is the database record (columns of the row that are not the table's, or mapped values, are not in it);
- the finder's lifecycle applies (§2): a core model is registered and saveable — the models the factory built were
  neither —, a DC_Multilingual model is detached and cannot be saved;
- there is no memo per entry: the registry answers for a registered record, otherwise each call is a query;
- `null` for a deleted record, unless the registry still holds an instance of it;
- the caller checks `meta->modelRef` itself instead of catching `MissingModelRefError`.

### 5. Reader auto-item lookup without `LIMIT 1`

Today: `fetchEntryByAutoItem()` adds a `SimpleEquationFilterElement` (`field = :autoItem`) via `withFilter()`, the
director assembles the query, `PageModifierListener` (priority 430) turns `ValidationIntent`'s page size 1 into
`LIMIT 1`, the loader fetches one row and compares `(string) $row[$field] !== $autoItem`. `ValidationIntent` is not
`SortableIntentInterface`, so the bundle's order listener adds no `ORDER BY` (a driver or a query listener may):
which of several collation matches comes first is up to the database.

New: after `createQueryBuilder()`, `setFirstResult(0)->setMaxResults(null)` (as `fetchEntriesByIds()`), then read the
result row by row and return the first row whose value is strictly equal — the comparison stays today's, on the
value cast to string (an integer id column must keep matching); no strict match → `null`. The result is `free()`d
in a `finally`. Reading row by row only spares building a PHP array of all matches: the drivers buffer the whole
result anyway (mysqli's `store_result()`, PDO's buffered queries), and a non-unique auto-item field can have many
matches. No cap — a cap would bring the bug back.

Two rows strictly equal (a non-unique auto-item field) share one URL; so that the same one answers every time, the
loader appends `ORDER BY main.id` after the paging reset (`addOrderBy()`, behind any order a driver or query listener
set — the tie-breaker of the query design): the record with the lowest id wins (M7). Today's winner was never
specified, so this may be another row than before.
`ValidationIntent` keeps its page size 1 (the query refactor removes it); the reset also overrides any limit another
`ModifyListQueryStructEvent` listener sets, as for the lookup by ids. The interface docblock of
`fetchEntryByAutoItem()` says "without LIMIT/OFFSET".

### 6. AGENTS.md

Line 39–41: "a model is made only at the Contao boundary" → no entry becomes a model; Contao APIs that need one load
it by `meta.modelRef` through the table's model class. The `src/Contao/ListEntry/` bullet: drop `EntryModelFactory`,
describe `EntryRelationResolver` as native (`DcaExtractor` relation, value from `entry.row`, related table's finders,
Contao's lazy relation resolution without a model of the entry, `UndefinedRelationError` for a column without
relation or a related table without model class), say that the schema.org listeners load their model by
`meta.modelRef` (one query), add the `modelRef` rule (physical record of the list's main table). The
reader bullet (line 193): the loader picks the strictly equal one among all collation matches.

## Decisions

Maintainer's (2026-11-13), settled:

- **M1** No entry becomes a Contao model; `EntryModelFactory` is removed without replacement, with test and docs.
- **M2** The schema.org listeners load via `Model::getClassFromTable($ref->table)::findById($ref->id)`, because the
  multilingual port relies on the table's own model class translating (`MultilingualTrait::buildFindQuery()`, finders
  by the fallback id), and a multilingual list may sit on `tl_news`, where the bundled listeners fire.
- **M3** `meta.modelRef` names the physical record of the list's main table.
- **M4** `flare_related()` resolves natively through the related table's finders; relation from the DCA, value from
  `entry.row`; no event. `EntryRelationResolverTest` checks equality with `Model::getRelated()`.
- **M5** The auto-item lookup drops `LIMIT 1` and picks the strictly equal row.
- **M6** Its own PR on `v0.2`, before the query refactor; nothing of the refactor, exclusive filters or the port.

Maintainer's (2026-11-16, after the two reviews), settled:

- **M7** Several strictly equal auto-item rows: `ORDER BY main.id`, the lowest id wins.
- **M8** Registry and multilingual: Contao's behaviour is accepted, with a note for the port ("Notes for later PRs").
- **M9** `contao/news-bundle` and `contao/calendar-bundle` go into `require-dev`, so the listener tests use the real
  `NewsModel`/`CalendarEventsModel`.
- **M10** The relation tests assert the resolver's finder call per case and compare with `getRelated()` against a
  database for the to-one and the to-many path.
- **M11** A to-many relation to `tl_files`.`uuid` calls `FilesModel::findMultipleByUuids()` literally: current core
  behaviour; 4.13 will not be supported much longer, and a later Contao-5-only version moves to the virtual
  filesystem anyway.

Claude's calls, all confirmed by the maintainer on 2026-11-16:

- **C1** `findById`, and the registry shortcut is accepted, not bypassed (bypassing still returns the registered
  instance, mutated by `mergeRow()`).
- **C2** The listeners keep their `instanceof NewsModel`/`CalendarEventsModel` guard; `null` → no data.
- **C3** The listeners reach `Model`, the found class and `News`/`Events` through `ContaoFramework` adapters, so the
  guard paths are unit-testable.
- **C4** To-many relations bind their values as parameters — in `IN(…)` and in the `FIND_IN_SET` order — instead of
  Contao's inlined literals; the field is quoted as Contao quotes it.
- **C5** `UndefinedRelationError` for a related table without a model class and for an unknown relation type; the
  entry's own table needs no model class any more.
- **C6** A relation column missing from `entry.row` is "no value" → `null`.
- **C7** Fetching all matches by resetting paging on the built query builder, like `fetchEntriesByIds()`.
- **C8** Rows are read one by one and reading stops at the first strictly equal one (which one that is: M7).
- **C9** `MissingModelRefError`'s message no longer speaks of becoming a model.
- **C10** The listeners do not catch `getClassFromTable()`'s exception (§2).

## Left to the builder

- The exact adapter wiring and helper split in the listeners and the resolver (a private `modelClass()` as the
  factory had it is fine).
- Wording of the reworded messages and docblocks.

## Open questions

None.

## Testing

- `EntryRelationResolverTest`, finder call per case: `hasOne`, `belongsTo`, to-one into the list's own table, a
  target field other than `id`, `hasMany` and `belongsToMany` (serialized, in stored order), to-many with delimiter,
  serialized values with string keys, values with a quote and a backslash, a target field that needs quoting,
  `tl_files`.`uuid` (string and binary; `FilesModel` is called, whatever class is registered), an empty value,
  `a:0:{}` and a missing column (no query); memo per entry and column (one finder call); `MissingModelRefError`;
  `UndefinedRelationError` for a column without relation, a related table without model class, an unknown type; an
  empty value returns `null` before either; a relation on a table without model class resolves.
- The same test, against a SQLite fixture (M10): to-one and to-many return what `getRelated()` returns for the
  record's model, the registry cleared between the two.
- Listener tests (new, C3, with the real model classes of M9): other table → untouched; finder returns `null` or a
  non-`NewsModel` → untouched; found → `findById` called on the class `getClassFromTable()` names with
  `modelRef.id`, data set. Same for events.
- `ValidationLoaderTest`: two rows `Foo` and `foo` in a `COLLATE NOCASE` column, and two rows `a` and `a ` in a
  `COLLATE RTRIM` column — each auto-item finds its own row in either insertion order; two rows with the same value
  — the lower id wins in either insertion order (M7); the existing cases (`'0'`, empty, non-canonical ids, other
  case) stay green.
- `composer.json`: `contao/news-bundle` and `contao/calendar-bundle` in `require-dev`, with the constraint of the
  core (`^4.13 || ^5.0`); the compatibility workflow has to keep resolving for every PHP × Contao pair.
- `FlareRuntimeTest`, `ServicesConfigTest` adjusted; `make test`, `make phpstan`, Mago in CI.

## Notes for later PRs

- **Query refactor.** C6 makes a relation column that is missing from the row "no value". Once the column list
  decides what a row holds, a column left out of it silently yields `null`, where the bundle otherwise throws for an
  undefined key (`UndefinedEntryKeyError`). The refactor's design has to say which it is.
- **Multilingual port.** The registry is keyed by table and id, whatever the class or language (M8): an untranslated
  instance registered earlier in the request wins over the translating finder, and a model the listener loads is
  what later callers of the finder get, where the class registers it. The port needs a regression test for both
  request orders.
- **Multilingual port, schema.org.** DC_Multilingual ships no news model, only the base class `Multilingual extends
  Contao\Model` and `MultilingualTrait`; its README's example is `class NewsModel extends Multilingual` for
  `tl_news` — no `Contao\NewsModel`. `News::getSchemaOrgData(NewsModel)` and `Events::getSchemaOrgData
  (CalendarEventsModel)` are typed on both majors, so the guard of C2 has to stay, and a project that follows the
  README gets no data from the bundled listeners. Only a class that extends `Contao\NewsModel` and uses the trait
  does. The port needs its own schema.org listener for README-style models.

## Documentation (`docs/`, branch `docs/main`, committed separately)

Paths below are inside the worktree, so `docs/docs/…` from the repository root.

- `docs/dev/entries.md` §8 "Contao APIs That Need a Model": rewritten — the finder of the table's class by
  `meta.modelRef`, the listener example without `EntryModelFactory`, the registry note replaced by C1's, the
  finder's lifecycle and its one query; §6 (`flare_related()`): native resolution from the entry's
  row, with the differences from `getRelated()` listed in §3; line 88: the `modelRef` rule.
- `docs/removed-in-v0.2.md` line 76: the replacement for `getModels()`/`getModel($id)` is the table's finder, not
  `EntryModelFactory`; add `EntryModelFactory` as "v0.2 development only" like the removed building events.
- `docs/reference/twig.md` (`flare_related` row) and `docs/templating.mdx` (lines 156, 599, 607–613): "a table without
  a model class" → "a related table without a model class"; `entry.meta.modelRef` is the physical record of the
  list's main table.
