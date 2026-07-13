---
title: Content Elements
sidebar_position: 4
---
# Content Elements for displaying Filters, Lists, and Readers

Flare provides multiple ways to configure and display lists and filters in your Contao pages, and content elements are
the primary way to do so. The built-in content elements can be used in your Contao articles to display lists and readers.
These content elements are designed to work seamlessly with the list and filter configurations.

## List View

Selecting the **List view <span style={{opacity:.6}}>[FLARE]</span>** option in the content element configuration allows you to display a list of entities
based on a specific list configuration. This content element will render the filter form and the list of entities
according to the selected configuration, including pagination.

It asks you to provide a **form name**, which is used to identify the filter form in the frontend.
The form name is also used to store the filter state in the URL query parameters, allowing users to bookmark or share
the filtered view.

### Separating Filter Form and List

The same mechanism can be leveraged to **separate filter form and listing** into multiple content elements, allowing you to
place your filter form in one place and the resulting list in another. For this mechanism to work as intended, ensure
that you select the appropriate **form-only or list-only templates** in the respective content elements.

## Detail Reader

Selecting the **Detail reader <span style={{opacity:.6}}>[FLARE]</span>** option in the content element config allows you to display a single entity
that is part of a list. This content element uses Contao's standard **auto-item** feature to determine
which entity to display from a unique ID or alias in the URL. Which field is used as the auto-item is defined in the
list configuration.

## Linking Between List and Reader

Which pages list entries and readers link to is configured on the list configuration under **Link Settings**:

- **Default Detail Reader Page**: the page opened when clicking a list entry.
- **Default List View Page**: the page opened when clicking a reader's back-to-list button.

Both defaults can be overridden per content element: the **List view** element offers **Override Detail Reader Page**,
and the **Detail reader** element offers **Override Back to List View Page**. A content element override takes
precedence over the list configuration's default.

The back-to-list button preserves the list view's filter and pagination state, including when navigating between
reader pages. See [Templating / Back-to-List Button](templating.mdx#back-to-list-button) for how to render and
customize it.

## Default Templates

The default template of each content element comes with a **bright red warning message** that indicates that you
should override the template in your theme. As each page requires different styling anyway, we chose to only provide a
very limited set of templates that you can use as a starting point.

:::info
When the default templates are rendered in a **development environment, debug mode, or preview mode,** blue areas
showing the entity properties’ keys and values are displayed to help you understand what data is available in the
template.
:::
