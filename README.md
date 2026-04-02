# FLARE 🔥 Filter, List and Reader Bundle

[![Latest Version on Packagist](https://img.shields.io/packagist/v/heimrichhannot/contao-flare-bundle.svg)](https://packagist.org/packages/heimrichhannot/contao-flare-bundle)
[![PHP Version](https://img.shields.io/packagist/dependency-v/heimrichhannot/contao-flare-bundle/php.svg)](https://packagist.org/packages/heimrichhannot/contao-flare-bundle)
[![Contao Version](https://img.shields.io/packagist/dependency-v/heimrichhannot/contao-flare-bundle/contao/core-bundle.svg)](https://packagist.org/packages/heimrichhannot/contao-flare-bundle)
[![PHPStan](https://github.com/heimrichhannot/contao-flare-bundle/actions/workflows/phpstan.yaml/badge.svg)](https://github.com/heimrichhannot/contao-flare-bundle/actions/workflows/phpstan.yaml)

A Contao CMS bundle for building filterable lists and detail pages — for news, events, or any DCA-based entity.

> [!NOTE]
> Flare is a work in progress. We are actively working on it and will release updates regularly.

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
- No modules, no worries!

<!-- Currently not supported --
- Integration with [terminal42/contao-DC_Multilingual](https://github.com/terminal42/contao-DC_Multilingual)
  and [terminal42/contao-changelanguage](https://github.com/terminal42/contao-changelanguage), respectively
-->

## Installation

Install the bundle via Composer, then update your database:

```bash
composer require heimrichhannot/contao-flare-bundle
```

Requires **Contao ^4.13 or ^5.0** and **PHP ^8.2**.

## Basic Usage

1. Create a new list configuration in the Contao backend under "Layout" &rarr; "Lists&ensp;<span style="opacity:.6">FLARE</span>"
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

## Resources

### Documentation
- **[FLARE 🔥 Documentation](https://heimrichhannot.github.io/contao-flare-bundle/docs/intro)**
- [Symfony Form Component](https://symfony.com/doc/6.4/forms.html)
- [Symfony OptionsResolver Component](https://symfony.com/doc/6.4/components/options_resolver.html)

### Source Code
- [GitHub](https://github.com/HeimrichHannot/contao-flare-bundle)
- [Packagist](https://packagist.org/packages/heimrichhannot/contao-flare-bundle)
- [Contao Extension](https://extensions.contao.org/?p=heimrichhannot/contao-flare-bundle)
