# Contao-Integration, Public API, Doku & Kompatibilität

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Bereits behobene Punkte (u. a. `mergePalettes`-No-Op, `huh.flare.list_type`-Alt-Tags, Attribut-Fallback für feste Driver-Tabellen, Terminal42-Doku-Markierung) sind nicht mehr enthalten. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## C-01: Doku-Beispiele erzeugen Fatal Error: `DcaBuilder` statt `DcaBuilderInterface` — Major (beide Audits)

Alle `buildDca()`-Beispiele typisieren den Parameter als konkrete Klasse; `DcaContract` verlangt das Interface (`src/Contract/DcaContract.php:18`) → Kontravarianz-Verletzung, Fatal Error beim Copy-Paste.

- `docs/docs/dev/dca-builder.md:15,77` · `docs/docs/dev/contracts/dca-contract.md:10,30` · `docs/docs/dev/filter-elements/index.md:125,226` · `docs/docs/dev/list-types/index.md:200` · `docs/docs/migrating-from-v0.1.md:140`

## C-02: Intrinsic-Handling ist Element-Verantwortung — Drittanbieterfalle — Major (beide Audits, teilentschärft)

Die Form-Factory filtert intrinsische Filter nicht zentral (`src/Filter/Factory/FilterFormFactory.php:60-121`); `AbstractFilterElement::buildForm()` ist No-Op-Default (`src/Filter/Element/AbstractFilterElement.php:51`). Ein Dritt-Element ohne eigenen `$context->config['intrinsic']`-Check rendert Formfelder für intrinsische Filter im Frontend. Der Dev-Guide dokumentiert das Muster inzwischen inkl. Beispiel (`docs/docs/dev/filter-elements/index.md:145-146,256-262`) — der Interface-Docblock nennt das Pflichtmuster aber weiterhin nicht (`src/Filter/Element/FilterElementInterface.php:13-24`), und ein zentraler Guard fehlt. Zusammen mit dem stillen Skip fehlender intrinsischer Elemente (PS-08 in [40-performance-stabilitaet.md](40-performance-stabilitaet.md)) ein potentielles Sichtbarkeits-Leak.

## C-03: Terminal42-/DcMultilingual-Integration halb verdrahtet — tote DB-Felder — Major (beide Audits, teilentschärft)

Code-Seite siehe A-01 in [10-architektur.md](10-architektur.md). Zusätzlich auf DCA-Seite: `tl_content.flare_dcMultilingualDisplay` definiert (`contao/dca/tl_content.php:76-85`), in keiner Palette (`:92-99`); `tl_flare_list.dcMultilingual_display` definiert (`contao/dca/tl_flare_list.php:288-297`), in keiner Palette (`:317-323`); Label für `flare_generic_dc_multilingual` fehlt in `translations/flare_list.{de,en}.php`. Es entstehen SQL-Spalten, die kein Redakteur sieht. Doku/README markieren die Integration inzwischen korrekt als disabled — die Entscheidung „aktivieren oder ausbauen" steht aus.

## C-04: Boolean-Element: Backend-Select zeigt rohe Übersetzungs-Keys, Feld-Labels fehlen — Major (claude)

`preselect`-Options nutzen die Keys `flare.bool_preselect.{null,true,false}`, die nirgends definiert sind (weder `translations/` noch `contao/languages/`); Contao übersetzt Options-Labels nicht automatisch. Zusätzlich fehlen Labels für `boolMode`/`boolBinaryChoices` in beiden Sprachdateien.

- `src/Filter/Element/BooleanFilterElement.php:117-130` · Felder `contao/dca/tl_flare_filter.php:616,630` · keine Label-Einträge in `contao/languages/{de,en}/tl_flare_filter.php`

## C-05: DBAL-2-Versprechen nicht erfüllt; Compatibility-CI toleriert alle Fehler — Mittel (codex)

`composer.json:12` erlaubt `doctrine/dbal ^2.13`, der Code nutzt `Doctrine\DBAL\ArrayParameterType` (erst ab DBAL 3.6): `src/Query/FilterQueryBuilder.php:7,123,160,202,206`, `src/Filter/Type/ArchiveFilterType.php:7,29`, `src/Filter/Type/IntegerIdChoiceFilterType.php:7`. Die Compatibility-Matrix läuft mit `continue-on-error: true` und prüft nur `composer update --dry-run`. (Fix: PS-03 in [40-performance-stabilitaet.md](40-performance-stabilitaet.md); CI: CI-01/CI-02 in [50-tests-ci.md](50-tests-ci.md).)

## C-06: Verhaltensänderungen fehlen in der Migrationsdoku: stiller Alias-Skip & Intrinsic-Verlagerung — Minor (beide Audits)

Aliase, die kein gültiger Symfony-Formname sind, werden still nicht gemountet (`src/Filter/Factory/FilterFormFactory.php:62-64`); das ist nur als Code-Docblock erklärt (`src/Util/Str.php:110-119`), nicht in `docs/docs/migrating-from-v0.1.md`. Gleiches gilt für die Intrinsic-Verantwortungsverlagerung (C-02) — beide Verhaltensänderungen gegenüber `main` fehlen auf der Migrationsseite.

## C-07: Übersetzungs-Domain-Mismatch bei Fehlermeldungen — Minor (claude)

Beide Controller fragen `ERR.flare.listview.malconfigured` mit Domain `contao_modules` an; definiert ist der Key in der Default-Sprachdatei (Domain `contao_default`) — funktioniert nur, weil Contao diese global lädt. Der Reader nutzt zudem denselben „list view"-Text. (Statuscode-Inkonsistenz 200 vs. 500: PS-05 in [40-performance-stabilitaet.md](40-performance-stabilitaet.md).)

- `src/Controller/ContentElement/ListViewController.php:69-70` · `src/Controller/ContentElement/ReaderController.php:80-81` · `contao/languages/{de,en}/default.php:73`

## C-08: DateRange: Optionen ohne Backend-Repräsentation, Palette ohne Legende — Minor (claude, teilentschärft)

`from_enabled`/`to_enabled` sind im Schema definiert (`src/Filter/Element/DateRangeFilterElement.php:37-38`), aber weder `transformFilterModel()` (`:41-46`) noch ein DCA-Feld setzt sie — nur programmatisch nutzbar. Palette ohne `{filter_legend}` (`:93`). (Inzwischen immerhin dokumentiert: `docs/docs/reference/filter-elements.md:13`. Funktionsloser `intrinsic`-Modus: K-10 in [20-korrektheit.md](20-korrektheit.md).)

## C-09: Übersetzungen: DE/EN-Lücken, Waisen, Tippfehler — Minor (claude)

- EN fehlt der Eintrag für `cfg_tags_search` (DE: `translations/flare_filter.de.php:19`). Abgeschwächt: das Element ist via `isSupported(): false` im Backend ausgeblendet.
- Verwaist: `useTablePtable` in `contao/languages/{de,en}/tl_flare_filter.php:25` (keine DCA-/Code-Referenz).
- Ungenutzt: `filter.limited_scope.*`, `filter.scope.*`, `filter.info.alias` in `translations/flare.{de,en}.yaml`; auch `filter.info.intrinsic.yes|no` scheinen ungenutzt.
- Tippfehler: „This filter **ist** not intrinsic" — `translations/flare.en.yaml:31`.

## C-10: Literal-Key-Trick in `messages.{de,en}.yaml` unkommentiert — Info (claude)

Die Keys `Listen (FLARE)`/`Listings (FLARE)` spiegeln die MOD-Labels (`contao/languages/de/modules.php:7`) und brechen still bei Label-Änderung; `src/EventListener/BackendMenuBuildListener.php:30-33` string-matcht zusätzlich am `(FLARE)`-Suffix. Ein erklärender Kommentar fehlt.

- `translations/messages.de.yaml:1` · `translations/messages.en.yaml:1`

## C-11: Stale Excludes in `services.yaml` — Minor (claude)

`../src/{…,Dto,…,Trait,…}` wird exkludiert — beide Verzeichnisse existieren nicht.

- `config/services.yaml:14`

## C-12: Tote Klasse `DateRangeFormType` inkl. verwaister Validator-Keys — Minor (claude)

Nur Selbstreferenzen; die einzig dort genutzten Keys `flare.form.date_range.from_invalid|to_invalid` (`src/Form/Type/DateRangeFormType.php:95,102`) stehen noch in `translations/validators.{de,en}.yaml`.

- `src/Form/Type/DateRangeFormType.php:14`

## C-13: `CodefogTagsSearchElement` als Stub im Auslieferungszustand — Info (claude)

`isSupported(): false`, zwei `TODO`-Bodies, als einziges Element nicht auf `…FilterElement`-Suffix umbenannt. Doku markiert es als disabled (`docs/docs/reference/filter-elements.md:32`).

- `src/Integration/CodefogTags/FilterElement/CodefogTagsSearchElement.php:16-38`

## C-14: Dokumentierte Builder-API `getDc()` existiert nicht — Minor (beide Audits)

Der Text bewirbt `getDc()` auf dem Builder; `ListSpecBuilder` (`src/List/ListSpecBuilder.php:39-122`) und das Interface besitzen keine solche Methode. Die dc-Auflösung passiert erst in `ListSpecFactory::resolveDataContainer()`.

- `docs/docs/dev/list-types/index.md:141-144`

## C-15: `field()` liefert laut Doku `DcaFieldBuilder`, Interface liefert `DcaFieldBuilderInterface` — Minor (claude)

- `docs/docs/dev/dca-builder.md:39` vs. `src/DataContainer/Builder/DcaBuilderInterface.php:17`

## C-16: AGENTS.md/CLAUDE.md verwendet alte Namen — Minor (beide Audits)

`ListBuilderFactory`/`ListBuilder` (tatsächlich `ListSpecBuilderFactory`/`ListSpecBuilder`), `#[AsListType]` (tatsächlich `AsListDriver`), `ListTypeRegistry` (tatsächlich `ListDriverRegistry`). (Vollständige Liste inkl. `FilterElementResolver`/EngineFactory: A-02 in [10-architektur.md](10-architektur.md).)

- `AGENTS.md:21,39,60,71`

## C-17: Kleinere Schönheitsfehler — Info (claude)

- Fallback-Label `'CBX'` erreicht ungefiltert das Frontend: `src/Filter/Element/BooleanFilterElement.php:56` (= K-11)
- `Message::addError(...)` hartkodiert Englisch (`src/Filter/Element/BooleanFilterElement.php:136`), während `src/List/Driver/GenericDataContainerListDriver.php:111` sauber den Translator nutzt
- Docblocks verweisen auf nicht existentes `configureDca()` (tatsächlich `buildDca`): `src/EventListener/Contao/ElementDcaListener.php:24`, `src/Event/ElementDcaEvent.php:12`
- Palette enthält `guests` — Feld existiert in Contao 5 nicht mehr: `contao/dca/tl_content.php:89`

## C-18: Offene Doku-Wünsche — Niedrig (codex)

- Skalierungsgrenzen für `FieldValueChoice` (DISTINCT-Werte) und Calendar nicht dokumentiert (`docs/docs/reference/filter-elements.md:12,16`)
- Suchverhalten (OR-Semantik, Stoppwörter, Sonderzeichen) nicht spezifiziert (`docs/docs/reference/filter-elements.md:19`)
- Expliziter Hinweis fehlt, dass der Generic-Driver keine Published-/Access-Filter ergänzt (`docs/docs/reference/list-types.md:11-13`); teilentschärft durch die neue Backend-Info-Meldung (`src/List/Driver/GenericDataContainerListDriver.php:97-113`, siehe SEC-02 in [30-sicherheit.md](30-sicherheit.md))

## C-19: DX-Reibungspunkte — Info (claude)

- `AbstractFilterElement` erzwingt `transformFilterModel()` als abstract — rein programmatische Elemente müssen eine leere Methode implementieren (`src/Filter/Element/AbstractFilterElement.php:47`)
- Elemente ohne `DcaContract` erhalten kommentarlos die nackte Prefix/Suffix-Palette, kein Hinweis-Log (`src/EventListener/Contao/ElementDcaListener.php:96-104`)

## Querverweise

- Stop-Word-Feature tot (`huh_flare.search_stop_words.{locale}` existiert nie): K-07 in [20-korrektheit.md](20-korrektheit.md)
- Backend-Ansicht ohne Null-Guard auf `$listModel`: PS-04 in [40-performance-stabilitaet.md](40-performance-stabilitaet.md) / A-15 in [10-architektur.md](10-architektur.md)
