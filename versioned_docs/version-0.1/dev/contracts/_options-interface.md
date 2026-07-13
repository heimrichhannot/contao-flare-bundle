# OptionsInterface

The `OptionsInterface` lets a service define and validate custom options with Symfony's `OptionsResolver`.

**Interface:** `HeimrichHannot\FlareBundle\Contract\OptionsInterface`

## Method

### `configureOptions(OptionsResolver $resolver): void`

Use this method to define required options, defaults, allowed types, and normalizers.

```php
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->define('left')->required()->allowedTypes('string');

    $resolver->define('operator')
        ->required()
        ->allowedTypes('string')
        ->normalize(static fn (Options $options, string $value): string => strtoupper($value));
}
```

## Important distinction

This contract is not the same as `FormTypeOptionsContract`.

- Use `OptionsInterface` to define and validate custom option structures with `OptionsResolver`.
- Use `FormTypeOptionsContract` to build Symfony form field options for a rendered filter field.

## Current state in this repository

`AbstractFilterElement` implements this interface with a no-op method, and `SimpleEquationElement` overrides it.

At the moment, there is no internal runtime call site in `src/` that invokes `OptionsInterface::configureOptions()`
directly. Treat it as part of Flare's extensibility surface for code that wants to resolve custom options explicitly.
