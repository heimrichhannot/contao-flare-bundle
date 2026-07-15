# Twig Functions

Flare registers the following Twig functions (see also [Templating](../templating.mdx)):

| Function | Description |
|---|---|
| `flare_content(model)` | Renders the content elements belonging to the given model. |
| `flare_enclosure(model\|entry_row, field = "enclosure")` | Retrieves file enclosure data, like `Contao\Controller::addEnclosuresToTemplate()`. |
| `flare_project(list_spec, context)` | Runs a projection directly: returns the view for the given [`ListSpec`](../spec/specifications.md) and context configuration. |
| `flare_schema_org(model = null)` | Prints JSON-LD schema markup for the given model (falls back to the context's model) in a reader context. Influence the data via `ReaderSchemaOrgEvent`. |

## Debugging: Symfony Profiler

In dev environments, Flare contributes a panel to the Symfony profiler / web debug toolbar
(via its data collector), currently showing the installed Flare version at a glance.
