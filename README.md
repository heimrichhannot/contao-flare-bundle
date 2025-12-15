# FLARE 🔥 Filter, List and Reader Bundle

This Contao CMS extension streamlines the filtering and listing of entities on the frontend while also supporting individual detail views (readers).

> [!NOTE]
> Flare is a work in progress and is not yet feature-complete. We are actively working on it and will release updates regularly.


## Installation

Install the bundle via Composer:

```bash
composer require heimrichhannot/contao-flare-bundle
```

Requires **Contao ^4.13 or ^5.0** and **PHP ^8.2**.


## Features

- Filter and list entities (e.g. news, events, or any generic data-container)
- Ease of use:
    - Only one place to manage list and filter configurations
    - Only two content elements (a list view with filter, and a reader)
- Filter forms created and displayed using [Symfony Forms](https://symfony.com/doc/6.4/forms.html)
- Pagination included (not based on Contao's pagination, this one is actually good!)
- Individual detail views (readers) using the Contao standard `auto_item` feature
- Batteries-included: Comes with a set of predefined filter and list types
- Customizable filter and list templates
- Extensible with custom filter and list types
- Integration with [terminal42/contao-DC_Multilingual](https://github.com/terminal42/contao-DC_Multilingual)
  and [terminal42/contao-changelanguage](https://github.com/terminal42/contao-changelanguage), respectively
- No modules, no worries!


## Usage

1. Create a new list configuration in the Contao backend under "Layout" &rarr; "Lists&emsp;FLARE"
2. Each list is an archive of filter elements: add filters as children to the list configuration
3. Add a list view content element to a page and select the list configuration
4. Add a reader content element to a separate page and select the list configuration
5. Select the reader page in the list configuration
6. Profit!


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


## Events

FLARE dispatches several events that can be used to modify the behavior of the filter and list types.

> [!NOTE]
> Flare's event namespace is `HeimrichHannot\FlareBundle\Event`.

Some events have an aliased name, allowing event listeners to target them more specifically.
These events are dispatched twice: once using their generic event class name, and again using their specific aliased name.
This way you can implement listeners for both the generic event and the aliased event depending on your needs.

### Form Generation

FLARE dispatches events when generating the filter form.

#### `FilterFormBuildEvent`
_Alias: `flare.form.<form_name>.build`_

Dispatched when building the filter form, after all filter elements have been assembled.

#### `FilterFormChildOptionsEvent`
_Alias: `flare.form.<parent_form_name>.child.<child_form_name>.options`_

Dispatched when assembling the options for each filter element's child form field.

> [!NOTE]
> These are the options passed to the Symfony Form type (the third argument of `$builder->add()`), not “Contao field
> options”. Selectable items are configured via `choices` (and related) options.

### Item Retrieval

FLARE dispatches events when retrieving items from the database.

#### `FetchAutoItemEvent`
Dispatched when retrieving an `auto_item` from the database, e.g., when a reader is displayed.

#### `FetchCountEvent`
_Alias: `flare.list.<list_type>.fetch_count`_

Dispatched when retrieving the total count of items that belong to a list.

#### `FetchListEntriesEvent`
_Alias: `flare.list.<list_type>.fetch_entries`_

Dispatched when retrieving the sorted and paginated items that belong to a list.

### Filter Element

Flare dispatches events when filter elements are being invoked.

#### `FilterElementInvokingEvent`
_Alias: `flare.filter_element.<filter_alias>.invoking`_

Dispatched before a filter element is being invoked.

#### `FilterElementInvokedEvent`
_Alias: `flare.filter_element.<filter_alias>.invoked`_

Dispatched after a filter element has been invoked.

### List View

Flare dispatches events when a list view is built and rendered.

#### `ListViewCreateBuilder`
Dispatched when creating a list view builder.

#### `ListViewBuildEvent`
Dispatched when building the list view.

#### `ListViewRenderEvent`
Dispatched when rendering the list view.

#### `ListViewDetailsPageUrlGeneratedEvent`
Dispatched when generating each URL to the detail page.

### Details Reader

Flare dispatches events when a details reader is rendered.

#### `ReaderRenderEvent`
Dispatched when rendering a reader.

### Palette Manipulation

Flare dispatches events when a list configuration's or filter element's palette is being assembled.

#### `PaletteEvent`
_Alias: `flare.list.palette`_

Dispatched when assembling the palette of a list.

#### `PaletteEvent`
_Alias: `flare.filter.palette`_

Dispatched when assembling the palette of a filter.


## Extending FLARE

We encourage you to extend FLARE with custom filter and list types.
This is done by creating a new class with the `#[AsFilterElement]` or `#[AsListType]` attributes and implementing the `__invoke()` method.
See the examples below.

### Creating a custom filter element

```php
<?php

namespace App\Flare\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\FilterElement\HydrateFormContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\Form\FormInterface;

/**
 * Create a custom filter element by using the AsFilterElement attribute.
 *
 * The **alias** is used to reference the filter element in the Contao backend
 *   and has to start with a letter and can only contain letters, numbers, and
 *   underscores.
 *
 *   USE A VENDOR PREFIX on the alias of each custom filter or list-type to
 *   prevent naming conflicts. For example: 'mycompany_awesomenessRating'.
 *   Use 'app_' as prefix in your application code that is not distributed as
 *   a library. All flare-included filter elements use 'flare_' as prefix.
 *
 * The **palette** parameter is used to define the basic contao palette that
 *   is inserted between the title and footer legends when editing a filter
 *   element in the Contao backend. (optional)
 *
 * The **formType** parameter is used to specify the Symfony FormType that is
 *   used to render the filter form in the frontend. Feel free to define your
 *   own FormType or use one of many provided by Symfony.
 *
 * If you do not specify a formType, the filter element can only be intrinsic.
 *
 * You MAY implement additional contract interfaces that allow you to modify
 *   the behavior of the filter element in various ways. These interfaces are
 *   documented in the following code on the methods that they require.
 */
#[AsFilterElement(
    alias: MyCustomElement::TYPE,
    palette: '{custom_legend},customFilterField',
    formType: AnySymfonyFormType::class,
)]
class MyCustomElement implements FormTypeOptionsContract, HydrateFormContract, PaletteContract
{
    public const TYPE = 'app_custom';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        /**
         * The FilterContext provides access to the filter element's configuration.
         * All fields that have been configured in the backend are available here.
         *
         * See {@see \HeimrichHannot\FlareBundle\Filter\FilterContext} for available
         *   methods and properties.
         *
         * @var FilterModel $filterModel
         */
        $filterModel = $context->getFilterModel();
        
        /**
         * The submitted form data and its type is dependent on the form type that is used.
         * If the form has not yet been submitted, this will be null.
         *
         * @var mixed $submittedData
         */
        $submittedData = $context->getSubmittedData();

        /**
          * {@see \HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder} to see how it works,
          *   although practically you only need to use the where() and bind() methods.
          *
          * Using `$qb->where(...)` multiple times will concatenate the conditions with AND.
          *   For a usage example with OR, see below.
          */
        $qb->where('someColumn = :custom')
            ->setParameter('custom', $filterModel->myCustomField);

        /**
         * For more complex queries, you may also use doctrine's expression builder,
         *   which can be easily accessed through the FilterQueryBuilder::expr() method.
         *
         * The following example also demonstrates how to create an OR query condition.
         */
        $qb->whereOr(
            $qb->expr()->isNotNull('anotherColumn'),
            $qb->expr()->eq('someColumn', ':custom'),
            'secondColumn = :second',  // functionally equivalent to line above
        );
        $qb->setParameter('custom', $filterModel->myCustomField);
        /*
         * `$qb->setParameter(...)` accepts an optional third value to explicitly specify a
         *   PDO-type of the parameter. See {@see \Doctrine\DBAL\ParameterType} and
         *   {@see \Doctrine\DBAL\ArrayParameterType} for available types.
         *   This is only necessary when the data type cannot be inferred from the value
         *   (e.g., on some mixed-type arrays or objects) and is usually not needed.
         */
        $qb->setParameter('second', $filterModel->secondValue,
                /* optional PDO-type: */\Doctrine\DBAL\ParameterType::STRING);
        
        /**
         * You can also abort the query if you want to stop filtering altogether.
         *
         * Calling {@see FilterQueryBuilder::abort()} will throw an exception and stop
         *   the execution of further filter elements. The filtering logic will handle
         *   the exception and return an empty result set.
         */
        if ($submittedData === 'make-me-break') {
            $qb->abort(); // Filter invocation will end here.
        }
    }

    /**
     * Specify options, load, and save callbacks for the dca fields when this filter element is used.
     * Use the AsFilterCallback attribute just like you would on a regular Contao Data Container, only
     *   with **specific arguments, that are automatically injected on demand**.
     *
     * #[AsFilterCallback(self::TYPE, 'fields.<field>.options')]
     * MAY receive these optional, auto-injected arguments:
     *   - {@see \HeimrichHannot\FlareBundle\Model\FilterModel}
     *   - {@see \HeimrichHannot\FlareBundle\Model\ListModel}
     *   - {@see \Contao\DataContainer}
     * MUST return an array of options.
     */
    #[AsFilterCallback(self::TYPE, 'fields.myCustomField.options')]
    public function getMyCustomFieldOptions(ListModel $listModel, /* ... */): array
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
     * MUST return the modified value.
     */
    #[AsFilterCallback(self::TYPE, 'fields.myCustomField.load')]
    #[AsFilterCallback(self::TYPE, 'fields.myCustomField.save')]
    public function onLoadSave_myCustomField(mixed $value, /* ... */): mixed
    {
        return $value;
    }

    /**
     * Implement the `FormTypeOptionsContract` interface to modify the form type options.
     *   e.g. to add choices to a choice field.
     *
     * The result of this method is passed to the Symfony FormType during form creation.
     *   i.e. `Symfony\Component\Form\AbstractType::buildForm(..., array $options)`.
     * 
     * To ease the process of adding choices to a form type, we provide a ChoicesBuilder
     *   abstraction layer. An instance of this class is always passed to the method, but
     *   its usage is optional.
     *
     * This method should return an array of options related to and configured by the
     *   respecitve Symfony form type. Returning invalid options will result in an error.
     */
    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        // This is how to use the choices-builder.
        $choices
            ->enable()  // Disabled by default. Will be ignored unless enabled.
            ->add('some_key', 'A Contao\Model instance, value, or label')
            ->add('another_key', 'Another string or object from which a label can be made')
            /**
             * You MAY add {@see \Contao\Model} instances as choices.
             *   When adding models, specify how their label should be created by setting labels
             *   with placeholders. The placeholders are replaced with the respective properties
             *   of the model instance.
             *
             * **Sensible default labels are already defined for the most common models.**
             * 
             * You might as well specify Symfony translation labels scoped to 'flare_form'.
             */
            ->setLabel('%title%')  // Define the default label for all passed model instances.
            /**
             * When setting labels, you MAY also specify the model class or table name to apply
             *   the label to all instances of that class or respective table. This way, you can
             *   add choices of multiple models to the same field.
             * 
             * Use respectively: 
             *   - {@see ChoicesBuilder::setLabelForTable()}
             *   - {@see ChoicesBuilder::setLabelForClass()}
             *   - {@see ChoicesBuilder::setLabelForModel()}
             */
            ->setLabelForTable('%id% - %title%', 'tl_news');

        // ... this is all you need to do to add choices to a choice field. The respecive choice
        //     callback options for the Symfony FormType are created and applied automatically.

        return [
            'any_option' => 'any_value',
        ];
    }

    /**
     * Implement the `HydrateFormContract` interface to modify the form field after it has been created.
     * This is useful for setting default values, adding attributes, or even changing the field type.
     */
    public function hydrateForm(FilterContext $context, FormInterface $field): void
    {
        // example: set a default value that is stored in the filter model
        
        $filterModel = $context->getFilterModel();

        if ($preselect = \Contao\StringUtil::deserialize($filterModel->preselect ?: null))
        {
            $field->setData($preselect);
        }
    }

    /**
     * Implement the `PaletteContract` interface to assemble custom palettes for the filter element.
     *   e.g. to respect certain conditions following the filter element's configuration.
     *
     * {@see \HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig} to see available methods.
     *
     * The method should return the palette string (or null if the default palette that is defined
     *   in the `AsFilterElement` attribute should be used).
     *
     * Just like the attribute palette, the returned palette string is inserted between the title
     *   and footer legends. But here you can manipulate the palette string to your liking, e.g.
     *   to conditionally add fields or legends, depending on the respective configuration.
     *
     * Tip: You may also use Contao's PaletteManipulator and apply it to an empty string to create
     *   the desired palette.
     */
    public function getPalette(PaletteConfig $config): ?string
    {
        // e.g. $filterModel = $config->getFilterModel();
        
        return '{custom_legend},customFilterField';
    }
}
```