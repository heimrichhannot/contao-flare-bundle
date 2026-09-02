# Tests, CI & Tooling

Kombinierte, am Stand `5940ad6` (2026-07-20) verifizierte Findings aus beiden Audits (claude 2607171801, codex 2607171755). Alle hier gelisteten Punkte wurden gegen den aktuellen Code geprüft und bestehen fort. Positive Beobachtungen: siehe [99-positive-punkte.md](99-positive-punkte.md).

## T-01: Risikoreichste Subsysteme ohne jegliche Tests — Major (beide Audits)

Die Testsuite besteht aus 22 Dateien (21 Testklassen + 1 Stub). Weiterhin vollständig ungetestet:

- `src/Query/` — insbesondere `src/Query/FilterQueryBuilder.php` (SQL-Injection-Leitplanke, Identifier-Whitelist, Parameterbindung) und `src/Query/TableAliasRegistry.php` (rekursive JOIN-Auflösung), außerdem `src/Query/Executor/ListQueryDirector.php`, `src/Query/Executor/FilterExecutor.php`
- `src/Filter/Type/` — 0 von 11 konkreten Filter-Types getestet, obwohl namensgebendes Feature des Branches
- Filter-Elemente: nur 2 von 10 konkreten Elementen getestet (`ArchiveFilterElement`, `SimpleEquationFilterElement`); `BooleanFilterElement`, `DateRangeFilterElement`, `PublishedFilterElement`, `SearchKeywordsFilterElement` etc. ungetestet
- Engine-Pipeline (Contexts, Loader, Mods, Views, `EngineFactory`) — nur `tests/Engine/Projector/InteractiveProjectorTest.php` existiert
- `src/EventListener/QueryStructModifier/`, `src/Paginator/Paginator.php`, `src/Form/ChoicesBuilder.php` — 0 Tests
- `src/Util/`, `src/Reader/` (inkl. Marshal-Logik in `src/Reader/ReaderRequestAttribute.php`), `src/InferPtable/`, `src/Controller/`, `src/Sort/`, `src/DataContainer/`, `src/Twig/`, `src/Integration/` (alle), `src/DataCollector/`, `src/DependencyInjection/`
- List-Driver: `src/List/Driver/NewsListDriver.php`, `src/Integration/ContaoCalendar/ListDriver/EventsListDriver.php`

Alles überwiegend pure PHP-Logik und gut unit-testbar. Regressionstests für die validen Korrektheits-Findings (siehe [20-korrektheit.md](20-korrektheit.md)) sollten gleich mitgenommen werden.

## T-02: Stub-Klassen nicht autoloadbar — Einzeldatei-Testläufe brechen — Minor/Mittel (beide Audits)

`FilterModelStub` ist in `tests/Filter/Element/SimpleEquationFilterElementTest.php:82` definiert, wird aber in `tests/Filter/Element/ArchiveFilterElementTest.php:95` benutzt; `ListModelStub` ist in `tests/List/BaseListOptionsTest.php:76` definiert, wird in `tests/List/ListSpecBuilderTest.php:106` und `:165` benutzt. Dateiname ≠ Klassenname → PSR-4-autoload-dev kann sie nicht auflösen; isolierte Einzeldatei-Läufe und randomisierte Reihenfolge sind fragil. Vorlage für den Fix existiert bereits: `tests/List/StubFilterElement.php` (eigene Datei).

## T-03: `phpunit.xml.dist` ohne `executionOrder="random"` / `beStrictAboutOutputDuringTests` — Minor (beide Audits)

`phpunit.xml.dist:2-8` enthält nur `failOnRisky`/`failOnWarning`; keine Random-Order, kein `resolveDependencies`, kein `beStrictAboutOutputDuringTests`. Random-Order würde das Stub-Problem (T-02) sofort aufdecken.

## T-04: `symfony/phpunit-bridge` in require-dev, aber nicht im Bootstrap — Minor (claude)

`phpunit.xml.dist:4` bootstrapt plain `vendor/autoload.php`; `composer.json:39` deklariert `symfony/phpunit-bridge` — kein Deprecation-Tracking.

## T-05: Coverage konfiguriert, aber nirgends erzeugt — Info (claude)

`phpunit.xml.dist:19-26` definiert den Coverage-Filter, aber alle Workflows setzen `coverage: none`; kein Coveralls-Upload trotz `php-coveralls` in require-dev.

## T-06: Keine DataProvider in der Suite — Info (claude)

0 Treffer für `dataProvider`/`DataProvider` in `tests/`. Geschmackssache, bei Transformer-/Boolean-/Choice-Tests aber deutlich kompakter.

## CI-01: PHPUnit läuft nur auf PHP 8.2 mit Highest-Deps — keine Runtime-Matrix — Major (beide Audits)

`.github/workflows/phpunit.yaml:24` pinnt `php-version: '8.2'`; `composer update` (`:41`) installiert Highest-Deps; kein `--prefer-lowest`, kein Contao-4.13-Lauf, keine Matrix — obwohl `composer.json:7,10` PHP ^8.2 × Contao ^4.13||^5.0 verspricht. Die Compatibility-Matrix (`.github/workflows/compatibility.yaml:20-26`) prüft nur `composer update --dry-run` (`:50-52`), nie Verhalten.

## CI-02: Compatibility-Matrix durch `continue-on-error: true` entwertet — Major (beide Audits)

`.github/workflows/compatibility.yaml:16` setzt `continue-on-error: true` auf Job-Ebene — jede rote Matrix-Zelle wird grün durchgewunken. Ausgerechnet der einzige Workflow mit `pull_request`-Trigger (`:4`) ist damit dekorativ.

## CI-03: Kein `pull_request`-Trigger auf PHPUnit/PHPStan/Mago — Fork-PRs ungeprüft — Minor (beide Audits)

`.github/workflows/phpunit.yaml:3-11`, `.github/workflows/phpstan.yaml:3-11` und `.github/workflows/mago.yaml:3-11` triggern nur auf `push` + `workflow_dispatch`. Fork-PRs laufen ohne Tests und Statik.

## CI-04: `composer audit || true` kann nie fehlschlagen — Minor/Mittel (beide Audits)

`.github/workflows/security.yaml:56` enthält `composer audit || true` — Advisories werden nie zum Gate. (Semgrep failt dagegen korrekt via `--error`, `security.yaml:68`.)

## CI-05: Mago lintet die Tests auf dem Branch nicht mehr — Niedrig (codex)

Branch-Regression: `mago.toml:6` enthält nur noch `paths = ["src/"]`; auf `main` steht `paths = ["src/", "tests/"]`. Die neue Testsuite wird nicht gelintet/formatiert.

## ST-01: PHPStan-Ignores zu breit — Minor/Niedrig (beide Audits)

`phpstan.neon:25` und `:29` ignorieren `Access to an undefined property Contao\…Model::$…` bzw. undefined static methods repo-weit ohne `path`-Eingrenzung — echte Tippfehler in `src/` werden verschluckt. `phpstan.neon:19-20` ignoriert `class.notFound` für ganz `src/Integration/` (auch hausgemachte Klassen in ContaoCalendar/ContaoNews/ContaoComments); `phpstan.neon:13` schließt `src/Integration/Terminal42Languages` komplett aus.

## ST-02: `phpVersion: 80200` — PHPStan sieht keine 8.4/8.5-Deprecations — Info (claude)

`phpstan.neon:7`; teilkompensiert durch Magos Multi-Version-Lint (`mago.yaml:57-75`).

## D-01: Tote Dev-Dependencies — Minor (claude)

Per Grep über `tests/`, `src/`, `.github/` verifiziert (0 Treffer): `contao/test-case` (`composer.json:33`), `heimrichhannot/contao-test-utilities-bundle` (`:35`), `php-coveralls/php-coveralls` (`:37`, kein Coverage-Workflow) und `symfony/phpunit-bridge` (`:39`, nicht im Bootstrap) werden nirgends benutzt.

## D-02: PHPUnit-Constraint `^8.0 || ^9.0` — `^8`-Standbein stale — Minor (claude)

`composer.json:36`; `phpunit.xml.dist:3` nutzt das 9.5-Schema, AGENTS.md dokumentiert PHPUnit 9.

## D-03: CSRF-Komponente in Tests nur transitiv deklariert — Info (claude)

`tests/Form/FilterFormFactoryTest.php:29` importiert `Symfony\Component\Security\Csrf\CsrfTokenManager`; `symfony/security-csrf` fehlt in `composer.json` (kommt nur transitiv über `contao/core-bundle`).

## M-01: Makefile-`.PHONY` unvollständig; Catch-all schluckt Tippfehler — Minor (claude)

`Makefile:1` listet `phpstan`/`phpstan-pro` (`Makefile:17-21`) nicht in `.PHONY`. Catch-all `%: @:` (`Makefile:52-53`) beendet Tippfehler lautlos mit Exit 0.
