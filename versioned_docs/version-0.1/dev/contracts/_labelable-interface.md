# LabelableInterface

The `LabelableInterface` lets an object provide custom translation parameters for dynamically generated choice labels.

**Interface:** `HeimrichHannot\FlareBundle\Contract\LabelableInterface`

:::warning
This interface is currently marked `@experimental` in the source code.
:::

## Method

### `getLabelParameters(): array<string, scalar>`

Return translation placeholders that should be merged into the parameter set used by `ChoicesBuilder`.

These parameters are passed to the translator when Flare renders labels for objects stored in a `ChoicesBuilder`.

## Current runtime usage

When a choice object implements `LabelableInterface`, `ChoicesBuilder` merges the returned parameters into the label
translation context before translating the final label.

This is useful when the label string contains placeholders such as `%title%`, `%count%`, or custom field markers.

## Example

```php
final class TagChoice implements \HeimrichHannot\FlareBundle\Contract\LabelableInterface
{
    public function __construct(
        private readonly string $title,
        private readonly int $count,
    ) {}

    public function getLabelParameters(): array
    {
        return [
            '%title%' => $this->title,
            '%count%' => $this->count,
        ];
    }
}
```
