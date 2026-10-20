# SPEC: Decoupling Filter Forms from Filter Elements

**Status:** Step 0 (§12) implemented and partly re-renamed by #48; step 1 partially implemented
(contracts, value objects, registry, the element contract's signature, and the query-side predicate
layer of §2.3 — the tree is red mid-cut); steps 2-6 not started. Progress and the open checklist
live in `PLAN_FILTER_FORMS.md`.
**Scope:** `src/Filter/`, `src/Form/`, `src/Query/`, `src/Event/`, `src/EventListener/NamedDispatch/`,
`src/Registry/`, `src/DependencyInjection/`, `src/DataContainer/Builder/`,
`contao/dca/tl_flare_filter.php`, `src/EventListener/Contao/ElementDcaListener.php`,
`src/Engine/Projector/InteractiveProjector.php`
**Breaking:** yes (`FilterElementInterface`, `Filter::$data`, `tl_flare_filter` schema, the
renames of §2.2 — service ids, event classes and the dispatch alias
`flare.filter_element.{type}.form_built` — and the replacement of filter types, `FilterBuilder` and
`FilterExecutor` by the predicate layer of §2.3)

**Reading note.** §1 describes the code *before* the refactor and uses the names of that time.
Everything from §2 on uses the current names (§2.2).

---

## 1. Motivation

Today a filter element owns both *what it matches* and *how it is presented*:
`FilterElementInterface::buildForm()` and `::buildFilter()` live on the same class, and the
"no form" case is expressed by an `intrinsic` boolean checked at the top of nine `buildForm()`
implementations.

Three concrete problems follow.

**1.1 Presentation variants become config columns.** `BooleanFilterElement` carries `boolMode` and
`boolBinaryChoices` — two DB columns, a `__selector__` entry and a subpalette
(`contao/dca/tl_flare_filter.php`) — whose only job is to pick a rendering. Adding a toggle or a
segmented control means more enum columns. This grows combinatorially.

**1.2 `buildForm()` and `buildFilter()` share an undeclared schema.** The coupling is real, not
incidental:

- `FieldValueChoiceFilterElement::buildForm()` builds a `ChoicesBuilder`; `buildFilter()` **rebuilds
  it** in `normalizeRuntimeValue()` to map submitted choices back to scalars.
- `DateRangeFilterElement::buildForm()` adds children `from`/`to`; `buildFilter()` reads
  `$data->get('from')` by those exact names.
- The `single()` vs. compound mount decision (`FilterFormFactory`, now `FormHarnessFactory`, §2.2)
  determines whether `buildFilter()` receives `getSingleValue()` or `get($name)`.

Extracting forms without addressing this trades one coupling for a worse, invisible one.

**1.3 `intrinsic` conflates three concepts.** It is simultaneously a backend checkbox, a
"value comes from config" semantic (`$config['intrinsic'] ? $config['preselect'] : $runtime`), and a
flag set programmatically where no backend row exists — `src/Engine/Loader/ValidationLoader.php:49,98`,
`src/Engine/Mod/SimpleEquationMod.php:29`, `src/List/Driver/NewsListDriver.php:58`,
`src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php:71`,
`src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php:140,152`.

The smell is visible in `BooleanFilterElement::buildDca()`:

```php
if ($intrinsic) {
    unset($preselectOptions['null']);
}
```

`null` means "nothing preselected" (valid) or "no intrinsic value" (meaningless) depending on a mode
flag. Two concepts fighting over one column.

---

## 2. Target model

A filter's **presentation** becomes a separate, registrable, per-instance-selectable concern that
owns its own config and its own slice of the backend palette.

```
FilterForm             — how the user supplies the value. Produces the value object.
Value object           — the typed contract between form and element.
FilterElement          — what rows match. Consumes the value object plus its config and emits
                         propositions (§2.3).
Predicate              — how a proposition becomes SQL conditions. Stateless service.
```

Registration is **one-directional**: a form binds to a *value object*, never to an element type.
Selectable forms for an element = registry lookup by the element's declared value class.
A mismatch is therefore structurally impossible rather than validated.

### 2.1 Nomenclature

`FilterForm` is the *per-filter* strategy. That name is currently occupied by the whole-form
machinery (`FilterFormFactory`, `FilterFormBuildEvent`, `flare.form.{name}.build`), which must
therefore be renamed before anything else — Phase 0 in §12.

The renaming axis is **not** filter-versus-list. A form belongs to filtering; the list is
exclusively output. The three concerns are peers:

| concern | meaning |
|---|---|
| **Filter** | what matches — the predicate, query side |
| **List** | what comes out — output |
| **Form** | what goes in — input |

The aggregate is therefore not list-scoped but simply the *plural* of the singular. Four layers,
one word each:

| term | multiplicity | what it is |
|---|---|---|
| `FormHarness` | one per list × form context | the filters, their root form, and the mount↔filter map |
| `FilterForm` | one per filter | registrable presentation strategy (`buildForm()`, `decode()`) |
| `FilterFormBuilder` | one per filter, transient | collect-only builder handed to `buildForm()` |
| **mount** | one per filter that has a form | the node mounted into the root form — flat field or compound group |

`FormHarness` is an **object**, not a bare `FormInterface` returned by a factory: it owns the
`decode()` loop (§8), which otherwise has no home but `InteractiveProjector` — where its
predecessor already landed wrongly (§7.2).

**"Mount"** is the codebase's own word; `FilterFormFactory` already names the variable `$mount`.
It replaces "field" throughout this spec, because in the compound case the mounted node is a
`FormType` group with children and not a field at all.

Directory split, unchanged by the rename: `src/Filter/Form/` holds FLARE `FilterForm`
implementations (peers of `src/Filter/Element/`); `src/Form/` keeps Symfony-level building blocks
(`ChoicesBuilder`, `Form/Type/DateRangeFormType`). Mixing the two is the ambiguity this section
exists to remove.

### 2.2 Renaming (Phase 0, revised by #48)

Phase 0 first named the aggregate `FilterSet`; #48 renamed it to `FormHarness` and moved it, with
its factory and `FilterMount`, from `src/Filter/` to `src/Form/`. The table gives the final names:

| before | now | scope |
|---|---|---|
| `Form\Factory\FilterFormFactory` | `Form\Factory\FormHarnessFactory` | harness |
| — | `Form\FormHarness` (new) | harness |
| — | `Form\FilterMount` (new) | harness |
| `Event\FilterFormBuildEvent` | `Event\FormHarnessBuildEvent` | harness |
| `EventListener\NamedDispatch\FilterFormListener` | `…\FormHarnessListener` | harness |
| `flare.form.{name}.build` | unchanged (was `flare.filter_set.{name}.build` in between) | harness |
| `Event\FilterElementFormBuiltEvent` | `Event\FilterFormBuiltEvent` | filter |
| `flare.filter_element.{type}.form_built` | `flare.filter_form.{type}.built` | filter |
| — | `EventListener\NamedDispatch\FilterFormListener` (new) | filter |
| `Filter\FilterFormBuilder`, `…Interface` | unchanged | filter |

The query-side renames of #48 are in §2.3.

`FormHarnessFactory` builds a `FormHarness`, not a form; the root form is `FormHarness::getForm()`.
Because the aggregate is now named after the form again, its dispatch alias went back to the
original `flare.form.{name}.build`. `FilterElementFormBuiltEvent` loses its detour over the element:
it was named after the element only because `FilterForm*` was taken — and its dispatch alias follows
the class, moving out of `FilterElementListener` into the `FilterFormListener` whose name the
`FilterFormListener` → `FormHarnessListener` rename frees. `{type}` there remains the *element* type,
which is what listeners target; the alias names the concern, not the key.

`FilterFormBuilder` keeps its name deliberately. Symfony's own
`FormTypeInterface::buildForm(FormBuilderInterface)` names the builder after what it builds, not
after who receives it, so `FilterForm::buildForm(FilterFormBuilderInterface)` reads as the familiar
pattern. `single()` is moreover a statement about the form, not about fields, which rules out
`FilterFieldsBuilder`.

Rejected names, recorded so the branches stay closed:

- **`ListForm…`** — puts the form on the output side of the model, contradicting the table above.
- **`ListFilterForm…`** — contains `FilterForm` as a substring, so every search for the per-filter
  concept also hits the aggregate.
- **`FilterFormType`** — `FilterType` was the old name of the SQL predicate (now
  `src/Filter/Predicate/`, §2.3), and the suffix falsely promises a Symfony `AbstractType`.
- **`FilterSet`** — Phase 0's name for the aggregate, replaced in #48 (§13.5).
- **`FilterBar` / `FilterPanel`** — commit to a layout no template is obliged to honour, and
  understate an object that also owns `decode()`.
- **`QueryForm`** — `src/Query/` is the SQL layer.
- **`FilterWidget` for the per-filter strategy** (leaving `FilterForm` on the aggregate) — the
  strategy has `decode()` and its own DCA slice, so "widget" promises rendering and hides half the
  contract; the term is also already occupied by Contao.

### 2.3 Query side: predicates, propositions, formula (#48)

Not part of the original design; introduced by #48 and recorded here because it changes the element
contract (§3.2). An element no longer writes into a query builder. It *describes* its conditions,
and the framework executes the description:

| term | what it is |
|---|---|
| `Predicate` (`src/Filter/Predicate/`) | stateless service turning resolved options into SQL conditions: `configureOptions()`, `buildConditions(FilterConditionsBuilder, array $options)`. Tagged `flare.filter_predicate`, registered in `FilterPredicateRegistry` keyed by **class**. Formerly "filter type". |
| `Proposition` | one predicate applied once: predicate service + class, target alias, resolved options |
| `Formula` | the list of propositions one filter produced for one invocation |
| `FormulaBuilder` | validates options through the predicate's `OptionsResolver` (memoised per predicate class) and collects propositions. Formerly `FilterBuilder`. |
| `FilterContextBuilder` | what `buildContext()` receives: `list`, `filter`, `engineContext`, `addPredicate(class, options, ?targetAlias)`, `abort()`. `build()` produces the `FilterContext` with its formula. |
| `FilterConditionsBuilder` / `FilterConditions` | the parameterised SQL fragment builder and its product. Formerly `FilterQueryBuilder` / `FilterQuery`. |

`FilterExecutor` is gone. `ListQueryDirector::invokeFilters()` asks `FilterContextFactory` for each
filter's context and runs every proposition's predicate into its own `FilterConditionsBuilder`;
failures are wrapped with the filter's source via `Util\CreatesFilterExceptionTrait`.

**Open:** where the formula is built relative to the form (§14.5), and what happens to
`FilterElementBuildingEvent` / `FilterElementBuiltEvent`, whose dispatcher was `FilterExecutor` and
which nothing dispatches now (§14.7).

---

## 3. Contracts

### 3.1 `FilterFormInterface`

```php
/** @template T of ValueInterface */
interface FilterFormInterface
{
    /** @return class-string<T> */
    public function getValueClass(): string;

    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Produces the element's canonical value from the mount, or null to contribute nothing.
     *
     * @return T|null
     */
    public function decode(FormInterface $form, FilterContext $context): ?ValueInterface;
}
```

Implementations extend `AbstractFilterForm`, which carries the same `@template`. Every value object
implements the marker interface `Filter\Value\ValueInterface`.

A form MAY additionally implement the existing contracts, which then apply to the form's own
config slice: `OptionsContract` (`configureOptions`), `TransformerContract`
(`configureTransformers`), `DcaContract` (`buildDca`).

**`decode()` receives the mount (§2.1), not a pre-flattened DTO.** The parameter is named `$form`
because it is a `FormInterface`, but it is always the filter's mounted node, never the root form.
Only the form knows whether it wants `getData()`, `getNormData()` or `getViewData()`, and it may
need attributes it set in `buildForm()` — whether `flare.choices_builder` is such an attribute is
open (`PLAN_FILTER_FORMS.md`, gap 3). See §7.

### 3.2 `FilterElementInterface` (revised)

```php
interface FilterElementInterface
{
    public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void;
}
```

`buildForm()` is removed, and `buildFilter()` became `buildContext()`: the element reads its config
from `$builder->filter->config`, and emits propositions via `$builder->addPredicate()` (§2.3) instead
of writing into a query builder. `$value` is the decoded form value, or the filter's programmatic
value (§8), or `null`.

**An implementation cannot narrow `$value` to its value class in the signature.** PHP enforces
parameter contravariance, so `?BoolValue` against the interface's `?ValueInterface` is a fatal
"declaration must be compatible" at class load. The check runs inside the method, through a
`@template`-annotated accessor on `AbstractFilterElement` so PHPStan still narrows call sites; a
mismatched value is a `FilterException`, not a silently wrong query.

`configureOptions`, `configureTransformers`, `buildDca` stay as they are.

### 3.3 Attributes

```php
#[AsFilterElement(type: self::TYPE, value: BoolValue::class)]
#[AsFilterForm(name: 'flare_checkbox', requires: [])]
```

- `AsFilterElement::$value` — the value class this element consumes. `null` means the element has no
  runtime value at all (see §5.3).
- `AsFilterForm::$name` — the identifier persisted in `tl_flare_filter.formVariant`. Defaults to
  `TypeNameFactory::createFilterFormType()` over the class (suffixes `Controller`, `FilterForm`,
  `Form` stripped). `""` (= intrinsic) and `"default"` are reserved. The value class is not a
  discriminator — several forms share one — so the name is needed; FQCNs are not stored because a
  class rename would orphan every row.
- `AsFilterForm::$requires` — capability interfaces the form needs the element to implement (§4.3).
- The value class the form produces comes from `FilterFormInterface::getValueClass()`, not from the
  attribute (#48 removed `value:` and `default:` from `AsFilterForm`). How the compiler pass, which
  must not instantiate services, learns it — and whether a per-value-class `default` returns — is
  open (§14.6).

`AsFilterForm` is repeatable. One form class can serve every element sharing a value class without
repeated per-element declarations.

### 3.4 Capability ports

A form MUST NOT read element-owned config in `decode()`. Anything it needs *pulled* from the element
is an explicit interface. For choice-based filters there is exactly one such port:

```php
interface ChoiceSourceContract
{
    /**
     * @throws FilterException On invalid filter configuration (no whitelist, no inferrable ptable, …).
     */
    public function buildChoices(FilterContext $context): ChoicesBuilder;

    /**
     * @param list<string> $keys Selected choice keys, verbatim — MAY include
     *   {@see ChoicesBuilder::EMPTY_CHOICE}, whose meaning is element-defined.
     */
    public function valueFromChoiceKeys(array $keys, FilterContext $context): ?ValueInterface;
}
```

(The interface in the tree still declares `?object`; tightening it is part of step 1.)

Registry filter for the backend select: value class matches **and** every entry in `requires` is
implemented by the element (plain `instanceof`, resolvable at compile time). This is not a second
pairing axis — the form still never names an element.

#### The decode division

> The **form** decodes the *widget*: which choice keys did the user pick.
> The **element** decodes the *domain*: what do those keys mean.

So a generic choice form's `decode()` is:

```php
$keys = (array) $form->getViewData();                 // widget → keys
return $element->valueFromChoiceKeys($keys, $ctx);    // keys → domain value
```

This is why the port needs a second method. Choice keys are element-defined: `FieldValueChoice` uses
bare ids, `ArchiveFilterElement` uses `"<table>.<id>"` in dynamic-ptable mode (§7.4). A form that had
to parse them would be reading element knowledge through the back door — the very thing `requires`
exists to prevent.

Two consequences worth stating:

- **The empty-option sentinel is passed through, not swallowed.** It looks like a widget artifact but
  can carry domain meaning: `ArchiveFilterElement::normalizeFilterValue()` treats a selected empty
  option as "use the full whitelist" (unless `use_whitelist_for_options_only`). The form therefore
  forwards `ChoicesBuilder::EMPTY_CHOICE` verbatim and lets the element decide.
- **Labelling is split.** `buildChoices()` MAY set labels it owns — `setLabelForTable()` fed from
  element-owned whitelist rows is the real case. The form owns only global overrides (`setLabel()`,
  `setModelSuffix()`, `setEmptyOption()`) and `applyFormOptions()`.

---

## 4. Ownership rules

### 4.1 Backend fields

> A field belongs to the **element** if its value changes *which rows match*.
> It belongs to the **form** if its value changes only *what the user sees or can submit*.

| field | owner | note |
|---|---|---|
| `fieldGeneric` | element | changes matching |
| `fieldPublished`, `invertPublished`, `startAt`, `stopAt` | element | changes matching |
| `targetAlias` | framework | stays in `__prefix__` |
| `label` | form | presentation only |
| `isMultiple`, `isExpanded` | form | value-shape change absorbed by `decode()` |
| `preselect` | form | initial hydration (§4.2) |
| `intrinsicValue` | element | *is* the matched value (§4.2) |
| `boolMode`, `boolBinaryChoices` | **removed** | these *are* the form choice; they become form variants |

### 4.2 The three value concepts

| concept | owner | meaning |
|---|---|---|
| `intrinsicValue` | element config | canonical value when no form is selected |
| `preselect` | form config | initial hydration → the field's native `data` option |
| `decode()` result | form | canonical value from submitted-or-hydrated data |

`intrinsicValue` is **not** a framework-mandated key. It is declared per element in
`configureOptions()` only where meaningful. `SimpleEquationFilterElement`,
`BelongsToRelationFilterElement` and `PublishedFilterElement` have no single value — their config
*is* the value — and declare none.

Because the decode path collects field defaults for unsubmitted forms (today
`InteractiveProjector::collectFilterData()`, after the cut `FormHarness::decode()`, §8),
preselect-as-`data` flows through the normal path and every `?? $config['preselect']` fallback
disappears.

The "user explicitly cleared" vs. "user never interacted" distinction is preserved: `decode()`
receives the `FormInterface` and can consult `isSubmitted()` / `getConfig()->getData()`.

### 4.3 Value object semantics, not structure

> Two elements share a value object only if every form registered for one is meaningful for the other.

`ChoiceValue` for the three choice-based elements; `KeywordsValue` for `SearchKeywordsFilterElement`
— even though both are "a list of strings". Merging structurally identical value objects
reintroduces the silent mismatch one level up, because a choice form would be offered for a
free-text filter.

---

## 5. Intrinsic filters

### 5.1 Config shape

The `intrinsic` boolean is replaced by a nullable `formVariant` slot in canonical config.
`null` = intrinsic. `$config['intrinsic']` becomes `$config['formVariant'] === null`.

The programmatic call sites in §1.3 keep working by simply not setting a form.

**Do not name the column `formAlias`** — that is taken; it is the query-parameter name
(`src/Model/FilterModel.php:91-102`). Use `formVariant`. Bare `form` was rejected as too generic
for a column in the one wide shared table (§6.3), even though `FilterForm` is now the noun it
selects.

### 5.2 Backend field

`formVariant` is a `select` with `submitOnChange => true`. Its blank option *is* intrinsic.
Options come from the registry, keyed by the element's declared value class and filtered by
`requires`. `ElementDcaListener` reads it alongside `$filterModel->type` to resolve the form service
before calling the form's `buildDca()`.

### 5.3 `IntrinsicContract` is deleted

`#[AsFilterElement(value: null)]` ⇒ no value object ⇒ no forms ⇒ intrinsic-only. This is more
precise than the interface it replaces, and it removes:

- `src/Contract/FilterElement/IntrinsicContract.php`
- `FieldsLoadAndSaveCallbacks::onLoadField_intrinsic()` and `::onSaveField_intrinsic()`
  (the force-check-and-disable trickery)
- `AbstractFilterElement::isOnlyIntrinsic()` and its three overrides

---

## 6. DCA composition

### 6.1 Palette segments

`DcaBuilder::palette()` currently *replaces*, and `apply()` writes one slot:

```php
$dca['palettes'][$predicate] = Str::mergePalettes($prefix, $this->palette, $suffix);
```

A third segment is required, with fixed order:

```
__prefix__  +  element palette  +  form palette  +  __suffix__
```

`ElementDcaListener` passes a scoped sub-builder to the form (e.g. `$dca->scope('form')`) whose
`palette()` lands in the form segment. `field()` stays **shared** — it already returns a shared
`DcaFieldBuilder`, and a form occasionally needs to tweak an element field's `eval`.

`apply()` keeps keying `palettes[$type]`; the palette is recomputed per record load, so no key
change is needed.

### 6.2 Legend convention

Pair the mechanism with distinct legends so the split is visible to the editor:
element contributes `{filter_legend},fieldGeneric`, form contributes
`{form_legend},label,isMultiple,isExpanded`.

### 6.3 Known gaps

- **`__selector__` is static** in `contao/dca/tl_flare_filter.php`. A form contributing a subpalette
  needs a `DcaBuilder::selector()` API. This gap exists today.
- **Columns are shared** — `tl_flare_filter` is one wide table, so form-owned columns need globally
  unique names, exactly as `boolBinaryChoices` does now. No namespacing.

---

## 7. The `FieldValueChoice` round-trip

### 7.1 The current defect

`FieldValueChoiceFilterElement::createChoices()` does:

```php
$choices->add((string) $id, (string) $label, $id);   // alias=id, choice=label, value=id
```

so `ChoicesBuilder::buildChoices()` returns `[id => labelString]`, the form's **model data** is
label strings, and `buildChoiceValueCallback()` reverse-maps via
`array_search($label, $this->choices, true)`. Consequences: two rows with the same display label
silently collide onto one id, and `buildFilter()` must construct a second `ChoicesBuilder` to do the
lookup at all.

### 7.2 Root cause and fix

`InteractiveProjector::collectFilterData()` flattens the form to `$child->getData()` — model data,
which for a `ChoiceType` is the choice objects. A query filter wants the *value*, which Symfony has
already computed as **view data**: `$mount->getViewData()` is `['5','7']`.

The fix is therefore §3.1: `decode(FormInterface $mount, ...)`. No reverse mapping, no second
`ChoicesBuilder`, no label collisions.

Additionally, fix `add()` so `choice` carries identity rather than the display string.

### 7.3 Division of labour after the change

| stays on the element (via `ChoiceSourceContract`) | moves to the form |
|---|---|
| `createChoices()` → `buildChoices()` | `buildPreselectData()` |
| `getForeignValues()` | `extractSubmittedData()` |
| `getLocalValues()` | |
| `normalizeRuntimeValue()` → `valueFromChoiceKeys()` | |

Choice provision is element knowledge (the target field's `foreignKey` relation), and so is key
interpretation. `normalizeRuntimeValue()` does **not** move to the form — it becomes
`valueFromChoiceKeys()` on the port and loses its `ChoicesBuilder` rebuild entirely, because view
data already carries the keys. The two form-side methods largely evaporate.

### 7.4 `ArchiveFilterElement` under the port

Archive is the stress test for this design and it fits, with three notes.

**One value class spans both ptable modes.** `PtableInferrer` selects at runtime between a static
main ptable (choices keyed `"<id>"`, predicate `ArchivePredicate(field: 'pid', parent_ids: [...])`)
and a dynamic ptable (choices keyed `"<table>.<id>"`, predicate `BelongsToRelationPredicate(parent_groups:,
submitted_data: <ids grouped by table>)`). The value shape therefore depends on runtime config, not
on a static declaration — which would break the one-element-one-value-class rule of §3.3 if the two
modes got separate classes. They do not: `ParentRefValue` holds `array<string $table, list<int> $ids>`
and the static mode is the single-table degenerate case. `buildContext()` re-derives the flat vs.
grouped proposition from the inferrer exactly as `buildFilter()` did.

**Config re-ownership** per the §4.1 rule:

| config | owner | why |
|---|---|---|
| `whitelist_parents`, `group_whitelist_parents` | element | changes matching |
| `use_whitelist_for_options_only` | element | changes matching (empty selection ⇒ `return` vs. `abort()`) |
| `format_label`, `format_empty_option`, `has_empty_option` | form | presentation only |
| `is_mandatory`, `is_multiple`, `is_expanded` | form | presentation only |
| `preselect` | form | hydration (§4.2) |

**Models leave the form data path.** Today the form's model data is `Model` instances, which
`processRuntimeValue()` filters against the whitelist. Under keys-based decode the element receives id
strings and validates them against `getWhitelistedParentIds()` — which it already computes. That
removes the Model round-trip through the form, simplifies `normalizeFilterValue()`, and keeps Models
out of value objects as §9 requires.

---

## 8. Programmatic data and `FilterData`

`Filter::$data` (typed `?FilterData`) becomes `Filter::$value` (`?ValueInterface`), typed as the
element's value object. A programmatic caller constructs the value directly instead of fabricating
form-shaped data. (Its former reader, `FilterExecutor`, is gone; today no value reaches
`buildContext()` at all — `FilterContextFactory` passes `null`, with a `todo` — so the cut must
re-establish the path below.)

**Consequence: `FilterData` largely disappears** (introduced in `4263611`; partly unwound here).
Both of its jobs relocate:

- `hasSingle()`'s submitted-vs-untouched distinction → answerable from the `FormInterface`.
- `toArray()`'s hashing role → the value object, flattened by `Util\Fingerprint::flatten()` (§9,
  §14.1).

`FilterFormBuilder`'s `single()` vs. compound mount decision is unaffected — that is genuinely a
form concern and stays where it is.

**The decode loop lives on `FormHarness`.** `InteractiveProjector::collectFilterData()` today walks
the root form and flattens every child to `$child->getData()` — the flattening §7.2 identifies as
the defect. Under the target model that walk becomes `FormHarness::decode()`: for each filter that
has a mount, call the filter form's `decode($mount, $context)` and keep the non-null results. The
projector asks the harness for values instead of reconstructing them from a form tree it does not
own. This is the reason the aggregate is an object rather than a bare `FormInterface` (§2.1).

**The value path.** Since #48 `ListSpec::$filters` is a plain list and every `Filter` carries an
alias (`FilterFactory` generates `_.{type}_{random}` when none is given), so the alias is the key
throughout: `FormHarnessFactory` keys mounts by alias, `FormHarness::decode()` returns
`array<alias, ValueInterface>`, that map travels through `ListQueryConfig::$filterValues` and the
loader configs, and `ListQueryDirector` hands `$values[$filter->alias] ?? $filter->value` to
`buildContext()`. Submitted data takes precedence over the programmatic value.

---

## 9. Value objects and hashing

`ListSpec::hash()` is `sha1(serialize([...]))` over `Filter::fingerprint()`. `serialize()` handles
readonly objects natively and includes the class name, so **a value object of scalars and arrays
needs no hashing interface at all** — it drops into the existing fingerprint.

`spl_object_id` is explicitly **not** usable: it is identity, not value. It is unstable across
requests (so the cache never hits), handles are recycled after GC (so distinct values can collide
into a wrong cache hit), and two equal values are always two objects. `readonly` guarantees contents
cannot drift after hashing; it does not make identity coincide with value.

What is required instead is a **containment rule**:

> A filter value object is `final readonly`; every property is a scalar, `null`, an enum, a nested
> filter value object, or an array of those. Anything with external identity is stored as its id;
> anything with multiple equal representations is normalized in the constructor.

Rationale per excluded type:

- **`\DateTimeInterface`** — bites immediately (`DateRangeFilterElement` deals in from/to).
  `serialize()` embeds `date`/`timezone_type`/`timezone`, so the same instant as `+02:00` vs.
  `Europe/Berlin` hashes differently. Store a timestamp or normalize the timezone. **Conflict:**
  `DateRangeValue` (#48) holds timezone-normalized `?\DateTimeImmutable`, which the rule above
  excludes and `ValueObjectContainmentTest` rejects — open, §14.8.
- **Contao `Model`** — `ArchiveFilterElement` works with selected models; serializing them drags
  whole rows plus `$arrModified` into the hash, so the fingerprint changes when an unrelated column
  changes. Store ids.
- **Closures** — `serialize()` throws.
- **Array order** — arrays serialize in insertion order, so `['a','b']` and `['b','a']` differ. Sort
  in the constructor where order is irrelevant to the query.

**Enforcement:** `tests/Filter/Value/ValueObjectContainmentTest.php` checks every class in
`src/Filter/Value/` and every class named in an `#[AsFilterElement(value: ...)]` by reflection. This
cannot drift from the real properties the way hand-written `hashKey()` methods would.

**Flattening.** §14.1 showed that `serialize()` is not a pure value function over an object graph,
so the hash does not rely on it alone: `Util\Fingerprint::flatten()` turns a value object into an
object-free structure before hashing. It exists and is tested, but `Filter::fingerprint()` does not
use it yet — it still flattens through `FilterData::toArray()`.

---

## 10. Enforcement summary

| risk | mechanism | when it fires |
|---|---|---|
| form produces the wrong shape | registry keyed by value class | structurally impossible |
| element receives the wrong type | checked accessor inside `buildContext()` (§3.2) | `FilterException`, at query build |
| form needs something the element can't supply | `requires` + `instanceof` check | container build — `requires` existence is checked, the per-element check is not implemented yet |
| value object not hashable | reflection test over declared value classes | test suite |

---

## 11. Migration

Phase 0 (§2.2) is rename-only and touches no schema. Everything below belongs to steps 1-4, with
the sole exception of the data migration, which is written **last** — see "Why the data migration
comes last" below.

Two separable things, deliberately not done together:

**Schema** — tracked by the DCA, applied by Contao's schema diff. Columns in this bundle come only
from `sql` keys in `contao/dca/tl_flare_filter.php`; there is no schema listener.

- New column `formVariant` (`varchar`), plus form-owned columns as they move.
- Drop `intrinsic`, `boolMode`, `boolBinaryChoices` and the `boolMode_binary` subpalette /
  `boolMode` selector entry.
- `preselect` stays a column but becomes form-owned; its semantics narrow to hydration only, and
  the `unset($preselectOptions['null'])` hack in `BooleanFilterElement::buildDca()` goes away.

**Data** — one Contao migration: `intrinsic = 0` → the default form for the element's value class;
`intrinsic = 1` → `''`.

### Why the data migration comes last

The mapping cannot be frozen correctly before the forms exist. Three reasons, in order of severity:

1. **The `flare_bool` arm is undecidable early.** `BooleanFilterElement::buildForm()` always mounts a
   `CheckboxType` and never reads `boolMode`; `ternary` posts a backend error as unsupported. But
   `boolBinaryChoices` *does* change matching, via `resolveRuntimeValue()` — under `NULL_TRUE` an
   unchecked box means "no opinion", under `NULL_FALSE`/`TRUE_FALSE` it means `false`. A checkbox
   form that owns no element config cannot express both, so which bool rows map to which bool form
   is only answerable once those forms are written.
2. **A migration file auto-registers the moment it exists.** `src/Migration` is not excluded from
   the PSR-4 service resource, and `autoconfigure: true` plus `AbstractMigration` earns Contao's
   `contao.migration` tag — no manual tag. So the file's mere presence means `contao:migrate` runs
   it, against form names that may not exist yet. It is also one-shot: once `shouldRun()` has been
   satisfied it goes quiet, so wrong values stay committed and need a *second* corrective migration.
3. **Form names are permanent data.** The migration must hardcode `element type → form name` as
   frozen literals rather than resolving through `FilterFormRegistry`, because a migration describes
   historical rows — resolving names through live code would let a later rename retroactively change
   what already-migrated rows meant. Freezing those literals before the names settle is the same
   mistake one step earlier.

**The ordering hazard this creates, and the constraint it imposes.** The backfill reads `intrinsic`,
which the schema step *drops*. Contao runs migrations before applying the schema diff, so a single
`contao:migrate` on a database still carrying `intrinsic` will backfill and then drop, in that order
— which is correct. But applying a schema update after the drop lands and before the migration
exists destroys the source column, and with it any chance of recovering the mapping. Therefore:

- The data migration MUST be written before any environment holding real rows applies the schema
  diff that drops `intrinsic`.
- `shouldRun()` MUST return `false` when `intrinsic` is already absent, so an environment that has
  passed that point fails closed rather than backfilling from nothing.
- `shouldRun()` MUST NOT key on `formVariant` being absent — the column arrives with the schema step
  and will already exist. Key on `intrinsic` being present plus a type-restricted count of rows
  still needing work; without the type restriction, the element types that get no form at all
  (`flare_published`, `flare_relation_belongsTo`, `flare_equation_simple`, `cfg_tags_search`) keep
  the migration pending forever.

---

## 12. Sequencing

Phase 0 clears the vocabulary so the rest can be written in the target words. After that the
contract set is the entire risk; the remaining elements are mechanical. `PLAN_FILTER_FORMS.md`
merges steps 1 and 3 into one atomic cut (no pilot, no legacy adapter) and tracks the open items.

0. **Nomenclature refactor.** ✅ **Done** (#47), then revised by #48 (§2.2). Pure rename plus one new
   object, no behaviour or schema change.
   - Applied the §2.2 table and introduced the aggregate —
     `FormHarnessFactory::create(ListSpec, FormContextInterface): FormHarness`, holding the root
     `FormInterface` and the mount↔filter map. `getForm()` returns the root form; callers that only
     need the form (`InteractiveProjector`, the list-view template data) go through it.
   - The factory's local variables follow the vocabulary: root builder `$root`, collect-only
     per-filter builder `$builder`, mounted node `$mount`.
   - `AGENTS.md` describes the `src/Filter/Form/` vs. `src/Form/` split and the current aliases.
   - Left over: two test files still carry the Phase 0 names (`FilterSetFactoryTest`,
     `FilterSetListenerTest`).

1. **Contracts + the cut.** 🟡 **In progress — tree red.**
   - ✅ Value objects + `ValueInterface` + containment test, `FilterFormInterface` /
     `AbstractFilterForm`, `ChoiceSourceContract`, `AsFilterForm` (`name:`, `requires:`),
     `AsFilterElement::$value`, `FilterFormRegistry` + `RegisterFilterFormsPass`, the
     `formVariant` column, `Util\Fingerprint`.
   - ✅ The predicate layer (§2.3) and the `buildContext()` signature (§3.2).
   - ⬜ Value-class discovery in the compiler pass (§14.6), the per-element `requires` check (§10).
   - ⬜ Element bodies, the five forms, `buildForm()` removed from elements, `Filter::$value` /
     `$form`, `formVariant` resolution in `FilterFactory`, `FormHarness::decode()` and the value
     path of §8, deletion of `IntrinsicContract`.
   - Originally piloted on `BooleanFilterElement`; the pilot is dropped because the contract change
     cannot be split without an adapter.
2. **DCA composition.** `DcaBuilder` palette segments, `scope()`, `selector()`; `ElementDcaListener`
   resolves and invokes the form's `buildDca()`.
3. **Migrate the rest.** Merged into step 1 (atomic cut). `FieldValueChoiceFilterElement` carries the
   §7 fix. `CodefogTagsSearchElement` has neither `buildForm()` nor `buildContext()` (only
   `buildDca()`) — it needs `value: null` set and nothing else.
4. **Schema changes** per §11 — drop `intrinsic`, `boolMode`, `boolBinaryChoices` and their
   palette/selector entries. DCA only; no data migration yet.
5. **Fold `FilterData`** out per §8.
6. **Data migration** per §11. Written last, once the forms and their names exist and the
   `flare_bool` mapping is decided. Subject to the ordering hazard §11 records: it reads
   `intrinsic`, which step 4 removes from the DCA, so it must land before any environment with real
   rows applies the schema diff.

---

## 13. Resolved decisions

Recorded here because each one closes a branch the design could otherwise have taken.

1. **`ArchiveFilterElement` gets a capability port** — and it drove the port's final shape.
   `ChoiceSourceContract` carries two methods, not one (§3.4), because choice keys are
   element-defined; Archive's `"<table>.<id>"` keys made that explicit. The port is shared by
   `FieldValueChoiceFilterElement`, `DcaSelectFieldFilterElement`, `CodefogTagsChoiceFilterElement`
   and `ArchiveFilterElement`, all served by one generic choice form. No second port is needed —
   see §7.4 for the mode-spanning value class and the config re-ownership table.
2. **No compound case needs per-child capability negotiation.** `requires` is declared per form, not
   per child field. `DateRangeFilterElement` needs no capability at all.
3. **Form-owned field labels stay in the central language files**
   (`contao/languages/{en,de}/tl_flare_filter.php`). Forms declare fields and palette segments; they
   do not declare translations. This keeps `tl_flare_filter`'s label surface in one place, consistent
   with the shared-column reality of §6.3.
4. **`intrinsic`-branching `buildDca()` methods collapse into the §6 palette segments.**
   `CalendarCurrentFilterElement::buildDca()` already appends `{form_legend},isLimited` only when not
   intrinsic — hand-rolling exactly the split §6 formalises. Under the target model that branch
   disappears and `isLimited` becomes form-owned config. The same applies to
   `SearchKeywordsFilterElement::buildDca()`, `DcaSelectFieldFilterElement::buildDca()` and
   `BooleanFilterElement::buildDca()`.
5. **The aggregate is called `FormHarness` and is an object.** The naming axis is multiplicity within
   *Filter*, not filter-versus-list (§2.1) — the list is exclusively output, so no `List…` name can
   be right for a form. Phase 0 named it `FilterSet`, on the argument that it named something that
   already existed unnamed (`ListSpec::$filters`). #48 renamed it `FormHarness` and moved it to
   `src/Form/`: the object holds the root form and the mounts, not the filters as such, and
   `ListSpec::$filters` stays a separate plain list (§14.4), so a collection name over-promised.
   Making it an object rather than a factory return value is what gives `decode()` a home (§8).
   Rejected names and their reasons are in §2.2.
6. **Elements describe conditions; predicates execute them** (#48, §2.3). An element emits
   propositions through `FilterContextBuilder::addPredicate()` and never touches a query builder.
   Predicates are registered by class, not by type name.
7. **The value class of a form is declared in code, not in the attribute** (#48, §3.3):
   `FilterFormInterface::getValueClass()` with a `@template`. Its consequence for compile-time
   registration is still open (§14.6).

---

## 14. Remaining unknowns

Items 1-4 were not blockers at spec time. Items 5-8 surfaced with #48 and **must be settled before
the cut can be finished**.

1. ~~**`serialize()` stability for readonly value objects**~~ — **measured** in step 0 and
   **resolved** by `Util\Fingerprint::flatten()` (§9).
   A throwaway probe test (commit 06fc013 on `feat/filter-types`, not carried over) exercised the real
   `ListSpec::hash()` path via `Filter::$data`. Results:
   - §9's core claim **holds**: two separately constructed, equal `final readonly` value objects of
     scalars, arrays, enums and nested value objects hash identically, and survive a
     `serialize()`/`unserialize()` round trip. Enums are value stable.
   - Every hazard §9 names is **confirmed**: `\DateTimeImmutable` hashes differently for the same
     instant as `+01:00` (`timezone_type` 1) vs. `Europe/Berlin` (`timezone_type` 3); array order
     changes the hash for both list and string keys; a mutation-state-carrying model-like object
     drags unrelated state in; a closure makes `serialize()` throw.
   - **New finding not anticipated by §9:** `serialize()` is not a pure value function over an
     object *graph*. A repeated object is emitted as a back-reference (`r:N;`), so a hash over two
     filters differs depending on whether they **share one value instance** or hold two equal ones.
     Today's code is immune only because `Filter::fingerprint()` flattens through
     `FilterData::toArray()`. The decision: keep a flattening step — `Util\Fingerprint::flatten()`,
     which `Filter::fingerprint()` must adopt in step 1. The blast radius was small anyway:
     `ListSpec::hash()`'s only consumer is an in-request memoization array in
     `ArchiveFilterElement`, so instability costs a cache miss, not correctness.
2. **`DcaBuilder::selector()`** (§6.3) has no consumer yet. Confirm whether any form in the initial
   migration actually contributes a subpalette; if none does, defer the API.
3. **`ArchiveFilterElement::buildPreselectData()`** is currently `ListSpec`-aware. Confirm it reduces
   to a generic key-lookup against `buildChoices()` once preselect is stored as choice keys, or
   whether preselect hydration needs its own port method.
4. **Whether `FormHarness` should also absorb `ListSpec::$filters`.** Phase 0 kept them apart:
   `ListSpec` is built in validation and aggregation contexts that never produce a form, so a
   form-carrying object cannot simply replace the array. #48 settled the naming side — the form
   object is `FormHarness`, `ListSpec::$filters` is a plain list keyed by position, and the filter
   alias is the key everywhere else (§8). What remains open is only whether `ListSpec::$filters`
   should become a collection object of its own (it still has a hand-rolled fingerprint loop in
   `hash()`).
5. **Form-time vs. query-time context.** `FilterContext` now carries the `Formula`, and
   `FilterContextFactory::create()` builds it by calling `buildContext($builder, null)`.
   `FormHarnessFactory` needs a `FilterContext` for `buildForm()` and `buildChoices()`, so building a
   form also builds — and discards — a formula, with no value, and an element that aborts aborts the
   form build. Candidate: a value-free `FilterContext` (`list`, `filter`, `engineContext`) for the
   form side, and the formula built only in `ListQueryDirector`, from the decoded value.
6. **How the compiler pass learns a form's value class.** `RegisterFilterFormsPass` must not
   instantiate services, but the value class now comes from an instance method (§3.3, §13.7). The
   pass and `FilterFormRegistry` still read `value` and `default` from the tag, which `AsFilterForm`
   no longer sets, so every form is registered with `value: null` and the §3.4 election finds
   nothing. Options: `value:` back on the attribute, a static method or class constant, or
   reflection on the `@template`. Decide together whether a per-value-class `default` returns.
7. **`FilterElementBuildingEvent` / `FilterElementBuiltEvent`.** Still defined, still aliased to
   `flare.filter_element.{type}.building|built` by `FilterElementListener`, but no longer dispatched
   (their dispatcher was `FilterExecutor`). Re-dispatch them around `buildContext()` with a payload of
   `FilterContextBuilder` + `?ValueInterface`, or remove them and their aliases.
8. **`DateRangeValue` holds `\DateTimeImmutable`.** It normalizes the timezone in its factory, which
   defuses the hashing hazard of §9, but the containment rule and `ValueObjectContainmentTest` exclude
   the type. Store timestamps, or amend §9 and the test to allow a timezone-normalized
   `\DateTimeImmutable`.
