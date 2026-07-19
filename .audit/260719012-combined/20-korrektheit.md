# Korrektheit & Bugs

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Mehrere ursprüngliche Top-Findings sind inzwischen behoben (u. a. Suche-verwirft-sich-selbst im Kern, `'0'`-Verlust im `ChoicesBuilder`, DCA-Laden im Frontend, halbiertes Paginator-Fenster, `mergePalettes`-No-Op) und daher nicht mehr enthalten. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## K-01: Boolean-Filter: `binary_choices` `NULL_FALSE`/`TRUE_FALSE` nicht implementiert — Major/Hoch (beide Audits)

`normalizeValue()` behandelt ausschließlich `NULL_TRUE` speziell; `NULL_FALSE` und `TRUE_FALSE` laufen in `filter_var()`, wodurch z. B. bei `null_false` eine angehakte Checkbox auf `true` statt `false` filtert. Die Enum-Helper `hasNull()`/`hasTrue()`/`hasFalse()` bleiben ungenutzt.

- `src/Filter/Element/BooleanFilterElement.php:90-107` (Sonderfall nur `:101`)

## K-02: Boolean-Filter: Preselect nicht abwählbar, Formular zeigt ihn nicht an — Major/Hoch (beide Audits)

`buildForm()` setzt kein `'data' => $preselect` (Checkbox rendert unangehakt trotz aktivem Filter). Abwählen + Submit ergibt `false` → `normalizeValue(false, NULL_TRUE)` → `null` → `?? $config['preselect']` reaktiviert den Filter — der Preselect ist unabwählbar.

- `src/Filter/Element/BooleanFilterElement.php:55-58` (kein `data`), `:87` (Preselect-Fallback)

## K-03: Calendar-Filter: numerisch gespeicherte Datumsgrenzen werden ignoriert — Major/Mittel (beide Audits)

Im Modus `date` normalisiert der Load-/Save-Callback `startAt`/`stopAt` auf einen numerischen Timestamp (`src/EventListener/DataContainer/FlareFilter/FieldsLoadAndSaveCallbacks.php:169-173`), aber `buildFilter()` ruft `\strtotime((string) $config['start_at'])` auf — `strtotime('1750723200')` ist `false` → `$start = 0`, `$stop = maxTimestamp()`; ebenso fehlen die min/max-Formattribute. `DateTimeHelper::toTimestamp()` (`src/Util/DateTimeHelper.php:103`) wird nicht benutzt.

- `src/Filter/Element/CalendarCurrentFilterElement.php:110-111,166-177`

## K-04: Calendar-Filter: `configure_start`/`configure_stop` gaten den Filter nicht; Save-Callback räumt nicht auf — Minor (beide Audits)

`buildFilter()` nutzt `start_at`/`stop_at` bedingungslos; der Save-Callback early-returnt bei Leerwahl (`if (!$value) return $value;`) und lässt den alten `startAt`-Wert stehen — der Filter filtert veraltet weiter.

- `src/Filter/Element/CalendarCurrentFilterElement.php:110-111` · `src/EventListener/DataContainer/FlareFilter/FieldsLoadAndSaveCallbacks.php:115-119`

## K-05: Calendar-Filter: ungefangene Exception bei Garbage-Strings — Minor (claude)

`mixedToDateTime()` wirft bei unparsebaren Strings ungefangen (`new \DateTimeImmutable($input)`), erreichbar über programmatische `Filter::$data`.

- `src/Filter/Element/CalendarCurrentFilterElement.php:225-227`

## K-06: Suchfilter: nur-leere Suchgruppen führen zu `ArgumentCountError` — Minor, Rest eines Major-Findings (beide Audits)

Der Kernbug (`return` mitten in der Schleife verwarf die gesamte Suche) ist behoben (`continue`). Die empfohlene Behandlung „gar keine valide Gruppe übrig" fehlt aber: Bei Suchtext nur aus Garbage/Stoppwörtern (z. B. `"!!!"` — erreichbar, da `SearchKeywordsFilterElement::buildFilter` jeden nicht-leeren String durchreicht, `src/Filter/Element/SearchKeywordsFilterElement.php:72-83`) bleibt `$or = []` und `$builder->expr()->or(...$or)` wird ohne Argumente aufgerufen — DBAL verlangt mindestens ein Argument → `ArgumentCountError` statt Ergebnisliste.

- `src/Filter/Type/SearchKeywordsFilterType.php:29-52` (insb. `:52`)

## K-07: Such-Stoppwörter erreichen den `ConfigProvider` nie — Mittel (codex)

Die Extension setzt nur `huh_flare` (Gesamtarray) und `huh_flare.format_label_defaults`; `ConfigProvider` fragt `huh_flare.search_stop_words.<locale>` ab, das nirgends erzeugt wird — die ausgelieferten Stoppwortlisten (`config/config.yaml:17`) sind wirkungslos (totes Feature).

- `src/DependencyInjection/HeimrichHannotFlareExtension.php:50-51` · `src/ConfigProvider.php:30-37`

## K-08: Choice-Elemente: Wert `'0'` und falsy Labels gehen verloren — Minor–Mittel (beide Audits, Teilaspekt `ChoicesBuilder` behoben)

Weiterhin valide Teilaspekte:

- `FieldValueChoiceFilterElement::extractSubmittedData()`: erstes `\array_filter($submittedData)` ohne Callback entfernt `'0'`; zudem pauschales `array_map('strtolower', ...)`. — `src/Filter/Element/FieldValueChoiceFilterElement.php:251-252`
- `DcaSelectFieldFilterElement::buildFilter()`: `if (!$selected) { return; }` verwirft sowohl den intrinsischen Preselect `'0'` als auch eine Runtime-Einzelauswahl mit Key `'0'`. — `src/Filter/Element/DcaSelectFieldFilterElement.php:103-109`
- `DcaSelectFilterType` Multi-Pfad: `if ($validOptions[$value] ?? null)` filtert Keys mit falsy Label (`'0'`, `''`) aus → ggf. `$filtered` leer → `abort()` → ganze Liste leer; der Single-Pfad nutzt korrekt `array_key_exists` (inkonsistent). — `src/Filter/Type/DcaSelectFilterType.php:59` vs. `:38`

## K-09: DcaSelect: Label→Key-Rückabbildung kollidiert bei doppelten Labels — Minor (beide Audits)

`normalizeSubmittedValue()` mappt submittete Labels per `array_search` auf Keys — bei identischen (übersetzten) Labels gewinnt immer der erste Key, unmappbare Werte werden `''`.

- `src/Filter/Element/DcaSelectFieldFilterElement.php:184-198`

## K-10: `DateRangeFilterElement`: `intrinsic`-Modus ist funktionslos — Minor (claude)

`intrinsic` ist über die Basis-Palette wählbar (`contao/dca/tl_flare_filter.php:645`), aber es gibt keine intrinsischen from/to-Konfigwerte; `buildFilter()` erhält leere `$values` → keinerlei Bedingung.

- `src/Filter/Element/DateRangeFilterElement.php:33-46,78-89`

## K-11: BooleanFilterElement: Debug-Platzhalter „CBX" als Frontend-Label — Minor (claude)

- `src/Filter/Element/BooleanFilterElement.php:56` (`'label' => $context->config['label'] ?? 'CBX'`)

## K-12: PaginatorFactory: `getTotalItems()` kann `null` liefern → TypeError — Minor (claude)

`PaginatorConfig::getTotalItems(): ?int` liefert `null` bei Default `-1`; `Paginator::__construct(int $totalItems)` ist nicht nullable — jeder API-Konsument mit Default-Config crasht.

- `src/Paginator/Factory/PaginatorFactory.php:39` · `src/Paginator/PaginatorConfig.php:57-60` · `src/Paginator/Paginator.php:17-21`

## K-13: `PaginatorConfig::getCurrentPageItemCount`: Off-by-one — Minor (beide Audits)

`getLastItemNumber() - getFirstItemNumber()` ohne `+1` — Seite mit Items 1–10 meldet 9.

- `src/Paginator/PaginatorConfig.php:158-161`

## K-14: `TableAliasRegistry`: aktivierter, aber nicht registrierter Alias wird still übersprungen — Minor (codex)

`resolveActiveJoins()` überspringt aktivierte Aliasse ohne registrierten Join kommentarlos, während `ConditionsModifierListener` die zugehörige Filterbedingung trotzdem anhängt — die Query referenziert dann einen nicht existierenden SQL-Alias (SQL-Fehler statt klarer Exception).

- `src/Query/TableAliasRegistry.php:150-152` · `src/EventListener/QueryStructModifier/ConditionsModifierListener.php:30-37`

## K-15: `FilterQueryBuilder`: Parameter-Prefixer ersetzt Tokens auch in String-Literalen — Minor, theoretisch (claude)

`build()` schreibt `:name`-Tokens per Regex über den gesamten SQL-String um, ohne String-Literale (z. B. gequotete REGEXP-Muster aus `SqlHelper`) auszunehmen. Nur ausgelöst, wenn ein gleichnamiger Parameter existiert — fragil, derzeit kaum erreichbar.

- `src/Query/FilterQueryBuilder.php:262-281`

## K-16: Expliziter `pageParam` gleich dem Formularnamen wird ungefragt suffigiert — Minor (claude, teilweise entschärft)

Ein explizit konfigurierter `pageParam`, der dem Formularnamen entspricht, wird kommentarlos mit `_page` suffigiert. (Der Teilaspekt „Vergleich läuft vor der Sanitisierung" ist behoben.)

- `src/Engine/Projector/InteractiveProjector.php:194-197`

## Beobachtungen (kein unmittelbarer Fix, aber weiterhin zutreffend)

- **`which_ptable` wird transformiert, aber nie gelesen** (claude O1): Runtime-Inferenz basiert auf der Listen-Config, `buildDca()` auf dem Filter-Model. — `src/Filter/Element/BelongsToRelationFilterElement.php:55` (gesetzt) vs. `:63-104` (ungelesen)
- **`genericPageMeta` im Schema definiert, aber in `transform()` nicht gemappt** (claude O2). — `src/List/BaseListOptions.php:41` vs. `:44-64`
- **`PtableInferrer`: `explode('.', $foreignKey)` ohne Limit/Guard** (claude O4) — foreignKey ohne Punkt erzeugt „Undefined array key 1". — `src/InferPtable/PtableInferrer.php:180`

## Querverweise (in anderen Kapiteln behandelt)

- DBAL-Constraint erlaubt inkompatibles 2.13/3.0–3.5 (codex STAB-01): PS-03 in [40-performance-stabilitaet.md](40-performance-stabilitaet.md)
- Entry-Cache positional statt per ID (codex STAB-02): PS-02 ebd.
- Laufzeitfehler erst im Twig-Rendern, 200-vs-500-Inkonsistenz (codex STAB-03): PS-01/PS-05 ebd.
- Backend-Vorschau dereferenziert gelöschte Liste (codex STAB-04): PS-04 ebd. / A-15 in [10-architektur.md](10-architektur.md)
- Alias-Kollision im Collector/Builder (beide, N8): A-07 in [10-architektur.md](10-architektur.md); zusätzlich betroffen: `src/List/ListSpecBuilder.php:77-78`
- `AggregationLoader::fetchCount()` ohne int-Cast (beide, N10): PS-12 ebd.
- `ValidationLoader` liefert `[]` statt `null` (beide, N13): A-10 in [10-architektur.md](10-architektur.md)
- Terminal42: tote Klassen (claude O3): A-01 in [10-architektur.md](10-architektur.md)
