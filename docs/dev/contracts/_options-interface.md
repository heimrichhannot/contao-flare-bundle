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

This contract is not the same as `FilterElementOptionsInterface`.

- Use `Filter\Element\FilterElementOptionsInterface` for a filter element's canonical config schema — it is
  resolved by FLARE's filter pipeline and pairs `configureOptions()` with `configFromRow()`.
- `Contract\OptionsInterface` is a generic marker for any service that wants to expose an
  `OptionsResolver`-based option schema.

## Current state in this repository

At the moment, there is no implementor and no internal runtime call site in `src/` that invokes
`OptionsInterface::configureOptions()` directly. Treat it as part of Flare's extensibility surface for code
that wants to resolve custom options explicitly.
