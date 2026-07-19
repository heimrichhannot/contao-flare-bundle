# Kombiniertes Audit: contao-flare-bundle — Branch `feat/filter-types`

**Datum:** 2026-07-20 · **Stand:** Commit `5940ad6`
**Quellen:** `.audit/2607171801-claude/` und `.audit/2607171755-codex/` (beide vom 2026-07-17, Review-Commit `39065f73`)

## Methodik

Jeder Punkt beider Audits wurde gegen den aktuellen Code (`5940ad6`) verifiziert. Enthalten sind **ausschließlich weiterhin valide Punkte** mit aktuellen Datei-/Zeilen-Belegen; inzwischen behobene sowie widerlegte Punkte wurden entfernt, Duplikate beider Audits zusammengeführt. Positive Beobachtungen stehen separat in [99-positive-punkte.md](99-positive-punkte.md), damit die actionable Dateien schlank bleiben.

Seit dem Audit-Datum wurden mehrere der ursprünglichen Top-Findings behoben — darunter der `?_preview`-Feld-Dump, die verlorene Listen-ID im `ListSpec`-Pfad, die Transformer-Memoization pro Klasse, das Suche-verwirft-sich-selbst-Kernproblem und der `'0'`-Verlust im `ChoicesBuilder`. Die Kapitel Tests/CI und Performance/Stabilität sind dagegen vollständig unverändert offen.

## Die Dateien

| Datei | Thema | Schwerste offene Findings |
|---|---|---|
| [10-architektur.md](10-architektur.md) | Architektur & Design | Terminal42 als toter, nicht kompilierbarer Code (A-01); stale AGENTS.md (A-02) |
| [20-korrektheit.md](20-korrektheit.md) | Korrektheit & Bugs | Boolean-Binary-Modi nicht implementiert (K-01), unabwählbarer Preselect (K-02), Kalender-Datumsgrenzen ignoriert (K-03), totes Stop-Word-Feature (K-07), `'0'`-Verluste (K-08) |
| [30-sicherheit.md](30-sicherheit.md) | Security & Query-Safety | Model-Registry umgeht `start`/`stop`-Fenster (SEC-01); Backend-Ausgabe teils unescaped (SEC-03) |
| [40-performance-stabilitaet.md](40-performance-stabilitaet.md) | Performance & Stabilität | 500er statt Degradierung im Render-Pfad (PS-01), positionaler Entry-Cache (PS-02), DBAL-Constraint (PS-03), Calendar-OOM-Potenzial (PS-15/16), doppelte Query-Pipeline (PS-14) |
| [50-tests-ci.md](50-tests-ci.md) | Tests, CI & Tooling | Query-Schicht/Filter-Types/Engine ungetestet (T-01), Compatibility-Gate durch `continue-on-error` entwertet (CI-02), PHPUnit nur PHP 8.2 (CI-01) |
| [60-contao-integration-doku.md](60-contao-integration-doku.md) | Contao-Integration, API, Doku & Kompatibilität | Doku-Beispiele mit Fatal Error (C-01), Intrinsic-DX-Falle (C-02), tote DB-Felder der Terminal42-Integration (C-03), rohe Backend-Labels (C-04) |
| [99-positive-punkte.md](99-positive-punkte.md) | Positivbefunde (nicht actionable) | — |

## Priorisierung (konsolidiert, nur offene Punkte)

### Vor dem Merge fixen

1. **DBAL-Constraint `^2.13 || ^3.0` erlaubt Versionen ohne `ArrayParameterType`** → Fatal auf Contao 4.13; Fix ist eine Zeile: `^3.6 || ^4.0` (PS-03, C-05).
2. **Render-Pfad-Stabilität:** `createView()` läuft erst im Template; Laufzeitfehler eines kaputten Filters reißt die Seite in einen 500er — `createView()` in den Controller ziehen bzw. `FlareException` beim Rendern abfangen; dazu 200-vs-500-Inkonsistenz Listview/Reader (PS-01, PS-05).
3. **Entry-Cache positional statt per ID indiziert** — falscher Datensatz im Reader-Pfad möglich, öffentliche API (PS-02).
4. **Doku-`buildDca()`-Beispiele erzeugen Fatal Error** (konkrete Klasse statt `DcaBuilderInterface`; C-01).

### Zeitnah (Korrektheit der namensgebenden Features)

5. **BooleanFilterElement:** `NULL_FALSE`/`TRUE_FALSE` nicht implementiert, unabwählbarer Preselect, „CBX"-Label, rohe Backend-Keys (K-01, K-02, K-11, C-04).
6. **CalendarCurrentFilterElement:** numerische Datumsgrenzen wirkungslos, kein Gating durch `configure_*`, ungefangene Exception (K-03, K-04, K-05).
7. **Suche:** nur-leere Suchgruppen → `ArgumentCountError` (K-06); Stop-Word-Feature komplett tot — Parameter existiert nie (K-07).
8. **`'0'`-/falsy-Verluste in den Choice-Pfaden** inkl. Label-Kollisionen (K-08, K-09).
9. **Sichtbarkeit:** still geskippte intrinsische Filter (PS-08), Registry-Shortcut umgeht `start`/`stop` (SEC-01), fehlende Generic-Driver-Warnung im No-Parent-Zweig (SEC-02), Preview-Modus ignoriert (PS-09).
10. **Intrinsic-Pflichtmuster** in Interface-Docblock/zentralem Guard verankern; Migrationsdoku um Alias-Skip + Intrinsic-Verlagerung ergänzen (C-02, C-06).

### Vor dem ersten Stable-Release

11. **Testabdeckung der Risikozonen:** `src/Query/` (SQL-Leitplanken!), Filter-Types, Engine-Pipeline, Paginator, ChoicesBuilder; Regressionstests für K-01–K-08 gleich mitnehmen; Stubs autoloadbar machen, Random-Order aktivieren (T-01, T-02, T-03).
12. **CI reparieren:** `continue-on-error` raus, PHPUnit-Matrix (lowest-deps + Contao 4.13), `pull_request`-Trigger, `composer audit` ohne `|| true`, Mago wieder inkl. `tests/` (CI-01–CI-05).
13. **Terminal42-Integration entscheiden:** portieren oder entfernen — toter, nicht kompilierbarer Code plus tote DB-Felder, unsichtbar nur dank PHPStan-Excludes (A-01, C-03).
14. **Public-API-/DX-Politur:** Registry-Vereinheitlichung, `#[TaggedIterator]`-Ablösung, `PaginatorConfig`-TypeErrors und Off-by-one, Alias-Kollisions-Warning, Übersetzungslücken/Waisen (A-03–A-16, K-12, K-13, C-07–C-19).

### Performance-Backlog (kein Blocker, aber lohnend)

- Filter-Pipeline-Ergebnis request-scoped teilen — Count + Entries + Partials rechnen bis zu 3× dasselbe (PS-14, PS-17, PS-21).
- Calendar-Integration: SQL-seitiges Zeitfenster + harte Occurrence-Obergrenze — Full-Fetch ×2 + unbegrenzte Expansion = OOM-Risiko durch Redakteurs-Eingabe (PS-15, PS-16).
- Shared-Service-Caches via `kernel.reset` leeren — sonst stale unter Worker-Runtimes (PS-07).
- Choices begrenzen (LIMIT/Suche/Ajax) und O(n²)-Wertauflösung beheben (PS-19, PS-20).
