# FLARE 🔥 Filter, List and Reader Bundle

This Contao CMS extension streamlines the filtering and listing of entities on the frontend while also supporting individual detail views (readers).

## Installation

Install the bundle via Composer:

```bash
composer require heimrichhannot/contao-flare-bundle
```

Requires **Contao ^4.13 or ^5.0** and **PHP ^8.2**.

## Features

- Filter and list entities (e.g. news, events, or any generic data-container)
- Filter forms created and displayed using Symfony FormTypes
- Pagination included (not based on Contao's pagination, this one is actually good!)
- Individual detail views (readers) using the Contao standard auto_item feature
- Customizable filter and list templates
- Batteries-included: Comes with a set of predefined filter and list types
- Easy to extend with custom filter and list types
- Easy to use: Only one place to manage list and filter configurations
- Easy to use: Only two content elements (a filter and list view and a reader)
- No modules, no worries!


## Usage

1. Create a new list configuration in the Contao backend under "Layout" &rarr; "Lists&emsp;FLARE"
2. Each list is an archive of filter elements
3. Add filters as child elements to the list configuration
4. Add a list view content element to a page and select the list configuration
5. Add a reader content element to a separate page and select the list configuration
6. Select the reader page in the list configuration
7. Profit!


### Filter Configuration

Each filter element type specifies its own configuration options. The following options are available for all filter types:
- **Title**: A title that should briefly describe the filter and is shown in the backend listings.
- **Type**: The filter element type to use.
- **Intrinsic**: If checked, the filter is always applied to the list view and not visible in the form shown to the user.
- **Published**: If unchecked, the filter is not shown in the form and not applied when filtering the list view.

#### What is intrinsic?
- Each filter has an "intrinsic" option, which means that the filter is always applied to the list view and not visible in the form shown to the user.
- A filter that has intrinsic unchecked is shown in the form and can be used by the user to filter the list view.
- Some filters can only be intrinsic, e.g. the "published" filter. Under the hood, these filters do not specify a Symfony FormType.


## Extending FLARE

We encourage you to extend FLARE with custom filter and list types.
This is done by creating a new class with the `#[AsFilterElement]` or `#[AsListType]` attributes and implementing the `__invoke()` method.
See the examples below.

### Creating a custom filter element

```php
<?php

namespace App\Flare\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsFilterElement(alias: MyCustomElement::TYPE)]
class MyCustomElement implements PaletteContract
{
    public const TYPE = 'app_custom';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        /** {@see \HeimrichHannot\FlareBundle\Filter\FilterContext} to see available methods */
        $filterModel = $context->getFilterModel();

        /**
          * {@see \HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder} to see how it works,
          *   although practically you only need to use the where() and bind() methods.
          *
          * $qb->bind(...) accepts an optional third parameter for the PDO-type of the value.
          *   {@see \Doctrine\DBAL\ParameterType} and {@see \Doctrine\DBAL\ArrayParameterType} for available types.
          *   This only becomes necessary when the data type cannot be inferred from the value,
          *   e.g. when using some arrays or objects.
          */
        $qb->where('my_custom_field = :custom')
            ->bind('custom', $filterModel->myCustomValue, \Doctrine\DBAL\ParameterType::STRING);
            
        /**
         * For more complex queries, you may also use doctrine's expression builder,
         *   that comes with the FilterQueryBuilder
         */
        $qb->where($qb->expr()->eq('my_custom_field', ':custom'))
            ->bind('custom', $filterModel->myCustomValue);
    }

    /**
     * Specify the options, load, and save callbacks for the dca fields when this filter element is used.
     * Use the AsFilterCallback attribute just like you would on a regular Contao Data Container, only
     *   with **specific arguments, that are automatically injected on demand**.
     *
     * #[AsFilterCallback(self::TYPE, 'fields.<field>.options')]
     * MAY receive these optional, auto-injected arguments:
     *   - {@see \HeimrichHannot\FlareBundle\Model\FilterModel}
     *   - {@see \HeimrichHannot\FlareBundle\Model\ListModel}
     *   - {@see \Contao\DataContainer}
     */
    #[AsFilterCallback(self::TYPE, 'fields.myCustomValue.options')]
    public function getMyCustomValueOptions(ListModel $listModel): array
    {
        if (!$listModel->dc) {
            return [];
        }

        return DcaHelper::getFieldOptions($listModel->dc);
    }
    
    /**
     * #[AsFilterCallback(self::TYPE, 'fields.<field>.load')] AND
     * #[AsFilterCallback(self::TYPE, 'fields.<field>.save')]
     * MUST receive this first argument:
     *   - mixed $value
     * MAY receive these optional, auto-injected arguments:
     *   - {@see \HeimrichHannot\FlareBundle\Model\FilterModel}
     *   - {@see \HeimrichHannot\FlareBundle\Model\ListModel}
     *   - {@see \Contao\DataContainer}
     */
    #[AsFilterCallback(self::TYPE, 'fields.myCustomValue.load')]
    #[AsFilterCallback(self::TYPE, 'fields.myCustomValue.save')]
    public function onLoadSave_myCustomValue(mixed $value, ListModel $listModel): mixed
    {
        return $value;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        // e.g. $filterModel = $config->getFilterModel();
    }
}
