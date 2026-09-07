# SPEC: Decoupling Filter Forms from Filter Elements

**Status:** Draft / design agreed, not implemented
**Scope:** `src/Filter/`, `src/Form/`, `src/DataContainer/Builder/`, `contao/dca/tl_flare_filter.php`,
`src/EventListener/Contao/ElementDcaListener.php`, `src/Engine/Projector/InteractiveProjector.php`
**Breaking:** yes (`FilterElementInterface`, `Filter::$data`, `tl_flare_filter` schema)

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
- The `single()` vs. compound mount decision (`FilterFormFactory`) determines whether `buildFilter()`
  receives `getSingleValue()` or `get($name)`.

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
FilterElement          — what rows match. Consumes a value object.
FilterForm             — how the user supplies that value. Produces the value object.
Value object           — the typed contract between the two.
```

Registration is **one-directional**: a form binds to a *value object*, never to an element type.
Selectable forms for an element = registry lookup by the element's declared value class.
A mismatch is therefore structurally impossible rather than validated.

---

## 3. Contracts

### 3.1 `FilterFormInterface`

```php
interface FilterFormInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void;

    /**
     * Produces the element's canonical value from the mounted field, or null to contribute nothing.
     */
    public function decode(FormInterface $field, FilterContext $context): ?object;
}
```

A form MAY additionally implement the existing contracts, which then apply to the form's own
config slice: `OptionsContract` (`configureOptions`), `TransformerContract`
(`configureTransformers`), `DcaContract` (`buildDca`).

**`decode()` receives the mounted `FormInterface`, not a pre-flattened DTO.** Only the form knows
whether it wants `getData()`, `getNormData()` or `getViewData()`, and it needs access to the
attributes it set in `buildForm()` (e.g. `flare.choices_builder`). See §7.

### 3.2 `FilterElementInterface` (revised)

```php
interface FilterElementInterface
{
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, ?object $value): void;
}
```

`buildForm()` is removed. Implementations narrow `$value` to their declared value class, so a
mismatched form is an immediate `TypeError` rather than a silently wrong query.

`configureOptions`, `configureTransformers`, `buildDca` stay as they are.

### 3.3 Attributes

```php
#[AsFilterElement(type: self::TYPE, value: BoolValue::class)]
#[AsFilterForm(value: BoolValue::class, requires: [], default: false)]
```

- `AsFilterElement::$value` — the value class this element consumes. `null` means the element has no
  runtime value at all (see §5.3).
- `AsFilterForm::$value` — the value class this form produces. This is the registry key.
- `AsFilterForm::$requires` — capability interfaces the form needs the element to implement (§4.3).
- `AsFilterForm::$default` — the fallback form for that value class. `AsFilterElement` MAY carry a
  `defaultForm:` override.

`AsFilterForm` is repeatable. One form class can serve every element sharing a value class without
repeated per-element declarations.

### 3.4 Capability ports

A form MUST NOT read element-owned config in `decode()`. Anything it needs *pulled* from the element
is an explicit interface:

```php
interface ChoiceSourceContract
{
    public function buildChoices(FilterContext $context): ChoicesBuilder;
}
```

Registry filter for the backend select: value class matches **and** every entry in `requires` is
implemented by the element (plain `instanceof`, resolvable at compile time). This is not a second
pairing axis — the form still never names an element.

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

Because `InteractiveProjector::collectFilterData()` already collects field defaults for unsubmitted
forms, preselect-as-`data` flows through the normal path and every `?? $config['preselect']`
fallback disappears.

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
(`src/Model/FilterModel.php:91-102`). Use `formVariant`.

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
$dca['palettes'][$type] = Str::mergePalettes($prefix, $this->palette, $suffix);
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
already computed as **view data**: `$field->getViewData()` is `['5','7']`.

The fix is therefore §3.1: `decode(FormInterface $field, ...)`. No reverse mapping, no second
`ChoicesBuilder`, no label collisions.

Additionally, fix `add()` so `choice` carries identity rather than the display string.

### 7.3 Division of labour after the change

| stays on the element (via `ChoiceSourceContract`) | moves to the form |
|---|---|
| `createChoices()` | `buildPreselectData()` |
| `getForeignValues()` | `normalizeRuntimeValue()` |
| `getLocalValues()` | `extractSubmittedData()` |

Choice provision is element knowledge (the target field's `foreignKey` relation). The three form-side
methods largely evaporate, since view data is already the scalar.

---

## 8. Programmatic data and `FilterData`

`Filter::$data` (typed `?FilterData`, read at `src/Query/Executor/FilterExecutor.php:54`) becomes
`Filter::$value`, typed as the element's value object. A programmatic caller constructs the value
directly instead of fabricating form-shaped data.

**Consequence: `FilterData` largely disappears** (introduced in `4263611`; partly unwound here).
Both of its jobs relocate:

- `hasSingle()`'s submitted-vs-untouched distinction → answerable from the `FormInterface`.
- `toArray()`'s hashing role → the value object (§9).

`FilterFormBuilder`'s `single()` vs. compound mount decision is unaffected — that is genuinely a
form concern and stays where it is.

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
  `Europe/Berlin` hashes differently. Store a timestamp or normalize the timezone.
- **Contao `Model`** — `ArchiveFilterElement` works with selected models; serializing them drags
  whole rows plus `$arrModified` into the hash, so the fingerprint changes when an unrelated column
  changes. Store ids.
- **Closures** — `serialize()` throws.
- **Array order** — arrays serialize in insertion order, so `['a','b']` and `['b','a']` differ. Sort
  in the constructor where order is irrelevant to the query.

**Enforcement:** one reflection-based test over every class named in an
`#[AsFilterElement(value: ...)]`. This cannot drift from the real properties the way hand-written
`hashKey()` methods would. Add an opt-out `fingerprint(): array` interface only if a future value
object legitimately must hold something non-serializable.

---

## 10. Enforcement summary

| risk | mechanism | when it fires |
|---|---|---|
| form produces the wrong shape | registry keyed by value class | structurally impossible |
| element receives the wrong type | native param type on `buildFilter()` | `TypeError`, immediately |
| form needs something the element can't supply | `requires` + `instanceof` check | container build |
| value object not hashable | reflection test over declared value classes | test suite |

---

## 11. Migration

- New column `formVariant` (`varchar`), plus form-owned columns as they move.
- Drop `intrinsic`, `boolMode`, `boolBinaryChoices` and the `boolMode_binary` subpalette /
  `boolMode` selector entry.
- Contao migration: `intrinsic = 0` → the default form for the element's value class;
  `intrinsic = 1` → `''`.
- `preselect` stays a column but becomes form-owned; its semantics narrow to hydration only, and
  the `unset($preselectOptions['null'])` hack in `BooleanFilterElement::buildDca()` goes away.

---

## 12. Sequencing

The contract set is the entire risk; the remaining elements are mechanical.

1. **Contracts + pilot.** Value objects, `FilterFormInterface`, `AsFilterForm`,
   `AsFilterElement::$value`, `decode(FormInterface)`, `buildFilter(?object)`, registry lookup by
   value class, `requires` check in the compiler pass. Pilot on `BooleanFilterElement` — it has the
   most presentation-config rot (`boolMode`, `boolBinaryChoices`, `label`, the `preselect` overload).
2. **DCA composition.** `DcaBuilder` palette segments, `scope()`, `selector()`; `ElementDcaListener`
   resolves and invokes the form's `buildDca()`.
3. **Migrate the rest.** Nine remaining elements + `CodefogTagsChoiceFilterElement`.
   `FieldValueChoiceFilterElement` carries the §7 fix. `CodefogTagsSearchElement` declares neither
   `buildForm()` nor `buildFilter()` (only `buildDca()`) — it needs `value:` set and nothing else.
4. **Schema migration** per §11.
5. **Fold `FilterData`** out per §8.

---

## 13. Open questions

1. **`ArchiveFilterElement`** is the largest element (658 lines) and mixes choice provision,
   preselect handling and ptable inference. Confirm it fits `ChoiceSourceContract` or needs a second
   capability port. It is the most likely place for this design to need an escape hatch.
2. **Multi-field forms and `requires`** — a compound form (`DateRange`) needs no capability today.
   Confirm no compound case needs per-child capability negotiation.
3. **Form-contributed translations** — form-owned fields need `tl_flare_filter` labels; decide
   whether forms declare them or they stay in the central language files.
4. **Element-owned `{form_legend}` fields** — `CalendarCurrentFilterElement::buildDca()` already
   appends `{form_legend},isLimited` only when not intrinsic (line 142-144). Under §6 that branch
   disappears and `isLimited` becomes form-owned config. Confirm the same holds for
   `SearchKeywordsFilterElement::buildDca()` and `DcaSelectFieldFilterElement::buildDca()`, which
   branch on `intrinsic` the same way.
