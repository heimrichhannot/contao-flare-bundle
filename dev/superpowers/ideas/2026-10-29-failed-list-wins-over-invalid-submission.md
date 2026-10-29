# Idea: a failed list always wins over an invalid submission

Not a spec, not a plan — a first idea to come back to.

## Why

#5 of the PR #49 review fixes (`dev/superpowers/specs/2026-10-29-pr49-review-fixes-design.md`) skips the COUNT query
for an invalid submission. Before that, `InteractiveProjector::project()` checks only the failures that are known
without building conditions: the collection failures on the `ListSpec` and the `buildForm()` failures recorded on the
form harness (#7). A list that is broken only in a filter's **conditions** — e.g. a fixed filter whose
`buildContext()` throws a `FilterException`, which today surfaces only inside the COUNT — then renders as an empty
list with a form error instead of `list.failed` for that one request.

That is a gap in §8.1's rule "a failed list is not an empty list", accepted for now: it needs an invalid submission
and a broken list at the same time, the inspector already reports such a filter as broken, and the next valid
request shows `list.failed`.

## Shape

- On an invalid submission, build every filter's conditions through the shared `Query/Executor/FilterInvoker`
  without running SQL, with the values the loader would have used (the decoded values; a rejected value is already
  dropped), and apply the director's retry rule (a controlled filter that fails with a value is retried with null).
- A `FilterException` from that pass returns `createFailedView()`, as a failing COUNT does today.
- Most likely a method on `ListQueryDirector` ("validate the list's filters, no query") so the projector does not
  re-implement the director's loop and retry rule.

## Cost

- CPU only, no query: building conditions is what the COUNT does before it hits the database. Some elements read
  the database while building (e.g. the archive element's parent lookups), so "no SQL" means "no list query".
- One more code path through the director to keep in sync with `invokeFilters()`.

## Open

- Whether the same pass should also run for valid submissions, to fail before any query.
