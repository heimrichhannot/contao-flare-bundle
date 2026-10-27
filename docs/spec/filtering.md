---
title: Filter Pipeline
sidebar_position: 2
---

# The Filter Pipeline

As of v0.2, FLARE's filtering is built from four decoupled concepts:

- **Filter Controls** (`HeimrichHannot\FlareBundle\Filter\Control\`) own *how the user supplies a value*: the
  frontend form fields, their own config and backend palette slice, and the translation of submitted data into
  a value object. See [Filter Controls](../dev/filter-controls.md).
- **Value Objects** (`HeimrichHannot\FlareBundle\Filter\Value\`) are immutable, typed records of *what the
  user said* — `BoolValue`, `ChoiceValue`, `DateRangeValue`, `KeywordsValue` — the channel between a control
  and an element. A **value provider** turns the stored `value` column into one.
- **Filter Elements** (`HeimrichHannot\FlareBundle\Filter\Element\`) own *what rows match*: the canonical
  config schema, the backend DCA representation, and the translation of config + value object into predicate
  calls.
- **Predicates** (`HeimrichHannot\FlareBundle\Filter\Predicate\`, formerly "filter types") are small, reusable,
  stateless query-fragment builders — the only components that write SQL conditions.
  See [Predicates](../dev/predicates.md).

A filter is applied in **two stages**: a value-free, role-typed scope (list × filter × engine context) is what
hooks receive before a value exists — an `ElementScope` for the element's `buildContext()` and its ports, a
`ControlScope` for the control; `$scope->get('key')` reads the calling component's own config. The
`FilterContext` is the element's product for one invocation, built once a value is known.

The pipeline, end to end:

```
tl_flare_filter rows ──(collector + transformers + value provider)──▶ Filter[] on the ListSpec
                                                          │
          form building: control->buildForm(builder, scope) per filter ──▶ FormHarness (root form + mounts)
                                                          │
         submit: control->decode(data, scope) ──▶ value object per filter alias
                                                          │
 query building (ListQueryDirector): element->buildContext(builder, scope, value) ──▶ Proposition[]
                                                          │
                      predicate->buildConditions() ──▶ FilterConditionsBuilder[] ──▶ SQL conditions
```

## 1. The `Filter` Value Object

`HeimrichHannot\FlareBundle\Filter\Filter` is the immutable runtime representation of a single filter within
a list. It binds a filter element with its canonical configuration, an optional control with its own, and an
optional value — no DCA or storage specifics.

Constructor properties:

| Property | Description |
|---|---|
| `element` | An `ElementBinding`: `component` (the `FilterElementInterface` — a registered element service or an inline instance), `type` (the registered element type alias, e.g. `flare_bool`, used for the `flare.filter_element.{type}.*` named events and targeting lookups) and `config` (the canonical **element** config following the element's schema, `elm_*` columns) |
| `alias` | The filter's key within the list **and** its form name / query-parameter name. An alias that is not a valid Symfony form name never mounts form children |
| `control` | A `ControlBinding` — `component` (the `FilterControlInterface`), `type` (the registered control type, or the class name of an inline control, used for the `flare.filter_control.{type}.*` named events) and `config` (the canonical **control** config, `ctl_*` columns) — or `null` for a filter without user input (intrinsic) |
| `value` | The filter's `ValueInterface` — the **fixed value** without a control, the **initial value** with one |
| `targetAlias` | Table alias the filter's conditions apply to |
| `targetingForced` | Whether the target alias applies even if the element is not marked as targeted |
| `source` | Provenance for error messages, e.g. `tl_flare_filter.42` |

Both configs are a `ComponentConfig` (`HeimrichHannot\FlareBundle\Config\ComponentConfig`): immutable, holding
scalars, arrays and enums only. `get('key')` returns a value — `null` for an option defined without default and
not set — and throws `UndefinedConfigKeyError` (an `\Error`) for a key the component's schema does not define;
`has('key')` and `all()` complete it. Inside a hook, `$scope->get('key')` is the short way to the own config.

Because `Filter` is immutable, all modification happens through withers that return a new instance:
`withValue()`, `withControl(?ControlBinding)`, `withTargetAlias(?string, bool $forced = true)`.

Filters are created without a database row through the `FilterFactory` service — which resolves registered
element aliases and control names to their services — or by constructing a `Filter` directly around an inline
element instance; see
[Programmatic Filters](../dev/filter-elements/index.md#9-programmatic-filters-without-a-backend-record).
`Filter::fingerprint()` returns a representation (element class, configs, control class and the value's
`fingerprint()`) used by `ListSpec::hash()` for in-request caching.

**Fixed vs. initial value.** The `control` column of a filter record decides the role of its value:

| `control` | `value` | initial display of the list | whole list (readers, validation) |
|---|---|---|---|
| blank (intrinsic) | fixed value | restricted | restricted |
| a control | initial value | pre-filtered, user can change it | not applied |

## 2. Collection

The `ListModelFilterCollector` (`Filter\Collector\`) reads the published `tl_flare_filter` rows of a
`tl_flare_list` record; the `FilterFactory` translates each row into a `Filter`: element config through the
element's transformers (the `configureTransformers()` map, usually the `transformFilterModel()` hook — see
[Config Schema](../dev/filter-elements/index.md#3-config-schema-configureoptions--configuretransformers)), the
control and its config through the control's transformers, and the value through the value provider for the
element's value class.
For every collected filter, a `FilterCollectedEvent` is dispatched before it is added to the list —
listeners may replace the (mutable) `$event->filter`, e.g. to change its config or target alias.

## 3. Form Building

For each filter *with a control* whose alias is a valid Symfony form name, the `FormHarnessFactory` creates a
`ControlScope` and hands a collect-only per-filter builder (`FilterFormBuilderInterface`) to the control's
`buildForm()`. **Single-field controls** declare their one field via `single()` — it is mounted flat on the root
form under the filter's alias (`form[alias]=x`). **Multi-field controls** `add()` children under local names,
which mount as a compound sub-form (`form[alias][from]=x`); `compound()` sets its form options, e.g. a label.
Each mount is hydrated with
`control->hydrate($filter->value, $scope)`.

The per-filter builder carries the `ControlScope` in its attribute bag under `ControlScope::ATTR_SELF`. After the
control built its fields, a `FilterControlBuildFormEvent` is dispatched — listeners can add, remove, or replace
children, adjust the `single()` declaration, or `cancel()` the filter's form entirely. Filters that declare no
fields are not mounted onto the root form.

The factory returns a `FormHarness`: the root form plus a mount↔filter map (`FilterMount` per mounted
filter, keyed by the filter's alias). `FormHarness::getForm()` is the root form. `FormHarness::decode()` calls
each mount's `control->decode()` and returns the value objects keyed by alias — for a submitted form an entry
for every mount (`null` included), for an unsubmitted one the non-null results (the decoded initial values).

## 4. Query Building

When a projector executes the list query, the `ListQueryDirector` processes each filter on the spec:

1. The filter's value is determined by its role: **with a control**, the form's value
   (`$values[$alias] ?? null`) — in the interactive list and its aggregation the submission or, unsubmitted,
   the decoded initial value; in a validation context (readers) there is no map, so the initial value does not
   apply. **Without a control**, the fixed `Filter::$value`, in every context.
2. The `FilterContextFactory` creates the `FilterContext` from an `ElementScope` and the value: the
   element's `buildContext()` runs and translates config + value into one or more **predicate calls** on the
   `FilterContextBuilderInterface`:

   ```php
   $builder->addPredicate(BooleanPredicate::class, ['field' => 'featured', 'value' => true]);
   ```

   Each call's options are resolved against the predicate's own `configureOptions()` schema and recorded
   as a `Proposition` (predicate, resolved options, target alias).
3. Each `Proposition` is executed: the predicate's `buildConditions(FilterConditionsBuilder $builder, array $options)`
   writes parameterized conditions into a `FilterConditionsBuilder` scoped to the call's target alias.

Calling `$builder->abort()` (which throws `AbortFilteringException`) anywhere in the pipeline short-circuits
the list to an empty result set — useful when a required value is missing or invalid. It is a *legitimate*
"matches nothing", distinct from a failure.

## 5. Target Aliases

Every predicate call runs against a table alias — by default `main`, the list's primary table (see
[SQL Query Struct & Aliasing](./query-struct.md)). Elements registered with
`#[AsFilterElement(isTargeted: true)]` respect the target alias configured on the filter record; a
programmatic filter can force one with `Filter::withTargetAlias('my_alias')`. In the backend,
`DcaContext::getTargetTable()` resolves the same aliasing so DCA option lookups match the queried table.

## 6. Failure Handling

Failures follow the two scopes of a filter: the element config and a fixed value bound the **whole list**, a
control and its initial value only bound the **initial display**.

| What fails | Behaviour |
|---|---|
| A filter's **element** cannot be built, **with or without a control** — unknown element type, broken element config (including a transformer that throws on the stored record) — or a filter **without a control** has an unreadable stored (fixed) value or fails at query time | **Fail closed:** the list as a whole fails; error logged |
| Two published filters of a list share an **alias** | **Fail closed:** the second one is recorded as a list failure instead of replacing the first; error logged |
| A filter's **control** cannot be built — its `control` names no control registered for the element's value class, its control config is broken, or its initial value is unreadable | **Fail open:** the filter is kept without control and initial value, bounded only by its element config; warning logged |
| A **controlled filter fails at query time with a non-null value** | Retried **once with value `null`**. Success: only the value is dropped (warning). Failure, or the value was already `null`: the element config is broken and bounds the whole list — fail closed |
| A control's **`buildForm()` or `hydrate()`** throws | **Fail open:** the filter is not mounted and contributes no form field |
| A control's **`decode()`** throws | The filter contributes no value. A **submitted** field gets an "invalid value" form error, so the form is invalid like any other invalid input and the list renders empty; a failed **initial** value is only dropped |

`\Error`s (programming errors) are never wrapped or retried. The transformer resolvers are the one deviation:
they wrap a `\ValueError` from casting a stale stored record like any other broken element config. Stale references (a stored choice whose record was
deleted) are not failures: they match nothing and, for whitelisted filters, drop out of the intersection.

A **failed list is not an empty list:**

- **List view:** the view exposes `flare_list.failed`; the template renders the translated `list.failed`
  message ("the list could not be created") instead of entries, empty-result text and pagination. The HTTP
  status stays **200**; the filter form may still render.
- **Reader:** membership cannot be decided, so the entry is not rendered and the reader answers **500**.
- **Debug mode** rethrows the exception.

`AbortFilteringException` keeps meaning "this filter legitimately matches nothing".

**Which exception to throw.** `FilterException` (and its subclass `UnreadableValueException`) is public API: filter
components — elements, controls, value providers and capability ports, bundled and userland alike — throw it to
report a filter that cannot work with its config, data or value; FLARE adds the filter's source. Every filter hook
catches any `\Exception` and handles it as the table above describes, but only a `FilterException` counts as
expected (warning logged). Any other exception is unexpected: it is logged as an error, and the control hooks rethrow
it, wrapped in a `FilterException`, in **debug mode**, so a broken control is not silently dropped during
development. `FlareException` and the other exceptions in `HeimrichHannot\FlareBundle\Exception` are thrown by FLARE
itself; catch them, but don't throw them. An element aborts with `$builder->abort()`, not by
throwing `AbortFilteringException`.
