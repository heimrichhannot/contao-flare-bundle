# Performance & Stabilität

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Keines der Findings dieses Kapitels wurde seit dem Audit-Datum behoben. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## Stabilität

### PS-01: Frontend-500 statt Degradierung — Query-/Filterfehler schlagen erst beim Twig-Rendern zu — Major (claude)

Der Graceful-Catch umschließt nur Spec-/Engine-Bau; `createView()` (Count + Entries + Formular) läuft erst im Template. Eine `FilterException` zur Laufzeit propagiert als Twig-`RuntimeError`; der Render-Catch rethrowt alles außer eingebetteter `ResponseException` — die ganze Seite wird zum 500er. Kaputte Filter-Konfiguration eines einzelnen Elements darf nicht die Seite reißen: `createView()` in den Controller ziehen bzw. `FlareException` beim Rendern abfangen. (`AbortFilteringException` ist dagegen sauber gelöst — leere Liste.)

- `src/Controller/ContentElement/ListViewController.php:95-116` (Catch nur um Bau), `:131` (Engine ans Template), `:136-151` (Render-Catch rethrowt) · `contao/templates/content_element/flare_listview.html.twig:7` · sauber: `src/Query/Executor/ListQueryDirector.php:60-67`

### PS-02: Latenter Korrektheitsbug: Entry-Cache positional statt per ID indiziert — Major (claude)

`ValidationLoader::fetchEntryById()` greift per `getEntryCache()[$id]` zu; die Cache-Closure aus `createFromInteractiveView()` liefert aber `InteractiveView::getEntries()` = rohes, positionsindiziertes `fetchAllAssociative()`-Resultat. Der Lookup trifft den Datensatz an *Position* `$id` — falscher Entry oder wirkungsloser Cache. `createFromInteractiveView()` ist öffentliche API.

- `src/Engine/Loader/ValidationLoader.php:29` · `src/Engine/Context/Factory/ValidationContextFactory.php:45-51` · `src/Engine/Loader/InteractiveLoader.php:40` · `src/Engine/View/InteractiveView.php:54-57`

### PS-03: DBAL-Constraint erlaubt Versionen, mit denen der Code fatal scheitert — Major (claude)

`composer.json:12` erlaubt `doctrine/dbal ^2.13 || ^3.0 || ^4.0`; der Code nutzt `Doctrine\DBAL\ArrayParameterType` (erst ab DBAL 3.6) und `executeQuery()` (ab 3.1). Contao 4.13 kann DBAL 3.3–3.5 auflösen → „Class not found" zur Laufzeit. **Fix ist eine Zeile: `^3.6 || ^4.0`.**

- `composer.json:12` · `src/Query/FilterQueryBuilder.php:7,123,160,202,206`

### PS-04: Backend-Vorschau crasht mit TypeError bei gelöschter Liste — Major (claude)

Siehe A-15 in [10-architektur.md](10-architektur.md): beide Backend-Responses dereferenzieren `$listModel` ohne Null-Guard außerhalb jedes try/catch (`src/Controller/ContentElement/ListViewController.php:154-170`, `src/Controller/ContentElement/ReaderController.php:220-236`).

### PS-05: Fehlerpfade uneinheitlich: Reader wirft 500, Listview antwortet cachebare 200 — Medium (claude)

`ReaderController` liefert für Fehler Status 500 bzw. `InternalServerErrorHttpException` (`src/Controller/ContentElement/ReaderController.php:74-82,149-155`); `ListViewController::getErrorResponse()` gibt für denselben Fehlertyp eine 200er-Response mit Fehlertext zurück — ohne `Cache-Control: no-store` (`src/Controller/ContentElement/ListViewController.php:63-71`).

### PS-06: Stiller Schlucker: ungültige `sortSettings` werden lautlos zu null — Medium (claude)

`SortOrderSequenceFactory::createFromList()` fängt die `FlareException` aus `createFromSettings()` und gibt kommentarlos null zurück — Liste rendert unsortiert, keine Logzeile.

- `src/Sort/Factory/SortOrderSequenceFactory.php:21-28`

### PS-07: Zustand in Shared Services: Caches wachsen prozessweit, kein `ResetInterface` — Medium (beide Audits)

Ohne Invalidierung/Reset: `ArchiveFilterElement::$_inferrer` (gekeyt per `ListSpec::hash()`, wächst unbegrenzt), `FieldValueChoiceFilterElement::$foreignValueCache`/`$localValueCache` (stale Choices unter Worker-Runtimes), `CfgTagsJoinsRegistry::$entries` (akkumuliert), `DcaHelper` mit `static $dcTableCache`. `ResetInterface`/`kernel.reset` kommt in `src/` und `config/` nicht vor.

- `src/Filter/Element/ArchiveFilterElement.php:34,399-404` · `src/Filter/Element/FieldValueChoiceFilterElement.php:31-32,263,300` · `src/Integration/CodefogTags/Registry/CfgTagsJoinsRegistry.php:14-18` · `src/Util/DcaHelper.php:64-77`

### PS-08: Fehlendes Filter-Element wird auch bei intrinsischen Sicherheitsfiltern kommentarlos geskippt — Minor, sicherheitsrelevant (claude)

Wirft `FilterFactory::createFromFilterModel()` (Element-Typ nicht registriert, Extension deinstalliert), loggt der Collector nur ein Warning und macht `continue` — auch für intrinsische Sicherheitsfilter wie `flare_published` → Liste zeigt ggf. Unveröffentlichtes (Sichtbarkeits-Leak).

- `src/List/Collector/ListModelFilterCollector.php:56-71` · `src/Filter/Factory/FilterFactory.php:104-107`

### PS-09: `PublishedFilterElement` ignoriert den Contao-Preview-Modus — Minor (claude)

Immer `published`-/`start`-/`stop`-Bedingung mit `'now' => time()`, ohne `TokenChecker::isPreviewMode()`-Bypass (`TokenChecker` kommt in `src/` nicht vor) — unveröffentlichte Einträge sind in der offiziellen Frontend-Vorschau unsichtbar.

- `src/Filter/Element/PublishedFilterElement.php:53-64`

### PS-10: HTTP-Cache vs. zeitabhängige Filter: nur Tabellen-Tags — Minor (claude)

Invalidierung ausschließlich über `contao.db.<table>`-Tags; ein rein zeitgesteuerter `start`/`stop`-Wechsel invalidiert nichts.

- `src/Controller/ContentElement/ListViewController.php:118` · `src/Filter/Element/PublishedFilterElement.php:62`

### PS-11: MariaDB + `ONLY_FULL_GROUP_BY`: `SELECT main.* … GROUP BY main.id` — Minor (claude)

MariaDB erkennt die funktionale Abhängigkeit vom PK nicht → Fehler 1055 bei aktivem `ONLY_FULL_GROUP_BY`. (Der Count-Pfad ist unbetroffen, da `SelectModifierListener` das GROUP BY entfernt.)

- `src/Query/Factory/ListExecutionContextFactory.php:40-44`

### PS-12: `AggregationLoader::fetchCount()` ohne int-Cast — Info (claude)

`$count = $result->fetchOne() ?: 0;` direkt aus einer `int`-typisierten Methode returnt — liefert der Treiber (Emulation + stringify) einen String, gibt es unter `strict_types` einen TypeError.

- `src/Engine/Loader/AggregationLoader.php:40-44`

### PS-13: `FlareCollector::getSemVersion()` crasht bei null-Version — Info (claude)

`data['version']` kommt aus `InstalledVersions::getVersion()` (kann null sein); `getSemVersion()` ruft `\explode('-', $this->data['version'])` ohne Guard — TypeError (nur mit aktivem Profiler relevant).

- `src/DataCollector/FlareCollector.php:19,38-44`

## Performance

### PS-14: Query-/Filter-Pipeline läuft pro Request doppelt (Count + Daten) — Major (beide Audits)

`InteractiveProjector::project()` erzeugt zuerst die AggregationView für den Count und danach den InteractiveLoader — beide Pfade laufen über `ListQueryDirector::createQueryBuilder()` und führen `FilterExecutor::invokeFilters()` komplett erneut aus, inkl. `FilterContextFactory::create()` mit OptionsResolver-`resolve()` pro Filter und Event-Dispatches. Filter-Elemente mit DB-Zugriff in `buildFilter()` zahlen doppelt; `ArchiveFilterElement` macht `findMultipleByIds`-Fetches zusätzlich ein drittes Mal in `buildForm()` (nur der `PtableInferrer` ist memoiert, `fetchParents()` nicht). Empfehlung: Filterquery-Fragmente request-scoped zwischen Count und Datenquery teilen.

- `src/Engine/Projector/InteractiveProjector.php:50,61-68` · `src/Query/Executor/ListQueryDirector.php:48` · `src/Query/Executor/FilterExecutor.php:49-62` · `src/Filter/Element/ArchiveFilterElement.php:118,282-314,584-598`

### PS-15: Calendar-Integration lädt die komplette Ergebnismenge unpaginiert — zweimal — Major/Hoch (beide Audits)

`EventsInteractiveLoader` setzt `ContaoCalendar_doNotPaginate` (Listener entfernt LIMIT/OFFSET), holt alle Zeilen per `fetchAllAssociative()`, expandiert via `groupEntriesByDate()` und paginiert erst in PHP. `EventsAggregationLoader::fetchCount()` macht denselben Full-Fetch samt kompletter Expansion separat noch einmal. Zwei Full-Fetches + zwei Recurrence-Expansionen pro Request. Empfehlung: SQL-seitiges Zeitfenster.

- `src/Integration/ContaoCalendar/Loader/EventsInteractiveLoader.php:31-48,50-86` · `src/Integration/ContaoCalendar/EventListener/DoNotPaginateModifierListener.php:15-19` · `src/Integration/ContaoCalendar/Loader/EventsAggregationLoader.php:30-58`

### PS-16: Unbegrenzte Recurrence-Expansion (OOM-/CPU-Risiko) — Major/Hoch (beide Audits)

`fillRecurringEvents()` läuft `while ($repeatDate <= $repeatEnd)` ohne Obergrenze; `fillInitialEvents()` legt per `DatePeriod` einen Eintrag pro Tag der gesamten Event-Dauer an. Ein minütlich wiederholtes Event mit fernem `repeatEnd` erzeugt Hunderttausende Array-Einträge pro Event — im Frontend-Request, auch im Count-Pfad. Redakteurs-Fehleingabe genügt für OOM. Empfehlung: harte Occurrence-Limits.

- `src/Integration/ContaoCalendar/GroupsEntriesTrait.php:132-137,67-71`

### PS-17: Partial-Templates triggern jeweils die volle Pipeline — Medium (beide Audits)

Alle drei Partials setzen selbst `{% set flare_list = flare.createView %}`; `Engine::createView()` memoiert nichts. Formular/Liste/Paginator als drei Content-Elemente derselben Liste → 3× Spec-Bau, Formular-Bau, Count- und ggf. Entries-Query.

- `contao/templates/content_element/flare_listview/form_only.html.twig:3`, `list_only.html.twig:3`, `paginator_only.html.twig:3` · `src/Engine/Engine.php:44-61`

### PS-18: Count-Query läuft auch bei ungültig submittetem Formular; View meldet inkonsistenten Count — Minor (beide Audits)

Siehe A-05 in [10-architektur.md](10-architektur.md): `$totalItems` wird vor der Validitätsprüfung berechnet und trotz `InteractiveEmptyLoader` unverändert an die View gereicht — `InteractiveView::getCount()` kann n > 0 bei leerer Liste melden (`src/Engine/Projector/InteractiveProjector.php:50,56-58,74-81`, `src/Engine/View/InteractiveView.php:49-52`).

### PS-19: `ChoicesBuilder`: O(n²)-Wertauflösung via `array_search` — Minor (beide Audits)

`buildChoiceValueCallback()` macht pro Choice ein lineares `array_search($choice, $this->choices, true)` — quadratisch beim Rendern großer Choice-Mengen; keine Reverse-Map.

- `src/Form/ChoicesBuilder.php:251-266`

### PS-20: `FieldValueChoiceFilterElement`: unbegrenzte DISTINCT-/Fremdtabellen-Scans — Minor/Mittel (beide Audits)

`getLocalValues()` macht `SELECT DISTINCT CAST(… AS CHAR) … ORDER BY` ohne LIMIT über die ganze Tabelle; `getForeignValues()` lädt die komplette Fremdtabelle (`fetchAllKeyValue` mit `CONCAT`-Label, kein LIMIT). Ergebnis wird ungebremst zu Form-Choices; keine Begrenzung/Suche/Ajax-Pfad.

- `src/Filter/Element/FieldValueChoiceFilterElement.php:298-325,261-295`

### PS-21: DCA-`options_callback` läuft pro Request bis zu dreimal — Mittel (codex)

`DcaSelectFieldFilterElement::getOptions()` (→ beliebige Contao-Callbacks) wird beim Formularbau und erneut beim Filterbau benötigt; da der Filterbau für Count und Daten doppelt läuft (PS-14), laufen Callbacks mit DB-Zugriff bis zu dreimal. Kein Request-Cache pro Tabelle/Feld.

- `src/Filter/Element/DcaSelectFieldFilterElement.php:76,101,123,308`

### PS-22: Suchfilter: unverankertes `LIKE '%…%'`, Term-Anzahl unbegrenzt — Info (beide Audits)

Pro Term × Spalte ein nicht verankertes LIKE (kein Index nutzbar); Term-Anzahl aus User-Input unbegrenzt (nur Stopwords/Deduplizierung). Positiv: `makeTerms()` entfernt Wildcards (`%`, `_`) zuverlässig.

- `src/Filter/Type/SearchKeywordsFilterType.php:37-47,55-64`

### PS-23: Eager-Instanziierung aller Filter-Elemente über die Registry — Info (claude)

Der Compiler-Pass injiziert echte Service-Referenzen per `addMethodCall('add', …)` — beim Instanziieren der `FilterElementRegistry` werden alle Elemente eager gebaut. Derzeit verschmerzbar; bei wachsendem Ökosystem auf ServiceLocator/lazy umstellen.

- `src/DependencyInjection/Compiler/RegisterFilterElementsPass.php:39-48`

### PS-24: `ListSpec::hash()` serialisiert die vollständige Config — Info (codex)

`sha1(serialize([...]))` über Driver-Klasse, Typ, dc, source, komplette Config und alle Filter-Fingerprints — bei großen dynamischen Config-Arrays potenziell teuer; für typische Listen unkritisch.

- `src/List/ListSpec.php:113-123`

## Querverweise

- Terminal42-Integration (toter Code): siehe A-01 in [10-architektur.md](10-architektur.md).
- `#[TaggedIterator]`-Deprecation: siehe A-13 in [10-architektur.md](10-architektur.md).
