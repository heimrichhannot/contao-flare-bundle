# Twig Functions

Flare registers the following Twig functions (see also [Templating](../templating.mdx)):

| Function | Description |
|---|---|
| `flare_content(entry)` | Renders the published content elements of the entry's record (`ptable` and `pid` from `entry.meta.modelRef`); throws without `entry.meta.modelRef`. |
| `flare_enclosure(entry\|array, field = "enclosure")` | Retrieves file enclosure data, like `Contao\Controller::addEnclosuresToTemplate()`; reads `entry.row` for an entry. |
| `flare_project(list, intent)` | Runs a projection directly: returns the view for the given [`ListCompound`](../spec/compounds.md) and intent. |
| `flare_related(entry, column)` | The record a column refers to, by the relation in the table's DCA (`foreignKey` + `relation`), like `Model::getRelated()`: a model (to-one), a model collection (to-many) or `null`; memoized per entry and column. Returns the raw relation without a membership check — for membership or a reader link, use `flare_reader.lookup()` in a reader template; in a list, use the lookup a [listener's phase 1](../dev/entries.md#4-computed-similarity) receives. Throws without `entry.meta.modelRef`, for a column without a relation and for a table without a model class. |
| `flare_schema_org(entry = null, list = null)` | Returns the schema.org (JSON-LD) data of an entry. Without arguments, only in a reader template: the reader's `entry` and the list of `flare`. Influence the data via `ReaderSchemaOrgEvent`. |

In reader templates, the view `flare_reader` adds:

| Method | Description |
|---|---|
| `flare_reader.lookup(id)` / `flare_reader.lookup([ids])` | Other entries of the list by id: one id → the entry or `null`, a list → a collection of the found entries in the given order. Membership in *this* list, by the reader's rules (fixed filter values, no form values) — the reader on the jump-to page must use the same list. Ids of the list's own table only: deserialize stored lists first; an empty field (`null`, `''`, `0`) counts as no id. See [Templating](../templating.mdx#linking-other-entries). |

## Debugging: Symfony Profiler

In dev environments, Flare contributes a panel to the Symfony profiler / web debug toolbar
(via its data collector), currently showing the installed Flare version at a glance.
