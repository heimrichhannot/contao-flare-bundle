---
title: Getting Started
sidebar_position: 2
---

# Installation

Flare is a professional Contao bundle that requires **Contao ^4.13 or ^5.0** and **PHP ^8.2**.

## 1. Install via Composer

You can install the bundle using Composer:

```bash
composer require heimrichhannot/contao-flare-bundle
```

## 2. Database Migration

After installation, update your Contao database schema using the Contao Manager or Contao Console:

```bash
php vendor/bin/contao-console contao:migrate
```

:::warning Upgrading to 0.1.10

Version 0.1.10 renames the database column `tl_content.flare_list` to
`tl_content.flare_listId`. The old name collided with the `flare_list` Twig variable that
holds the list view, which broke the default list view template.

Running `contao:migrate` is **required** after updating. A bundled migration performs the
rename and preserves every existing list assignment on your list and reader content
elements. Skipping it is the one way to lose those assignments.

The `flare_list` template variable is unchanged and still holds the view, so custom
templates using `flare_list.entries`, `flare_list.form` and friends need no changes. Only
code reading the raw content-element row field as an ID has to switch to `flare_listId`.

:::

## 3. Configuration

Flare is fully integrated into the Contao backend. You can find the main configuration under <strong>Layout → Listings&nbsp;&nbsp;<span style={{ opacity: .6 }}>FLARE</span></strong>.

No additional bundle configuration with config files is required for basic usage.
