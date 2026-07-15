# OptionsContract

The `OptionsContract` lets a service declare a canonical config schema with Symfony's `OptionsResolver`.

**Interface:** `HeimrichHannot\FlareBundle\Contract\OptionsContract`

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

## Current runtime usage

- **Filter elements** (`AbstractFilterElement`) declare their canonical config schema through this
  contract; FLARE's `FilterOptionsResolver` resolves every filter's config against it. The schema is
  paired with the element's transformers (`TransformerContract`), which translate stored sources onto it.
- **List types** (`AbstractListType`) declare their type schema through this contract; it is resolved on
  top of the framework-owned `BaseListOptions` schema by the `ListOptionsResolver`.
- **Filter types** declare `configureOptions()` directly on `FilterTypeInterface` (not via this
  contract); the options passed to `FilterBuilderInterface::add()` are validated against it.

`configureOptions()` is declarative, memoizable setup — it runs once per class, not per record.
