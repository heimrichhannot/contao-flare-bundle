# Security & Query-Safety

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Bereits behobene Punkte (u. a. der `?_preview`-Feld-Dump und die fehlende `model_table`-Verifikation beim Unmarshal) sind nicht mehr enthalten. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## SEC-01: Contao-Model-Registry kann Reader-/Listenfilter teilweise umgehen — Niedrig (beide Audits, teilweise entschärft)

`fetchModel()` bedient sich zuerst aus Contaos globaler Model-Registry, bevor die durch FLARE-Filter abgesicherte Query läuft. Seit dem Audit wurde eine Prüfung des `published`-Flags ergänzt (`src/Engine/View/HandlesModelsTrait.php:24-28`), die den ursprünglichen Kernfall abfängt. **Nicht abgedeckt bleiben:** die `start`/`stop`-Zeitfenster, die der `PublishedFilterType` in der Query erzwingt (`src/Filter/Type/PublishedFilterType.php:32-45`), sowie sämtliche anderen konfigurierten Filterbedingungen — ein im selben Request ungefiltert gecachtes Modell mit `published=1`, aber abgelaufenem `stop`-Datum (oder außerhalb anderer Filterkriterien) wird über den Registry-Shortcut ausgeliefert, ohne dass die gefilterte Query je läuft.

Beleg: `src/Engine/View/HandlesModelsTrait.php:20-29`

## SEC-02: Generischer List-Driver ohne Published-Filter — „secure by default" fehlt — Info (beide Audits, teilweise entschärft)

`NewsListDriver` und `EventsListDriver` fügen automatisch einen intrinsischen `PublishedFilterElement` hinzu (`src/List/Driver/NewsListDriver.php:51-58`, `src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php:64-69`); der `GenericDataContainerListDriver` nicht — eine generische Liste ohne konfigurierten Published-Filter liefert unpublizierte Datensätze an anonyme Besucher aus. Die empfohlene Backend-Warnung wurde inzwischen implementiert (`checkPublishedFilter()`, `src/List/Driver/GenericDataContainerListDriver.php:98-114`), hat aber eine Lücke: Sie läuft nur im `hasParent`-Zweig — für Listen **ohne** Parent greift der Early-Return in `buildDca()` (`src/List/Driver/GenericDataContainerListDriver.php:51-54`) vor dem Aufruf in Zeile 93, dort erscheint keine Warnung. Zudem ist es nur `Message::addInfo`, keine Warnung.

## SEC-03: Unescapte Ausgabe in Backend-Vorschau-Responses — Niedrig [BE-Admin] (beide Audits, teilweise entschärft)

Teilfixes seit dem Audit: ListView schleust `title`, Typ-Übersetzung und `dc` durch `strip_tags()` (`src/Controller/ContentElement/ListViewController.php:166-168`); der Headline-Tag-Name wird gegen eine Whitelist geprüft (`src/Util/Str.php:236-242`). **Weiterhin offen:**

- Der Headline-**Wert** wird in beiden Controllern unescaped in HTML interpoliert (`src/Controller/ContentElement/ListViewController.php:165`, `src/Controller/ContentElement/ReaderController.php:231` — `Str::formatHeadline()` escapet den Text nicht).
- Der `ReaderController` gibt `$listModel->title` und `$listModel->dc` komplett roh aus, ohne `strip_tags`/Escaping (`src/Controller/ContentElement/ReaderController.php:229-235`).
- Beide `catch`-Blöcke geben rohe Exception-Messages als Response aus (`src/Controller/ContentElement/ListViewController.php:160`, `src/Controller/ContentElement/ReaderController.php:226`).

## SEC-04: Zentrale Query-Struktur validiert SQL-Identifier nicht — Niedrig (codex)

`ListExecutionContextFactory::create()` setzt `ListSpec::$dc` ungeprüft als `FROM` (`src/Query/Factory/ListExecutionContextFactory.php:29-44`). `SqlQueryStruct` nimmt Select-/Join-/Group-/Order-/Having-Fragmente als rohe Strings entgegen (`src/Query/SqlQueryStruct.php:55-146`); die Validator-Constraints prüfen nur `NotNull`/`NotBlank`/`Count`, keine Identifier-Form. `QueryBuilderFactory::create()` reicht alle Fragmente ungequotet an den DBAL-QueryBuilder durch (`src/Query/Factory/QueryBuilderFactory.php:30-64`). Die `Str::isValidSqlName()`-Prüfung der Tabelle läuft nur pro Filter in `FilterExecutor::invokeFilter()` (`src/Query/Executor/FilterExecutor.php:76-82`) — bei einer Liste ohne Filter gar nicht. Kein anonymer Angriffspfad (Driver/Events sind Erweiterungscode), aber die Factory erzwingt ihre eigenen Invarianten nicht — Defense in Depth gegen fehlerhafte Driver/Events/Redakteursdaten fehlt.

## SEC-05: `composer audit || true` im Security-Workflow — Niedrig, Prozess (codex)

Ein zukünftiges Advisory kann den CI-Job nie fehlschlagen lassen. Beleg: `.github/workflows/security.yaml:56`. (Siehe auch CI-04 in [50-tests-ci.md](50-tests-ci.md).)

## SEC-06: Keine Testabdeckung der Sicherheits-Leitplanken in `src/Query/` — Info (claude)

`tests/` enthält keinerlei Tests für `src/Query/`. Regressionen an `FilterQueryBuilder::column()`/`setParameter()` blieben still und wären sicherheitsrelevant. (Siehe auch T-01 in [50-tests-ci.md](50-tests-ci.md).)
