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

## 3. Configuration

Flare is fully integrated into the Contao backend. You can find the main configuration under <strong>Layout → Lists&nbsp;&nbsp;<span style={{ opacity: .6 }}>FLARE</span></strong>.

No additional bundle configuration with config files is required for basic usage.
