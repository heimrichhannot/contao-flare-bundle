# Positive Punkte (nicht actionable)

Positivbefunde aus beiden Audits (claude 2607171801, codex 2607171755), am Stand `5940ad6` (2026-07-20) nachgeprüft und weiterhin zutreffend. Bewusst aus den actionable Dateien herausgehalten — dieser Katalog dient dazu, dass diese Punkte in künftigen Reviews nicht erneut als Verdachtsfälle aufschlagen.

## Architektur & Design

- Kern-DTOs `ListSpec` und `Filter` sind `final readonly` mit `with*()`-Kopiersemantik (`src/List/ListSpec.php:26`, `src/Filter/Filter.php:21`); `type` und `dc` sind explizite Spec-Properties und fließen zusammen mit Driver-Klasse, Source, Config und Filter-Fingerprints in den Spec-Hash ein (`src/List/ListSpec.php:113-122`). *(beide)*
- `ListSpecFactory` ist der zentrale Konstruktionspfad für Typ-, Driver-, Config- und Data-Container-Auflösung; `OptionsResolver` an den Konstruktionsgrenzen macht Configfehler früh sichtbar. *(beide)*
- Transformer-Caches sind nach `(type, class)`-Paar getrennt memoiziert (`src/Filter/Resolver/FilterTransformerResolver.php:35-47`) — kein aliasübergreifendes Teilen von Konfiguration. *(ursprüngliches Audit-Finding, inzwischen gefixt)*
- Compiler-Passes exponieren Typ-Services als Aliase auf die Original-Definition (`src/DependencyInjection/Compiler/RegisterListDriversPass.php:47`, analog `RegisterFilterElementsPass.php:47`) — Container und Registry liefern dieselbe Instanz. *(claude)*
- Erweiterbarkeit intern bewiesen: Die ContaoCalendar-Integration ersetzt Projector/Loader/View ausschließlich über `supports()`/`priority()` (`src/Integration/ContaoCalendar/Projector/EventsInteractiveProjector.php:25-30`), ohne Kern-Services zu überschreiben. *(beide)*
- Collect-only `FilterFormBuilder` mit klaren Fehlerbarrieren (`addEventSubscriber(): never`, `getForm(): never` — `src/Form/FilterFormBuilder.php:65,74`). *(claude)*
- Named-Dispatch-Events als klare Alternative zu Service-Overrides; einheitliches, schlankes Muster (`src/EventListener/NamedDispatch/`). *(beide)*
- Lifecycle-Taxonomie `configure*` vs. `build*` konsequent über Filter-Elemente und List-Driver durchgezogen. *(beide)*
- Export-Achse bewusst unimplementiert und konsistent verdrahtet: `ExportProjector::supports()` → `false` (`src/Engine/Projector/ExportProjector.php:17-19`), keine broken References. *(claude)*
- DI-Tags auf einheitlichen `flare.*`-Namespace konsolidiert; Event-Klassen durchgängig im readonly-Property-Stil (Ausnahme by design: Render-Event-Familie mit `ModifiesTemplateTrait`). *(claude)*

## Security & Query-Safety

- Identifier-Validierung durchgängig: `FilterQueryBuilder::column()` erzwingt Regex `^[a-zA-Z0-9_]+$` + `quoteIdentifier()` (`src/Query/FilterQueryBuilder.php:51-57`); rohe SQL-Fragmente der FilterTypes interpolieren nur das validierte Ergebnis (z. B. `src/Filter/Type/PublishedFilterType.php:34,42`).
- Werte strikt parametrisiert: Parameternamen regex-validiert (`src/Query/FilterQueryBuilder.php:127`), Prefix-Rewriting ebenfalls (`:252`); Werte gelangen nie als String-Literal in SQL. **Keine SQL-Injection über anonyme Frontend-Requests gefunden.** *(beide)*
- ORDER BY abgesichert: `SortOrder` validiert Alias und Spalte via `Str::isValidSqlName()` (`src/Sort/SortOrder.php:134-138`).
- Serialisierte Spaltensuche gehärtet: `SqlHelper::findInSerializedArrayColumn()` nutzt `preg_quote` und quotet das Pattern über die Connection (`src/Util/SqlHelper.php:16,23`).
- Keine PHP-Object-Injection: alle nativen `unserialize()`-Aufrufe mit `['allowed_classes' => false]` (`src/Sort/SortOrder.php:65`, `src/Paginator/PaginatorConfig.php:204`).
- CSRF-Design korrekt: Filterformular bewusst GET ohne CSRF-Token für idempotente Queries (`src/Filter/Factory/FilterFormFactory.php:45`).
- Paginator-Input gehärtet: Seite via `query->getInt()`, Parameternamen sanitisiert (`src/Paginator/Factory/PaginatorFactory.php:38,127-138`).
- News-/Events-Driver fügen automatisch einen intrinsischen Published-Filter hinzu (`src/List/Driver/NewsListDriver.php:51-58`, `src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php:64-69`).
- Tabellenname wird pro Filterausführung validiert (`src/Query/Executor/FilterExecutor.php:76-82`).
- Baseline zum Audit-Zeitpunkt: Semgrep 0 Findings, Composer Audit ohne Advisories.

## Korrektheit

- Die `abort()`-Muster (`FilterBuilder::abort()` / `FilterQueryBuilder::abort()` als `never`-werfende Methoden) sind korrekt; verdächtig aussehende `if (!$x = …) { $builder->abort(); }`-Konstrukte sind unproblematisch (`src/Query/FilterQueryBuilder.php:69-73`).
- Keine OR/AND-Präzedenzfalle: Conditions werden durchgängig über DBALs `CompositeExpression` kombiniert, die bei ≥2 Teilen jeden Teil einklammert (`src/Query/FilterQueryBuilder.php:245`, `src/EventListener/QueryStructModifier/ConditionsModifierListener.php:50-56`).
- `setParameter(':name', …)` mit führendem Doppelpunkt ist unschädlich (`ltrim($param, ':')`, `src/Query/FilterQueryBuilder.php:125`).
- Geprüfter Nicht-Bug: Callbacks interner Funktionen (`array_filter`/`array_map`) laufen coercive — der `fn (string $key)`-Callback in `PaginatorFactory` mit numerischen Query-Keys ist kein TypeError (empirisch bestätigt; `src/Paginator/Factory/PaginatorFactory.php:79-83`).
- Als geprüft-in-Ordnung bestätigt: OptionsResolver-Memoisierung, `FilterContext::SINGLE_VALUE = '0'` als Formkey, `collectFilterData`-Pfade, Build-Reihenfolge des `ListSpecBuilder` (Overrides gewinnen wie dokumentiert), `TransformerResolver`-Fastpath, topologische Join-Sortierung inkl. `requires` (`src/Query/TableAliasRegistry.php:176-207`), `FilterModel::findByPid` nie null-foreach.

## Performance & Stabilität

- COUNT-/Daten-Trennung korrekt: der Count-Pfad läuft ohne ORDER BY, LIMIT/OFFSET und GROUP BY (`SelectModifierListener` setzt `COUNT(DISTINCT main.id)` + `setGroupBy(null)`; `PageModifierListener`/`OrderModifierListener` steigen bei `isCounting` früh aus).
- `AbortFilteringException` sauber gelöst: `src/Query/Executor/ListQueryDirector.php:60-67` fängt sie, loggt debug und liefert eine leere Liste statt eines Fehlers.
- Wildcard-Entschärfung der Suche wirksam: `SearchKeywordsFilterType::makeTerms()` entfernt `%`/`_` zuverlässig aus User-Input (`:55-64`).
- Kein N+1 auf Model-Ebene: `HandlesModelsTrait::createModelsFromEntries()` hydratisiert aus dem geladenen Resultset; Reader-URLs werden pro ID gecacht (`src/Engine/View/LinksToReaderTrait.php:36-50`).
- Memoization der `configure*`-Familie funktioniert wie dokumentiert (`SchemaResolver` pro Key, `src/Filter/FilterBuilder.php:18` statisch).
- Pagination korrekt via LIMIT/OFFSET; Offset nie negativ (`src/Paginator/PaginatorConfig.php:26-28`); Reader-Lookup effektiv mit LIMIT 1 (`src/Engine/Context/ValidationContext.php:35`).
- Indizes auf `tl_flare_filter` decken die Zugriffe ab (`contao/dca/tl_flare_filter.php:21-26`).
- Exception-Hygiene: breite `catch (\Throwable)` in `FilterExecutor` und den Loadern wrappen konsequent in `FilterException`/`FlareException` mit Quellen-Metadaten; `FilterOptionsResolver` (`src/Filter/Resolver/FilterOptionsResolver.php:35-47`) liefert vorbildliche Fehlermeldungen.
- Unbekannter Listentyp degradiert sauber (Collector → null, Controller-Graceful-Path greift).
- `FlareCollector` läuft nur mit aktivem Profiler — kein Produktions-Overhead.

## Tests & Tooling

- Testqualität vorbildlich: nur 2 `createMock`-Aufrufe in der gesamten Suite; echte Kollaborateure (echte Form-Factory inkl. CSRF-Extension, echter `EventDispatcher`) und echte Ergebnis-Assertions statt Interaktionsprüfung.
- Präzise Edge-Cases und Schema-Roundtrip-Tests („Transform erfüllt das eigene Schema") mit realistischen Contao-Daten (`serialize()`-Blobs, Checkbox-`'1'`/`''`, String-IDs); schnelle Suite ohne Framework-Boot.
- `phpunit.xml.dist` setzt `failOnRisky`/`failOnWarning`, `error_reporting=-1`; Coverage-Filter konsistent mit PHPStan-Excludes.
- PHPStan Level 5 mit `bleedingEdge` + Symfony-Extension, ohne Baseline-Datei — keine versteckten Altlasten.
- Semgrep mit `--error` tatsächlich verpflichtend (`.github/workflows/security.yaml:68`); Mago lintet streng (`--minimum-fail-level note`) über PHP 8.2–8.5, Version gepinnt.
- `composer validate --strict` in den Workflows; Composer-Caching und Path-Filter konsistent; Makefile konsistent mit AGENTS.md.

## Contao-Integration, Doku & DX

- Übersetzungs-Rename sauber: `translations/flare_filter.{de,en}.php` und `flare_list.{de,en}.php` nutzen `::TYPE`-Klassenkonstanten direkt als Keys — verwaiste Typ-Keys strukturell ausgeschlossen.
- Migrationsdoku vorhanden und substanziell: `docs/docs/migrating-from-v0.1.md`, `docs/docs/removed-in-v0.2.md`; Named-Dispatch-Muster in `docs/docs/dev/events.md` dokumentiert.
- Erweiterbarkeits-DX gut: eigenes FilterElement mit `#[AsFilterElement]` + `AbstractFilterElement` in wenigen Zeilen; `registerAttributeForAutoconfiguration` wirkt auch für Fremd-Bundles (`src/DependencyInjection/HeimrichHannotFlareExtension.php:58-70`); reservierte Typnamen werden validiert (`src/DependencyInjection/Compiler/RegisterListDriversPass.php:57-59`).
- Bundle-Bootstrap korrekt und vollständig: `contao/config/config.php`, Backend-Modul, `ContaoManager\Plugin`, Compiler-Passes; bedingte Integration-Loads passen zu `config/integrations/*.yaml`.
- Template-↔-View-Datenvertrag konsistent (`flare_listview.html.twig`/`flare_reader.html.twig` gegen die View-Klassen); Twig-Globals `flare_str`/`flare_env` verdrahtet.
- Der v0.1-Snapshot unter `docs/versioned_docs/` dokumentiert absichtlich die Alt-API — kein Drift-Problem.
- Seit dem Audit verbessert: Intrinsic-Muster im Filter-Element-Guide dokumentiert (`docs/docs/dev/filter-elements/index.md:256-262`); Generic-Driver zeigt Backend-Info bei fehlendem Published-Filter (`src/List/Driver/GenericDataContainerListDriver.php:111`); Terminal42-Integration in Doku/README als disabled markiert.
