# Creating Custom List Types

To create a custom list type, you need to tag your desired service with the `AsListType` attribute.
No interfaces or inheritance required, only an alias under which to register your list type. And, in the most
basic configuration, specify the data container you want to use for your list type.

```php title="/src/Flare/ListType/MyCustomListType.php"
<?php

namespace App\Flare\ListType;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;

#[AsListType(
    alias: 'app_myCustomListType',
    dataContainer: 'tl_app_any_table'
)]
class MyCustomListType
{
}
```
