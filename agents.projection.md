# FLARE Architektur-Übersicht: Projection Pattern für Contao CMS

---

## Rolle

Du bist ein Senior Software Architect und Developer Experte für Symfony 7 und PHP 8 im Kontext des Contao CMS.

Wir entwickeln eine Contao-Erweiterung namens FLARE (Akronym "Filter, List, and Reader").

---

## Architektur-Übersicht

Wir arbeiten mit einem spezialisierten Design-Pattern zur Generierung und Darstellung von Datenlisten, das Konzepte aus **CQRS** (Command Query Responsibility Segregation) und **ADR** (Action-Domain-Responder) adaptiert.

Das System basiert auf einer strikten Trennung der Responsibilities, optimiert für Testbarkeit, Symfony 7 Autowiring und kognitive Klarheit (Code Discovery). Die Architektur gliedert sich in vier logische Schichten (Layers):

1. Definition (Was?): Statische Beschreibung der Datenquelle und Filter (`Specification`).
2. Kontext (Wie?): Spezifische Konfiguration des Anwendungsfalls (`Context`).
3. Verarbeitung (Wer?): Stateless Services, die die Projektion durchführen (`Projector`).
4. Ergebnis (Daten): Stateful Data Objects für die Ausgabe (`View`).

Diese Trennung spiegelt sich direkt in der Namespace-Struktur wider (Specification, Context, Projector, View), um eine klare Orientierung im Bundle (HeimrichHannot\FlareBundle) zu gewährleisten.

---

## Die 4 Kern-Komponenten

### ListSpecification
- Art: Value Object / Definition Class.
- Aufgabe: Enthält die statischen Grundeinstellungen einer Liste und eine Collection von Filter-Elementen.
- Funktion: Sie definiert die Query-Manipulationen und die Datenquelle abstrakt, ohne zu wissen, wie sie später dargestellt wird.

### ContextConfig
- Art: Value Object / DTO.
- Aufgabe: Definiert den spezifischen Anwendungsfall (Kontext).
- Beispiele: InteractiveConfig (für HTML-Listen mit Paginierung/Formularen), ExportConfig (für CSV-Downloads), ValidationConfig.
- Funktion: Steuert Parameter wie Paginierungslänge, Formatierung oder lazy loading Verhalten für den spezifischen Aufruf.

### The Projector (Service / Factory)
- Art: Stateless Symfony Service.
- Aufgabe: Nimmt eine ListSpecification und eine ContextConfig entgegen.
- Funktion: Er "projiziert" die Spezifikation auf den Kontext. Er führt (ggf. lazy) Datenbank-Queries aus oder bereitet Query-Builder vor und instanziert das Ergebnis-Objekt.
- Beispiel: `InteractiveProjector` erstellt eine `InteractiveView`.

### The View (Data Object / Result)
- Art: Data Object (Stateful) – Kein Service!
- Aufgabe: Hält die finalen Daten oder Iteratoren für die Ausgabe.
- Verwendung: Wird an Twig-Templates oder Export-Funktionen übergeben.
- API: Enthält Methoden wie `getEntries()`, `getTotal()`, etc.
  Wichtig: Es ist kein fertiger HTML string, sondern ein Daten-Container ("View Data"), der im Template konsumiert wird.

---

## Der Flow

- Controller oder Twig-Funktion erstellt/lädt eine `ListSpecification`.
- Ein passendes ContextConfig Objekt wird erstellt (z.B. für eine interaktive Liste).
- Der passende Projector Service wird aufgerufen: `$projector->project($spec, $config)`.
- Rückgabe ist ein View.
- Der View wird im Twig Template genutzt, um die Liste zu rendern, oder in Controller, um Inhalte/Dateien/Berichte zu generieren.

---

## Standard-Kontexte & Anwendungsfälle

Das System liefert vier spezialisierte Standard-Implementierungen für `ContextConfig` und die dazugehörigen Projektionen.

### 1. Interactive (Frontend-Listen)
- Konfig: `InteractiveConfig`
- Ziel: Klassische Darstellung von Daten im Browser (HTML).
- Besonderheit: Verarbeitet Request-Daten (GET) automatisch.
- Features:
    - Verwaltet Pagination (Seite X von Y) und Paginierungslinks (mit `{list}_page`-Parameter in der URL).
    - Baut das Symfony Filter-Formular basierend auf der Specification.
    - Handhabt Sortierung durch User-Input.
- Ergebnis (`InteractiveView`): Liefert Methoden für `getEntries()` (Iterierbare Entities), `getPagination()` (Metadaten) und `getFormComponent()` (für Twig).

### 2. Aggregation (Zählung & Statistik)
- Konfig: `AggregationConfig`
- Ziel: Effiziente Ermittlung der Gesamtmenge ohne Laden der eigentlichen Daten.
- Besonderheit: Performance-optimiert (führt `COUNT` Queries aus, kein Entity-Hydrating).
- Features: Ignoriert Paginierung und Sortierung, wendet aber alle Filter-Kriterien der Specification an.
- Ergebnis (`AggregationView`): Liefert primär `getCount()`.

### 3. Validation (Prüfung & Auswahl)
- Konfig: `ValidationConfig`
- Ziel: Überprüfung, ob spezifische IDs oder Werte unter den aktuellen Filterbedingungen "sichtbar" bzw. gültig sind.
- Besonderheit: Wird oft intern genutzt, um z. B. Foreign-Key Constraints zu prüfen oder "White-lists" zu generieren.
- Features: Akzeptiert eine Liste von IDs ("Candidates"), um die Ergebnismenge darauf zu beschränken.
- Ergebnis (`ValidationView`): Liefert `isValid(id)` (Boolean) oder `getModel(id)` für valide Kandidaten.

### 4. Export (Daten-Download)
- Konfig: `ExportConfig`
- Ziel: Ausgabe aller Daten (oder einer großen Teilmenge) für externe Weiterverarbeitung (CSV, XML, JSON).
- Besonderheit: Deaktiviert standardmäßig die Paginierung (Limit = 0).
- Features: Kann Speicher-optimierte Iteratoren verwenden (unbuffered Queries), um Memory-Limits bei großen Listen nicht zu sprengen.
- Ergebnis (`ExportView`): Liefert einen reinen Daten-Iterator, optimiert für `fputcsv` oder Serializer.

---

## Namespace-Architektur

Die Architektur folgt einer Layer-basierten Struktur, um technische Verantwortlichkeiten (Definition vs. Verarbeitung vs. Ergebnis) sauber zu trennen. Dies begünstigt Symfony Autowiring und die kognitive Erfassbarkeit des Codes.

**Root-Namespace** (Bundle): `HeimrichHannot\FlareBundle`

### 1. Definition

Hier liegen die grundlegenden Definitionsobjekte, die das "Was" beschreiben, unabhängig vom Kontext.

- Namespace: `HeimrichHannot\FlareBundle\Specification`
- Kern-Klasse: `ListSpecification`

### 2. Context

Beheimatet die Konfigurations-DTOs (ContextConfigs), die den Anwendungsfall steuern.

- Namespace: `HeimrichHannot\FlareBundle\Context`
- Klassen:
  - `InteractiveConfig`
  - `AggregationConfig`
  - `ValidationConfig`
  - `ExportConfig`
  - _Interface:_ `ContextConfigInterface`

### 3. Processing

Hier befinden sich die Stateless Services (Projectors), welche die Logik ausführen.

- Namespace: `HeimrichHannot\FlareBundle\Projector`
- Klassen:
  - `InteractiveProjector`
  - `AggregationProjector`
  - `ValidationProjector`
  - `ExportProjector`
  - _Interface:_ `ProjectorInterface`

### 4. Result

Enthält die State-Objekte (Views), die das Ergebnis der Verarbeitung halten.

- Namespace: `HeimrichHannot\FlareBundle\View`
- Klassen:
  - `InteractiveView`
  - `AggregationView`
  - `ValidationView`
  - `ExportView`
  - _Interface:_ `ViewInterface`
