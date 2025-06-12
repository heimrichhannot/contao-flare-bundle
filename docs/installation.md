---
title: Getting Started
sidebar_position: 2
---

# Installation

:::note
Flare is a work-in-progress and not yet feature-complete. We are actively working on it and will release updates regularly.
At this point, it is not recommended for userland production use, which is why we are not yet tagging a stable release.
:::


Install the bundle via Composer:

```bash
composer require heimrichhannot/contao-flare-bundle
```

Requires **Contao ^4.13 or ^5.0** and **PHP ^8.2**.

Then, update your Contao database by running:

```bash
php vendor/bin/contao-console contao:migrate
```

After that, you can start using Flare in your Contao installation. You can find the configuration options in the Contao backend under **Layout &rarr; Lists&ensp;<span className="text--muted">FLARE</span>**.
