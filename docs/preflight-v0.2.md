---
title: v0.2 Release Preflight
unlisted: true
---

# v0.2 Release Preflight

:::warning[Maintainers only]

This page is intentionally **unlisted** (reachable by URL, not in the sidebar). It tracks code removals that
must land in the bundle before the v0.2 release is tagged. **Delete this page when v0.2 ships.**

:::

The v0.2 docs already describe these APIs as removed — the code still ships them on the release branch:

## Pending code removals

- [ ] **`flare_enclosure_files` Twig function** — remove:
  - the `@deprecated` runtime method `FlareRuntime::getEnclosureFiles()` (`src/Twig/Runtime/FlareRuntime.php`)
  - its registration in `FlareExtension::getFunctions()` (`src/Twig/Extension/FlareExtension.php`)
- [ ] **`PtableInferrer` deprecated members** (`src/InferPtable/PtableInferrer.php`) — three `@deprecated`
  members, two explicitly marked "Removal pending for v0.2":
  - the legacy accessor deprecated in favor of `getEntityDca()`
  - the legacy method deprecated in favor of `getInferredPtable()` (removal pending v0.2)
  - the method whose return type changes to `void` / visibility changes per its `@deprecated` note

## Docs alignment

- [ ] After the removals land, re-run the stale-token check over `docs/docs/`
      (`flare_enclosure_files` must only appear in `removed-in-v0.2.md` and this page).
- [ ] Follow the release procedure in `AGENTS.md` (relabel versions in `docusaurus.config.js`,
      bump `latestDocsPath`), then **delete this page**.
