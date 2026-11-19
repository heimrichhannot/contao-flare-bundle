# Idea: a filter's setup failure wins over another filter's abort

Not a spec, not a plan — a first idea to come back to.

## Why

The cross-check of branch `v0.2-calendar-columns` (2026-11-19) found that the order of a list's filters decides
whether a broken filter is noticed at runtime. `Query/Executor/ListQueryDirector` invokes the filters in order and stops
at the first `AbortFilteringException` (a filter that legitimately matches nothing). A later filter whose
`buildEffect()` would throw a `FilterException` for its setup — a time window with an inverted period or a missing
column, an archive filter without parents — is never invoked: the list renders empty and the reader answers 404,
instead of `list.failed` and 500. Reversed, the same setup fails the list. This is older than that branch and affects
every element; the backend inspector already reports the broken filter, since it invokes each filter on its own.

Related: `2026-10-29-failed-list-wins-over-invalid-submission.md` (the same rule, "a failed list is not an empty
list", for an invalid submission).

## Shape

- On an abort, keep the empty result but invoke the remaining applicable filters (through the shared
  `Query/Executor/FilterInvoker`) only to detect setup failures; any `FilterException` fails the list as usual.
- No list query in either case. Test both filter orders.
