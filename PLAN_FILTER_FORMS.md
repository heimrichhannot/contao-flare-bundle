# Implementation Plan — Decoupling Filter Forms from Filter Elements

Implements `SPEC_FILTER_FORMS.md` (repo root). §n references point at that spec.

**Decisions taken for this plan:** atomic cut (no legacy adapter) · break public API freely (Beta/WIP,
no deprecation layer) · fix the choice round-trip defect in **both** `FieldValueChoiceFilterElement`
and `DcaSelectFieldFilterElement`.

**Last reconciled with the tree:** after Phase 0 landed (`1490eb8`, `06fc013` on `feat/filter-types`;
`9518aa3` on `docs/main`). Line numbers below are from that tree.

---

## Status

| plan phase | spec step | state |
|---|---|---|
| **Phase 0** — nomenclature refactor | §12 step 0 | ✅ **done** — see below |
| **Phase 1** — contracts, value objects, registry | part of §12 step 1 | ✅ **done** — see the Phase 1 plan |
| Phase 2 — the cut | rest of §12 step 1 + step 3 | not started |
| Phase 3 — DCA composition | §12 step 2 | not started |
| Phase 4 — drop the legacy columns | §12 step 4 | not started |
| Phase 5 — fold out `FilterData` | §12 step 5 | not started |
| **Phase 6** — the data migration | §12 step 6 | not started — deliberately last |

**This plan deliberately diverges from §12's sequencing.** The spec pilots step 1 on
`BooleanFilterElement` and migrates the remaining ten in step 3. That is not achievable without a
legacy adapter: step 1 changes `FilterElementInterface::buildFilter()`'s signature and removes
`buildForm()`, which every element must follow in the same commit. Given the atomic-cut decision,
Phase 2 migrates all elements at once and there is no pilot. Everything else maps 1:1.

### Phase 0 (done) — what changed under you

The vocabulary is now the spec's (§2.1/§2.2), so the names below are what the rest of this plan uses:

| old | new |
|---|---|
| `Filter\Factory\FilterFormFactory` | `Filter\Factory\FilterSetFactory` |
| — | `Filter\FilterSet`, `Filter\FilterMount` (new) |
| `Event\FilterFormBuildEvent` | `Event\FilterSetBuildEvent` |
| `Event\FilterElementFormBuiltEvent` | `Event\FilterFormBuiltEvent` |
| `EventListener\NamedDispatch\FilterFormListener` | `…\FilterSetListener` |
| — | `EventListener\NamedDispatch\FilterFormListener` (new) |
| `flare.form.{name}.build` | `flare.filter_set.{name}.build` |
| `flare.filter_element.{type}.form_built` | `flare.filter_form.{type}.built` |
| `tests/Form/FilterFormFactoryTest.php` | `tests/Filter/FilterSetFactoryTest.php` |
| `tests/Form/FilterFormBuilderTest.php` | `tests/Filter/FilterFormBuilderTest.php` |

Three consequences for the phases below:

1. **`FilterSetFactory::create()` returns a `FilterSet`**, not a `FormInterface` — the root form plus
   `array<string|int, FilterMount>` keyed by the `ListSpec::$filters` key, where
   `FilterMount{Filter $filter, string $alias, FilterContext $context}`. Only filters that actually
   mount get an entry. `getMount($key)` resolves the child lazily against the root form and returns
   `null` when it is absent.
2. **`FilterSet` is where the decode loop goes** (§8), not a rewritten
   `InteractiveProjector::collectFilterData()`. The mount map already carries the `Filter` and the
   `FilterContext` each `decode()` call needs, so Phase 2 needs no `FilterContextFactory` in the
   projector. `InteractiveProjector::createFilterSet()` (`:123`) is the hook.
3. **`FilterSet::getMount()`/`getMounts()` have no production consumer until Phase 2.** `mago.toml`
   sets `find-unused-definitions = true`, so a local `mago analyze` reports them; CI only runs
   `mago lint`, so nothing is red.

Phase 0 also answered §14.1 — see the box in Phase 1.

---

## Context

A filter element currently owns both *what rows it matches* and *how the user supplies the value*.
`FilterElementInterface` declares `buildForm()` and `buildFilter()` on the same class, and "this
filter has no form" is expressed by an `intrinsic` boolean that 11 of 12 elements read.

The inventory confirms the three costs §1 predicts, and one more:

- **Presentation leaks into the schema.** `boolMode` + `boolBinaryChoices` are two DB columns
  (`contao/dca/tl_flare_filter.php:616,630`), a `__selector__` entry (`:643`) and a subpalette
  (`:664`) whose only job is picking a rendering. Neither has a language entry in
  `contao/languages/{en,de}/tl_flare_filter.php`, so dropping them is cheap.
- **`buildForm()`/`buildFilter()` share an undeclared schema.** `FieldValueChoiceFilterElement`
  builds a `ChoicesBuilder` in `buildForm()` (`:70`) and **rebuilds it** in `normalizeRuntimeValue()`
  (`:207`) to map submitted choices back to ids — `createChoices()` runs three times per request
  (`:69`, `:137`, `:207`), each time hitting `loadDataContainer()` and the DB.
  `DcaSelectFieldFilterElement::normalizeSubmittedValue()` (`:175-200`) hand-rolls the identical
  `array_search(..., true)` reverse mapping — spec §7 names only the former.
- **`intrinsic` conflates three concepts** across 12 `configureOptions()` declarations, 11
  `transformFilterModel()` writes, 8 verbatim `if ($config['intrinsic']) return;` guards at the top
  of `buildForm()`, 6 copies of the `$config['intrinsic'] ? preselect : normalize(submitted)` ternary
  in `buildFilter()`, 5 palette branches, a load/save callback pair and an `isOnlyIntrinsic()`
  contract.
- **New finding:** the three elements overriding `isOnlyIntrinsic() => true`
  (`SimpleEquationFilterElement:26`, `PublishedFilterElement:23`, `BelongsToRelationFilterElement:34`)
  are *exactly* the three with no `buildForm()` that ignore `$data`. `isOnlyIntrinsic` is fully
  derivable from "declares no value object", which is what §5.3 replaces it with.
  (`CodefogTagsSearchElement` has neither — it is an `isSupported(): false` stub with only
  `buildDca()`; §12 step 3 says it needs `value:` set and nothing else.)

Outcome: presentation becomes a separately registered, per-instance-selectable concern bound to a
**value object**, so a form/element mismatch is structurally impossible rather than validated.

### Spec gaps and conflicts this plan resolves

1. **`AsFilterForm` needs a storable identifier.** §3.3 shows `#[AsFilterForm(value:, requires:,
   default:)]` — but `tl_flare_filter.formVariant` must store *which* form, and the registry key
   (the value class) does not discriminate between several forms sharing it. This plan adds
   `name:`, defaulting to a **new** `TypeNameFactory::createFilterFormType($class)` — that method
   does not exist yet; `TypeNameFactory` currently has only `createFilterElementType()` and
   `createListDriverType()`, both delegating to a private `createType($class, $suffixes)` with the
   most specific suffix first. Follow that shape with `['Controller', 'FilterForm', 'Form']`. FQCNs
   are deliberately not stored — a class rename would orphan every row.
2. **`FilterOptionsResolver` keys the schema cache on `$element::class` alone.** It holds no cache
   itself; it passes that string as the `$key` to `SchemaResolver::resolve()`
   (`src/Filter/Resolver/FilterOptionsResolver.php:37`), which memoises one `OptionsResolver` per
   key. Once a form also contributes `configureOptions()`, the first-seen form's schema is reused
   for every other form on the same element, silently rejecting valid options. The key must become
   composite. `FilterTransformerResolver:35` already uses `sprintf('%s@%s', $type, $element::class)`
   and needs only the form appended. Note `SchemaResolver` is `shared: false`
   (`config/services.yaml:30-31`), so a *separate* resolver service for form config could not
   collide with the element one — but a composite key is still required for two forms on one element.
3. **§3.1 and this plan disagree about `flare.choices_builder`.** §3.1 justifies passing the mount to
   `decode()` because the form "needs access to the attributes it set in `buildForm()` (e.g.
   `flare.choices_builder`)" — while the attribute is provably write-only today (set at
   `ArchiveFilterElement:102`, `DcaSelectFieldFilterElement:89`, `FieldValueChoiceFilterElement:84`,
   `CodefogTagsChoiceFilterElement:101`; zero `getAttribute()` anywhere). Under §3.4's two-method
   port `decode()` reads `$mount->getViewData()` and hands the keys to
   `$element->valueFromChoiceKeys()`, so it needs no `ChoicesBuilder` at all. **Resolve in Phase 2:
   the four `setAttribute()` calls move onto the choice form (which owns `applyFormOptions()` and
   the empty-option config) or disappear — decide once the form is written, and update §3.1's
   parenthetical either way.** Do not simply delete them: `FilterSetFactory:128-131` copies wrapper
   attributes onto the mount, so the attribute reaches `form.vars` and is semi-public surface for
   custom form themes. Removal needs a line in the release notes.

---

## Phase 1 — Contracts, value objects, registry (additive, stays green)

New files plus one additive column. Nothing existing changes behaviour.

> **§14.1 is answered — and it constrains this phase.** Phase 0's throwaway probe
> (`tests/Filter/ValueObjectSerializeProbeTest.php`, `@group probe`) measured `serialize()` against
> `ListSpec::hash()`. §9's core claim holds: equal-but-distinct `final readonly` value objects of
> scalars, arrays, enums and nested value objects hash identically and survive a round trip, and
> every hazard §9 names is confirmed. **But `serialize()` is not a pure value function over an
> object graph:** a repeated object is emitted as a back-reference (`r:N;`), so the hash differs
> depending on whether two filters share one value instance or hold two equal ones. Today's code is
> immune only because `Filter::fingerprint():100` flattens through `FilterData::toArray()` — the
> flattening Phase 5 removes. So **either keep a flattening step (§9's opt-in `fingerprint(): array`,
> which then is not optional) or accept the cache miss.** Blast radius is one in-request memo array
> in `ArchiveFilterElement:401-408`, so instability costs a miss, not correctness; no collision was
> observed. Decide here, before `Filter::$value` exists. Full write-up in §14.1.

**Contracts** — `src/Filter/Form/`:
- `FilterFormInterface` — `buildForm(FilterFormBuilderInterface, FilterContext): void` and
  `decode(FormInterface $mount, FilterContext): ?object` per §3.1.
- `src/Contract/FilterElement/ChoiceSourceContract` — the two-method port from §3.4
  (`buildChoices()`, `valueFromChoiceKeys()`). Place it beside the existing
  `src/Contract/FilterElement/IntrinsicContract.php`, which Phase 2 deletes.

**Value objects** — `src/Filter/Value/`, one per §4.3 *semantic* group (not per structure):
`BoolValue`, `ChoiceValue` (the 4 choice elements), `KeywordsValue`, `DateRangeValue`,
`ParentRefValue` (§7.4 — `array<string $table, list<int> $ids>`, spanning both ptable modes).
Each `final readonly`, obeying the §9 containment rule.

**Registration** — copy the established pattern exactly:
- `src/DependencyInjection/Attribute/AsFilterForm.php` — mirror `AsFilterElement.php`'s shape
  (`const TAG`, manually-assigned `$type`/`$name`, promoted public props for the typed args, and
  every named arg copied back into `public array $attributes`). Ctor:
  `(?string $name = null, ?string $value = null, array $requires = [], bool $default = false, mixed ...$attributes)`.
- `AsFilterElement` gains `?string $value` — **and `RegisterFilterElementsPass:39` must be updated
  in the same commit**, because it reconstructs the attribute as
  `new Definition(AsFilterElement::class, [$type, $attributes['isTargeted'] ?? null])` — positional
  args only, so a new property is silently dropped otherwise. The autoconfiguration closure keeps
  everything in the raw tag, but the pass calls `clearTag()` at `:32`, so anything not replayed is
  gone.
- `src/Registry/FilterFormRegistry.php` — auto-registered (`src/Registry/` is in no exclude list in
  `config/services.yaml`). Model the class-string keying on `FilterTypeRegistry` (uninitialised
  typed property + `!isset()` guard, so "resolved but empty" is representable).
  **There is no existing `default:`/`requires:` election to copy** — `ProjectorRegistry::getProjectorFor()`
  is a `supports()` + highest-`priority()` tournament with an exclusion set, a different shape
  entirely; write the §3.4 election (value class matches **and** every `requires` entry is
  `instanceof`-satisfied by the element) fresh.
  Prefer the **`CodefogTagsPass` ServiceLocator shape** (`:25-58` — `array<serviceId, Reference>`
  inlined as a `Definition(ServiceLocator::class)` tagged `container.service_locator`, injected by
  named argument) over `RegisterFilterElementsPass`'s eager `addMethodCall('add', [Reference, ...])`,
  which instantiates every element on first registry use — a list uses a handful of forms, not all
  of them.
- `src/DependencyInjection/Compiler/RegisterFilterFormsPass.php` — use `hasDefinition()` (the
  correct guard; `RegisterFilterElementsPass:21` uses `has()`, which also resolves aliases), pass
  ctor args positionally, and do the §10 compile-time checks here: every `requires` entry is an
  existing interface, and every element value class has ≥1 form whose `requires` that element
  satisfies. **Register it before `RegisterFilterElementsPass`** in
  `src/HeimrichHannotFlareBundle.php:49` if it needs to read `flare.filter_element` tags — that pass
  clears them.
- Add the attribute to the `$attributesForAutoconfiguration` map in
  `HeimrichHannotFlareExtension.php:53` — a one-line entry; the closure is already generic over
  `public array $attributes`.

**Additive schema, no backfill** — the column lands here so records can carry a form from Phase 2
on; the data migration is deferred to **Phase 6**, after every other phase.
- `contao/dca/tl_flare_filter.php`: add `formVariant` (`select`, `submitOnChange => true`, blank
  option = intrinsic, `sql` `['type' => 'string', 'length' => 128, 'default' => '', 'notnull' => true]`).
  `intrinsic` (`:138`) stays for now.
- **No `src/Migration/BackfillFilterFormVariantMigration.php` yet.** Its `element type → form name`
  map cannot be frozen before the forms exist — the `flare_bool` arm in particular is undecidable
  until `ChoiceBoolFilterForm`'s semantics are settled (Phase 2). And a migration file
  auto-registers the moment it exists (`src/Migration` is not excluded from the PSR-4 resource, and
  `autoconfigure` + `AbstractMigration` earns `contao.migration`), so its mere presence means
  `contao:migrate` would run it against names that do not exist — one-shot, with `shouldRun()`
  going quiet afterwards. See Phase 6 and spec §11.

**Tests:** a reflection test over every class named in `#[AsFilterElement(value: …)]` asserting the
§9 containment rule (§10, row 4). The §14.1 probe already exists; extend it rather than duplicating
it if the flattening decision needs more evidence. Existing suite is plain PHPUnit with no kernel,
so this is idiomatic.

---

## Phase 2 — The cut (atomic)

One commit. The seam is a single call site plus one data path, so it cannot be split without an
adapter.

**Signatures:**
- `FilterElementInterface`: `buildForm()` removed; `buildFilter(FilterBuilderInterface, FilterContext, ?object $value)`.
  Each element narrows `$value` to its declared class — a mismatched form is then a native `TypeError`.
- `AbstractFilterElement`: drop the `buildForm()` no-op (`:52`) and `isOnlyIntrinsic()` (`:61`); drop
  the `IntrinsicContract` from its `implements` clause. While here: `$choicesBuilderFactory` is
  `private` (`:31`), so `FieldValueChoiceFilterElement` promotes its own copy (`:37`) to read it
  directly at `:149`. Consolidate on the protected
  `createChoicesBuilder()` accessor.
- `Filter`: `$data` → `$value`, plus a `?FilterFormInterface $form`. **All three `with*()` methods
  (`:49`, `:63`, `:77`) enumerate every ctor arg explicitly and will silently drop new properties** —
  update them and `fingerprint():94`, adding the form as a class-string beside `'element'`, or
  `ListSpec::hash():113` collides across form variants. `withAlias():63` has zero call sites anywhere,
  including tests — delete it rather than maintain it.
- `FilterElementBuildingEvent` / `FilterElementBuiltEvent`: `$data` → `$value`.

**Wiring:**
- `FilterSetFactory:82` — `$filter->element->buildForm($builder, $filterContext)` becomes
  `$filter->form?->buildForm(...)`, skipping the mount when there is no form. Everything at
  `:84-138` (cancel, single/compound election, `ATTR_SINGLE_FIELD`, attribute transfer,
  deferred-listener replay, `$mounts[$key]` recording) is unchanged.
- `FilterFactory::createFromFilterModel():60` — resolve the form from `$filterModel->formVariant`
  via the registry, run its transformers, and merge element + form config through a `ConfigBuilder`
  following `ListSpecFactory:75-89`. Read `formVariant` off the raw model, not from canonical
  config — the form's own transformers have not run yet. `FilterFactory::create():33` gains
  `?FilterFormInterface $form = null`, so the seven programmatic call sites
  (`ValidationLoader:46,95`, `SimpleEquationMod:26`, `NewsListDriver:55`, `EventsListDriver:68`,
  `ChangelanguageListener:137,149`) keep working by passing nothing.
- `FilterOptionsResolver:37` — combined `$configure` closure (element slice + form slice) in the
  `ListOptionsResolver:34-40` style, **with a composite `SchemaResolver` key** per gap 2 above.
  `FilterTransformerResolver:35` — append the form class to the existing key.
- **`FilterSet::decode()`** — the §8 loop lands here, not in the projector. Walk `getMounts()`, and
  for each `FilterMount` call `$mount->filter->form->decode($this->getMount($key), $mount->context)`,
  keeping only non-null results. `InteractiveProjector::collectFilterData():140-184` is then deleted
  and `project():50` calls `$filterSet->decode()`. This is the §7.2 fix: `decode()` reads
  `getViewData()`, so no reverse mapping and no label collisions.
- `FilterExecutor:54` — `$options->filterValues[$key] ?? $filter->value` (no `FilterData::none()`).
- Delete `IntrinsicContract`, `FieldsLoadAndSaveCallbacks::onLoadField_intrinsic()`/`::onSaveField_intrinsic()`
  (its only consumers, `:85` and `:106`) and their `#[AsCallback]` attributes, and the three
  `isOnlyIntrinsic()` overrides.
- Settle `flare.choices_builder` per gap 3 — move onto the form or drop with a release note; do not
  delete silently.

**Element → form migration.** 11 `buildForm()` bodies consolidate into ~5 forms:

| form | value | serves | notes |
|---|---|---|---|
| `ChoiceFilterForm` | `ChoiceValue` | FieldValueChoice, DcaSelectField, CodefogTagsChoice | `requires: [ChoiceSourceContract::class]`; `decode()` is the two-line §3.4 body |
| `CheckboxFilterForm` | `BoolValue` | Boolean | replaces `boolMode`/`boolBinaryChoices` |
| `ChoiceBoolFilterForm` | `BoolValue` | Boolean | the variant those two columns used to select |
| `DateRangeFilterForm` | `DateRangeValue` | DateRange, CalendarCurrent | absorbs the byte-identical `validateRange()` bodies (`DateRangeFilterElement:100-112` ≡ `CalendarCurrentFilterElement:236-248`, docblock included) and the only deferred-listener use |
| `KeywordsFilterForm` | `KeywordsValue` | SearchKeywords | |

`Published`, `BelongsToRelation`, `SimpleEquation` and `CodefogTagsSearchElement` get
`#[AsFilterElement(value: null)]` and no form. `Archive` uses `ParentRefValue` (§7.4) and is also
served by `ChoiceFilterForm` via the port; its `buildForm()` throws `FilterException` at four sites,
and the form must keep that failure mode or the archive filter degrades silently. Note
`ArchiveFilterElement:101-104` installs `applyFormOptions()` and `single(ChoiceType::class, …)`
*before* the choices are added (`:161-168`), working only because `buildCallbackChoiceLoader()`
defers — preserve the deferral when the form takes over.

**Both choice defects** (per the decision): `createChoices()` → `buildChoices()`;
`normalizeRuntimeValue()` and `normalizeSubmittedValue()` → `valueFromChoiceKeys()` on the port; and
`ChoicesBuilder::add():139` fixed so `choice` carries identity rather than the display string, which
removes the `array_search($choice, $this->choices, true)` reverse lookup at `ChoicesBuilder:259`
inside `buildChoiceValueCallback():251-268`. Fixing `add()` also lets
`FieldValueChoiceFilterElement`'s three `createChoices()` calls collapse to one.
`ChoicesBuilder` has real dead API — verified zero call sites for `setLabelForModel`,
`setLabelForClass`, `setEmptyOptionValue`, `getChoice`, `hasEmptyOption`, `buildFormOptions`,
`addGroup`, `removeGroup`, plus the write-only `$groups`/`$choiceGroupMap` fields; leave it alone
unless the port makes deletion obvious. (`setLabel` and `count()` are **live** — `ArchiveFilterElement:117`
and `:164`. Every one of these carries `/** @api */`, so deletions need a release note.)

**Do not carry over:** `CalendarCurrentFilterElement`'s positional `$data->all()` fallback
(`processRuntimeValue():187-210`, exactly-two-element) — the value object replaces it, and
`DateRangeFilterElement:87-88` never had an equivalent, so the two siblings disagree on the runtime
contract today. `DateRangeFilterElement`'s `from_enabled`/`to_enabled` (`:38-39`, read at `:55,:64`)
are dead knobs — defined, never written by `transformFilterModel():42-47`, absent from every palette
and language file, so both branches are permanently true — either surface them as real form config
or drop them and add the fields unconditionally.

---

## Phase 3 — DCA composition

- `DcaBuilder` — third palette segment with fixed order `__prefix__ + element + form + __suffix__`.
  `Str::mergePalettes()` is already variadic and drops empty segments, so `apply():89` needs one
  extra argument. Add `scope('form')` returning a sub-builder whose `palette()` lands in the form
  segment; `field()` stays shared (it already returns a memoized shared `DcaFieldBuilder` at `:68`,
  and forms occasionally tweak an element field's `eval`). `DcaBuilder` is `final` and deliberately
  **not** a service (excluded wholesale at `config/services.yaml:15`) — it is `new`ed once, at
  `ElementDcaListener:108` — and `ElementDcaEvent` holds the concrete class, so widening it is a
  local change.
  While here: `Str::mergePalettes(?string ...$palettes)` maps a non-nullable `string $palette`
  closure over its args *before* filtering (`src/Util/Str.php:97-100`), under `strict_types=1`,
  while `DcaBuilder::apply()` passes a `?string` and `ArchiveFilterElement:444` passes `null`
  explicitly. Adding a third nullable segment makes this worth settling — filter first, or widen the
  closure param.
- `ElementDcaListener:79,108-118` — resolve the form service alongside `$filterModel->type` and call
  its `buildDca()` into the form scope.
- `FieldsOptionsCallbacks` — new `#[AsCallback(self::TABLE_NAME, 'fields.formVariant.options')]`:
  registry entries for the element's declared value class, filtered by `requires`. Follow the
  existing `fields.type.options` callback at `:43-63`.
- Split the 5 `intrinsic`-branching palettes (`BooleanFilterElement:110-126`,
  `SearchKeywordsFilterElement:87-94`, `DcaSelectFieldFilterElement:202-212`,
  `ArchiveFilterElement:411-444`, `CalendarCurrentFilterElement:139-147`) into element + form
  segments per the §6.2 legend convention. `FieldValueChoiceFilterElement:113-115` and
  `CodefogTagsChoiceFilterElement:128-130` currently show form-only fields even when intrinsic —
  this fixes that inconsistency too.
- §14.2: confirm whether any first-wave form contributes a subpalette. `__selector__` is static in
  `contao/dca/tl_flare_filter.php:641-644`, and **`DcaBuilder::selector()` does not exist at all** —
  neither producer nor consumer, and `DcaBuilderInterface` has only `palette`, `getPalette`,
  `prefix`, `suffix`, `field`, `apply`. `apply()` never touches `palettes.__selector__` or
  `subpalettes`. So this is an API to *write*, not one to find a consumer for — **defer it if no
  first-wave form needs it.**
- Form-owned field labels stay in `contao/languages/{en,de}/tl_flare_filter.php` (§13.3).
- Add a `DcaBuilder` unit test asserting segment order — new but idiomatic (`$GLOBALS['TL_DCA']` set
  up by hand; no kernel). There is no `tests/DataContainer/` or `tests/Util/` today, so
  `mergePalettes()` is untested as well.

---

## Phase 4 — Drop the legacy columns

Everything here is deletion, and it must land after Phase 3 so the backend never references a
dropped column.

> **Do not apply the schema diff on a database holding real rows until Phase 6 has landed.** This
> phase removes `intrinsic` from the DCA, and Phase 6's backfill reads it. Contao runs migrations
> before applying the diff, so a single `contao:migrate` with both phases in place backfills and
> then drops, in that order — which is correct. Updating the schema in between destroys the source
> column and with it the mapping, unrecoverably.

- `contao/dca/tl_flare_filter.php`: remove the `intrinsic` (`:138`), `boolMode` (`:616`) and
  `boolBinaryChoices` (`:630`) fields, the `boolMode_binary` subpalette (`:664`), the `boolMode`
  entry in `__selector__` (`:643`), and `intrinsic` from
  `'__prefix__' => '{title_legend},title,type,intrinsic'` (`:645`).
  Columns come only from these `sql` keys (no schema listener), so Contao's diff drops them.
- **`AddTargetAliasFieldCallback:50` anchors on the dropped column** —
  `PaletteManipulator::create()->addField('targetAlias', 'intrinsic')` must re-anchor on
  `formVariant`, or `targetAlias` silently stops being inserted for every targeted element.
- `FilterModel:34` `isFilterIntrinsic()` and the `'intrinsic'` arm of `__get():114`; the
  `@property bool $intrinsic` in `DocumentsFilterModelTrait:15`.
- `ListCallbacks:34,51` — `$row['intrinsic']` becomes `($row['formVariant'] ?? '') === ''`. The
  `is_intrinsic` flag in `templates/backend/be_filter_info.html.twig` is purely cosmetic (icon +
  opacity) and needs no change beyond that. Keep the `filter.info.intrinsic.yes/no` keys in
  `translations/flare.{en,de}.yaml:31-33`, or rename together with the template. (Unrelated but
  adjacent: that template's `form_alias` / `duplicate_filter_aliases` are the `formAlias` *query
  parameter* column, which §5.1 explicitly reserves — do not touch.)
- Remove the `'intrinsic'` key from the 12 `configureOptions()` and 11 `transformFilterModel()`
  declarations, and the `unset($preselectOptions['null'])` hack at `BooleanFilterElement:124-126`.
- `preselect` stays a column, re-owned by the form, semantics narrowed to hydration (§4.2). Because
  the decode path already harvests field defaults for unsubmitted forms, preselect-as-data flows
  through the normal path and every `?? $config['preselect']` fallback disappears.

---

## Phase 5 — Fold out `FilterData`

`FilterData` was introduced in `4263611`; this partly unwinds it. Both of its jobs relocate:
`hasSingle()`'s submitted-vs-untouched distinction is answerable from the `FormInterface`
(`decode()` receives the mount), and `toArray()`'s hashing role moves to the value object — subject
to the §14.1 flattening decision recorded in Phase 1, which is precisely the constraint on removing
`toArray()`.

Mechanically small — the `array<string|int, FilterData>` map is typed in **docblocks only**, so the
15 transport hops (`AggregationContext`, `ValidationContext`, `InteractiveLoaderConfig`,
`AggregationLoaderConfig`, `ListQueryConfig`, the two Calendar loaders, …) need annotation updates,
not signature changes. `count()` and `getIterator()` (and therefore the
`\IteratorAggregate, \Countable` clause) have zero call sites anywhere; `hasSingle()` and `isEmpty()`
are dead in `src/` but covered by tests. Delete the class and `tests/Filter/FilterDataTest.php`.

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
   `boolBinaryChoices` *does* change matching via `resolveRuntimeValue():84-89` — under `NULL_TRUE`
   an unchecked box means "no opinion", under `NULL_FALSE`/`TRUE_FALSE` it means `false`. A checkbox
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

The 11 programmatic `'intrinsic' => true` sites (`ValidationLoader:49,98`, `SimpleEquationMod:29`,
`EventsListDriver:71`, `ChangelanguageListener:140,152`, `NewsListDriver:58`) are config array
literals, not DB rows. The migration touches only `tl_flare_filter` and cannot see them.

---

## Files that change most

- `src/Filter/Element/*` (12 elements) — `buildForm()` out, `buildFilter(?object)` in
- new `src/Filter/Form/*`, `src/Filter/Value/*`, `src/Contract/FilterElement/ChoiceSourceContract.php`
- `src/Filter/Factory/{FilterFactory,FilterSetFactory,FilterContextFactory}.php`
- `src/Filter/Resolver/{FilterOptionsResolver,FilterTransformerResolver}.php`
- `src/Filter/{Filter,FilterData,FilterSet}.php`, `src/Engine/Projector/InteractiveProjector.php`,
  `src/Query/Executor/FilterExecutor.php`
- `src/DataContainer/Builder/DcaBuilder.php`, `src/EventListener/Contao/ElementDcaListener.php`
- `contao/dca/tl_flare_filter.php`, `src/EventListener/DataContainer/FlareFilter/*`
- `src/Form/ChoicesBuilder.php`
- `src/Migration/BackfillFilterFormVariantMigration.php` (Phase 6 only)

---

## Verification

Per phase, then end-to-end. The `php` binary is unavailable — use the Makefile's Docker targets.

1. `make phpstan` (level 5, `src/` minus `src/Model/` and `src/Integration/Terminal42Languages/`)
   after every phase. It will catch the `Filter::with*()` drop-through and the `buildFilter()`
   signature fan-out. There is no baseline file, so nothing hides a new error.
2. `make test` after every phase. Existing suites that must be updated rather than deleted:
   `tests/Filter/FilterTest.php` (fingerprint), `tests/Engine/Projector/InteractiveProjectorTest.php`
   (its `collectFilterData` coverage moves to `FilterSet::decode()`),
   `tests/Query/Executor/FilterExecutorTest.php`, `tests/Filter/FilterSetFactoryTest.php`,
   `tests/Filter/FilterFactoryTest.php`, `tests/Filter/FilterOptionsResolverTest.php` (asserts
   `intrinsic`), `tests/Filter/Element/{ArchiveFilterElementTest,SimpleEquationFilterElementTest}.php`
   (both assert `intrinsic`), `tests/Config/ConfigBuilderTest.php` (asserts `intrinsic`),
   `tests/List/StubFilterElement.php` (the shared double — anything touching
   `FilterElementInterface` touches it).
   Coverage gap to be aware of before Phase 2: only `Archive` and `SimpleEquation` have
   element-level tests, and both only assert `transformFilterModel()` output. `DateRange`,
   `CalendarCurrent`, `DcaSelectField`, `FieldValueChoice`, `Boolean`, `SearchKeywords` and
   `ChoicesBuilder` have **none**, so the choice-defect fix and the `validateRange()` extraction have
   no safety net. Write `ChoicesBuilderTest` first.
3. New tests: value-object containment (reflection over `#[AsFilterElement(value: …)]`),
   `DcaBuilder` segment order, and a `ChoiceFilterForm::decode()` case with **two rows sharing a
   display label** — the collision the old `array_search` reverse lookup produced. The `serialize()`
   stability probe already exists from Phase 0.
4. Container build: `make php bin/console debug:container flare.filter_form.choice` and
   `debug:container --tag=flare.filter_form`. Deliberately mis-declare a `requires` entry once and
   confirm the compiler pass fails the build (§10, row 3). **Note this repo has no `bin/console`** —
   it is a bundle with no app skeleton, so container checks need a host Contao install.
5. Backend, on a Contao install with existing filter rows. **Phases 1-5 have no migration to run** —
   `formVariant` is uniformly `''` and `intrinsic` stays authoritative until Phase 4; set
   `formVariant` by hand to exercise a form. From **Phase 6** on, run the migration
   (`make php bin/console contao:migrate --dry-run` first), then confirm for a `flare_bool` filter
   that the `formVariant` select lists both bool forms, that switching it swaps the palette segment
   via `submitOnChange`, that the blank option hides the form fields, and that a previously
   `intrinsic = 1` row came through as blank. Check `targetAlias` still appears after the
   `AddTargetAliasFieldCallback` re-anchor.
6. Frontend: a list with an Archive filter in **both** ptable modes (static and dynamic — the
   `PtableInferrer` branch), plus a `FieldValueChoice` filter on a `foreignKey` field with duplicate
   labels. Verify submitted values narrow the result set, that the empty option still means "use the
   full whitelist" (`ChoicesBuilder::EMPTY_CHOICE` passed through verbatim, §3.4), and that an
   unsubmitted form applies its `preselect`.
7. `make semgrep-sec` before finishing — `FilterQueryBuilder` parameterisation is untouched, but the
   filter path is where it matters. Mago runs in CI only (`mago lint`, never `mago analyze`); there
   is no make target.

**Docs are a separate worktree.** `docs/` is a git worktree on branch `docs/main`, so every phase
that changes public API needs its own commit there — `docs/docs/dev/events.md`,
`docs/docs/spec/filtering.md`, `docs/docs/dev/filter-elements/index.md`,
`docs/docs/migrating-from-v0.1.md` and `docs/docs/removed-in-v0.2.md` all describe this surface.
`docs/versioned_docs/version-0.1/**` snapshots the shipped 0.1 API and must stay frozen.
