---
sidebar_position: 10
---

# Engine Mods

Engine mods allow developers to manipulate the Flare `Engine` before a view is created. They are applied in Twig
templates via `flare.addMod(type, options)` and executed during `flare.createView()`. Mods can add filters, change
pagination parameters, or modify any other aspect of the engine's context or list spec.

See the [Templating](../templating#modding-the-flare-engine) page for usage in Twig.

## Architecture

Mods are registered as tagged services (`flare.engine_mod`) and resolved through `EngineModRegistry`. When
`Engine::createView()` is called, each queued mod is retrieved by its type string and applied to a clone of the engine.

The flow:

1. Template calls `flare.addMod('type', { ... })` — the type and config are stored on the engine.
   The config dictionary is optional and defaults to `{}` for mods without options.
2. Template calls `flare.createView()` — the engine clones itself, iterates over queued mods, resolves each from the
   registry, and calls `ModInterface::apply()`.
3. `apply()` runs the mod's logic — `AbstractMod` first resolves the options via Symfony's `OptionsResolver`.
4. The mod manipulates the cloned engine (its `ListSpec`, `ContextInterface`, etc.).
5. After all mods have been applied, the engine projects the view.

## Creating a Custom Mod

### 1. Extend `AbstractMod`

Create a class extending `AbstractMod`. You need to implement three things:

- `getType()` — a unique string identifier used in Twig (`flare.addMod('your_type', { ... })`).
  - Third party vendor mods should use the vendor name as a prefix, e.g. `acme_vendor.my_mod`.
- `configureOptions()` — define required/optional options using Symfony's `OptionsResolver`.
- `__invoke()` — the logic that modifies the engine.

```php
<?php

namespace App\Flare\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Engine\Mod\AbstractMod;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Filter;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BooleanTrueMod extends AbstractMod
{
    public static function getType(): string
    {
        return 'app.boolean_true';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('field');
        $resolver->setAllowedTypes('field', 'string');
    }

    public function __invoke(Engine $engine, array $options): void
    {
        $filter = new Filter(
            type: SimpleEquationFilterElement::TYPE,
            config: [
                'intrinsic' => true,
                'left' => $options['field'],
                'operator' => SqlEquationOperator::EQUALS,
                'right' => '1',
            ],
        );

        $engine->setList($engine->getList()->withFilter($filter));
    }
}
```

### 2. Register the Service

The `ModInterface` carries the `#[AutoconfigureTag('flare.engine_mod')]` attribute, so any class implementing it (or
extending `AbstractMod`) is automatically tagged when autowiring is enabled. No manual service configuration is needed.

If you do not use autowiring, tag the service manually:

```yaml
# config/services.yaml
services:
    App\Flare\Mod\BooleanTrueMod:
        tags: ['flare.engine_mod']
```

### 3. Use in Twig

```twig
{% set view = flare.addMod('app.boolean_true', { field: 'published' }).createView() %}
```

## `ModInterface`

If you need full control, implement `ModInterface` directly instead of extending `AbstractMod`:

```php
namespace HeimrichHannot\FlareBundle\Engine\Mod;

interface ModInterface
{
    public static function getType(): string;
    public function apply(Engine $engine, array $options): void;
}
```

When implementing the interface directly, you must handle option resolution yourself in `apply()`. `AbstractMod` does
this for you — it defines an overridable `configureOptions()` hook, resolves the options with it, and then calls
`__invoke()` with the resolved array.

## `AbstractMod`

`AbstractMod` implements `ModInterface` with the following contract:

| Method               | Purpose                                                          |
|----------------------|------------------------------------------------------------------|
| `getType()`          | Returns the mod's unique type string.                            |
| `configureOptions()` | Configures a Symfony `OptionsResolver`. Override as needed.      |
| `__invoke()`         | Receives the engine and resolved options. Apply your logic here. |
| `apply()`            | Final. Resolves options and calls `__invoke()`. Do not override. |

## What a Mod Can Access

The `Engine` passed to your mod exposes:

- `$engine->getList()` / `$engine->setList()` — the [`ListSpec`](../spec/specifications.md), giving access
  to filters, config, and table setup. `ListSpec` is immutable: derive a new spec with its `with*()`
  methods and set it back on the engine.
- `$engine->getContext()` — the `ContextInterface`, giving access to request-level state like pagination parameters.

### Common operations

**Add a filter** (a [`Filter`](../spec/filtering.md#1-the-filter-value-object) value object; keyed by its
alias, or an auto-generated key):

```php
$engine->setList($engine->getList()->withFilter($filter));
```

**Add a named filter** (an existing key is replaced):

```php
$engine->setList($engine->getList()->withFilter($filter, 'my_filter'));
```

**Remove a filter by key:**

```php
$engine->setList($engine->getList()->withoutFilter('my_filter'));
```

**Change the pagination query parameter:**

```php
$context = $engine->getContext();
if ($context instanceof PaginatedContextInterface) {
    $context->setPaginatorQueryParameter('my_page');
}
```

## Built-in Mods Reference

### `SimpleEquationMod` (type: `equation`)

Adds a simple SQL equation filter to the list.

| Option     | Type                              | Required | Default | Description                    |
|------------|-----------------------------------|----------|---------|--------------------------------|
| `operand1` | `string`                         | yes      | —       | Database column name.          |
| `operator` | `string` or `SqlEquationOperator`| yes      | —       | SQL operator (`=`, `>=`, etc.).|
| `operand2` | `string`, `int`, `array`, `null` | no       | `null`  | Value to compare against.      |
| `name`     | `string` or `null`               | no       | `null`  | Named filter key.              |

### `PageParamMod` (type: `page_param`)

Changes the query parameter used for pagination. Useful when rendering multiple paginated lists in one template.

| Option  | Type     | Required | Default | Description                           |
|---------|----------|----------|---------|---------------------------------------|
| `param` | `string` | yes      | —       | Query parameter name (alphanumeric).  |
