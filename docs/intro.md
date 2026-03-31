---
title: Introduction
sidebar_position: 1
---

# FLARE 🔥 <small>Filter, List and Reader Bundle</small>

[heimrichhannot/contao-flare-bundle](https://github.com/heimrichhannot/contao-flare-bundle) is a powerful [Contao CMS](https://contao.org/) extension designed for high-performance filtering, listing, and individual detail views (readers).

Built on a modern, decoupled architecture, Flare provides a flexible and developer-friendly way to manage complex data displays without the overhead of traditional Contao modules.

## Features

- **Decoupled Architecture**: Clean separation of Specification, Context, and View.
- **Context-Aware Filtering**: Different filtering logic for interactive lists, aggregations, or exports.
- **Symfony Forms Integration**: Filter forms are built using standard Symfony FormTypes, ensuring full compatibility and easy customization.
- **High Performance**: Optimized SQL query building with structured aliasing and automatic join resolution.
- **Custom Pagination**: Built-in paginator that is robust and easy to style (Twig-based).
- **Detail Views (Readers)**: Full support for individual entity views using Contao's `auto_item` feature.
- **Developer First**: PHP 8 attributes (`#[AsListType]`, `#[AsFilterElement]`, `#[AsFilterInvoker]`) for rapid development and clear code discovery.
- **Extensible**: Easy to add custom list types, filter elements, and global query manipulations via events.
- **No Modules Required**: Everything is managed via Content Elements and backend configurations.
