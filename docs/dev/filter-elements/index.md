# Creating Custom Filter Elements

To create a custom filter element, you need to tag your desired service with the `AsFilterElement` attribute.
Again, no interfaces or inheritance required, only an alias under which to register your filter element.

```php title="/src/Flare/FilterElement/MyCustomFilterElement.php"
<?php

namespace App\Flare\FilterElement;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;

#[AsFilterElement(
    alias: 'app_myCustomFilterElement',
)]
class MyCustomFilterElement
{
    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        // Your custom filter element logic here
    }
}
```

