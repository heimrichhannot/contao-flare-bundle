# Integrations

Flare ships with optional integrations for popular Contao bundles. Each integration activates
**automatically** when the corresponding package is installed — there is nothing to configure.
(Internally, the bundle extension loads a service set from `config/integrations/*.yaml` per
detected package.)

## Contao News

**Package:** `contao/news-bundle`

The **News** list type (`flare_news`) works on `tl_news` and joins `tl_news_archive`. It adds a
`published` filter by default — adding your own published filter replaces the default one.
The integration also provides reader listeners that populate page metadata and Schema.org JSON-LD
for news detail pages.

## Contao Calendar

**Package:** `contao/calendar-bundle`

Adds the **Events** list type (`flare_events`) for `tl_calendar_events`, joining `tl_calendar` as
the archive table. Like the News type, it adds a `published` filter by default. On top of the
regular list behavior, the calendar integration provides:

- An event-aware interactive view (`InteractiveEventsView`) whose `getEntriesGrouped()` groups
  entries by date, expanding multi-day and recurring events onto each date they occur.
- The **Calendar time window** filter element (`flare_calendar_current`) for filtering events by
  a date range that respects recurring events.
- Reader page-meta support for event detail pages.

## Contao Comments

**Package:** `contao/comments-bundle`

Attaches comments data to reader pages when the list's data container supports comments. The
default reader template renders them in its `footer` block — see
[Templating / Comments Integration](../templating.mdx#comments-integration).

## Codefog Tags

**Package:** `codefog/tags-bundle`

Adds two filter elements for tag-based filtering:

- **Tags choice** (`cfg_tags_choice`): a choice field of the source field's tags, with
  single/multiple selection and preselect support.
- **Tags search** (`cfg_tags_search`): a keyword search across tag names.

The integration registers the necessary `tl_cfg_tag` joins automatically based on the tag manager
of the filtered field.

## Terminal42 ChangeLanguage / DC_Multilingual

**Packages:** `terminal42/changelanguage`, `terminal42/dc_multilingual`

:::caution[Not yet active]

This integration (alternate-language reader URLs and a multilingual data container list type,
`flare_generic_dc_multilingual`) exists in the codebase but is currently **disabled** and under
development.

:::
