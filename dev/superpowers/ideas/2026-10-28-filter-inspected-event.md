# Idea: `FilterInspectedEvent`

Not a spec, not a plan — a first idea to come back to.

## Why

The backend `FilterInspector` decides alone what is wrong with a filter. Bundles that change filters at runtime
(a `ListDriverBuildListEvent` listener removing or replacing a row's filter, a driver adding its own) know things the
inspector cannot: a removed filter's "broken" mark may be moot, a replaced one may need a different explanation, and a
bundle may want to report problems of its own (a missing archive, a misconfigured target table).

## Shape

- A mutable event, dispatched once per inspected filter, after the bundle's own checks and after duplicate grouping:
  - `Filter $filter`: the filter as checked
  - `?FilterModel $model`: its row, or null for a filter without a row (driver, listener)
  - `?ListSpec $list`: the list scope, or null if it could not be built
  - its issues, with methods to add, remove and replace them, e.g. `addIssue(FilterIssueKind, string|TranslatableInterface)`,
    `removeIssues(FilterIssueKind)`, `getIssues()`
- Named dispatch like the other element events: `flare.filter_element.{type}.inspected`.
- Possibly a list-level counterpart (`ListInspectedEvent`) to adjust the overview counts or add list-level findings.

## What it would commit to

- `FilterIssue` and `FilterIssueKind` become public API. The enum's cases become a contract: adding a kind later
  breaks a listener that matches every case.
- The reason likely needs to accept a `TranslatableInterface` next to a plain string, so a bundle can ship its own
  translated reasons (translated with the editor's locale when the row is rendered).
- Whether a listener may add a `DuplicateAlias` issue, or remove one: probably not — duplicate grouping is the
  framework's rule and the overview message names the aliases.

## Open questions

- Should a listener be able to mark an issue as "does not count" (e.g. the filter is removed at runtime), instead of
  removing it, so the row still shows the icon?
- An `\Error` in a listener: the same debug-mode handling as the inspector's own checks (programming error outside
  debug mode)?
- Is one event per filter cheap enough? The inspection runs on every filter overview and edit form GET, once per list.

## When

Not before a bundle needs it; exposing the issue types is the real cost.
