---
title: Migrating from v0.1
sidebar_position: 10
---

# Migrating from v0.1

v0.2 rebuilds the filter subsystem around three decoupled concerns: a **filter element** owns its config schema,
backend DCA and which rows match; a **[filter control](./dev/filter-controls.md)** owns how the user supplies the
value and turns it into a typed value object; and reusable **[predicates](./dev/predicates.md)** write the SQL.
The invoker and DCA-callback subsystems are gone.

**If you only configure lists and filters in the Contao backend, the content stays but the schema changes:**
`tl_flare_filter` columns are renamed and a few settings were reshaped (see
[Database & Backend](#database--backend) — run `contao:migrate` **without deletes** until the migrated site is
verified). Templates and the content elements are
unchanged apart from the failure state described there. This guide is for everyone who upgrades; the
developer sections cover PHP extensions.

For the exhaustive list of deleted APIs, see [Removed in v0.2](./removed-in-v0.2.md).

## Namespaces & Classes

| v0.1 | v0.2 |
|---|---|
| `HeimrichHannot\FlareBundle\FilterElement\FooElement` | `HeimrichHannot\FlareBundle\Filter\Element\FooFilterElement` — all elements moved and renamed with a `FilterElement` suffix; the `::TYPE` strings (e.g. `flare_bool`) are unchanged |
| `Specification\FilterDefinition`, `Collection\FilterDefinitionCollection` | `Filter\Filter` — a single immutable value object; see [Filter Pipeline](./spec/filtering.md) |
| `FilterCollector\*` | `Filter\Collector\*`; `collect()` now returns `array<string\|int, Filter>\|null` |
| `Form\Type\DateRangeFilterType` (Symfony form type) | `Form\Type\DateRangeFormType` — renamed; the query-side `Filter\Type\*FilterType` classes are now `Filter\Predicate\*Predicate` |
| `ListType\ListTypeInterface`, `ListType\AbstractListType` | `List\Driver\ListDriverInterface`, `List\Driver\AbstractListDriver` — "list types" are implemented by **list drivers**; the registered type aliases (e.g. `flare_news`) are unchanged |
| `ListType\NewsListType`, `ListType\GenericDataContainerListType`, ... | `List\Driver\NewsListDriver`, `List\Driver\GenericDataContainerListDriver`, ... — built-in drivers renamed accordingly (see [built-in list types](./reference/list-types.md)) |
| `Contract\ListType\*` | `Contract\ListDriver\*`; `DataContainerContract::getDataContainerName()` is now `resolveDataContainerTable()` — the old name lives on `ListDriverInterface` and resolves the runtime table from canonical config |

## Attributes

| v0.1 | v0.2 |
|---|---|
| `#[AsFilterElement(type:, formType:, palette:, method:, isTargeted:)]` | `#[AsFilterElement(type:, isTargeted:, value:)]` — palette moves into `buildDca()`, the form type into a [filter control](./dev/filter-controls.md), the invoked method is always `buildContext()`; `value:` names the value class the element consumes (none = always intrinsic) |
| — | `#[AsFilterControl(type:)]` — registers a [filter control](./dev/filter-controls.md); new in v0.2 |
| `#[AsListType(type:, dataContainer:, palette:)]` | `#[AsListDriver(type:, dataContainer:)]` — renamed; palette moves into `buildDca()`; `dataContainer` is only a backend default for new records |
| `#[AsFilterInvoker]` | Removed — branch on `$scope->engineContext` inside `buildContext()` or a control hook |
| `#[AsFilterCallback]`, `#[AsListCallback]`, `#[AsFlareCallback]` | Removed — use [`buildDca()` / the DCA events](./dev/dca-builder.md) |

## Filter Element API

The `__invoke(FilterInvocation $invocation, FilterQueryBuilder $qb)` entry point is replaced by a single
lifecycle method on the element, plus a separate control (see the
[custom filter elements guide](./dev/filter-elements/index.md) and the
[custom filter controls guide](./dev/filter-controls.md)):

| v0.1 | v0.2 |
|---|---|
| `formType:` attribute parameter | A [`FilterControlInterface`](./dev/filter-controls.md): `buildForm(FilterFormBuilderInterface $builder, ControlScope $scope)`, `decode()`, `hydrate()` — registered with `#[AsFilterControl]` and chosen per filter record in the `control` field |
| `__invoke(FilterInvocation, FilterQueryBuilder)` | `buildContext(FilterContextBuilderInterface $builder, ElementScope $scope, ?ValueInterface $value): void` — emit predicate calls instead of writing SQL |
| SQL written directly in the element | A [`PredicateInterface`](./dev/predicates.md) service; the element calls `$builder->addPredicate(MyPredicate::class, [...])` |
| `FilterInvocation->getValue()` | `$value` — a typed value object (`BoolValue`, `ChoiceValue`, `DateRangeValue`, `KeywordsValue`) — and `$scope->get('key')` (resolved config) |
| `FilterQueryBuilder::abort()` in the element | `$builder->abort()` on the `FilterContextBuilderInterface` (predicates may still use `FilterConditionsBuilder::abort()`) |
| `$filter->data` / `FilterData` | Removed. Programmatic runtime data is `Filter::$value` — a fixed value without a control, an initial value with one |
| `IntrinsicContract` / `intrinsic` config option | Removed. An element without a value class is intrinsic; otherwise the editor leaves `control` blank (the `value` is then a fixed value) |
| `ChoicesBuilder::add(string\|int $key, Model\|... $value)` | `ChoicesBuilder::add(ChoiceObjectInterface $choice)` — pass a `ContaoModelChoice` or `ValueChoice`; `setLabelForModel()`, `getChoice()`, `addGroup()` and `removeGroup()` are gone |

## Contracts

| v0.1 contract | v0.2 equivalent |
|---|---|
| `FormDataContract::extractFormData()` | The control's `decode()` — returns a value object |
| `RuntimeValueContract::processRuntimeValue()` | Normalize values in the control's `decode()` or the element's `buildContext()` |
| `IntrinsicValueContract::getIntrinsicValue()` | `$value` of a filter without a control — the fixed value |
| `HydrateFormContract::hydrateForm()` | The control's `hydrate()` |
| `FormTypeOptionsContract::handleFormTypeOptions()` | Build options directly in the control's `buildForm()`; third parties use `FilterControlBuildFormEvent` |
| `PaletteContract::getPalette()` | [`DcaContract::buildDca()`](./dev/contracts/dca-contract.md) |

New interfaces: `FilterElementInterface` (required, one method), `FilterControlInterface`,
`ValueProviderInterface`, the capability ports `ChoiceSourceContract` / `DateBoundsContract`,
`OptionsContract` (config schema), `TransformerContract` (source-to-config translation) and `DcaContract`
(backend DCA). `AbstractFilterElement` / `AbstractFilterControl` implement the optional ones.

## Events

| v0.1 | v0.2 |
|---|---|
| `FilterElementInvokingEvent` (`flare.filter_element.{type}.invoking`) | **No successor.** Add or remove filters in a `ListDriverBuildListEvent` (`flare.list_driver.{type}.build_list`), set `Filter::$value` programmatically, or write a custom filter element |
| `FilterElementInvokedEvent` (`flare.filter_element.{type}.invoked`) | **No successor** — same alternatives |
| `FilterFormChildOptionsEvent` (`flare.form.{parent}.child.{name}.options`) | Removed — customize in the control's `buildForm()`, or listen to `FilterControlBuildFormEvent` (`flare.filter_control.{type}.build_form`) |
| `FilterElementFormTypeOptionsEvent` | Removed — same replacement as above |
| `PaletteEvent` (`flare.filter_element.{alias}.palette`, `flare.list.{type}.palette`) | `FilterElementBuildDcaEvent` (`flare.filter_element.{type}.dca`), `ListDriverBuildDcaEvent` (`flare.list_driver.{type}.dca`); controls have `FilterControlBuildDcaEvent` (`flare.filter_control.{type}.dca`) |
| `FilterDefinitionCreatedEvent` | `FilterCollectedEvent` — dispatched per filter collected from the database; `$event->filter` is replaceable |

`flare.form.{formName}.build` is now fired with a `FormHarnessBuildEvent` (formerly `FilterFormBuildEvent`); the
alias is unchanged. The `flare.filter_element.{type}.building` / `.built` events that existed during v0.2
development are gone as well.

The form attribute `flare.choices_builder`, which exposed a filter's `ChoicesBuilder` on the mounted field, was
removed: a control keeps its choices to itself and a value is read back through the choice objects' keys.

## Database & Backend

v0.2 renames the `tl_flare_filter` columns and replaces several settings. **Run `contao:migrate`** after
updating; two migrations ship with the bundle.

:::warning Run the migrations without deletes

Run `contao:migrate` **without** `--with-deletes` and keep it that way until you have verified that the migrated
site works: lists, filters, filter forms and reader pages. The legacy columns are the only copy of the old
settings; the backfill reads them, and you need them to compare or to repeat a migration. Only then run the
proposed DROPs (see below).

:::

The migrations:

- `RenameFilterColumnsMigration` renames the columns (`ALTER TABLE ... CHANGE`, compatible with MySQL 5.7/8 and
  MariaDB): element columns get the prefix **`elm_`** (`fieldGeneric` → `elm_fieldGeneric`, ...), control columns
  **`ctl_`** (`label` → `ctl_label`, `isMultiple` → `ctl_isMultiple`, ...), and `formAlias` becomes **`alias`**.
  `RenameContentListColumnMigration` renames `tl_content.flare_list` to `flare_listId`.
- `BackfillFilterValuesMigration` fills the new framework columns from the legacy settings, **once per row**
  (tracked in the migration-managed marker column `legacyBackfilled`):
  - `control`: filters with `intrinsic` become a blank control; the others get the control matching their
    element — `flare_choice` (archive, field value, DCA select, Codefog tags), `flare_date_range`
    (date range, calendar current), `flare_keywords`, and for bools `flare_checkbox` (`boolMode` = binary) or
    `flare_choice_bool` (ternary).
  - `value`: from `preselect` / `prefill`, only where the old code actually read it, so no leftover becomes a
    restriction.
  - `ctl_boolBinaryChoices`: `null_false` → `true_false` (the `NULL_FALSE` enum case is removed).
  - `ctl_hasEmptyOption` / `ctl_formatEmptyOption(Custom)` / `ctl_placeholder`: derived so choice widgets look
    the same as before (single-selects get an empty option unless expanded and mandatory; an old placeholder
    becomes the custom empty-option label).

**Migrations never delete.** `contao:migrate` is run **without** deletes (`--with-deletes` off, the default).
The legacy columns **`intrinsic`**, **`boolMode`**, **`preselect`** and **`prefill`**, and the
**`legacyBackfilled`** marker, remain in the table until you run the proposed DROPs manually
(`contao:migrate --with-deletes`) — do that only after you tested the migrated site, never before the backfill
has run, because the deletes destroy its source data. On **MariaDB** the old index names may remain as
duplicates of the renamed ones until the deletes have been run; this is harmless.

Backend changes in the filter form:

- The `intrinsic` checkbox is replaced by the **Control** (`control`) field. Leave it blank for a filter that
  applies a fixed value (the new `value` field); choose a control for a filter the user can operate (then
  `value` is the initial value).
- `boolMode` and the `preselect` / `prefill` fields are gone; a bool filter offers both bool controls and swaps
  the palette on change.

## Behaviour Changes

- **Reader pages.** Only fixed values and the elements' own config bound the *whole list*. A bool (or any)
  filter *with* a control no longer restricts reader pages — before, a bool `preselect` also acted as a
  fallback in validation.
- **Failed lists.** A broken filter element (with or without a control), a broken fixed value, or two
  published filters sharing an alias make the list *fail* instead of silently dropping or replacing the filter: the list view renders the `list.failed` message (HTTP 200), and a reader of such a list
  answers 500. A controlled filter that fails with a value is retried once without it: if that works only the value is dropped (warning logged); if it fails too, the element config is broken and the list fails closed like a fixed filter. Custom
  list templates that replace `flare_listview` should check `flare_list.failed`.

## ListSpecification → ListSpec

`ListSpecification` is replaced by the immutable `List\ListSpec`, built by the `List\ListSpecBuilder`
(see [List Specs & Filters](./spec/specifications.md)); programmatic construction goes through the
`ListSpecFactory` service, which accepts a driver instance or a registered type alias and resolves the
list's data container definitively. The spec carries the driver instance as `$spec->driver`; the main
table is `$spec->dc`. Filters are a keyed `array<string, Filter>`
instead of a `FilterDefinitionCollection`. While a list is being built (a driver's `buildList()` hook
or a `ListDriverBuildListEvent` listener), use the mutable builder; on a finished spec, use the withers:

| v0.1 | v0.2 |
|---|---|
| `$spec->getFilters()->add($definition)` | `$builder->addFilter($filter)` while building; `$spec = $spec->withFilter($filter)` afterwards |
| `$spec->getFilters()->set('name', $definition)` | `$builder->addFilter($filter, 'name')` while building; on a finished spec `$spec->withFilter($filter)`, keyed by the filter's alias. Neither replaces: **a taken key or a duplicate alias throws a `FlareException`** (remove the filter first with `removeFilter()` / `withoutFilter()`) |
| `$spec->getFilters()->hasType('flare_bool')` | `hasFilterInstance(BooleanFilterElement::class)` on builder or spec |
| — | `$builder->getFilter('name')`, `$builder->removeFilter('name')`, `$spec->withoutFilter('name')` |
| `$definition->forceTargetAlias('alias')` | `$filter->withTargetAlias('alias')` — **returns a new instance** (`Filter` is immutable) |

Element `define()` factories (e.g. `PublishedFilterElement::define()`,
`SimpleEquationFilterElement::define(...)`) were removed: construct a
[`Filter`](./dev/filter-elements/index.md#9-programmatic-filters-without-a-backend-record) directly with the element's
type alias and canonical config, e.g.
`FilterFactory::create(element: PublishedFilterElement::TYPE, config: [...])`.

## Before / After

A minimal v0.1 element and its v0.2 equivalent:

```php
// v0.1
#[AsFilterElement(type: 'app_city', formType: TextType::class, palette: '{filter_legend},fieldGeneric')]
class CityElement extends AbstractFilterElement
{
    public function __invoke(FilterInvocation $invocation, FilterQueryBuilder $qb): void
    {
        if (!$value = $invocation->getValue()) {
            return;
        }

        $qb->where($qb->column('city') . ' = :city', ['city' => $value]);
    }
}
```

```php
// v0.2 — the element
#[AsFilterElement(type: 'app_city', isTargeted: true, value: KeywordsValue::class)]
class CityFilterElement extends AbstractFilterElement
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('field')->default('city')->allowedTypes('string');
    }

    protected function transformFilterModel(ConfigBuilder $config, FilterModel $model): void
    {
        $config->set('field', $model->elm_fieldGeneric ?: 'city');
    }

    public function buildDca(DcaBuilderInterface $builder, DcaContext $context): void
    {
        $builder->palette('{filter_legend},elm_fieldGeneric');
    }

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        if (!$value = $this->expectValue($value, KeywordsValue::class)) {
            return;
        }

        $builder->addPredicate(SimpleEquationPredicate::class, [
            'operand_left' => $scope->get('field'),
            'operator' => SqlEquationOperator::EQUALS,
            'operand_right' => $value->keywords,
        ]);
    }
}
```

The text input is no longer part of the element: the built-in `flare_keywords` control (also a `KeywordsValue`
producer) is selectable for this element in the backend. A custom input would be a
[filter control](./dev/filter-controls.md).
