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

After installation, update your Contao database schema:

```bash
# Using the Contao Console
php vendor/bin/contao-console contao:migrate

# OR via the Contao Install Tool or Manager
```

## 3. Configuration

Flare is fully integrated into the Contao backend. You can find the main configuration under **Layout → Lists (FLARE)**.

No additional bundle configuration in `config/config.yaml` is required for basic usage.
