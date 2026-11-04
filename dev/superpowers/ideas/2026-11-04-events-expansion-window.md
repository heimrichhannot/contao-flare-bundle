# Idea: bound the Events occurrence expansion by the filter's date window

Noted while designing the query refactor (`dev/superpowers/specs/2026-11-04-query-architecture-design.md`, §2a);
deliberately not fixed there (user's decision).

## Observation

`Integration/ContaoCalendar/GroupsEntriesTrait` expands every fetched event row into occurrences: each day of a
multi-day event (`fillInitialEvents()`) and each repetition of a recurring event from its start until `repeatEnd`
(`fillRecurringEvents()`). The expansion knows nothing about the date range the `CalendarCurrentFilterElement`
applied. The SQL condition (`CalendarCurrentPredicate`) only decides *which events* match — a recurring event matches
if it started before the window's end and repeats into it — so a weekly event that started in 2020 appears in a list
"from today" with every occurrence since 2020.

## Direction

The element that restricts the window would report its effective range for the current value (the same `start` /
`stop` it hands its predicate) through a further `FilterEffect` channel, and the expanding driver
(`ExpandsEntriesContract::expandEntries()`) would read it and drop occurrences outside it. This is another consumer of
the effect channels prepared for sort hints and derivations, so it fits after the sort-hint PR.

## Open questions

- Which channel shape: a typed annotation on the effect (e.g. a date window) or something more generic the expanding
  driver can query by type?
- Several date filters on one list: intersect their windows?
- Multi-day events overlapping the window's start: keep only the days inside the window, or the whole event?
- Does the Count of an expanding list follow automatically (it counts expanded entries), and is that the wanted total?
