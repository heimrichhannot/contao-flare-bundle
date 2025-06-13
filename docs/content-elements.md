---
title: Content Elements
sidebar_position: 4
---
# Displaying Filters, Lists, and Readers

Flare provides multiple ways to configure and display lists and filters in your Contao pages, and content elements are
the primary way to do so. You may also inject lists and filters directly into your own templates using our custom-built Twig functions, which
allow you to provide list and filter configurations on the fly, without the need for content elements.

## Content Elements

Flare provides a set of content elements that can be used in your Contao articles to display lists and readers.
These content elements are designed to work seamlessly with the FLARE list and filter configurations.

### FLARE List View

Selecting the **FLARE List View** option in the content element configuration allows you to display a list of entities
based on a specific list configuration. This content element will render the filter form and the list of entities
according to the selected configuration.

It asks you to provide a **form name**, which is used to identify the filter form in the frontend.
The form name is also used to store the filter state in the URL query parameters, allowing users to bookmark or share
the filtered view.

#### Separating Filter Form and List

The same mechanism can be leveraged to **separate filter form and listing** into multiple content elements, allowing you to
place your filter form in one place and the resulting list in another. For this mechanism to work as intended, ensure
that you select the appropriate **form-only or list-only templates** in the respective content elements.

### FLARE Detail Reader

Selecting the **FLARE Detail Reader** option in the content element config allows you to display a single entity
that is part of a list. This content element uses Contao's standard **auto-item** feature to determine
which entity to display from a unique ID or alias in the URL. Which field is used as the auto-item is defined in the
list configuration.

## Default Templates

The default template of each content element comes with a **bright red warning message** that indicates that you
should override the template in your theme. As each page requires different styling anyway, we chose to only provide a
very limited set of templates that you can use as a starting point.

:::info
When the default templates are rendered in a **development environment, debug mode, or preview mode,** blue areas
showing the entity properties’ keys and values are displayed to help you understand what data is available in the
template.
:::
