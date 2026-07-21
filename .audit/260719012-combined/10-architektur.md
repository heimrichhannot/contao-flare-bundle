# Architektur & Design

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Die ursprünglichen Top-Findings beider Audits — verlorene Listen-ID im `ListSpec`-Pfad (Formularnamen-Kollision) und Transformer-Memoization pro Klasse statt (Klasse, Typ) — sind inzwischen behoben und daher hier nicht mehr enthalten. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## A-01: Terminal42-ChangeLanguage-Integration ist toter, nicht kompilierbarer Code — Major (beide Audits)

Der Listener importiert fünf nicht existierende Klassen (`Event\AbstractFetchEvent`, `Event\FetchAutoItemEvent`, `Event\FetchCountEvent`, `Event\FetchListEntriesEvent`, `Query\ListQueryBuilder`), abonniert nie dispatchte Event-Namen und benutzt die entfernte Fetch-API (`getListQueryBuilder()`, `getFilters()`, `getContentContext()`). Das Laden der Integration ist auskommentiert; PHPStan excludiert das Verzeichnis komplett und ignoriert `class.notFound` für `src/Integration/` — der Bruch bleibt systematisch unsichtbar. Nebenbefund: Klasse/Namespace `DcMultilingualListType` tragen als letzte Stelle das alte `ListType`-Vokabular (Attribut ist bereits `#[AsListDriver]`).

**Entscheidung nötig: portieren oder entfernen.**

- `src/Integration/Terminal42Languages/EventListener/ChangelanguageListener.php:13-17,22` (tote Imports), `:92,116` (nie dispatchte Events), `:70,100-101,108-109,119-129` (entfernte API)
- `src/DependencyInjection/HeimrichHannotFlareExtension.php:43-45` (auskommentierter Loader)
- `phpstan.neon:13,18-20` · `src/Integration/Terminal42Languages/ListType/DcMultilingualListType.php:16-19`

## A-02: AGENTS.md/CLAUDE.md beschreiben nicht mehr existierende APIs — Minor (claude)

Doku-Drift gegen den aktuellen Code: `ListBuilderFactory` heißt `ListSpecBuilderFactory`; `#[AsListType]` heißt `AsListDriver`; `ListTypeRegistry` heißt `ListDriverRegistry`; `FilterElementResolver` existiert nicht; „EngineFactory creates Engine with appropriate Context" ist falsch — die Factory erhält den Context als Parameter (`src/Engine/Factory/EngineFactory.php:21-29`). Dazu Doc-Drift im Code: Verweis auf `DcaContract::configureDca()`, die Methode heißt `buildDca()` (`src/EventListener/Contao/ElementDcaListener.php:24` vs. `src/Contract/DcaContract.php:18`).

- `AGENTS.md:21,43,55,60,71`

> ## A-03: Registry-Duplikation und heterogene Lookup-Semantik — Minor (claude)
> 
> `FilterElementRegistry` und `ListDriverRegistry` sind strukturell nahezu identisch (gleiches `add`/`remove`/`prune`/`typesByClass`-Muster) — Kandidat für Basis/Trait. Daneben drei weitere Stile: `FilterTypeRegistry` (TaggedIterator, Key = Klassenname), `EngineModRegistry` (TaggedIterator, `defaultIndexMethod: 'getType'`), `ProjectorRegistry` (`supports()`/`priority()`-Scan). Fünf Registries, vier Lookup-Semantiken.
> 
> - `src/Registry/FilterElementRegistry.php:39-57` vs. `src/Registry/ListDriverRegistry.php:34-52` · `src/Registry/FilterTypeRegistry.php:25-28,53` · `src/Registry/EngineModRegistry.php:15` · `src/Registry/ProjectorRegistry.php:28-63`
> 
> **Nutzer-Antwort: Das ist kein Design-Fehler, sondern eine Konvention. Einzelne Klassen sorgen für Typsicherheit. Die Klassen sind atomar und benötigen künftig keiner Feature-Erweiterung, daher keine gemeinsame Basisklasse.**

> ## A-04: `PaginatorConfig`: latenter `TypeError` in `count()` + deprecated `\Serializable` — Minor (claude)
> 
> `count(): int` gibt `getLastPageNumber(): ?int` zurück — `TypeError` bei `itemsPerPage < 1` oder unbekanntem `totalItems`. Zusätzlich implementiert die Klasse das deprecated `\Serializable`-Interface mit `serialize()`/`unserialize()` neben `__serialize`/`__unserialize`.
> 
> - `src/Paginator/PaginatorConfig.php:192-195` (`count()`), `:107-118` (`getLastPageNumber(): ?int`), `:7,197-205` (`\Serializable`)
> 
> **Nutzer-Antwort: TypeError erledigt, \Serializable ist nicht deprecated, siehe folgende Notiz.**
> > As of PHP 8.1.0, a class which implements Serializable without also implementing __serialize() and __unserialize() will generate a deprecation warning.

> ## A-05: `InteractiveProjector`: COUNT-Query läuft vor der Invalid-Form-Prüfung — Minor (claude)
>
> Die Aggregations-COUNT-Query (`src/Engine/Projector/InteractiveProjector.php:50`) wird ausgeführt, bevor geprüft wird, ob das Formular invalid submitted wurde (`:56-58`) — pro invalidem Submit eine unnötige Query. Zudem wird `totalItems` unverändert an die View durchgereicht, sodass diese `totalItems > 0` bei leerem `InteractiveEmptyLoader` meldet (`:74-81`).
> 
> **Nutzer-Antwort: Das ist kein Fehler. Da sich das Formular nicht auf die Aggregation-COUNT-Query auswirkt, muss die totale Anzahl der Elemente trotzdem berechnet werden.**

> ## A-06: Context-Verträge mit kleinen LSP/ISP-Brüchen — Minor (claude)
> 
> `InteractiveContext::getPaginatorConfig(): PaginatorConfig` gibt das nullable Property ungeprüft zurück — `TypeError` bei programmatischer Konstruktion ohne Validator-Lauf (`src/Engine/Context/InteractiveContext.php:25,45-48`). Die readonly `ValidationContext` trägt einen No-op-Setter `setPaginatorQueryParameter()`, weil `PaginatedContextInterface` ihn erzwingt (`src/Engine/Context/ValidationContext.php:77-80`).
> 
> **Nutzer-Antwort: PaginatorConfig nun korrekt null-safe, ValidationContext no-op-Setter ist korrekt für den Zweck.** 

> ## A-07: Stille Alias-Kollision im Filter-Collector — Minor (claude)
> 
> `$filters[$filter->alias] = $filter;` — zwei publizierte Filter derselben Liste mit gleichem Formular-Alias überschreiben sich kommentarlos; nur der letzte wird angewendet. Ein Kollisions-Warning fehlt (das Factory-Fehler-Warning existiert dagegen).
> 
> - `src/List/Collector/ListModelFilterCollector.php:75`
> 
> **Nutzer-Antwort: Im Backend wird nun ein Fehler ausgegeben, wenn zwei Filter mit demselben Alias publiziert werden.**

> ## A-08: `FlareException`: `method` vs. `source` inkonsistent — Minor (claude)
> 
> Die Exception bietet beide Parameter (`src/Exception/FlareException.php:17-18`), der Code nutzt beide uneinheitlich mit demselben Inhalt (`__METHOD__`): Loader nutzen `method:` (`src/Engine/Loader/InteractiveLoader.php:52`, `AggregationLoader.php:52`), Projector/Views/Calendar-Integration `source:` (`src/Engine/Projector/AbstractProjector.php:124`, `src/Engine/View/HandlesModelsTrait.php:33,42,57,66,75`, `src/Integration/ContaoCalendar/Loader/EventsAggregationLoader.php:66`), `ValidationLoader` keins von beiden (`src/Engine/Loader/ValidationLoader.php:57,92`).
>
> **Nutzer-Antwort: Angeglichen -- method: __METHOD__, source, wenn verfügbar: table.id -- übertragen auf gesamte Codebase**

> ## A-09: `symfony/event-dispatcher` nicht direkt deklariert — Minor (claude, reduzierter Umfang)
>
> `FilterFormFactory` instanziiert direkt `new EventDispatcher()` (`src/Filter/Factory/FilterFormFactory.php:17,70`), deklariert ist aber nur `symfony/event-dispatcher-contracts` (`composer.json:17`); das konkrete Paket kommt nur transitiv über `contao/core-bundle`.
> 
> **Nutzer-Antwort: Required in composer.json**

> ## A-10: `ValidationLoader::executeQuery()` liefert `[]` statt `null` bei abgebrochenem Query-Aufbau — Minor (claude)
>
> Bei `!$qb` wird `[]` zurückgegeben — harmlos (falsy), aber semantisch schief gegenüber dem `?array`-Vertrag, in dem `null` „nicht gefunden" bedeutet (`:117`: `return $entry ?: null;`).
>
> - `src/Engine/Loader/ValidationLoader.php:107-109`
>
> **Nutzer-Antwort: Return-type auf `array` angepasst.**

> ## A-11: Query-Assemblierung lebt in Event-Listener-Prioritäten ohne zentrale Übersicht — Info (claude)
> 
> Select@490, Conditions@470, Page@430, Order@420, Join@-450; Integrations-Listener dazwischen (250/220/200/190/100). Die Gesamtordnung ist nirgends zentral dokumentiert (kein Pipeline-Kommentar im `ListQueryDirector`).
> 
> - `src/EventListener/QueryStructModifier/SelectModifierListener.php:13`, `ConditionsModifierListener.php:11`, `PageModifierListener.php:11`, `OrderModifierListener.php:12`, `JoinModifierListener.php:10` · `src/Integration/ContaoCalendar/EventListener/CountEventsModifierListener.php:14` u. a.
> 
> **Nutzer-Antwort: Das muss in einem zukünftigen PR nochmal überarbeitet werden.**

> ## A-12: `ViewInterface` ist leerer Marker; Aufrufer müssen downcasten — Info (claude)
>
> Das Interface ist leer (`src/Engine/View/ViewInterface.php:7-9`); `ReaderController` downcastet auf `ValidationView` (`src/Controller/ContentElement/ReaderController.php:127`). Die `@template`-Annotationen sind nur mit dem `generics.noParent`-Ignore in PHPStan haltbar.

> ## A-13: `#[TaggedIterator]` ist seit Symfony 7.1 deprecated — Info (claude)
> 
> Genutzt in drei Registries; relevant für Deprecation-Logs bei Support-Matrix ^5.4|^6|^7. Nachfolger `AutowireIterator` existiert erst ab 6.3 → für die Matrix ggf. `!tagged_iterator` in YAML.
> 
> - `src/Registry/EngineModRegistry.php:15` · `src/Registry/ProjectorRegistry.php:19` · `src/Registry/FilterTypeRegistry.php:18`
>
> **Nutzer-Antwort: Passt so.**

> ## A-14: Statische Contao-Aufrufe in Context-DTOs — Info (claude, reduzierter Umfang)
> 
> `PageModel::findByPk` in wertartigen Context-Objekten — DB-Zugriffe, testfeindlich, aber Contao-idiomatisch.
> 
> - `src/Engine/Context/ReaderUrlConfigCreatorTrait.php:18` · `src/Engine/Context/ValidationContext.php:44`
> 
> **Nutzer-Antwort: Weiterhin statische Aufrufe, aber nun besser gekapselt.**

> ## A-15: Backend-Responses ohne Null-Check auf `$listModel` — Info (claude)
> 
> `getRelated()` kann `null` liefern; der Catch deckt nur Exceptions ab. Danach werden `$listModel->title` / `trans($listModel->type)` ungeprüft dereferenziert — in beiden Controllern. (Gelöschte/fehlende Liste → Backend-Crash; siehe auch SEC-03 in [30-sicherheit.md](30-sicherheit.md).)
> 
> - `src/Controller/ContentElement/ReaderController.php:220-236` (Zugriff `:232-233`) · `src/Controller/ContentElement/ListViewController.php:154-168` (Zugriff `:166-167`)
> 
> **Nutzer-Antwort: Good Catch! Ist jetzt mit einer entsprechenden Warnung gesichert.**

> ## A-16: `Engine`-Mods-API mischt Semantiken — Info (claude)
> 
> `addMod()` appendet numerisch, `setMod()`/`unsetMod()` arbeiten mit String-Keys im selben Array; `unsetMod()` kann appendete Mods nicht adressieren — öffentlicher `@api`-Punkt.
> 
> - `src/Engine/Engine.php:66-93`
>
> **Nutzer-Antwort: Das ist kein Fehler sondern explizit so gewollt. Der Nutzer hat die Wahl, Filter für mehrfache veränderung überschreibbar zu machen, oder nicht. In den meisten Fällen wird das nicht gebraucht, daher reicht Listenindexierung ohne Möglichkeit zur Änderung.**
