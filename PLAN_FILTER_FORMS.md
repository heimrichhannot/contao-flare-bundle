# Implementation Plan — Decoupling Filter Forms from Filter Elements

Implements `SPEC_FILTER_FORMS.md` (repo root). §n references point at that spec.

**Decisions taken for this plan:** atomic cut (no legacy adapter) · break public API freely (Beta/WIP,
no deprecation layer) · fix the choice round-trip defect in **both** `FieldValueChoiceFilterElement`
and `DcaSelectFieldFilterElement`.

**Last reconciled with the tree:** `v0.2` at `3a49404` (#48), work branch `v0.2-refactor-filter-api`.
Class and method names below are the post-#48 names (see "Naming as of #48"). Line numbers are
given only where they were re-verified against that tree; everything else is referenced by method.

---

## Status

| plan phase | spec step | state |
|---|---|---|
| **Phase 0** — nomenclature refactor | §12 step 0 | ✅ done, then partly renamed again by #48 |
| **Phase 1** — contracts, value objects, registry | part of §12 step 1 | ✅ done — with two divergences, see Phase 1 |
| **Phase 2** — the cut | rest of §12 step 1 + step 3 | 🟡 **in progress, tree red** — see the Phase 2 checklist |
| Phase 3 — DCA composition | §12 step 2 | not started |
| Phase 4 — drop the legacy columns | §12 step 4 | not started |
| Phase 5 — fold out `FilterData` | §12 step 5 | not started |
| **Phase 6** — the data migration | §12 step 6 | not started — deliberately last |

**Red at `3a49404`:** `make phpstan` reports 53 errors, almost all in the element bodies (Phase 2,
item C). `make test` dies on load with a fatal error: the test double `PassFilterFormBase::decode()`
in `tests/DependencyInjection/Compiler/RegisterFilterFormsPassTest.php` still returns `?object`
against the interface's `?ValueInterface`. Nothing after that fatal error has been observed.

**This plan deliberately diverges from §12's sequencing.** The spec pilots step 1 on
`BooleanFilterElement` and migrates the remaining ten in step 3. That is not achievable without a
legacy adapter: step 1 changes the element contract and removes `buildForm()`, which every element
must follow in the same commit. Given the atomic-cut decision, Phase 2 migrates all elements at once
and there is no pilot.

### Naming as of #48

#48 went beyond Phase 0's rename table. Besides re-renaming the form aggregate, it introduced a
**query-side layer** the spec did not foresee (§2.3 there): an element no longer writes into a
query builder; it emits *propositions* — a predicate service plus resolved options — which
`ListQueryDirector` executes. Cumulative map, oldest name first:

| before the refactor | Phase 0 | now (#48) |
|---|---|---|
| `Form\Factory\FilterFormFactory` | `Filter\Factory\FilterSetFactory` | `Form\Factory\FormHarnessFactory` |
| — | `Filter\FilterSet` | `Form\FormHarness` |
| — | `Filter\FilterMount` | `Form\FilterMount` |
| `Event\FilterFormBuildEvent` | `Event\FilterSetBuildEvent` | `Event\FormHarnessBuildEvent` |
| `NamedDispatch\FilterFormListener` (aggregate) | `…\FilterSetListener` | `…\FormHarnessListener` |
| `flare.form.{name}.build` | `flare.filter_set.{name}.build` | `flare.form.{name}.build` (back to the original) |
| `Event\FilterElementFormBuiltEvent` | `Event\FilterFormBuiltEvent` | unchanged |
| `flare.filter_element.{type}.form_built` | `flare.filter_form.{type}.built` | unchanged |
| `Filter\Type\*FilterType`, `FilterTypeInterface`, `AbstractFilterType` | — | `Filter\Predicate\*Predicate`, `PredicateInterface`, `AbstractPredicate` |
| `Registry\FilterTypeRegistry` | — | `Registry\FilterPredicateRegistry` (tag `flare.filter_predicate`, keyed by class) |
| `Filter\FilterBuilder`, `FilterBuilderInterface` | — | `Filter\FormulaBuilder`, `FormulaBuilderInterface` |
| `Filter\FilterCall` | — | `Filter\Proposition`; new `Filter\Formula` (list of propositions) |
| `Query\FilterQueryBuilder`, `FilterQuery` | — | `Query\FilterConditionsBuilder`, `FilterConditions` |
| `Query\Executor\FilterExecutor` | — | **removed**; execution lives in `Query\Executor\ListQueryDirector` |
| `FilterElementInterface::buildFilter(FilterBuilderInterface, FilterContext, FilterData)` | — | `::buildContext(FilterContextBuilder, ?ValueInterface)` |
| — | — | new `Filter\FilterContextBuilder`, `Factory\FilterContextBuilderFactory`, `Factory\FormulaBuilderFactory` |

Further #48 changes that later phases depend on:

- **`FilterContext`** is now `(list, filter, formula, engineContext)`. It lost `$config`; the
  canonical config is on `$context->filter->config`. `FilterContextFactory::create()` builds the
  formula eagerly by calling `buildContext($builder, null)`, with a `todo` where the runtime value
  should be passed.
- **`ListSpec::$filters` is a list.** `withFilter(Filter)` takes no key, `withoutFilter()` matches by
  instance, alias or element class. `FilterFactory::create()` generates `_.{type}_{random8}` when no
  alias is given; `createFromFilterModel()` takes `FilterModel::getFilterFormName()` with no fallback.
  `FormHarnessFactory` keys mounts by **alias** (`$mounts[$alias]`), while the docblocks of
  `FormHarness` and `FilterMount` still say "keyed by the `ListSpec::$filters` key".
- **Config is resolved at creation.** `FilterOptionsResolver::resolve(FilterElementInterface, array)`
  runs inside `FilterFactory`, so a `Filter` always carries canonical config.
- `Util\CreatesFilterExceptionTrait` wraps predicate failures with the filter's source.
- `FilterElementBuildingEvent` / `FilterElementBuiltEvent` still exist (now carrying a
  `FormulaBuilderInterface` and `FilterData`) and `FilterElementListener` still aliases them to
  `flare.filter_element.{type}.building|built` — but **nothing dispatches them**; their dispatcher
  was `FilterExecutor`.

### Spec gaps and conflicts this plan resolves

1. ✅ **`AsFilterForm` needs a storable identifier.** Done: `name:` defaulting to
   `TypeNameFactory::createFilterFormType($class)` (suffixes `Controller`, `FilterForm`, `Form`);
   `""` and `"default"` are rejected. FQCNs are deliberately not stored — a class rename would orphan
   every row.
2. **`FilterOptionsResolver` keys the schema cache on `$element::class` alone** — still open. It
   passes that string as the `$key` to `SchemaResolver::resolve()`, which memoises one
   `OptionsResolver` per key. Once a form also contributes `configureOptions()`, the first-seen
   form's schema is reused for every other form on the same element. The key must become composite.
   `FilterTransformerResolver:35` already uses `sprintf('%s@%s', $type, $element::class)` and needs
   only the form appended. `SchemaResolver` is `shared: false` (`config/services.yaml`), so a
   *separate* resolver service for form config could not collide with the element one — but a
   composite key is still required for two forms on one element.
3. **§3.1 and this plan disagree about `flare.choices_builder`** — still open. §3.1 justifies
   passing the mount to `decode()` because the form "needs access to the attributes it set in
   `buildForm()` (e.g. `flare.choices_builder`)" — while the attribute is write-only today (set in
   the `buildForm()` of `ArchiveFilterElement`, `DcaSelectFieldFilterElement`,
   `FieldValueChoiceFilterElement` and `CodefogTagsChoiceFilterElement`; zero `getAttribute()`
   anywhere). Under §3.4's two-method port `decode()` reads `$form->getViewData()` and hands the
   keys to `$element->valueFromChoiceKeys()`, so it needs no `ChoicesBuilder`. **Resolve in Phase 2:
   the four `setAttribute()` calls move onto the choice form or disappear, and §3.1's parenthetical
   is updated either way.** Do not delete them silently: `FormHarnessFactory` copies collector
   attributes onto the mount, so the attribute reaches `form.vars` and is semi-public surface for
   custom form themes. Removal needs a line in the release notes.

---

## Phase 0 — Nomenclature (done)

Landed on `feat/filter-types` and was replayed onto `v0.2` as #47. Its own target names were then
superseded by #48 — see the map above. The test files were moved but not renamed with #48:
`tests/Filter/FilterSetFactoryTest.php` and `tests/EventListener/NamedDispatch/FilterSetListenerTest.php`
test `FormHarnessFactory` and `FormHarnessListener` (Phase 2, item N).

Phase 0 also answered §14.1 — see Phase 1.

---

## Phase 1 — Contracts, value objects, registry (done)

What exists:

- **Contracts** — `Filter\Form\FilterFormInterface` (`getValueClass()`, `buildForm()`, `decode()`),
  `Filter\Form\AbstractFilterForm` (`@template T of ValueInterface`),
  `Contract\FilterElement\ChoiceSourceContract` (`buildChoices()`, `valueFromChoiceKeys()`).
- **Value objects** — `Filter\Value\{BoolValue, ChoiceValue, KeywordsValue, DateRangeValue,
  ParentRefValue}`, all `final readonly` with a `tryFrom()` that returns `null` for "carries no query
  information", behind the marker `Filter\Value\ValueInterface`. Each has a unit test;
  `tests/Filter/Value/ValueObjectContainmentTest.php` enforces §9 by reflection.
- **Registration** — `#[AsFilterForm(name:, requires:)]`, `RegisterFilterFormsPass` (registered before
  `RegisterFilterElementsPass`; public alias `flare.filter_form.{name}`; `requires` entries checked to
  be existing interfaces), `Registry\FilterFormRegistry` (metadata as plain arrays, services behind a
  `container.service_locator`; `findNames()` / `findDefaultName()` implement the §3.4 election).
  `AsFilterElement` gained `?string $value`, replayed by `RegisterFilterElementsPass`.
- **Schema** — `tl_flare_filter.formVariant` (+ en/de labels). `intrinsic` stays until Phase 4.
- **Hashing** — §14.1's flattening decision is taken: `Util\Fingerprint::flatten()` exists and is
  tested. It is **not wired yet** — `Filter::fingerprint()` still flattens through
  `FilterData::toArray()` (Phase 2, item E).

**Divergences from what this phase originally specified — both unresolved:**

1. **The value class moved off the attribute.** #48 removed `value:` and `default:` from
   `AsFilterForm`; a form now declares its value class through the instance method
   `FilterFormInterface::getValueClass()`. But `RegisterFilterFormsPass` still reads
   `$attributes['value']` into the registry metadata, and `FilterFormRegistry` still reads `'value'`
   and `'default'`. The election therefore sees `value: null` for every form. A compiler pass cannot
   call an instance method without instantiating the service, so **how the pass learns the value
   class is a design question** (attribute again, a static method or class constant, or reflection
   on `AbstractFilterForm`'s `@template`). The §10 row 3 check ("every element value class has ≥1
   satisfiable form") is also not implemented yet.
2. **Only one element declares its value class.** `CalendarCurrentFilterElement` carries
   `value: DateRangeValue::class`; the other elements carry no `value:` yet.

---

## Phase 2 — The cut (in progress)

Still one atomic cut in intent; the tree is red until it is complete.

### Done in #48

- Element contract changed to `buildContext(FilterContextBuilder $builder, ?ValueInterface $value)`
  on `FilterElementInterface` and `AbstractFilterElement` (the signatures, not the bodies).
- Query-side layer: predicates (`src/Filter/Predicate/`, one per former filter type plus
  `IntegerIdChoicePredicate`), `FormulaBuilder` → `Formula` of `Proposition`s, executed by
  `ListQueryDirector::buildFilterConditionsBuilders()`. `FilterExecutor` deleted.
- `FilterOptionsResolver` moved into `FilterFactory`; `FilterContext` reduced to
  `(list, filter, formula, engineContext)`.
- `AbstractFilterForm`, `getValueClass()`, `decode(FormInterface $form, …): ?ValueInterface`.
- Value objects reworked to `tryFrom()` factories.

### Open — checklist

Ordered so that each item can be built on the previous one. Items marked **[design]** are open
design questions that must be settled first; they are listed in spec §14.

- **A. Registration consistency [design].** Decide how `RegisterFilterFormsPass` learns a form's
  value class (Phase 1, divergence 1) and whether `default` returns. Then make pass, registry and
  `FilterFormRegistryTest` agree, and add the §10 row 3 compile-time check.
- **B. Form-time vs. query-time context [design].** `FormHarnessFactory` calls
  `FilterContextFactory::create()`, which runs `buildContext($builder, null)` — so building a form
  already builds a formula that is thrown away, every element must tolerate a `null` value at form
  time, and an element that `abort()`s (e.g. on missing config) aborts the form build too.
  The candidate split: `FilterContext` = `(list, filter, engineContext)` for forms and
  `buildChoices()`; the `Formula` is built only in `ListQueryDirector`, from the decoded value.
- **C. Element bodies.** In all 11 value-bearing and value-less elements plus
  `CodefogTagsChoiceFilterElement`: `$context->config` → `$builder->filter->config` (or whatever B
  decides), `$builder->add()` → `addPredicate()`, `FilterData` accessors → the value object. An
  element **cannot narrow** `?ValueInterface` to its own class in the signature (PHP parameter
  contravariance makes that a fatal error at class load), so add a checked accessor on
  `AbstractFilterElement` — the formerly planned `expectValue()`, `@template`-annotated so PHPStan
  narrows call sites. It does not exist yet. Declare `#[AsFilterElement(value: …)]` on every
  element: `null` for `Published`, `BelongsToRelation`, `SimpleEquation`, `CodefogTagsSearch`.
- **D. Forms.** None exist yet. 11 `buildForm()` bodies consolidate into 5 forms:

  | form | value | serves | notes |
  |---|---|---|---|
  | `ChoiceFilterForm` | `ChoiceValue` | FieldValueChoice, DcaSelectField, CodefogTagsChoice | `requires: [ChoiceSourceContract::class]`; `decode()` is the two-line §3.4 body |
  | `CheckboxFilterForm` | `BoolValue` | Boolean | replaces `boolMode`/`boolBinaryChoices` |
  | `ChoiceBoolFilterForm` | `BoolValue` | Boolean | the variant those two columns used to select |
  | `DateRangeFilterForm` | `DateRangeValue` | DateRange, CalendarCurrent | absorbs the byte-identical `validateRange()` bodies of both elements and the only deferred-listener use |
  | `KeywordsFilterForm` | `KeywordsValue` | SearchKeywords | |

  `Archive` uses `ParentRefValue` (§7.4) and is also served by `ChoiceFilterForm` via the port. Its
  `buildForm()` throws `FilterException` at four sites, and the form must keep that failure mode or
  the archive filter degrades silently. It installs `applyFormOptions()` and
  `single(ChoiceType::class, …)` *before* the choices are added, which works only because
  `buildCallbackChoiceLoader()` defers — preserve the deferral. Then remove `buildForm()` from
  `FilterElementInterface`, `AbstractFilterElement` and every element.
- **E. `Filter` DTO.** `$data` → `?ValueInterface $value`, plus `?FilterFormInterface $form`. All
  `with*()` methods enumerate every ctor arg explicitly and silently drop new properties — update
  them. `withAlias()` has no call site anywhere; delete it. `fingerprint()` adds the form class
  beside `'element'` (or `ListSpec::hash()` collides across form variants) and flattens the value
  through `Util\Fingerprint::flatten()`. Fix the stale `"_.{source}"` remark in the ctor docblock.
- **F. `FilterFactory`.** `createFromFilterModel()` resolves the form from `$filterModel->formVariant`
  via the registry (read off the raw model, not canonical config — the form's transformers have not
  run yet), runs its transformers and merges element + form config through a `ConfigBuilder`
  following `ListSpecFactory`. `create()` gains `?FilterFormInterface $form = null`; the programmatic
  call sites (`ValidationLoader` ×2, `SimpleEquationMod`, `NewsListDriver`, `EventsListDriver`,
  `ChangelanguageListener` ×2) keep working by passing nothing. Composite keys in
  `FilterOptionsResolver` and `FilterTransformerResolver` per gap 2.
- **G. Value flow.** Today submitted values reach no filter: `InteractiveProjector::collectFilterData()`
  still builds `FilterData`, and `FilterContextFactory` passes `null`.
  - `FormHarness::decode()`: for each mount, `$mount->filter->form->decode($this->getChild($alias),
    $mount->context)`, keeping non-null results, keyed by alias. Fix the "keyed by the `ListSpec`
    key" docblocks to say alias.
  - `InteractiveProjector::project()` calls it; `collectFilterData()` is deleted. This is the §7.2
    fix: `decode()` reads view data, so no reverse mapping and no label collisions.
  - The value map travels through `ListQueryConfig::$filterValues` and the loader/context configs to
    `ListQueryDirector`, which passes `$values[$filter->alias] ?? $filter->value` into
    `buildContext()`. The `array<…, FilterData>` annotations change accordingly (they are docblocks
    only; Phase 5 deletes the class).
- **H. Element events [design].** Re-dispatch `FilterElementBuildingEvent` / `FilterElementBuiltEvent`
  around `buildContext()` with the new payload (`FilterContextBuilder`, `?ValueInterface`), or delete
  both and their aliases in `FilterElementListener`. Either way, docs and release notes.
- **I. Intrinsic plumbing.** Delete `IntrinsicContract`,
  `FieldsLoadAndSaveCallbacks::onLoadField_intrinsic()` / `::onSaveField_intrinsic()` with their
  `#[AsCallback]` attributes, `AbstractFilterElement::isOnlyIntrinsic()` and its three overrides
  (`SimpleEquation`, `Published`, `BelongsToRelation`).
- **J. `flare.choices_builder`** — settle per gap 3.
- **K. `ChoiceSourceContract::valueFromChoiceKeys()`** returns `?object`; tighten to `?ValueInterface`.
- **L. Choice defects** (per the decision): `createChoices()` → `buildChoices()`;
  `FieldValueChoiceFilterElement::normalizeRuntimeValue()` and
  `DcaSelectFieldFilterElement::normalizeSubmittedValue()` → `valueFromChoiceKeys()`; fix
  `ChoicesBuilder::add()` so `choice` carries identity rather than the display string, which removes
  the `array_search($choice, $this->choices, true)` reverse lookup in `buildChoiceValueCallback()`.
  `tests/Form/ChoicesBuilderTest.php` exists and pins today's behaviour —
  `testDuplicateDisplayStringsCollapseOntoTheFirstAlias` must flip. Fixing `add()` also lets
  `FieldValueChoiceFilterElement`'s three `createChoices()` calls collapse to one.
  `ChoicesBuilder` has dead API (`setLabelForModel`, `setLabelForClass`, `setEmptyOptionValue`,
  `getChoice`, `hasEmptyOption`, `buildFormOptions`, `addGroup`, `removeGroup`, the write-only
  `$groups`/`$choiceGroupMap`); leave it unless the port makes deletion obvious. Everything there is
  `/** @api */`, so deletions need a release note.
- **M. `DateRangeValue` vs. §9 [design].** It holds `?DateTimeImmutable` (timezone-normalized), which
  §9's containment rule excludes and `ValueObjectContainmentTest` checks for. Either store timestamps
  or amend §9 to allow timezone-normalized `DateTimeImmutable` (and the test with it).
- **N. Tests.** Fix the `decode()` return type in the `RegisterFilterFormsPassTest` doubles and
  `tests/List/StubFilterElement.php`. `tests/Query/Executor/FilterExecutorTest.php` and
  `tests/Filter/FilterSetFactoryTest.php` import classes that no longer exist
  (`Query\Executor\FilterExecutor`, `Filter\Factory\FormHarnessFactory`) — rewrite as
  `ListQueryDirectorTest` and `FormHarnessFactoryTest`. Rename `FilterSetListenerTest` →
  `FormHarnessListenerTest` and `FilterBuilderTest` → `FormulaBuilderTest`. `InteractiveProjectorTest`'s
  `collectFilterData` coverage moves to a `FormHarness::decode()` test.

**Do not carry over:** `CalendarCurrentFilterElement`'s positional `$data->all()` fallback
(exactly-two-element) — the value object replaces it, and `DateRangeFilterElement` never had an
equivalent, so the two siblings disagree on the runtime contract today. `DateRangeFilterElement`'s
`from_enabled`/`to_enabled` are dead knobs — defined, never written by `transformFilterModel()`,
absent from every palette and language file, so both branches are permanently true — either surface
them as real form config or drop them and add the fields unconditionally.

---

## Phase 3 — DCA composition

- `DcaBuilder` — third palette segment with fixed order `__prefix__ + element + form + __suffix__`.
  `Str::mergePalettes()` is already variadic and drops empty segments, so `apply()` (`:76`, merge at
  `:89`) needs one extra argument. Add `scope('form')` returning a sub-builder whose `palette()`
  lands in the form segment; `field()` (`:66`) stays shared, because forms occasionally tweak an
  element field's `eval`. `DcaBuilder` is `final` and deliberately **not** a service (excluded
  wholesale in `config/services.yaml`) — it is `new`ed once, at `ElementDcaListener:108` — and
  `ElementDcaEvent` holds the concrete class, so widening it is a local change.
  While here: `Str::mergePalettes(?string ...$palettes)` (`src/Util/Str.php:95`) maps a non-nullable
  `string $palette` closure over its args *before* filtering, under `strict_types=1`, while
  `DcaBuilder::apply()` passes a `?string` and `ArchiveFilterElement::buildDca()` passes `null`
  explicitly. Adding a third nullable segment makes this worth settling — filter first, or widen
  the closure param.
- `ElementDcaListener` (type read at `:79`, builder at `:108`) — resolve the form service alongside
  `$filterModel->type` and call its `buildDca()` into the form scope.
- `FieldsOptionsCallbacks` — new `#[AsCallback(self::TABLE_NAME, 'fields.formVariant.options')]`:
  registry entries for the element's declared value class, filtered by `requires`. Follow the
  existing `fields.type.options` callback (`:43`).
- Split the 5 `intrinsic`-branching palettes (`buildDca()` of `BooleanFilterElement`,
  `SearchKeywordsFilterElement`, `DcaSelectFieldFilterElement`, `ArchiveFilterElement`,
  `CalendarCurrentFilterElement`) into element + form segments per the §6.2 legend convention.
  `FieldValueChoiceFilterElement` and `CodefogTagsChoiceFilterElement` currently show form-only
  fields even when intrinsic — this fixes that inconsistency too.
- §14.2: confirm whether any first-wave form contributes a subpalette. `__selector__` is static in
  `contao/dca/tl_flare_filter.php:655`, and **`DcaBuilder::selector()` does not exist** — neither
  producer nor consumer, and `apply()` never touches `palettes.__selector__` or `subpalettes`. So
  this is an API to *write*, not one to find a consumer for — **defer it if no first-wave form needs
  it.**
- Form-owned field labels stay in `contao/languages/{en,de}/tl_flare_filter.php` (§13.3).
- Add a `DcaBuilder` unit test asserting segment order (`$GLOBALS['TL_DCA']` set up by hand; no
  kernel). There is no `tests/DataContainer/` today, and `mergePalettes()` is untested.

---

## Phase 4 — Drop the legacy columns

Everything here is deletion, and it must land after Phase 3 so the backend never references a
dropped column.

> **Do not apply the schema diff on a database holding real rows until Phase 6 has landed.** This
> phase removes `intrinsic` from the DCA, and Phase 6's backfill reads it. Contao runs migrations
> before applying the diff, so a single `contao:migrate` with both phases in place backfills and
> then drops, in that order — which is correct. Updating the schema in between destroys the source
> column and with it the mapping, unrecoverably.

- `contao/dca/tl_flare_filter.php`: remove the `intrinsic` (`:139`), `boolMode` (`:630`) and
  `boolBinaryChoices` fields, the `boolMode_binary` subpalette (`:678`), the `boolMode` entry in
  `__selector__` (`:657`), and `intrinsic` from
  `'__prefix__' => '{title_legend},title,type,intrinsic'` (`:659`).
  Columns come only from these `sql` keys (no schema listener), so Contao's diff drops them.
- **`AddTargetAliasFieldCallback:50` anchors on the dropped column** —
  `PaletteManipulator::create()->addField('targetAlias', 'intrinsic')` must re-anchor on
  `formVariant`, or `targetAlias` silently stops being inserted for every targeted element.
- `FilterModel::isFilterIntrinsic()` (`:34`) and the `'intrinsic'` arm of `__get()` (`:114`); the
  `@property bool $intrinsic` in `DocumentsFilterModelTrait:15`.
- `ListCallbacks:34` — `$row['intrinsic']` becomes `($row['formVariant'] ?? '') === ''`. The
  `is_intrinsic` flag (`:53`) in `templates/backend/be_filter_info.html.twig` is purely cosmetic
  (icon + opacity). Keep the `filter.info.intrinsic.yes/no` keys in `translations/flare.{en,de}.yaml`,
  or rename together with the template. (Unrelated but adjacent: that template's `form_alias` /
  `duplicate_filter_aliases` are the `formAlias` *query parameter* column, which §5.1 explicitly
  reserves — do not touch.)
- Remove the `'intrinsic'` key from every element's `configureOptions()` and
  `transformFilterModel()`, and the `unset($preselectOptions['null'])` hack in
  `BooleanFilterElement::buildDca()`.
- `preselect` stays a column, re-owned by the form, semantics narrowed to hydration (§4.2). Because
  the decode path harvests field defaults for unsubmitted forms, preselect-as-data flows through the
  normal path and every `?? $config['preselect']` fallback disappears.

---

## Phase 5 — Fold out `FilterData`

`FilterData` (#46) is partly unwound. Both of its jobs relocate: `hasSingle()`'s
submitted-vs-untouched distinction is answerable from the `FormInterface` (`decode()` receives the
mount), and `toArray()`'s hashing role moves to `Util\Fingerprint::flatten()` over the value object
(Phase 2, item E).

Mechanically small — the `array<…, FilterData>` maps are typed in **docblocks only**, so the
transport hops (`AggregationContext`, `ValidationContext`, `InteractiveLoaderConfig`,
`AggregationLoaderConfig`, `ListQueryConfig`, the two Calendar loaders, …) need annotation updates,
not signature changes — and Phase 2 item G already retypes them. Delete the class and
`tests/Filter/FilterDataTest.php`.

`FilterFormBuilder`'s `single()`-vs-compound decision is unaffected — that is genuinely a form
concern and stays.

---

## Phase 6 — The data migration

Deliberately last: everything above must be in place before the mapping can be written correctly.

`src/Migration/BackfillFilterFormVariantMigration.php`, modelled on
`RenameContentListColumnMigration` (`SHOW TABLES` / `SHOW COLUMNS` guards, raw `executeStatement`,
`createResult`; not `final`, not a `readonly` class, `private readonly Connection`). It needs no
registration — `src/Migration` is not excluded from the PSR-4 service resource, and `autoconfigure`
plus `AbstractMigration` earns Contao's `contao.migration` tag.

**Why it could not be written earlier.** Three reasons, in order of severity:

1. **The `flare_bool` arm was undecidable.** `BooleanFilterElement::buildForm()` always mounts a
   `CheckboxType` and never reads `boolMode`; `ternary` posts a backend error as unsupported. But
   `boolBinaryChoices` *does* change matching via `resolveRuntimeValue()` — under `NULL_TRUE` an
   unchecked box means "no opinion", under `NULL_FALSE`/`TRUE_FALSE` it means `false`. A checkbox
   form owning no element config cannot express both, so which bool rows map to which bool form is
   only answerable once Phase 2's `ChoiceBoolFilterForm` exists. Decide it there, record it here.
2. **The file auto-registers on sight.** Its mere presence means `contao:migrate` runs it, against
   form names that may not exist. It is one-shot — once `shouldRun()` is satisfied it goes quiet, so
   wrong values stay committed and need a *second* corrective migration.
3. **Form names are permanent data.** Hardcode `element type → form name` as frozen literals, not
   `Element\ArchiveFilterElement::TYPE` and not a `FilterFormRegistry` lookup: a migration describes
   historical rows, so binding it to live code would let a later rename retroactively change what
   already-migrated rows meant. This is a deliberate divergence from
   `RenameContentListColumnMigration`, which references `ContentContainer::FIELD_LIST` — there the
   constant *is* the target schema; here the literal *is* historical data.

**The mapping**, pinned from Phase 2's form table. Element types with no form are absent and keep
`''` — they never rendered a widget:

| element `type` (frozen DB value) | form name |
|---|---|
| `flare_archive`, `flare_fieldValueChoice`, `flare_dcaSelectField`, `cfg_tags_choice` | `flare_choice` |
| `flare_dateRange`, `flare_calendar_current` | `flare_date_range` |
| `flare_search_keywords` | `flare_keywords` |
| `flare_bool` | `flare_checkbox` / `flare_choice_bool` per row — see reason 1 |
| `flare_published`, `flare_relation_belongsTo`, `flare_equation_simple`, `cfg_tags_search` | *(absent — stays `''`)* |

The form names in this table are provisional until the forms exist with their final `name:`.

**`shouldRun()` constraints**, each one a trap:

- **Do not key on `formVariant` being absent.** Phase 1 landed the column via the DCA `sql` key, so
  the schema diff has already created it. Key on `intrinsic` being *present* plus a type-restricted
  count of rows still needing work.
- **Restrict that count to the mapped types.** Without it, the four unmapped types' `intrinsic = 0`
  rows keep the count non-zero forever and Contao offers a pending migration that never completes.
- **Return `false` when `intrinsic` is already absent**, so an environment past the Phase 4 drop
  fails closed rather than backfilling from nothing.

**The ordering hazard.** The backfill reads `intrinsic`, which Phase 4 removes from the DCA. Contao
runs migrations before applying the schema diff, so one `contao:migrate` with both phases in place
backfills and *then* drops — correct. But applying a schema update after Phase 4 lands and before
this phase exists destroys the source column and the mapping with it, unrecoverably. So this phase
must land before any environment holding real rows updates its schema.

The programmatic `'intrinsic' => true` sites (`ValidationLoader:50,100`, `SimpleEquationMod:31`,
`EventsListDriver:71`, `ChangelanguageListener:140,152`, `NewsListDriver:58`) are config array
literals, not DB rows. The migration touches only `tl_flare_filter` and cannot see them; Phase 4
removes the key from them.

---

## Files that change most

- `src/Filter/Element/*` (11 elements) and `src/Integration/CodefogTags/FilterElement/*` —
  `buildForm()` out, `buildContext()` bodies rewritten
- new `src/Filter/Form/*` (the five forms)
- `src/Filter/{Filter,FilterContext,FilterContextBuilder}.php`,
  `src/Filter/Factory/{FilterFactory,FilterContextFactory}.php`
- `src/Form/{FormHarness,FilterMount}.php`, `src/Form/Factory/FormHarnessFactory.php`
- `src/Filter/Resolver/{FilterOptionsResolver,FilterTransformerResolver}.php`
- `src/Registry/FilterFormRegistry.php`, `src/DependencyInjection/Compiler/RegisterFilterFormsPass.php`
- `src/Engine/Projector/InteractiveProjector.php`, `src/Query/Executor/ListQueryDirector.php`,
  `src/Query/ListQueryConfig.php`
- `src/DataContainer/Builder/DcaBuilder.php`, `src/EventListener/Contao/ElementDcaListener.php`
- `contao/dca/tl_flare_filter.php`, `src/EventListener/DataContainer/FlareFilter/*`
- `src/Form/ChoicesBuilder.php`
- `src/Migration/BackfillFilterFormVariantMigration.php` (Phase 6 only)

---

## Verification

Per phase, then end-to-end. The `php` binary is unavailable — use the Makefile's Docker targets.

1. `make phpstan` (level 5, `src/` minus `src/Model/` and `src/Integration/Terminal42Languages/`)
   after every phase. There is no baseline file, so nothing hides a new error.
2. `make test` after every phase. Suites to update rather than delete: `tests/Filter/FilterTest.php`
   (fingerprint), `tests/Engine/Projector/InteractiveProjectorTest.php` (its `collectFilterData`
   coverage moves to `FormHarness::decode()`), `tests/Filter/FilterFactoryTest.php`,
   `tests/Filter/FilterOptionsResolverTest.php`, `tests/Filter/FilterTransformerResolverTest.php`,
   `tests/Filter/Element/{ArchiveFilterElementTest,SimpleEquationFilterElementTest}.php` (both
   assert `intrinsic`), `tests/Config/ConfigBuilderTest.php` (asserts `intrinsic`),
   `tests/Registry/FilterFormRegistryTest.php`, `tests/List/StubFilterElement.php` (the shared
   double — anything touching `FilterElementInterface` touches it), plus the renames and rewrites in
   Phase 2 item N.
   Coverage gap: only `Archive` and `SimpleEquation` have element-level tests, and both only assert
   `transformFilterModel()` output. `DateRange`, `CalendarCurrent`, `DcaSelectField`,
   `FieldValueChoice`, `Boolean` and `SearchKeywords` have **none**, so the `validateRange()`
   extraction has no safety net. `ChoicesBuilderTest` exists and covers the choice-defect fix.
3. New tests: `DcaBuilder` segment order, a `ChoiceFilterForm::decode()` case with **two rows sharing
   a display label** (the collision the old reverse lookup produced), `FormHarness::decode()`, and
   the §10 row 3 compile-time check.
4. Container build: `make php bin/console debug:container flare.filter_form.choice` and
   `debug:container --tag=flare.filter_form`. Deliberately mis-declare a `requires` entry once and
   confirm the compiler pass fails the build (§10, row 3). **This repo has no `bin/console`** — it is
   a bundle with no app skeleton, so container checks need a host Contao install.
5. Backend, on a Contao install with existing filter rows. **Phases 1-5 have no migration to run** —
   `formVariant` is uniformly `''` and `intrinsic` stays authoritative until Phase 4; set
   `formVariant` by hand to exercise a form. From **Phase 6** on, run the migration
   (`contao:migrate --dry-run` first), then confirm for a `flare_bool` filter that the `formVariant`
   select lists both bool forms, that switching it swaps the palette segment via `submitOnChange`,
   that the blank option hides the form fields, and that a previously `intrinsic = 1` row came
   through as blank. Check `targetAlias` still appears after the `AddTargetAliasFieldCallback`
   re-anchor.
6. Frontend: a list with an Archive filter in **both** ptable modes (static and dynamic — the
   `PtableInferrer` branch), plus a `FieldValueChoice` filter on a `foreignKey` field with duplicate
   labels. Verify submitted values narrow the result set, that the empty option still means "use the
   full whitelist" (`ChoicesBuilder::EMPTY_CHOICE` passed through verbatim, §3.4), and that an
   unsubmitted form applies its `preselect`.
7. `make semgrep-sec` before finishing — `FilterConditionsBuilder` parameterisation is untouched, but
   the filter path is where it matters. Mago runs in CI only (`mago lint`); there is no make target.

**Docs are a separate worktree.** `docs/` is a git worktree on branch `docs/main`, so every phase
that changes public API needs its own commit there — `docs/docs/dev/events.md`,
`docs/docs/spec/filtering.md`, `docs/docs/dev/filter-elements/index.md`,
`docs/docs/migrating-from-v0.1.md` and `docs/docs/removed-in-v0.2.md` all describe this surface.
`docs/versioned_docs/version-0.1/**` snapshots the shipped 0.1 API and must stay frozen.
