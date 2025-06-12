---
sidebar_position: 3
---

# Basic Usage

1. Create a new list configuration in the Contao backend under **Layout &rarr; Lists&ensp;<span className="text--muted">FLARE</span>**
2. Each list is an archive of filter elements: Add filters as children to your list configuration
3. Add a list view content element to a page and select the list configuration
4. Add a reader content element to a separate page and select the list configuration
5. Select the reader page in the list configuration
6. Profit!

## What is a List Configuration?

A list configuration is a collection of filter elements that define how entities should be filtered in a list view. Each
list configuration can have multiple filter elements, which can be used to filter the entities based on various
criteria.

Each list configuration is of a specific type, which determines parts of the behavior of the list and first and foremost
the data container of the entities to be fetched. Included **list types** are:
- **News**: For filtering and listing news articles.
- **Events**: For filtering and listing calendar events.
- **Generic Data Container**: For filtering and listing any data container, including custom tables.

If you need your lists to behave differently than the ones provided, you may develop your own list types.
Consult the [Developer Documentation](dev/extending-flare.mdx) for more information.

### Displaying Filters and Lists

A list configuration can be used in a [list view content element](content-elements), which will render:
1. The filter form based on the ([non-intrinsic](#what-does-intrinsic-mean)) filter elements defined in the list configuration;
2. The list of entities based on the applied filters defined in the list configuration.

See [Templating](templating) for more information on how to customize the list view and filter form templates.

### Displaying Readers

[Reader content elements](content-elements) can
be used to display individual entities on detail pages. This content element uses Contao standard auto-item feature to
figure out which entity to display based on the URL. Which of the entity's fields to use as the auto-item is defined in
the list configuration. If you specify a reader page as a redirect target in the list configuration, flare can
automatically generate each entity's detail URL.

## Filter Configuration

Each filter element type specifies its own configuration options. The following options are available for all filter types:
- **Title**: A title that should briefly describe the filter and is shown in the backend listings.
- **Type**: The filter element type to use.
- **Intrinsic**: If checked, the filter is always applied to the list view and not visible in the form shown to the user.
- **Published**: If unchecked, the filter is not shown in the form and not applied when filtering the list view.

### What does _Intrinsic_ mean?
- Each filter has an "intrinsic" option, which means that the filter is always applied to the list view and not visible in the form shown to the user.
- A filter that has intrinsic unchecked is shown in the form and can be used by the user to filter the list view.
- Some filters can only be intrinsic, e.g. the "published" filter. Under the hood, these filters do not specify a Symfony FormType.
